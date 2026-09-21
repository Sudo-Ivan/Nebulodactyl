package cmd

import (
	"encoding/json"
	"fmt"
	"net"
	"net/url"
	"os"
	"os/exec"
	"os/user"
	"path/filepath"
	"strings"
	"time"

	"github.com/spf13/cobra"

	"github.com/Sudo-Ivan/Nebulodactyl/comet/config"
)

var enrollArgs struct {
	PanelURL      string
	Token         string
	Node          string
	Override      bool
	AllowInsecure bool
	NoService     bool
}

var enrollCmd = &cobra.Command{
	Use:   "enroll",
	Short: "Link this machine to a panel node and write the daemon configuration",
	Long: `Enroll fetches the node configuration from the panel, detects the
local container engine (Podman or Docker), writes the configuration file,
and installs a systemd unit when running as root.

Generate an enroll command from the panel under Admin > Nodes > Configuration.`,
	Run: enrollCmdRun,
}

func init() {
	enrollCmd.Flags().StringVarP(&enrollArgs.PanelURL, "panel-url", "p", "", "The base URL of the panel")
	enrollCmd.Flags().StringVarP(&enrollArgs.Token, "token", "t", "", "The deployment token from the panel")
	enrollCmd.Flags().StringVarP(&enrollArgs.Node, "node", "n", "", "The ID of the node this machine will run")
	enrollCmd.Flags().BoolVar(&enrollArgs.Override, "override", false, "Overwrite an existing configuration file")
	enrollCmd.Flags().BoolVar(&enrollArgs.AllowInsecure, "allow-insecure", false, "Disable certificate checking for the panel request")
	enrollCmd.Flags().BoolVar(&enrollArgs.NoService, "no-service", false, "Skip systemd unit installation and service start")
}

func enrollCmdRun(cmd *cobra.Command, args []string) {
	if enrollArgs.AllowInsecure {
		fmt.Fprintln(os.Stderr, "WARNING: certificate checking is disabled for the panel request.")
	}

	if enrollArgs.PanelURL == "" || enrollArgs.Token == "" || enrollArgs.Node == "" {
		fmt.Fprintln(os.Stderr, "Missing required flags: --panel-url, --token and --node are all required.")
		fmt.Fprintln(os.Stderr, "Generate a complete command from the panel: Admin > Nodes > Configuration > Generate Token.")
		os.Exit(1)
	}
	if !nodeIdRegex.MatchString(enrollArgs.Node) {
		fmt.Fprintln(os.Stderr, "Invalid node ID: expected a number.")
		os.Exit(1)
	}
	if _, err := url.ParseRequestURI(enrollArgs.PanelURL); err != nil {
		fmt.Fprintln(os.Stderr, "Invalid panel URL:", err.Error())
		os.Exit(1)
	}

	if _, err := os.Stat(configPath); err == nil && !enrollArgs.Override {
		fmt.Fprintf(os.Stderr, "A configuration file already exists at %s. Re-run with --override to replace it.\n", configPath)
		os.Exit(1)
	}

	warnInsecurePanelURL(enrollArgs.PanelURL)
	fmt.Printf("Fetching node %s configuration from %s ...\n", enrollArgs.Node, enrollArgs.PanelURL)
	b, err := fetchNodeConfiguration(enrollArgs.PanelURL, enrollArgs.Token, enrollArgs.Node, enrollArgs.AllowInsecure)
	if err != nil {
		fmt.Fprintln(os.Stderr, "Enrollment failed:", err.Error())
		os.Exit(1)
	}

	cfg, err := config.NewAtPath(configPath)
	if err != nil {
		fmt.Fprintln(os.Stderr, "Failed to initialize configuration:", err.Error())
		os.Exit(1)
	}
	if err := json.Unmarshal(b, cfg); err != nil {
		fmt.Fprintln(os.Stderr, "Panel returned an invalid configuration:", err.Error())
		os.Exit(1)
	}
	cfg.PanelLocation = enrollArgs.PanelURL

	// Detect the local container engine. Explicit DOCKER_HOST always wins;
	// otherwise probe the usual Podman and Docker socket locations.
	host := detectEngineSocket()
	if host != "" {
		cfg.Docker.Host = host
	}
	reportEngine(host)

	if err := config.WriteToDisk(cfg); err != nil {
		fmt.Fprintln(os.Stderr, "Failed to write configuration:", err.Error())
		os.Exit(1)
	}
	fmt.Println("Configuration written to", configPath)

	if enrollArgs.NoService || os.Geteuid() != 0 {
		printManualNextSteps()
		return
	}

	if err := installAndStartService(); err != nil {
		fmt.Fprintln(os.Stderr, "Service setup failed:", err.Error())
		fmt.Fprintln(os.Stderr, "Start the daemon manually with: comet --config", configPath)
		os.Exit(1)
	}

	fmt.Println()
	fmt.Println("Enrollment complete. The node should now show as online in the panel.")
}

// detectEngineSocket finds a usable container engine socket. It returns an
// empty string when only the default Docker socket path exists, since the
// daemon already defaults to it.
func detectEngineSocket() string {
	if h := os.Getenv("DOCKER_HOST"); h != "" {
		return h
	}

	var candidates []string
	if u, err := user.Current(); err == nil && u.Uid != "0" {
		if dir := os.Getenv("XDG_RUNTIME_DIR"); dir != "" {
			candidates = append(candidates, filepath.Join(dir, "podman", "podman.sock"))
		}
	}
	candidates = append(candidates,
		"/run/podman/podman.sock",
		"/var/run/podman/podman.sock",
		"/var/run/docker.sock",
		"/run/docker.sock",
	)

	for _, socket := range candidates {
		if _, err := os.Stat(socket); err != nil {
			continue
		}
		host := "unix://" + socket
		if engineReachable(host) {
			return host
		}
	}
	return ""
}

// engineReachable performs a quick ping against a unix socket engine endpoint.
func engineReachable(host string) bool {
	socket := strings.TrimPrefix(host, "unix://")
	conn, err := net.DialTimeout("unix", socket, 2*time.Second)
	if err != nil {
		return false
	}
	conn.Close()
	return true
}

func reportEngine(host string) {
	switch {
	case host == "":
		fmt.Println("Container engine: using the default Docker socket (/var/run/docker.sock)")
		fmt.Println("  If the engine lives elsewhere, set docker.host in the config or export DOCKER_HOST.")
	case strings.Contains(host, "podman"):
		fmt.Println("Container engine: detected Podman at", host)
	default:
		fmt.Println("Container engine: detected socket at", host)
	}
}

// installAndStartService writes the bundled systemd unit and starts comet.
func installAndStartService() error {
	const unit = `[Unit]
Description=Nebulodactyl Comet Daemon
After=docker.service podman.socket network-online.target
Wants=network-online.target

[Service]
Type=simple
User=root
WorkingDirectory=/etc/comet
ExecStart=/usr/local/bin/comet
Restart=on-failure
RestartSec=5s
StartLimitInterval=180
StartLimitBurst=30

[Install]
WantedBy=multi-user.target
`
	if _, err := exec.LookPath("systemctl"); err != nil {
		return fmt.Errorf("systemctl not found; install the unit manually (see comet/deploy/comet.service)")
	}

	if err := os.WriteFile("/etc/systemd/system/comet.service", []byte(unit), 0o644); err != nil {
		return err
	}
	fmt.Println("Wrote /etc/systemd/system/comet.service")

	for _, args := range [][]string{
		{"daemon-reload"},
		{"enable", "--now", "comet"},
	} {
		if out, err := exec.Command("systemctl", args...).CombinedOutput(); err != nil {
			return fmt.Errorf("systemctl %s: %s (%w)", strings.Join(args, " "), string(out), err)
		}
	}
	return nil
}

func printManualNextSteps() {
	fmt.Println()
	fmt.Println("Next steps:")
	fmt.Println("  1. Review", configPath)
	if os.Geteuid() != 0 {
		fmt.Println("  2. For rootless operation, copy comet/deploy/comet.container into")
		fmt.Println("     ~/.config/containers/systemd/ then run: systemctl --user daemon-reload")
		fmt.Println("     and: systemctl --user enable --now comet")
	} else {
		fmt.Println("  2. Install comet/deploy/comet.service and run: systemctl enable --now comet")
	}
}
