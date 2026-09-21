// Command nebulod is the Nebulodactyl node agent. It enrolls the host onto
// the panel managed Nebula overlay, keeps the certificate fresh, supervises
// the nebula daemon, and exposes a small status API on the overlay address.
//
// Subcommands:
//
//	run           enroll, render the nebula config, and supervise everything
//	enroll        perform a single enrollment and exit
//	keygen        generate the node keypair and exit
//	print-config  enroll and write the rendered nebula config to stdout
//	version       print the build version
package main

import (
	"context"
	"errors"
	"fmt"
	"log/slog"
	"net/http"
	"os"
	"os/signal"
	"path/filepath"
	"sync"
	"syscall"
	"time"

	"github.com/Sudo-Ivan/Nebulodactyl/agent/internal/config"
	"github.com/Sudo-Ivan/Nebulodactyl/agent/internal/enroll"
	"github.com/Sudo-Ivan/Nebulodactyl/agent/internal/identity"
	"github.com/Sudo-Ivan/Nebulodactyl/agent/internal/overlay"
	"github.com/Sudo-Ivan/Nebulodactyl/agent/internal/status"
)

var (
	version   = "dev"
	startTime = time.Now()
)

func main() {
	slog.SetDefault(slog.New(slog.NewTextHandler(os.Stderr, nil)))

	if len(os.Args) < 2 {
		usage()
		os.Exit(2)
	}

	var err error
	switch os.Args[1] {
	case "run":
		err = cmdRun()
	case "enroll":
		err = cmdEnroll()
	case "keygen":
		err = cmdKeygen()
	case "print-config":
		err = cmdPrintConfig()
	case "version":
		fmt.Println(version)
	default:
		usage()
		os.Exit(2)
	}

	if err != nil {
		slog.Error("fatal", "error", err)
		os.Exit(1)
	}
}

func usage() {
	fmt.Fprintln(os.Stderr, "usage: nebulod <run|enroll|keygen|print-config|version> [-config path]")
}

// configPath pulls the -config flag out of the raw args so subcommands
// stay dependency free.
func configPath() string {
	path := "/etc/nebulod.yml"
	for i, arg := range os.Args {
		if arg == "-config" && i+1 < len(os.Args) {
			path = os.Args[i+1]
		}
	}
	return path
}

func load() (*config.Config, error) {
	return config.Load(configPath())
}

func keyPath(cfg *config.Config) string {
	return filepath.Join(cfg.Nebula.WorkDir, "host.key")
}

func doEnroll(ctx context.Context, cfg *config.Config) (*enroll.Enrollment, error) {
	token, err := cfg.Token()
	if err != nil {
		return nil, err
	}

	kp, err := identity.LoadOrGenerate(keyPath(cfg))
	if err != nil {
		return nil, err
	}

	e, err := enroll.NewClient(cfg.Panel, token).Enroll(ctx, kp.PublicB64())
	if err != nil {
		return nil, err
	}
	if err := e.WriteTo(cfg.Nebula.WorkDir); err != nil {
		return nil, err
	}

	return e, nil
}

func cmdEnroll() error {
	cfg, err := load()
	if err != nil {
		return err
	}
	e, err := doEnroll(context.Background(), cfg)
	if err != nil {
		return err
	}
	slog.Info("enrolled", "ip", e.IP, "expires", e.ExpiresAt)
	return nil
}

func cmdKeygen() error {
	cfg, err := load()
	if err != nil {
		return err
	}
	kp, err := identity.LoadOrGenerate(keyPath(cfg))
	if err != nil {
		return err
	}
	fmt.Print(kp.PublicPEM())
	return nil
}

func cmdPrintConfig() error {
	cfg, err := load()
	if err != nil {
		return err
	}
	e, err := doEnroll(context.Background(), cfg)
	if err != nil {
		return err
	}
	doc, err := overlay.Render(e, cfg)
	if err != nil {
		return err
	}
	fmt.Print(string(doc))
	return nil
}

func cmdRun() error {
	cfg, err := load()
	if err != nil {
		return err
	}

	ctx, stop := signal.NotifyContext(context.Background(), syscall.SIGINT, syscall.SIGTERM)
	defer stop()

	configPath := filepath.Join(cfg.Nebula.WorkDir, "nebula.yml")

	e, err := doEnroll(ctx, cfg)
	if err != nil {
		return fmt.Errorf("initial enrollment: %w", err)
	}
	doc, err := overlay.Render(e, cfg)
	if err != nil {
		return err
	}
	if err := os.WriteFile(configPath, doc, 0o600); err != nil {
		return err
	}
	slog.Info("enrolled", "ip", e.IP, "name", e.Name, "expires", e.ExpiresAt)

	var (
		mu       sync.Mutex
		current  = e
		lastSync = time.Now()
	)

	super := overlay.NewSupervisor(cfg.Nebula.Binary, configPath)
	go func() {
		if err := super.Run(ctx); err != nil {
			slog.Error("supervisor stopped", "error", err)
			stop()
		}
	}()

	api := status.New(cfg.API.Listen, cfg.API.Token, version, func() status.State {
		mu.Lock()
		defer mu.Unlock()
		return status.State{
			Enrolled:    true,
			OverlayIP:   current.IP,
			Name:        current.Name,
			Fingerprint: current.Fingerprint,
			ExpiresAt:   current.ExpiresAt,
			NebulaUp:    super.Running(),
			Uptime:      int64(time.Since(startTime).Seconds()),
			Version:     version,
		}
	})
	go func() {
		if err := api.Run(); err != nil && !errors.Is(err, http.ErrServerClosed) {
			slog.Error("status api stopped", "error", err)
		}
	}()

	// Re-enrollment loop. Refreshes the certificate when it approaches
	// expiry, and periodically re-syncs anyway so lighthouse map and
	// firewall changes on the panel propagate to nodes.
	go func() {
		ticker := time.NewTicker(time.Minute)
		defer ticker.Stop()
		for {
			select {
			case <-ctx.Done():
				return
			case <-ticker.C:
				mu.Lock()
				due := time.Until(current.ExpiresAt) < cfg.ReenrollBefore ||
					time.Since(lastSync) > cfg.ReenrollInterval
				mu.Unlock()
				if !due {
					continue
				}

				fresh, err := doEnroll(ctx, cfg)
				if err != nil {
					slog.Warn("reenrollment failed", "error", err)
					continue
				}
				doc, err := overlay.Render(fresh, cfg)
				if err != nil {
					slog.Warn("render failed", "error", err)
					continue
				}
				if err := os.WriteFile(configPath, doc, 0o600); err != nil {
					slog.Warn("write config failed", "error", err)
					continue
				}

				mu.Lock()
				current = fresh
				lastSync = time.Now()
				mu.Unlock()
				super.Restart()
				slog.Info("re-enrolled", "ip", fresh.IP, "expires", fresh.ExpiresAt)
			}
		}
	}()

	<-ctx.Done()
	slog.Info("shutting down")
	return nil
}
