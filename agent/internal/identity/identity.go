// Package identity manages the node's X25519 keypair in the PEM format
// nebula-cert expects. Keys are raw 32 byte curve25519 values, base64
// encoded inside NEBULA X25519 PUBLIC KEY and PRIVATE KEY PEM blocks,
// identical to what nebula-cert keygen produces.
package identity

import (
	"crypto/ecdh"
	"crypto/rand"
	"encoding/base64"
	"encoding/pem"
	"fmt"
	"os"

	"github.com/Sudo-Ivan/Nebulodactyl/agent/internal/fsutil"
)

const (
	publicKeyType  = "NEBULA X25519 PUBLIC KEY"
	privateKeyType = "NEBULA X25519 PRIVATE KEY"
)

// Keypair is a node identity.
type Keypair struct {
	Private *ecdh.PrivateKey
}

// PublicPEM returns the PEM encoded public key sent to the panel during
// enrollment.
func (k *Keypair) PublicPEM() string {
	return string(pem.EncodeToMemory(&pem.Block{
		Type:  publicKeyType,
		Bytes: k.Private.PublicKey().Bytes(),
	}))
}

// PublicB64 returns the base64 public key, the wire format the panel
// enrollment endpoint validates.
func (k *Keypair) PublicB64() string {
	return base64.StdEncoding.EncodeToString(k.Private.PublicKey().Bytes())
}

// LoadOrGenerate reads the keypair from keyPath, or creates and persists a
// fresh one. The private key file is written with 0600 permissions.
func LoadOrGenerate(keyPath string) (*Keypair, error) {
	data, err := os.ReadFile(keyPath)
	if err == nil {
		block, _ := pem.Decode(data)
		if block == nil || block.Type != privateKeyType {
			return nil, fmt.Errorf("identity: %s does not contain a %s block", keyPath, privateKeyType)
		}
		key, err := ecdh.X25519().NewPrivateKey(block.Bytes)
		if err != nil {
			return nil, fmt.Errorf("identity: parse private key: %w", err)
		}
		return &Keypair{Private: key}, nil
	}
	if !os.IsNotExist(err) {
		return nil, fmt.Errorf("identity: read %s: %w", keyPath, err)
	}

	key, err := ecdh.X25519().GenerateKey(rand.Reader)
	if err != nil {
		return nil, fmt.Errorf("identity: generate key: %w", err)
	}

	if err := fsutil.WriteAtomic(keyPath, pem.EncodeToMemory(&pem.Block{
		Type:  privateKeyType,
		Bytes: key.Bytes(),
	}), 0o600); err != nil {
		return nil, err
	}

	return &Keypair{Private: key}, nil
}
