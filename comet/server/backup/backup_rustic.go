package backup

import (
	"bytes"
	"context"
	"encoding/json"
	"io"
	"io/fs"
	"os"
	"os/exec"
	"path/filepath"
	"strings"

	"emperror.dev/errors"
	"github.com/mholt/archives"

	"github.com/Sudo-Ivan/Nebulodactyl/comet/config"
	"github.com/Sudo-Ivan/Nebulodactyl/comet/remote"
	"github.com/Sudo-Ivan/Nebulodactyl/comet/server/filesystem"
)

// RusticBackup implements the backup interface on top of the rustic binary,
// giving deduplicated, encrypted, incremental backups in a restic-compatible
// repository. Repository credentials are fetched from the Panel per operation
// so passwords never live in the daemon config.
type RusticBackup struct {
	Backup

	serverUuid string
	repoType   string
}

var _ BackupInterface = (*RusticBackup)(nil)

// rusticMeta is the sidecar written next to each backup so snapshot lookups
// for delete, download, and restore do not have to enumerate the repository.
type rusticMeta struct {
	SnapshotID string `json:"snapshot_id"`
	Size       int64  `json:"size"`
	RepoType   string `json:"repo_type"`
	// SourcePath is the directory rustic backed up. Rustic mirrors the
	// absolute source path below the restore target, so restores need it to
	// locate the content root.
	SourcePath string `json:"source_path"`
}

func NewRustic(client remote.Client, serverUuid string, backupUuid string, ignore string, adapter AdapterType) *RusticBackup {
	repoType := "local"
	if adapter == RusticS3Adapter {
		repoType = "s3"
	}
	return &RusticBackup{
		Backup: Backup{
			client:  client,
			Uuid:    backupUuid,
			Ignore:  ignore,
			adapter: adapter,
		},
		serverUuid: serverUuid,
		repoType:   repoType,
	}
}

// MetaPath returns the path of the sidecar that records the rustic snapshot
// backing this backup.
func (b *RusticBackup) MetaPath() string {
	identifier, err := b.normalizedIdentifier()
	if err != nil {
		identifier = filepath.Base(b.Identifier())
	}
	return filepath.Join(config.Get().System.BackupDirectory, "rustic", identifier+".json")
}

// Path returns the sidecar path so callers that stat the location see a
// regular file for rustic backups.
func (b *RusticBackup) Path() string {
	return b.MetaPath()
}

// LocateRustic finds a rustic backup sidecar on this machine. The second
// return value describes the sidecar file itself.
func LocateRustic(client remote.Client, serverUuid string, uuid string) (*RusticBackup, os.FileInfo, error) {
	b := NewRustic(client, serverUuid, uuid, "", RusticLocalAdapter)
	if err := b.validateIdentifier(); err != nil {
		return nil, nil, err
	}
	st, err := os.Stat(b.MetaPath())
	if err != nil {
		return nil, nil, err
	}
	if st.IsDir() {
		return nil, nil, errors.New("invalid rustic metadata, is directory")
	}
	// The sidecar records which repository type wrote the snapshot so later
	// operations hit the right backend even if the node default changed.
	if meta, err := b.readMeta(); err == nil && meta.RepoType == "s3" {
		b.repoType = "s3"
	}
	return b, st, nil
}

// WithLogContext attaches additional context to the log output for this backup.
func (b *RusticBackup) WithLogContext(c map[string]interface{}) {
	b.logContext = c
}

// Generate runs rustic backup against the server data directory and records
// the resulting snapshot in a local sidecar.
func (b *RusticBackup) Generate(ctx context.Context, fsys *filesystem.Filesystem, ignore string) (*ArchiveDetails, error) {
	if err := b.validateIdentifier(); err != nil {
		return nil, err
	}
	env, cfg, err := b.repositoryEnv(ctx)
	if err != nil {
		return nil, err
	}

	b.log().WithField("repository", cfg.RepositoryPath).Info("creating deduplicated backup for server")
	if err := b.ensureRepository(ctx, env); err != nil {
		return nil, err
	}

	args := []string{"backup", "--json", "--tag", "nbd-backup=" + b.Identifier()}

	// The ignore list uses gitignore syntax; rustic glob-file entries behave
	// the same way for exclusion purposes.
	var ignoreFile string
	if strings.TrimSpace(ignore) != "" {
		f, err := os.CreateTemp("", "comet-ignore-*")
		if err != nil {
			return nil, errors.WrapIf(err, "backup/rustic: failed to create ignore file")
		}
		if _, err := f.WriteString(ignore); err != nil {
			_ = f.Close()
			_ = os.Remove(f.Name())
			return nil, errors.WrapIf(err, "backup/rustic: failed to write ignore file")
		}
		_ = f.Close()
		ignoreFile = f.Name()
		defer os.Remove(ignoreFile)
		args = append(args, "--glob-file", ignoreFile)
	}
	args = append(args, fsys.Path())

	out, err := b.run(ctx, env, args...)
	if err != nil {
		return nil, err
	}

	snapID, size := parseBackupSummary(out)
	if !validSnapshotID(snapID) {
		return nil, errors.New("backup/rustic: could not determine a valid snapshot id from rustic output")
	}

	meta := rusticMeta{SnapshotID: snapID, Size: size, RepoType: b.repoType, SourcePath: fsys.Path()}
	if err := b.writeMeta(meta); err != nil {
		return nil, errors.WrapIf(err, "backup/rustic: failed to record snapshot metadata")
	}
	b.log().WithField("snapshot", snapID).WithField("size", size).Info("created deduplicated backup successfully")

	return &ArchiveDetails{
		Checksum:     snapID,
		ChecksumType: "sha256",
		Size:         size,
		SnapshotID:   snapID,
	}, nil
}

// Remove forgets the snapshot recorded in the sidecar and prunes unreferenced
// data from the repository.
func (b *RusticBackup) Remove() error {
	if err := b.validateIdentifier(); err != nil {
		return err
	}
	meta, err := b.readMeta()
	if err != nil {
		return err
	}
	if meta.RepoType == "s3" {
		b.repoType = "s3"
	}

	env, _, err := b.repositoryEnv(context.Background())
	if err != nil {
		return err
	}
	if _, err := b.run(context.Background(), env, "forget", "--prune", meta.SnapshotID); err != nil {
		return errors.WrapIf(err, "backup/rustic: failed to forget snapshot "+meta.SnapshotID)
	}
	return os.Remove(b.MetaPath())
}

// Restore materializes the snapshot into a scratch directory and feeds every
// entry through the callback in the same order a tar extract would.
func (b *RusticBackup) Restore(ctx context.Context, _ io.Reader, callback RestoreCallback) error {
	if err := b.validateIdentifier(); err != nil {
		return err
	}
	meta, err := b.readMeta()
	if err != nil {
		return errors.WrapIf(err, "backup/rustic: failed to read snapshot metadata")
	}
	if meta.RepoType == "s3" {
		b.repoType = "s3"
	}
	env, _, err := b.repositoryEnv(ctx)
	if err != nil {
		return err
	}

	target, err := os.MkdirTemp("", "comet-restore-*")
	if err != nil {
		return errors.WrapIf(err, "backup/rustic: failed to create restore scratch dir")
	}
	defer os.RemoveAll(target)

	b.log().WithField("snapshot", meta.SnapshotID).Info("restoring deduplicated backup")
	if _, err := b.run(ctx, env, "restore", meta.SnapshotID+":/", target); err != nil {
		return errors.WrapIf(err, "backup/rustic: failed to restore snapshot "+meta.SnapshotID)
	}

	root := b.restoredRoot(target, meta.SourcePath)
	return filepath.WalkDir(root, func(p string, d fs.DirEntry, err error) error {
		if err != nil {
			return err
		}
		if d.IsDir() {
			return nil
		}
		info, err := d.Info()
		if err != nil {
			return err
		}
		rel, err := filepath.Rel(root, p)
		if err != nil {
			return err
		}
		f, err := os.Open(p)
		if err != nil {
			return err
		}
		return callback(filepath.ToSlash(rel), info, f)
	})
}

// StreamArchive materializes the snapshot and writes it to w as a gzipped tar
// stream so rustic backups remain downloadable through the backup download
// endpoint.
func (b *RusticBackup) StreamArchive(ctx context.Context, w io.Writer) error {
	meta, err := b.readMeta()
	if err != nil {
		return err
	}
	if meta.RepoType == "s3" {
		b.repoType = "s3"
	}
	env, _, err := b.repositoryEnv(ctx)
	if err != nil {
		return err
	}

	target, err := os.MkdirTemp("", "comet-dump-*")
	if err != nil {
		return errors.WrapIf(err, "backup/rustic: failed to create dump scratch dir")
	}
	defer os.RemoveAll(target)

	if _, err := b.run(ctx, env, "restore", meta.SnapshotID+":/", target); err != nil {
		return errors.WrapIf(err, "backup/rustic: failed to restore snapshot for download")
	}

	files, err := collectFiles(b.restoredRoot(target, meta.SourcePath))
	if err != nil {
		return err
	}
	return format.Archive(ctx, w, files)
}

// restoredRoot locates the level inside the scratch dir that holds the server
// files. Rustic mirrors the absolute source path below the restore target, so
// a backup of /var/lib/comet/volumes/<uuid> lands at
// <target>/var/lib/comet/volumes/<uuid>.
func (b *RusticBackup) restoredRoot(target string, sourcePath string) string {
	if sourcePath == "" {
		sourcePath = filepath.Join(config.Get().System.Data, b.serverUuid)
	}
	// The candidate must stay inside the scratch dir. A corrupted sidecar
	// with traversal segments could otherwise point the walk at arbitrary
	// host paths.
	candidate := filepath.Join(target, filepath.Clean(sourcePath))
	if candidate == filepath.Clean(target) || strings.HasPrefix(candidate, filepath.Clean(target)+string(os.PathSeparator)) {
		if dirExists(candidate) {
			return candidate
		}
	}
	// Fall back to descending through single-child directories that mirror
	// the source prefix.
	root := target
	for depth := 0; depth < 8; depth++ {
		entries, err := os.ReadDir(root)
		if err != nil || len(entries) != 1 || !entries[0].IsDir() {
			return root
		}
		root = filepath.Join(root, entries[0].Name())
	}
	return root
}

func dirExists(p string) bool {
	st, err := os.Stat(p)
	return err == nil && st.IsDir()
}

// repositoryEnv resolves the repository configuration from the Panel and
// builds the environment rustic needs to reach the backend.
func (b *RusticBackup) repositoryEnv(ctx context.Context) ([]string, *remote.RusticConfig, error) {
	cfg, err := b.client.GetRusticConfig(ctx, b.serverUuid, b.repoType)
	if err != nil {
		return nil, nil, errors.WrapIf(err, "backup/rustic: failed to fetch repository configuration from panel")
	}
	if cfg.RepositoryPath == "" || cfg.RepositoryPassword == "" {
		return nil, nil, errors.New("backup/rustic: panel returned an incomplete repository configuration")
	}

	// Start from the process environment so certificate bundles, proxies,
	// and TMPDIR keep working, then set the rustic specific overrides.
	env := os.Environ()
	env = append(env,
		"RUSTIC_PASSWORD="+cfg.RepositoryPassword,
		"RUSTIC_CACHE_DIR="+filepath.Join(config.Get().System.BackupDirectory, "rustic-cache"),
	)

	if b.repoType == "s3" {
		cred := cfg.S3Credentials
		if cred == nil || cred.Bucket == "" {
			return nil, nil, errors.New("backup/rustic: panel returned no S3 credentials for a rustic_s3 backup")
		}
		env = append(env,
			"RUSTIC_REPOSITORY=opendal:s3",
			"OPENDAL_S3_BUCKET="+cred.Bucket,
			"OPENDAL_S3_REGION="+cred.Region,
			"OPENDAL_S3_ACCESS_KEY_ID="+cred.AccessKeyID,
			"OPENDAL_S3_SECRET_ACCESS_KEY="+cred.SecretAccessKey,
		)
		if cred.Endpoint != "" {
			env = append(env, "OPENDAL_S3_ENDPOINT="+cred.Endpoint)
		}
		// The panel returns "<bucket>/<prefix>/<server-uuid>"; the bucket is
		// already set above so the remainder becomes the opendal root.
		if root := strings.TrimPrefix(cfg.RepositoryPath, cred.Bucket+"/"); root != cfg.RepositoryPath {
			env = append(env, "OPENDAL_S3_ROOT="+root)
		}
		if cred.ForcePathStyle {
			env = append(env, "OPENDAL_S3_ENABLE_VIRTUAL_HOST_STYLE=false")
		}
	} else {
		env = append(env, "RUSTIC_REPOSITORY="+cfg.RepositoryPath)
		if err := os.MkdirAll(cfg.RepositoryPath, 0o700); err != nil {
			return nil, nil, errors.WrapIf(err, "backup/rustic: failed to create repository directory")
		}
	}
	return env, &cfg, nil
}

// ensureRepository initializes the repository the first time it is used.
func (b *RusticBackup) ensureRepository(ctx context.Context, env []string) error {
	if _, err := b.run(ctx, env, "snapshots", "--json"); err == nil {
		return nil
	}
	if _, err := b.run(ctx, env, "init"); err != nil {
		return errors.WrapIf(err, "backup/rustic: failed to initialize repository")
	}
	return nil
}

func (b *RusticBackup) run(ctx context.Context, env []string, args ...string) ([]byte, error) {
	binary := config.Get().System.Backups.RusticBinary
	if binary == "" {
		binary = "rustic"
	}
	cmd := exec.CommandContext(ctx, binary, args...)
	cmd.Env = env
	var stdout, stderr bytes.Buffer
	cmd.Stdout = &stdout
	cmd.Stderr = &stderr
	if err := cmd.Run(); err != nil {
		return nil, errors.New("backup/rustic: rustic " + strings.Join(args, " ") + " failed: " + strings.TrimSpace(stderr.String()))
	}
	return stdout.Bytes(), nil
}

func (b *RusticBackup) readMeta() (rusticMeta, error) {
	var meta rusticMeta
	raw, err := os.ReadFile(b.MetaPath())
	if err != nil {
		return meta, err
	}
	if err := json.Unmarshal(raw, &meta); err != nil {
		return meta, errors.WrapIf(err, "backup/rustic: corrupt snapshot metadata")
	}
	// The snapshot id becomes an argument to the rustic binary, so reject
	// anything that is not a plain hex id. A tampered sidecar must not be
	// able to smuggle extra flags into forget or restore.
	if !validSnapshotID(meta.SnapshotID) {
		return meta, errors.New("backup/rustic: snapshot metadata contains an invalid snapshot id")
	}
	return meta, nil
}

// validSnapshotID accepts full or abbreviated lowercase hex snapshot ids.
func validSnapshotID(id string) bool {
	if len(id) < 8 || len(id) > 64 {
		return false
	}
	for _, r := range id {
		if (r < '0' || r > '9') && (r < 'a' || r > 'f') {
			return false
		}
	}
	return true
}

func (b *RusticBackup) writeMeta(meta rusticMeta) error {
	if err := os.MkdirAll(filepath.Dir(b.MetaPath()), 0o700); err != nil {
		return err
	}
	raw, err := json.Marshal(meta)
	if err != nil {
		return err
	}
	return os.WriteFile(b.MetaPath(), raw, 0o600)
}

// parseBackupSummary pulls the snapshot id and processed byte count out of
// rustic's JSON summary. The last JSON object on stdout wins.
func parseBackupSummary(out []byte) (string, int64) {
	var snapID string
	var size int64
	for _, line := range bytes.Split(out, []byte("\n")) {
		line = bytes.TrimSpace(line)
		if len(line) == 0 || line[0] != '{' {
			continue
		}
		var summary struct {
			SnapshotID          string `json:"snapshot_id"`
			ID                  string `json:"id"`
			TotalBytesProcessed int64  `json:"total_bytes_processed"`
		}
		if err := json.Unmarshal(line, &summary); err != nil {
			continue
		}
		if summary.SnapshotID != "" {
			snapID = summary.SnapshotID
		} else if summary.ID != "" {
			snapID = summary.ID
		}
		if summary.TotalBytesProcessed > 0 {
			size = summary.TotalBytesProcessed
		}
	}
	return snapID, size
}

func collectFiles(root string) ([]archives.FileInfo, error) {
	var files []archives.FileInfo
	err := filepath.WalkDir(root, func(p string, d fs.DirEntry, err error) error {
		if err != nil {
			return err
		}
		if d.IsDir() {
			return nil
		}
		info, err := d.Info()
		if err != nil {
			return err
		}
		rel, err := filepath.Rel(root, p)
		if err != nil {
			return err
		}
		path := p
		files = append(files, archives.FileInfo{
			FileInfo:      info,
			NameInArchive: filepath.ToSlash(rel),
			Open:          func() (fs.File, error) { return os.Open(path) },
		})
		return nil
	})
	return files, err
}
