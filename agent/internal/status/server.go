// Package status exposes the agent's small HTTP surface: health, current
// enrollment state, and a minimal metrics endpoint the panel or a
// prometheus scrape can consume over the overlay.
package status

import (
	"encoding/json"
	"fmt"
	"net"
	"net/http"
	"strings"
	"time"
)

// State is the live view of the agent reported to callers.
type State struct {
	Enrolled    bool      `json:"enrolled"`
	OverlayIP   string    `json:"overlay_ip,omitempty"`
	Name        string    `json:"name,omitempty"`
	Fingerprint string    `json:"fingerprint,omitempty"`
	ExpiresAt   time.Time `json:"expires_at,omitempty"`
	NebulaUp    bool      `json:"nebula_up"`
	Uptime      int64     `json:"uptime_seconds"`
	Version     string    `json:"version"`
}

// Server is the agent status API.
type Server struct {
	version string
	token   string
	state   func() State
	http    *http.Server
}

func New(listen, token, version string, state func() State) *Server {
	s := &Server{version: version, token: token, state: state}

	mux := http.NewServeMux()
	mux.HandleFunc("/healthz", s.wrap(s.healthz))
	mux.HandleFunc("/status", s.wrap(s.status))
	mux.HandleFunc("/metrics", s.wrap(s.metrics))

	s.http = &http.Server{
		Addr:              listen,
		Handler:           mux,
		ReadHeaderTimeout: 5 * time.Second,
	}
	return s
}

// Run blocks until the server stops.
func (s *Server) Run() error {
	ln, err := net.Listen("tcp", s.http.Addr)
	if err != nil {
		return fmt.Errorf("status api listen %s: %w", s.http.Addr, err)
	}
	return s.http.Serve(ln)
}

func (s *Server) wrap(next http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		if s.token != "" {
			if !strings.EqualFold("Bearer "+s.token, r.Header.Get("Authorization")) {
				http.Error(w, "unauthorized", http.StatusUnauthorized)
				return
			}
		}
		next(w, r)
	}
}

func (s *Server) healthz(w http.ResponseWriter, _ *http.Request) {
	st := s.state()
	code := http.StatusOK
	if !st.NebulaUp {
		code = http.StatusServiceUnavailable
	}
	writeJSON(w, code, map[string]string{"status": map[bool]string{true: "ok", false: "degraded"}[st.NebulaUp]})
}

func (s *Server) status(w http.ResponseWriter, _ *http.Request) {
	writeJSON(w, http.StatusOK, s.state())
}

// metrics emits a minimal prometheus compatible text exposition.
func (s *Server) metrics(w http.ResponseWriter, _ *http.Request) {
	st := s.state()
	w.Header().Set("Content-Type", "text/plain; version=0.0.4")
	fmt.Fprintf(w, "nebulod_up %d\n", boolInt(true))
	fmt.Fprintf(w, "nebulod_nebula_running %d\n", boolInt(st.NebulaUp))
	fmt.Fprintf(w, "nebulod_enrolled %d\n", boolInt(st.Enrolled))
	fmt.Fprintf(w, "nebulod_uptime_seconds %d\n", st.Uptime)
	if !st.ExpiresAt.IsZero() {
		fmt.Fprintf(w, "nebulod_cert_expiry_timestamp %d\n", st.ExpiresAt.Unix())
	}
}

func writeJSON(w http.ResponseWriter, code int, v any) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(code)
	_ = json.NewEncoder(w).Encode(v)
}

func boolInt(b bool) int {
	if b {
		return 1
	}
	return 0
}
