package overlay

import (
	"context"
	"fmt"
	"log/slog"
	"os"
	"os/exec"
	"sync/atomic"
	"syscall"
	"time"
)

// Supervisor runs the nebula daemon and restarts it on failure with
// exponential backoff. A new certificate or configuration triggers a clean
// restart through Restart.
type Supervisor struct {
	binary     string
	configPath string

	running  atomic.Bool
	restart  chan struct{}
	lastExit atomic.Int64
}

func NewSupervisor(binary, configPath string) *Supervisor {
	return &Supervisor{
		binary:     binary,
		configPath: configPath,
		restart:    make(chan struct{}, 1),
	}
}

// Running reports whether the nebula child process is currently alive.
func (s *Supervisor) Running() bool {
	return s.running.Load()
}

// LastExit is the unix timestamp of the most recent child exit.
func (s *Supervisor) LastExit() int64 {
	return s.lastExit.Load()
}

// Restart asks the loop to bounce the nebula process, used after a fresh
// certificate is written.
func (s *Supervisor) Restart() {
	select {
	case s.restart <- struct{}{}:
	default:
	}
}

// Run is the supervision loop. It blocks until ctx is cancelled.
func (s *Supervisor) Run(ctx context.Context) error {
	if _, err := exec.LookPath(s.binary); err != nil {
		return fmt.Errorf("nebula binary %q not found: %w", s.binary, err)
	}

	backoff := time.Second
	for {
		cmd := exec.CommandContext(ctx, s.binary, "-config", s.configPath)
		cmd.Stdout = os.Stdout
		cmd.Stderr = os.Stderr
		cmd.Cancel = func() error {
			return cmd.Process.Signal(syscall.SIGTERM)
		}
		cmd.WaitDelay = 5 * time.Second

		s.running.Store(true)
		slog.Info("starting nebula", "binary", s.binary, "config", s.configPath)
		started := time.Now()
		err := cmd.Run()
		s.running.Store(false)
		s.lastExit.Store(time.Now().Unix())

		if ctx.Err() != nil {
			return nil
		}
		if err != nil {
			slog.Warn("nebula exited", "error", err)
		}

		// A process that survived more than a minute resets the backoff.
		if time.Since(started) > time.Minute {
			backoff = time.Second
		}

		select {
		case <-ctx.Done():
			return nil
		case <-s.restart:
			slog.Info("restarting nebula for refreshed configuration")
			continue
		case <-time.After(backoff):
		}

		backoff = min(backoff*2, 60*time.Second)
	}
}
