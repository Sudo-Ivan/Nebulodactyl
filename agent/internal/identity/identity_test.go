package identity

import (
	"encoding/base64"
	"encoding/pem"
	"os"
	"path/filepath"
	"strings"
	"testing"
)

func TestLoadOrGenerateRoundtrip(t *testing.T) {
	dir := t.TempDir()
	keyPath := filepath.Join(dir, "host.key")

	kp, err := LoadOrGenerate(keyPath)
	if err != nil {
		t.Fatalf("generate: %v", err)
	}

	// Public key must be a base64 encoded 32 byte X25519 key, which is the
	// exact shape the panel enrollment endpoint validates.
	raw, err := base64.StdEncoding.DecodeString(kp.PublicB64())
	if err != nil {
		t.Fatalf("public key not base64: %v", err)
	}
	if len(raw) != 32 {
		t.Fatalf("public key length = %d, want 32", len(raw))
	}

	// PEM block type must match what nebula-cert produces.
	block, _ := pem.Decode([]byte(kp.PublicPEM()))
	if block == nil || block.Type != publicKeyType {
		t.Fatalf("unexpected PEM block %v", block)
	}

	// A second load must return the same key rather than rotating it.
	kp2, err := LoadOrGenerate(keyPath)
	if err != nil {
		t.Fatalf("reload: %v", err)
	}
	if kp.PublicB64() != kp2.PublicB64() {
		t.Fatal("key changed across reload")
	}

	info, err := os.Stat(keyPath)
	if err != nil {
		t.Fatal(err)
	}
	if info.Mode().Perm() != 0o600 {
		t.Fatalf("key permissions = %o, want 600", info.Mode().Perm())
	}
}

func TestLoadRejectsWrongBlock(t *testing.T) {
	dir := t.TempDir()
	keyPath := filepath.Join(dir, "host.key")
	if err := os.WriteFile(keyPath, []byte("-----BEGIN RSA PRIVATE KEY-----\nAAAA\n-----END RSA PRIVATE KEY-----\n"), 0o600); err != nil {
		t.Fatal(err)
	}
	if _, err := LoadOrGenerate(keyPath); err == nil || !strings.Contains(err.Error(), privateKeyType) {
		t.Fatalf("expected block type error, got %v", err)
	}
}
