// Package config loads nebulod's YAML configuration with environment
// variable overrides so the agent can run under systemd, docker, or a
// Coolify managed container without code changes.
package config

import (
	"fmt"
	"os"
	"strings"
	"time"

	"gopkg.in/yaml.v3"
)

// Config is the top level agent configuration.
type Config struct {
	Panel  PanelConfig  `yaml:"panel"`
	Nebula NebulaConfig `yaml:"nebula"`
	API    APIConfig    `yaml:"api"`

	// ReenrollInterval controls how often the agent refreshes its
	// certificate and lighthouse map from the panel.
	ReenrollInterval time.Duration `yaml:"reenroll_interval"`

	// ReenrollBefore re-enrolls when the certificate has less than this
	// much validity left, regardless of the interval.
	ReenrollBefore time.Duration `yaml:"reenroll_before"`
}

type PanelConfig struct {
	// URL is the base URL of the panel, for example https://panel.example.com.
	URL string `yaml:"url"`

	// Token is the node daemon token in the form "token_id.secret". Read
	// it from TokenFile in production so it can come from a mounted secret.
	Token     string `yaml:"token"`
	TokenFile string `yaml:"token_file"`

	// Groups requests additional nebula cert groups at enrollment.
	Groups []string `yaml:"groups"`

	// InsecureSkipVerify disables panel TLS verification. Only useful for
	// local development against a self signed panel.
	InsecureSkipVerify bool `yaml:"insecure_skip_verify"`
}

type NebulaConfig struct {
	// Binary is the path or name of the nebula daemon binary.
	Binary string `yaml:"binary"`

	// WorkDir holds the keypair, issued certificate, CA certificate, and
	// the rendered nebula configuration.
	WorkDir string `yaml:"work_dir"`

	// Interface is the tun device name nebula creates.
	Interface string `yaml:"interface"`

	// ListenPort is the UDP underlay port nebula binds.
	ListenPort int `yaml:"listen_port"`
}

type APIConfig struct {
	// Listen is the address the status API binds. Bind it to the node
	// overlay address so it is only reachable inside the mesh, or to
	// 127.0.0.1 for local only access.
	Listen string `yaml:"listen"`

	// Token, when set, requires Authorization: Bearer on every endpoint.
	Token string `yaml:"token"`
}

// Load reads the YAML file at path and applies environment overrides.
func Load(path string) (*Config, error) {
	cfg := &Config{
		Nebula: NebulaConfig{
			Binary:     "nebula",
			WorkDir:    "/var/lib/nebulod",
			Interface:  "nebula1",
			ListenPort: 4242,
		},
		API: APIConfig{
			Listen: "127.0.0.1:9770",
		},
		ReenrollInterval: 12 * time.Hour,
		ReenrollBefore:   48 * time.Hour,
	}

	data, err := os.ReadFile(path)
	if err != nil {
		return nil, fmt.Errorf("read config: %w", err)
	}
	if err := yaml.Unmarshal(data, cfg); err != nil {
		return nil, fmt.Errorf("parse config: %w", err)
	}

	cfg.applyEnv()
	if err := cfg.validate(); err != nil {
		return nil, err
	}

	return cfg, nil
}

func (c *Config) applyEnv() {
	if v := os.Getenv("NEBULOD_PANEL_URL"); v != "" {
		c.Panel.URL = v
	}
	if v := os.Getenv("NEBULOD_TOKEN_FILE"); v != "" {
		c.Panel.TokenFile = v
	}
	if v := os.Getenv("NEBULOD_LISTEN"); v != "" {
		c.API.Listen = v
	}
}

func (c *Config) validate() error {
	if c.Panel.URL == "" {
		return fmt.Errorf("panel.url is required")
	}
	c.Panel.URL = strings.TrimSuffix(c.Panel.URL, "/")

	if c.Panel.Token == "" && c.Panel.TokenFile == "" {
		return fmt.Errorf("panel.token or panel.token_file is required")
	}

	return nil
}

// Token resolves the daemon token, preferring the file form so secrets do
// not sit in the YAML file.
func (c *Config) Token() (string, error) {
	if c.Panel.TokenFile != "" {
		data, err := os.ReadFile(c.Panel.TokenFile)
		if err != nil {
			return "", fmt.Errorf("read token file: %w", err)
		}
		return strings.TrimSpace(string(data)), nil
	}
	return c.Panel.Token, nil
}
