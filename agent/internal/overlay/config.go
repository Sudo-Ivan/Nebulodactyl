// Package overlay renders the nebula daemon configuration from an
// enrollment bundle and supervises the nebula process.
package overlay

import (
	"path/filepath"

	"github.com/Sudo-Ivan/Nebulodactyl/agent/internal/config"
	"github.com/Sudo-Ivan/Nebulodactyl/agent/internal/enroll"
	"gopkg.in/yaml.v3"
)

// Render produces the nebula daemon YAML for this enrollment. Certificates
// and keys live in the agent work directory under fixed names.
func Render(e *enroll.Enrollment, cfg *config.Config) ([]byte, error) {
	lighthouseHosts := e.Lighthouses
	if lighthouseHosts == nil {
		lighthouseHosts = []string{}
	}

	doc := map[string]any{
		"pki": map[string]any{
			"ca":   filepath.Join(cfg.Nebula.WorkDir, "ca.crt"),
			"cert": filepath.Join(cfg.Nebula.WorkDir, "host.crt"),
			"key":  filepath.Join(cfg.Nebula.WorkDir, "host.key"),
		},
		"static_host_map": e.StaticHostMap,
		"lighthouse": map[string]any{
			"am_lighthouse": false,
			"hosts":         lighthouseHosts,
		},
		"listen": map[string]any{
			"host": "0.0.0.0",
			"port": firstNonZero(e.ListenPort, cfg.Nebula.ListenPort),
		},
		"tun": map[string]any{
			"dev": cfg.Nebula.Interface,
		},
		"firewall": e.Firewall,
		"logging": map[string]any{
			"level":  "info",
			"format": "text",
		},
	}

	return yaml.Marshal(doc)
}

func firstNonZero(values ...int) int {
	for _, v := range values {
		if v != 0 {
			return v
		}
	}
	return 0
}
