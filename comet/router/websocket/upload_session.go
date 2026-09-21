package websocket

import (
	"sync"
	"time"

	"github.com/google/uuid"
)

// uploadSessionTTL is how long an upload session may sit idle before a
// different connection is allowed to take the path over. Chunks update the
// activity timestamp, so only genuinely abandoned sessions expire.
const uploadSessionTTL = 15 * time.Minute

// uploadSession tracks one chunked upload on a server.
type uploadSession struct {
	id         string
	connID     uuid.UUID
	lastActive time.Time
}

// uploadRegistry serializes upload ownership of a file path across websocket
// connections. Without it two connections could open sessions on the same
// path and interleave chunks at overlapping offsets.
var uploadRegistry = struct {
	sync.Mutex
	servers map[string]map[string]*uploadSession
}{servers: make(map[string]map[string]*uploadSession)}

// sweeperOnce starts the janitor goroutine on the first claim so tests that
// never upload do not leave a stray goroutine running.
var sweeperOnce sync.Once

// claimUpload registers a session for path on serverID owned by connID. It
// returns the session ID and true on success. If another live connection owns
// an unexpired session on the same path the claim is rejected.
func claimUpload(serverID, path string, connID uuid.UUID) (string, bool) {
	sweeperOnce.Do(func() {
		go func() {
			ticker := time.NewTicker(uploadSessionTTL / 3)
			defer ticker.Stop()
			for range ticker.C {
				sweepUploads()
			}
		}()
	})

	uploadRegistry.Lock()
	defer uploadRegistry.Unlock()

	paths, ok := uploadRegistry.servers[serverID]
	if !ok {
		paths = make(map[string]*uploadSession)
		uploadRegistry.servers[serverID] = paths
	}

	if existing, ok := paths[path]; ok {
		if existing.connID != connID && time.Since(existing.lastActive) < uploadSessionTTL {
			return "", false
		}
	}

	session := &uploadSession{
		id:         uuid.Must(uuid.NewRandom()).String(),
		connID:     connID,
		lastActive: time.Now(),
	}
	paths[path] = session

	return session.id, true
}

// touchUpload refreshes the activity timestamp for a session. It returns
// false if the session does not exist or does not match the given ID, which
// means the claim was lost or never held.
func touchUpload(serverID, path, sessionID string, connID uuid.UUID) bool {
	uploadRegistry.Lock()
	defer uploadRegistry.Unlock()

	session, ok := uploadRegistry.servers[serverID][path]
	if !ok || session.id != sessionID || session.connID != connID {
		return false
	}

	session.lastActive = time.Now()
	return true
}

// releaseUpload removes a session if it is still owned by the given session
// ID and connection.
func releaseUpload(serverID, path, sessionID string, connID uuid.UUID) {
	uploadRegistry.Lock()
	defer uploadRegistry.Unlock()

	if session, ok := uploadRegistry.servers[serverID][path]; ok &&
		session.id == sessionID && session.connID == connID {
		delete(uploadRegistry.servers[serverID], path)
		if len(uploadRegistry.servers[serverID]) == 0 {
			delete(uploadRegistry.servers, serverID)
		}
	}
}

// sweepUploads drops sessions idle longer than the TTL so abandoned paths do
// not accumulate in the registry. Expired sessions can already be reclaimed
// by claimUpload; this only bounds memory.
func sweepUploads() {
	uploadRegistry.Lock()
	defer uploadRegistry.Unlock()

	cutoff := time.Now().Add(-uploadSessionTTL)
	for serverID, paths := range uploadRegistry.servers {
		for path, session := range paths {
			if session.lastActive.Before(cutoff) {
				delete(paths, path)
			}
		}
		if len(paths) == 0 {
			delete(uploadRegistry.servers, serverID)
		}
	}
}
