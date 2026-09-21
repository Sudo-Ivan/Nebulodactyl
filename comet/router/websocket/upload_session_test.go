package websocket

import (
	"testing"
	"time"

	"github.com/google/uuid"
)

func resetUploadRegistry(t *testing.T) {
	t.Helper()
	uploadRegistry.Lock()
	uploadRegistry.servers = make(map[string]map[string]*uploadSession)
	uploadRegistry.Unlock()
	t.Cleanup(func() {
		uploadRegistry.Lock()
		uploadRegistry.servers = make(map[string]map[string]*uploadSession)
		uploadRegistry.Unlock()
	})
}

func TestUploadSession_ClaimAndRelease(t *testing.T) {
	resetUploadRegistry(t)
	conn := uuid.Must(uuid.NewRandom())

	id, ok := claimUpload("srv", "/file.tar.gz", conn)
	if !ok || id == "" {
		t.Fatal("expected first claim to succeed")
	}

	// A different connection must not be able to take the live session.
	if _, ok := claimUpload("srv", "/file.tar.gz", uuid.Must(uuid.NewRandom())); ok {
		t.Fatal("expected competing claim on live session to be rejected")
	}

	// The same connection re-claiming rotates the session ID.
	id2, ok := claimUpload("srv", "/file.tar.gz", conn)
	if !ok || id2 == id {
		t.Fatal("expected re-claim by owner to issue a new session ID")
	}

	// Chunks tagged with the stale session ID must be rejected.
	if touchUpload("srv", "/file.tar.gz", id, conn) {
		t.Fatal("expected stale session ID to be rejected")
	}
	if !touchUpload("srv", "/file.tar.gz", id2, conn) {
		t.Fatal("expected current session ID to be accepted")
	}

	releaseUpload("srv", "/file.tar.gz", id2, conn)

	// After release another connection may claim the path.
	if _, ok := claimUpload("srv", "/file.tar.gz", uuid.Must(uuid.NewRandom())); !ok {
		t.Fatal("expected claim after release to succeed")
	}
}

func TestUploadSession_ExpiredClaimCanBeTakenOver(t *testing.T) {
	resetUploadRegistry(t)
	owner := uuid.Must(uuid.NewRandom())

	id, ok := claimUpload("srv", "/a", owner)
	if !ok {
		t.Fatal("expected claim to succeed")
	}

	// Age the session past the TTL.
	uploadRegistry.Lock()
	uploadRegistry.servers["srv"]["/a"].lastActive = time.Now().Add(-2 * uploadSessionTTL)
	uploadRegistry.Unlock()

	// A different connection may take over an expired session.
	if _, ok := claimUpload("srv", "/a", uuid.Must(uuid.NewRandom())); !ok {
		t.Fatal("expected takeover of expired session to succeed")
	}

	// The old owner must not touch the replaced session.
	if touchUpload("srv", "/a", id, owner) {
		t.Fatal("expected old session to be rejected after takeover")
	}
}

func TestUploadSession_SweepRemovesExpired(t *testing.T) {
	resetUploadRegistry(t)
	conn := uuid.Must(uuid.NewRandom())

	if _, ok := claimUpload("srv", "/stale", conn); !ok {
		t.Fatal("expected claim to succeed")
	}
	if _, ok := claimUpload("srv", "/fresh", conn); !ok {
		t.Fatal("expected claim to succeed")
	}

	uploadRegistry.Lock()
	uploadRegistry.servers["srv"]["/stale"].lastActive = time.Now().Add(-2 * uploadSessionTTL)
	uploadRegistry.Unlock()

	sweepUploads()

	uploadRegistry.Lock()
	_, stale := uploadRegistry.servers["srv"]["/stale"]
	_, fresh := uploadRegistry.servers["srv"]["/fresh"]
	uploadRegistry.Unlock()

	if stale {
		t.Fatal("expected expired session to be swept")
	}
	if !fresh {
		t.Fatal("expected live session to survive sweep")
	}
}
