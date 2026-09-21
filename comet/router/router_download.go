package router

import (
	"bufio"
	"errors"
	"net/http"
	"os"
	"regexp"
	"strconv"

	"github.com/gin-gonic/gin"
	"github.com/google/uuid"

	"github.com/Sudo-Ivan/Nebulodactyl/comet/remote"
	"github.com/Sudo-Ivan/Nebulodactyl/comet/router/middleware"
	"github.com/Sudo-Ivan/Nebulodactyl/comet/router/tokens"
	"github.com/Sudo-Ivan/Nebulodactyl/comet/server"
	"github.com/Sudo-Ivan/Nebulodactyl/comet/server/backup"
)

// Handle a download request for a server backup.
func getDownloadBackup(c *gin.Context) {
	client := middleware.ExtractApiClient(c)
	manager := middleware.ExtractManager(c)

	// Get the payload from the token.
	token := tokens.BackupPayload{}
	if err := tokens.ParseToken([]byte(c.Query("token")), &token); err != nil {
		middleware.CaptureAndAbort(c, err)
		return
	}

	// Get the server using the UUID from the token.
	if _, ok := manager.Get(token.ServerUuid); !ok || token.Denylisted() || !token.IsUniqueRequest() || !token.HasScope(tokens.BackupDownload) {
		c.AbortWithStatusJSON(http.StatusNotFound, gin.H{
			"error": "The requested resource was not found on this server.",
		})
		return
	}

	// Validate that the BackupUuid field is actually a UUID and not some random characters or a
	// file path.
	if _, err := uuid.Parse(token.BackupUuid); err != nil {
		middleware.CaptureAndAbort(c, err)
		return
	}

	// Rustic backups live in a repository, not as a plain archive on disk.
	// Materialize the snapshot and stream it back as a tar.gz.
	if token.BackupDisk == "rustic_local" || token.BackupDisk == "rustic_s3" {
		getDownloadRusticBackup(c, manager, client, &token)
		return
	}

	// Locate the backup on the local disk.
	b, st, err := backup.LocateLocal(client, token.BackupUuid)
	if err != nil {
		if errors.Is(err, os.ErrNotExist) {
			c.AbortWithStatusJSON(http.StatusNotFound, gin.H{
				"error": "The requested backup was not found on this server.",
			})
			return
		}

		middleware.CaptureAndAbort(c, err)
		return
	}

	// The use of `os` here is safe as backups are not stored within server
	// accessible directories.
	f, err := os.Open(b.Path())
	if err != nil {
		middleware.CaptureAndAbort(c, err)
		return
	}
	defer f.Close()

	c.Header("Content-Length", strconv.Itoa(int(st.Size())))
	c.Header("Content-Disposition", "attachment; filename="+strconv.Quote(st.Name()))
	c.Header("Content-Type", "application/octet-stream")

	_, _ = bufio.NewReader(f).WriteTo(c.Writer)
}

// getDownloadRusticBackup streams a rustic snapshot back as a gzipped tar.
// The snapshot is materialized into a scratch directory first since rustic
// cannot emit a tar stream directly.
func getDownloadRusticBackup(c *gin.Context, manager *server.Manager, client remote.Client, token *tokens.BackupPayload) {
	s, ok := manager.Get(token.ServerUuid)
	if !ok {
		c.AbortWithStatusJSON(http.StatusNotFound, gin.H{"error": "The requested resource was not found on this server."})
		return
	}
	// Snapshot identifiers are hex; rejecting anything else keeps the value
	// safe to hand to the rustic CLI even if a token claim is malformed.
	if matched, _ := regexp.MatchString(`^[0-9a-f]{8,64}$`, token.SnapshotID); !matched {
		c.AbortWithStatusJSON(http.StatusBadRequest, gin.H{"error": "The backup snapshot identifier is invalid."})
		return
	}
	repoType := "local"
	if token.RepositoryType == "s3" || token.BackupDisk == "rustic_s3" {
		repoType = "s3"
	}
	adapter := backup.RusticLocalAdapter
	if repoType == "s3" {
		adapter = backup.RusticS3Adapter
	}
	b := backup.NewRustic(client, s.ID(), token.BackupUuid, "", adapter)
	c.Header("Content-Disposition", "attachment; filename="+strconv.Quote(token.BackupUuid+".tar.gz"))
	c.Header("Content-Type", "application/x-gzip")
	if err := b.StreamArchive(c.Request.Context(), c.Writer); err != nil {
		middleware.ExtractLogger(c).WithField("error", err).Error("failed to stream rustic backup archive")
	}
}

// Handles downloading a specific file for a server.
func getDownloadFile(c *gin.Context) {
	manager := middleware.ExtractManager(c)
	token := tokens.FilePayload{}
	if err := tokens.ParseToken([]byte(c.Query("token")), &token); err != nil {
		middleware.CaptureAndAbort(c, err)
		return
	}

	s, ok := manager.Get(token.ServerUuid)
	if !ok || token.Denylisted() || !token.IsUniqueRequest() || !token.HasScope(tokens.FileDownload) {
		c.AbortWithStatusJSON(http.StatusNotFound, gin.H{
			"error": "The requested resource was not found on this server.",
		})
		return
	}

	f, st, err := s.Filesystem().File(token.FilePath)
	if err != nil {
		middleware.CaptureAndAbort(c, err)
		return
	}
	defer f.Close()
	if st.IsDir() {
		c.AbortWithStatusJSON(http.StatusNotFound, gin.H{
			"error": "The requested resource was not found on this server.",
		})
		return
	}

	// ServeContent handles Range requests automatically so interrupted
	// downloads can be resumed and large files can be fetched in parallel
	// chunks.
	c.Header("Content-Disposition", "attachment; filename="+strconv.Quote(st.Name()))
	c.Header("Content-Type", "application/octet-stream")
	http.ServeContent(c.Writer, c.Request, st.Name(), st.ModTime(), f)
}
