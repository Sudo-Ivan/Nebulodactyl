// Package enroll implements the node enrollment handshake against the
// panel's remote API. The agent proves its identity with the node daemon
// token and receives a signed nebula certificate plus everything needed to
// render the local nebula configuration.
package enroll

import (
	"bytes"
	"context"
	"crypto/sha256"
	"crypto/tls"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"path/filepath"
	"strings"
	"time"

	"github.com/Sudo-Ivan/Nebulodactyl/agent/internal/config"
	"github.com/Sudo-Ivan/Nebulodactyl/agent/internal/fsutil"
)

// Enrollment mirrors the panel's enrollment response.
type Enrollment struct {
	Name          string              `json:"name"`
	Certificate   string              `json:"certificate"`
	CACertificate string              `json:"ca_certificate"`
	IP            string              `json:"ip"`
	CIDR          string              `json:"cidr"`
	Groups        []string            `json:"groups"`
	Lighthouses   []string            `json:"lighthouses"`
	StaticHostMap map[string][]string `json:"static_host_map"`
	ListenPort    int                 `json:"listen_port"`
	AgentPort     int                 `json:"agent_port"`
	Firewall      map[string]any      `json:"firewall"`
	ExpiresAt     time.Time           `json:"expires_at"`
	Fingerprint   string              `json:"fingerprint"`
}

// Client talks to the panel remote API.
type Client struct {
	cfg   config.PanelConfig
	token string
	http  *http.Client
}

func NewClient(cfg config.PanelConfig, token string) *Client {
	transport := http.DefaultTransport.(*http.Transport).Clone()
	transport.TLSClientConfig = &tls.Config{
		InsecureSkipVerify: cfg.InsecureSkipVerify, //nolint:gosec // explicit opt-in for local development
	}

	return &Client{
		cfg:   cfg,
		token: token,
		http:  &http.Client{Timeout: 30 * time.Second, Transport: transport},
	}
}

// Enroll posts the public key and returns the signed enrollment bundle.
func (c *Client) Enroll(ctx context.Context, publicKeyB64 string) (*Enrollment, error) {
	body, err := json.Marshal(map[string]any{
		"public_key": publicKeyB64,
		"groups":     c.cfg.Groups,
	})
	if err != nil {
		return nil, err
	}

	req, err := http.NewRequestWithContext(ctx, http.MethodPost,
		c.cfg.URL+"/api/remote/nebula/enroll", bytes.NewReader(body))
	if err != nil {
		return nil, err
	}
	req.Header.Set("Authorization", "Bearer "+c.token)
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Accept", "application/json")

	resp, err := c.http.Do(req)
	if err != nil {
		return nil, fmt.Errorf("enroll request: %w", err)
	}
	defer resp.Body.Close()

	data, err := io.ReadAll(io.LimitReader(resp.Body, 1<<20))
	if err != nil {
		return nil, fmt.Errorf("enroll read: %w", err)
	}

	if resp.StatusCode != http.StatusOK {
		return nil, fmt.Errorf("enroll: panel returned %s: %s", resp.Status, truncate(string(data), 256))
	}

	var enrollment Enrollment
	if err := json.Unmarshal(data, &enrollment); err != nil {
		return nil, fmt.Errorf("enroll: decode response: %w", err)
	}
	if enrollment.Certificate == "" || enrollment.CACertificate == "" || enrollment.IP == "" {
		return nil, fmt.Errorf("enroll: response missing certificate fields")
	}

	// The panel reports sha256 of the public key we sent as the host
	// fingerprint. A mismatch means the bundle was corrupted or does not
	// belong to this keypair.
	sum := sha256.Sum256([]byte(strings.TrimSpace(publicKeyB64)))
	if enrollment.Fingerprint != "" && !strings.EqualFold(hex.EncodeToString(sum[:]), enrollment.Fingerprint) {
		return nil, fmt.Errorf("enroll: fingerprint does not match the submitted public key")
	}

	return &enrollment, nil
}

// WriteTo persists the enrollment material into the agent work directory.
func (e *Enrollment) WriteTo(workDir string) error {
	for name, data := range map[string]string{
		"ca.crt":   e.CACertificate,
		"host.crt": e.Certificate,
	} {
		if err := fsutil.WriteAtomic(filepath.Join(workDir, name), []byte(data), 0o644); err != nil {
			return err
		}
	}
	return nil
}

func truncate(s string, n int) string {
	if len(s) <= n {
		return s
	}
	return s[:n] + "..."
}
