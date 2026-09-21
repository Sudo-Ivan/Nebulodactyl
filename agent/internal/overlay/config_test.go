package overlay

import (
	"strings"
	"testing"
	"time"

	"github.com/Sudo-Ivan/Nebulodactyl/agent/internal/config"
	"github.com/Sudo-Ivan/Nebulodactyl/agent/internal/enroll"
)

func testConfig() *config.Config {
	cfg := &config.Config{}
	cfg.Nebula.WorkDir = "/var/lib/nebulod"
	cfg.Nebula.Interface = "nebula1"
	cfg.Nebula.ListenPort = 4242
	return cfg
}

func TestRender(t *testing.T) {
	e := &enroll.Enrollment{
		Name:          "node-1",
		IP:            "10.42.0.5",
		CIDR:          "10.42.0.5/16",
		ListenPort:    4242,
		Lighthouses:   []string{"10.42.0.1"},
		StaticHostMap: map[string][]string{"10.42.0.1": {"203.0.113.10:4242"}},
		Firewall: map[string]any{
			"outbound": []any{map[string]any{"port": "any", "proto": "any", "host": "any"}},
		},
		ExpiresAt: time.Now().Add(24 * time.Hour),
	}

	doc, err := Render(e, testConfig())
	if err != nil {
		t.Fatalf("render: %v", err)
	}
	out := string(doc)

	for _, want := range []string{
		"/var/lib/nebulod/ca.crt",
		"10.42.0.1",
		"203.0.113.10:4242",
		"dev: nebula1",
		"port: 4242",
	} {
		if !strings.Contains(out, want) {
			t.Errorf("rendered config missing %q\n%s", want, out)
		}
	}
}

func TestRenderEmptyLighthouses(t *testing.T) {
	e := &enroll.Enrollment{
		IP:         "10.42.0.5",
		ListenPort: 4242,
	}
	doc, err := Render(e, testConfig())
	if err != nil {
		t.Fatalf("render: %v", err)
	}
	if !strings.Contains(string(doc), "hosts: []") {
		t.Errorf("expected empty lighthouse hosts list\n%s", doc)
	}
}
