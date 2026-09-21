package server

import (
	"encoding/json"
	"testing"

	. "github.com/franela/goblin"

	"github.com/Sudo-Ivan/Nebulodactyl/comet/environment"
	"github.com/Sudo-Ivan/Nebulodactyl/comet/events"
	"github.com/Sudo-Ivan/Nebulodactyl/comet/system"
)

func TestResourceUsage(t *testing.T) {
	g := Goblin(t)

	g.Describe("ResourceUsage", func() {
		g.It("includes cumulative network bytes in the marshaled stats payload", func() {
			ru := ResourceUsage{State: system.NewAtomicString(environment.ProcessRunningState)}
			ru.UpdateStats(environment.Stats{
				Memory:      1024,
				MemoryLimit: 2048,
				CpuAbsolute: 12.5,
				Uptime:      3600,
				Network: environment.NetworkStats{
					RxBytes: 123456,
					TxBytes: 7890,
				},
			})

			b, err := json.Marshal(ru)
			g.Assert(err).IsNil()

			var payload struct {
				State       string  `json:"state"`
				Disk        int64   `json:"disk_bytes"`
				Memory      uint64  `json:"memory_bytes"`
				MemoryLimit uint64  `json:"memory_limit_bytes"`
				CpuAbsolute float64 `json:"cpu_absolute"`
				Uptime      int64   `json:"uptime"`
				Network     struct {
					RxBytes uint64 `json:"rx_bytes"`
					TxBytes uint64 `json:"tx_bytes"`
				} `json:"network"`
			}
			g.Assert(json.Unmarshal(b, &payload)).IsNil()

			g.Assert(payload.State).Equal(environment.ProcessRunningState)
			g.Assert(payload.Memory).Equal(uint64(1024))
			g.Assert(payload.MemoryLimit).Equal(uint64(2048))
			g.Assert(payload.CpuAbsolute).Equal(12.5)
			g.Assert(payload.Uptime).Equal(int64(3600))
			g.Assert(payload.Network.RxBytes).Equal(uint64(123456))
			g.Assert(payload.Network.TxBytes).Equal(uint64(7890))
		})

		g.It("retains network counters when decoded from a resource event", func() {
			// Mirrors the decode path in StartEventListeners which turns an
			// environment.ResourceEvent into the stored resource usage. If the
			// network counters were dropped here they would never reach the
			// stats event sent to the panel.
			st := environment.Stats{
				Network: environment.NetworkStats{
					RxBytes: 4321,
					TxBytes: 987,
				},
			}
			enc, err := json.Marshal(events.Event{Topic: environment.ResourceEvent, Data: st})
			g.Assert(err).IsNil()

			var decoded struct {
				Topic string
				Data  environment.Stats
			}
			g.Assert(events.DecodeTo(enc, &decoded)).IsNil()

			ru := ResourceUsage{State: system.NewAtomicString(environment.ProcessRunningState)}
			ru.UpdateStats(decoded.Data)

			g.Assert(ru.Network.RxBytes).Equal(uint64(4321))
			g.Assert(ru.Network.TxBytes).Equal(uint64(987))
		})

		g.It("resets network counters to zero", func() {
			ru := ResourceUsage{State: system.NewAtomicString(environment.ProcessRunningState)}
			ru.UpdateStats(environment.Stats{
				Memory:      1024,
				CpuAbsolute: 12.5,
				Uptime:      3600,
				Network: environment.NetworkStats{
					RxBytes: 123456,
					TxBytes: 7890,
				},
			})

			ru.Reset()

			g.Assert(ru.Memory).Equal(uint64(0))
			g.Assert(ru.CpuAbsolute).Equal(0.0)
			g.Assert(ru.Uptime).Equal(int64(0))
			g.Assert(ru.Network.RxBytes).Equal(uint64(0))
			g.Assert(ru.Network.TxBytes).Equal(uint64(0))
		})
	})
}
