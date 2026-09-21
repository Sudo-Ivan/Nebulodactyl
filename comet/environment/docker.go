package environment

import (
	"context"
	"slices"
	"strconv"
	"strings"
	"sync"

	"emperror.dev/errors"
	"github.com/apex/log"
	"github.com/docker/docker/api/types/container"
	"github.com/docker/docker/api/types/network"
	"github.com/docker/docker/client"

	"github.com/Sudo-Ivan/Nebulodactyl/comet/config"
)

var (
	_conce  sync.Once
	_client *client.Client

	_runtime struct {
		once     sync.Once
		podman   bool
		rootless bool
	}
)

// Docker returns a docker client to be used throughout the codebase. Once a
// client has been created it will be returned for all subsequent calls to this
// function.
func Docker() (*client.Client, error) {
	var err error
	_conce.Do(func() {
		opts := []client.Opt{client.WithAPIVersionNegotiation()}
		if host := config.Get().Docker.Host; host != "" {
			opts = append(opts, client.WithHost(host))
		} else {
			opts = append(opts, client.FromEnv)
		}
		_client, err = client.NewClientWithOpts(opts...)
	})
	return _client, errors.Wrap(err, "environment/docker: could not create client")
}

// inspectRuntime detects which container engine Comet is talking to and
// whether the engine is running in rootless mode. Detection runs once and is
// cached for the lifetime of the process.
func inspectRuntime(ctx context.Context, cli *client.Client) {
	_runtime.once.Do(func() {
		if v, err := cli.ServerVersion(ctx); err == nil {
			for _, comp := range v.Components {
				if strings.Contains(strings.ToLower(comp.Name), "podman") {
					_runtime.podman = true
					break
				}
			}
		}
		if info, err := cli.Info(ctx); err == nil {
			_runtime.rootless = slices.ContainsFunc(info.SecurityOptions, func(s string) bool {
				return s == "rootless" || s == "name=rootless"
			})
		}
		if _runtime.podman {
			log.Info("detected podman container engine")
		}
		if _runtime.rootless {
			log.Info("detected rootless container engine, enabling rootless container mode")
			config.Update(func(c *config.Configuration) {
				c.System.User.Rootless.Enabled = true
			})
		}
	})
}

// IsPodman reports whether the connected container engine is Podman rather
// than Docker. Detection only happens once ConfigureDocker has run, calls
// before that return false.
func IsPodman() bool {
	return _runtime.podman
}

// LogConfig returns the configured container log config adjusted for the
// detected container engine. Podman has no "local" log driver, so it is
// mapped to "json-file" which understands the same max-size and max-file
// options.
func LogConfig(lc container.LogConfig) container.LogConfig {
	if _runtime.podman && lc.Type == "local" {
		lc.Type = "json-file"
	}
	return lc
}

// ConfigureDocker configures the required network for the docker environment.
func ConfigureDocker(ctx context.Context) error {
	// Ensure the required docker network exists on the system.
	cli, err := Docker()
	if err != nil {
		return err
	}

	inspectRuntime(ctx, cli)

	nw := config.Get().Docker.Network
	resource, err := cli.NetworkInspect(ctx, nw.Name, network.InspectOptions{})
	if err != nil {
		if !client.IsErrNotFound(err) {
			return err
		}

		log.Info("creating missing comet0 interface, this could take a few seconds...")
		if err := createDockerNetwork(ctx, cli); err != nil {
			return err
		}
	}

	config.Update(func(c *config.Configuration) {
		c.Docker.Network.Driver = resource.Driver
		switch c.Docker.Network.Driver {
		case "host":
			c.Docker.Network.Interface = "127.0.0.1"
			c.Docker.Network.ISPN = false
		case "overlay":
			fallthrough
		case "weavemesh":
			c.Docker.Network.Interface = ""
			c.Docker.Network.ISPN = true
		default:
			c.Docker.Network.ISPN = false
		}
	})
	return nil
}

// Creates a new network on the machine if one does not exist already.
func createDockerNetwork(ctx context.Context, cli *client.Client) error {
	nw := config.Get().Docker.Network
	enableIPv6 := true

	// Podman does not understand the com.docker.network.* option namespace,
	// passing only the options it supports.
	var options map[string]string
	if _runtime.podman {
		options = map[string]string{
			"mtu": strconv.FormatInt(nw.NetworkMTU, 10),
		}
		if nw.Driver == "bridge" {
			options["isolate"] = strconv.FormatBool(!nw.EnableICC)
		}
	} else {
		options = map[string]string{
			"encryption": "false",
			"com.docker.network.bridge.default_bridge":       "false",
			"com.docker.network.bridge.enable_icc":           strconv.FormatBool(nw.EnableICC),
			"com.docker.network.bridge.enable_ip_masquerade": "true",
			"com.docker.network.bridge.host_binding_ipv4":    "0.0.0.0",
			"com.docker.network.bridge.name":                 "comet0",
			"com.docker.network.driver.mtu":                  strconv.FormatInt(nw.NetworkMTU, 10),
		}
	}

	_, err := cli.NetworkCreate(ctx, nw.Name, network.CreateOptions{
		Driver:     nw.Driver,
		EnableIPv6: &enableIPv6,
		Internal:   nw.IsInternal,
		IPAM: &network.IPAM{
			Config: []network.IPAMConfig{{
				Subnet:  nw.Interfaces.V4.Subnet,
				Gateway: nw.Interfaces.V4.Gateway,
			}, {
				Subnet:  nw.Interfaces.V6.Subnet,
				Gateway: nw.Interfaces.V6.Gateway,
			}},
		},
		Options: options,
	})
	if err != nil {
		return err
	}
	if nw.Driver != "host" && nw.Driver != "overlay" && nw.Driver != "weavemesh" {
		config.Update(func(c *config.Configuration) {
			c.Docker.Network.Interface = c.Docker.Network.Interfaces.V4.Gateway
		})
	}
	return nil
}
