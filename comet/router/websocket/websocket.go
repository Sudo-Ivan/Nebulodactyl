package websocket

import (
	"bytes"
	"context"
	"encoding/base64"
	"encoding/json"
	"fmt"
	"net/http"
	"path/filepath"
	"strconv"
	"strings"
	"sync"
	"time"

	"emperror.dev/errors"
	"github.com/Sudo-Ivan/Nebulodactyl/comet/internal/models"
	"github.com/apex/log"
	"github.com/gbrlsnchs/jwt/v3"
	"github.com/gin-gonic/gin"
	"github.com/google/uuid"
	"github.com/gorilla/websocket"

	"github.com/Sudo-Ivan/Nebulodactyl/comet/system"

	"github.com/Sudo-Ivan/Nebulodactyl/comet/config"
	"github.com/Sudo-Ivan/Nebulodactyl/comet/environment"
	"github.com/Sudo-Ivan/Nebulodactyl/comet/environment/docker"
	"github.com/Sudo-Ivan/Nebulodactyl/comet/router/tokens"
	"github.com/Sudo-Ivan/Nebulodactyl/comet/server"
)

const (
	PermissionConnect          = "websocket.connect"
	PermissionSendCommand      = "control.console"
	PermissionSendPowerStart   = "control.start"
	PermissionSendPowerStop    = "control.stop"
	PermissionSendPowerRestart = "control.restart"
	PermissionReceiveErrors    = "admin.websocket.errors"
	PermissionReceiveInstall   = "admin.websocket.install"
	PermissionReceiveTransfer  = "admin.websocket.transfer"
	PermissionReceiveBackups   = "backup.read"
	PermissionFileCreate       = "file.create"
	PermissionFileUpdate       = "file.update"
)

// The default 4KB frame limit is raised while an upload session is open so
// base64 chunks can be a useful size, then restored when it closes.
const (
	defaultReadLimit      = 4096
	uploadReadLimit       = 8 << 20
	maxUploadChunkEncoded = 6 << 20 // 6MiB base64 ~= 4.5MiB decoded

	// MaxUploadFrame is the largest websocket frame accepted while an upload
	// session is open. It must fit one max-size base64 chunk plus the JSON
	// envelope.
	MaxUploadFrame = 8 << 20
)

type Handler struct {
	sync.RWMutex `json:"-"`
	Connection   *websocket.Conn `json:"-"`
	jwt          *tokens.WebsocketPayload
	server       *server.Server
	ra           server.RequestActivity
	uuid         uuid.UUID
	limiter      *LimiterBucket

	// Active chunked upload state. Only one upload is tracked per
	// connection; a second start request is rejected while one is open. The
	// session ID is issued by the daemon on upload start and must be echoed
	// back on every chunk so a stale or hijacked stream cannot write into
	// the file.
	uploadPath      string
	uploadOffset    int64
	uploadSessionID string
	uploading       bool
}

var (
	ErrJwtNotPresent    = errors.New("jwt: no jwt present")
	ErrJwtNoConnectPerm = errors.New("jwt: missing connect permission")
	ErrJwtUuidMismatch  = errors.New("jwt: server uuid mismatch")
	ErrJwtOnDenylist    = errors.New("jwt: created too far in past (denylist)")
)

func IsJwtError(err error) bool {
	return errors.Is(err, ErrJwtNotPresent) ||
		errors.Is(err, ErrJwtNoConnectPerm) ||
		errors.Is(err, ErrJwtUuidMismatch) ||
		errors.Is(err, ErrJwtOnDenylist) ||
		errors.Is(err, jwt.ErrExpValidation)
}

// NewTokenPayload parses a JWT into a websocket token payload.
func NewTokenPayload(token []byte) (*tokens.WebsocketPayload, error) {
	var payload tokens.WebsocketPayload
	if err := tokens.ParseToken(token, &payload); err != nil {
		return nil, err
	}

	if payload.Denylisted() {
		return nil, ErrJwtOnDenylist
	}

	if !payload.HasPermission(PermissionConnect) || !payload.HasScope(tokens.Websocket) {
		return nil, ErrJwtNoConnectPerm
	}

	return &payload, nil
}

// GetHandler returns a new websocket handler using the context provided.
func GetHandler(s *server.Server, w http.ResponseWriter, r *http.Request, c *gin.Context) (*Handler, error) {
	upgrader := websocket.Upgrader{
		EnableCompression: true,
		// Ensure that the websocket request is originating from the Panel itself,
		// and not some other location.
		CheckOrigin: func(r *http.Request) bool {
			o := r.Header.Get("Origin")
			if o == config.Get().PanelLocation {
				return true
			}
			for _, origin := range config.Get().AllowedOrigins {
				if origin == "*" || origin == o {
					return true
				}
			}
			return false
		},
	}

	conn, err := upgrader.Upgrade(w, r, nil)
	if err != nil {
		return nil, err
	}

	u, err := uuid.NewRandom()
	if err != nil {
		return nil, err
	}

	conn.SetReadLimit(defaultReadLimit)
	_ = conn.SetCompressionLevel(5)

	return &Handler{
		Connection: conn,
		jwt:        nil,
		server:     s,
		ra:         s.NewRequestActivity("", c.ClientIP()),
		uuid:       u,
		limiter:    NewLimiter(),
	}, nil
}

func (h *Handler) Uuid() uuid.UUID {
	return h.uuid
}

func (h *Handler) Logger() *log.Entry {
	return log.WithField("subsystem", "websocket").
		WithField("connection", h.Uuid().String()).
		WithField("server", h.server.ID())
}

func (h *Handler) SendJson(v Message) error {
	// Do not send JSON down the line if the JWT on the connection is not valid!
	if err := h.TokenValid(); err != nil {
		_ = h.unsafeSendJson(Message{
			Event: JwtErrorEvent,
			Args:  []string{err.Error()},
		})
		return nil
	}

	if j := h.GetJwt(); j != nil {
		// If we're sending installation output but the user does not have the required
		// permissions to see the output, don't send it down the line.
		if v.Event == server.InstallOutputEvent {
			if !j.HasPermission(PermissionReceiveInstall) {
				return nil
			}
		}

		// If the user does not have permission to see backup events, do not emit
		// them over the socket.
		if strings.HasPrefix(string(v.Event), server.BackupCompletedEvent) {
			if !j.HasPermission(PermissionReceiveBackups) {
				return nil
			}
		}

		// If we are sending transfer output, only send it to the user if they have the required permissions.
		if v.Event == server.TransferLogsEvent {
			if !j.HasPermission(PermissionReceiveTransfer) {
				return nil
			}
		}
	}

	if err := h.unsafeSendJson(v); err != nil {
		// Not entirely sure how this happens (likely just when there is a ton of console spam)
		// but I don't care to fix it right now, so just mask the error and throw a warning into
		// the logs for us to look into later.
		if errors.Is(err, websocket.ErrCloseSent) {
			if h.server != nil {
				h.server.Log().WithField("subsystem", "websocket").
					WithField("event", v.Event).
					Warn("failed to send event to websocket: close already sent")
			}
			return nil
		}

		return err
	}

	return nil
}

// Sends JSON over the websocket connection, ignoring the authentication state of the
// socket user. Do not call this directly unless you are positive a response should be
// sent back to the client!
func (h *Handler) unsafeSendJson(v interface{}) error {
	h.Lock()
	defer h.Unlock()

	return h.Connection.WriteJSON(v)
}

// TokenValid checks if the JWT is still valid.
func (h *Handler) TokenValid() error {
	j := h.GetJwt()
	if j == nil {
		return ErrJwtNotPresent
	}

	if err := jwt.ExpirationTimeValidator(time.Now())(&j.Payload); err != nil {
		return err
	}

	if j.Denylisted() {
		return ErrJwtOnDenylist
	}

	if !j.HasPermission(PermissionConnect) || !j.HasScope(tokens.Websocket) {
		return ErrJwtNoConnectPerm
	}

	if h.server.ID() != j.GetServerUuid() {
		return ErrJwtUuidMismatch
	}

	return nil
}

// SendErrorJson sends an error back to the connected websocket instance by checking the permissions
// of the token. If the user has the "receive-errors" grant we will send back the actual
// error message, otherwise we just send back a standard error message.
func (h *Handler) SendErrorJson(msg Message, err error, shouldLog ...bool) error {
	j := h.GetJwt()
	isJWTError := IsJwtError(err)

	wsm := Message{
		Event: ErrorEvent,
		Args:  []string{"an unexpected error was encountered while handling this request"},
	}

	if isJWTError || (j != nil && j.HasPermission(PermissionReceiveErrors)) {
		if isJWTError {
			wsm.Event = JwtErrorEvent
		}
		wsm.Args = []string{err.Error()}
	}

	m, u := h.GetErrorMessage(wsm.Args[0])
	wsm.Args = []string{m}

	if !isJWTError && (len(shouldLog) == 0 || (len(shouldLog) == 1 && shouldLog[0] == true)) {
		h.server.Log().WithFields(log.Fields{"event": msg.Event, "error_identifier": u.String(), "error": err}).
			Errorf("error processing websocket event \"%s\"", msg.Event)
	}

	return h.unsafeSendJson(wsm)
}

// GetErrorMessage converts an error message into a more readable representation and returns a UUID
// that can be cross-referenced to find the specific error that triggered.
func (h *Handler) GetErrorMessage(msg string) (string, uuid.UUID) {
	u := uuid.Must(uuid.NewRandom())

	m := fmt.Sprintf("Error Event [%s]: %s", u.String(), msg)

	return m, u
}

// GetJwt returns the JWT for the websocket in a race-safe manner.
func (h *Handler) GetJwt() *tokens.WebsocketPayload {
	h.RLock()
	defer h.RUnlock()

	return h.jwt
}

// setJwt sets the JWT for the websocket in a race-safe manner.
func (h *Handler) setJwt(token *tokens.WebsocketPayload) {
	h.Lock()
	h.ra = h.ra.SetUser(token.UserUUID)
	h.jwt = token
	h.Unlock()
}

// HandleInbound handles an inbound socket request and route it to the proper action.
func (h *Handler) HandleInbound(ctx context.Context, m Message) error {
	if h.server.IsSuspended() {
		return server.ErrSuspended
	}

	if h.IsThrottled(m.Event) {
		return nil
	}

	if m.Event != AuthenticationEvent {
		if err := h.TokenValid(); err != nil {
			h.unsafeSendJson(Message{
				Event: JwtErrorEvent,
				Args:  []string{err.Error()},
			})
			return nil
		}
	}

	switch m.Event {
	case AuthenticationEvent:
		{
			token, err := NewTokenPayload([]byte(strings.Join(m.Args, "")))
			if err != nil {
				return err
			}

			// Check if the user has previously authenticated successfully.
			newConnection := h.GetJwt() == nil

			// Previously there was a HasPermission(PermissionConnect) check around this,
			// however NewTokenPayload will return an error if it doesn't have the connect
			// permission meaning that it was a redundant function call.
			h.setJwt(token)

			// Tell the client they authenticated successfully.
			_ = h.unsafeSendJson(Message{Event: AuthenticationSuccessEvent})

			// Check if the client was refreshing their authentication token
			// instead of authenticating for the first time.
			if !newConnection {
				// This prevents duplicate status messages as outlined in
				// https://github.com/pterodactyl/panel/issues/2077
				return nil
			}

			// Now that we've authenticated with the token and confirmed that we're not
			// reconnecting to the socket, register the event listeners for the server and
			// the token expiration.
			h.registerListenerEvents(ctx)

			// On every authentication event, send the current server status back
			// to the client. :)
			state := h.server.Environment.State()
			_ = h.SendJson(Message{
				Event: server.StatusEvent,
				Args:  []string{state},
			})

			// Only send the current disk usage if the server is offline, if docker container is running,
			// Environment#EnableResourcePolling() will send this data to all clients.
			if state == environment.ProcessOfflineState {
				if !h.server.IsInstalling() && !h.server.IsTransferring() {
					_ = h.server.Filesystem().HasSpaceAvailable(false)

					b, _ := json.Marshal(h.server.Proc())
					_ = h.SendJson(Message{
						Event: server.StatsEvent,
						Args:  []string{string(b)},
					})
				}
			}

			return nil
		}
	case SetStateEvent:
		{
			action := server.PowerAction(strings.Join(m.Args, ""))

			actions := make(map[server.PowerAction]string)
			actions[server.PowerActionStart] = PermissionSendPowerStart
			actions[server.PowerActionStop] = PermissionSendPowerStop
			actions[server.PowerActionRestart] = PermissionSendPowerRestart
			actions[server.PowerActionTerminate] = PermissionSendPowerStop

			// Check that they have permission to perform this action if it is needed.
			if permission, exists := actions[action]; exists {
				if !h.GetJwt().HasPermission(permission) {
					return nil
				}
			}

			err := h.server.HandlePowerAction(action)
			if errors.Is(err, system.ErrLockerLocked) {
				m, _ := h.GetErrorMessage("another power action is currently being processed for this server, please try again later")

				_ = h.SendJson(Message{
					Event: ErrorEvent,
					Args:  []string{m},
				})

				return nil
			}

			if err == nil {
				h.server.SaveActivity(h.ra, models.Event(server.ActivityPowerPrefix+action), nil)
			}

			return err
		}
	case SendServerLogsEvent:
		{
			ctx, cancel := context.WithTimeout(context.Background(), time.Second*5)
			defer cancel()
			if running, _ := h.server.Environment.IsRunning(ctx); !running {
				return nil
			}

			logs, err := h.server.Environment.Readlog(config.Get().System.WebsocketLogCount)
			if err != nil {
				return err
			}

			for _, line := range logs {
				_ = h.SendJson(Message{
					Event: server.ConsoleOutputEvent,
					Args:  []string{line},
				})
			}

			return nil
		}
	case SendStatsEvent:
		{
			b, _ := json.Marshal(h.server.Proc())
			_ = h.SendJson(Message{
				Event: server.StatsEvent,
				Args:  []string{string(b)},
			})

			return nil
		}
	case SendCommandEvent:
		{
			if !h.GetJwt().HasPermission(PermissionSendCommand) {
				return nil
			}

			if h.server.Environment.State() == environment.ProcessOfflineState {
				return nil
			}

			// TODO(dane): should probably add a new process state that is "booting environment" or something
			//  so that we can better handle this and only set the environment to booted once we're attached.
			//
			//  Or maybe just an IsBooted function?
			if h.server.Environment.State() == environment.ProcessStartingState {
				if e, ok := h.server.Environment.(*docker.Environment); ok {
					if !e.IsAttached() {
						return nil
					}
				}
			}

			if err := h.server.Environment.SendCommand(strings.Join(m.Args, "")); err != nil {
				return err
			}
			h.server.SaveActivity(h.ra, server.ActivityConsoleCommand, models.ActivityMeta{
				"command": strings.Join(m.Args, ""),
			})
			return nil
		}
	case UploadStartEvent:
		return h.handleUploadStart(m)
	case UploadChunkEvent:
		return h.handleUploadChunk(m)
	case UploadFinishEvent:
		return h.handleUploadFinish()
	case UploadAbortEvent:
		return h.handleUploadAbort()
	}

	return nil
}

// handleUploadStart opens a chunked upload session over the websocket. The
// path is resolved against the server root and any existing bytes are
// reported back as the resume offset so interrupted uploads can continue
// instead of restarting.
func (h *Handler) handleUploadStart(m Message) error {
	if !h.GetJwt().HasPermission(PermissionFileCreate) && !h.GetJwt().HasPermission(PermissionFileUpdate) {
		return nil
	}
	if h.uploading {
		_ = h.SendJson(Message{Event: ErrorEvent, Args: []string{"an upload is already in progress on this connection"}})
		return nil
	}
	if len(m.Args) < 1 || m.Args[0] == "" {
		_ = h.SendJson(Message{Event: ErrorEvent, Args: []string{"missing upload path"}})
		return nil
	}

	path := m.Args[0]
	if err := h.server.Filesystem().IsIgnored(path); err != nil {
		_ = h.SendJson(Message{Event: ErrorEvent, Args: []string{"the requested file is on the denylist"}})
		return nil
	}
	var offset int64
	if st, err := h.server.Filesystem().UnixFS().Stat(path); err == nil {
		if st.IsDir() {
			_ = h.SendJson(Message{Event: ErrorEvent, Args: []string{"cannot upload over a directory"}})
			return nil
		}
		offset = st.Size()
	}

	// Claim the path for this connection so a second connection cannot open
	// its own session on the same file and interleave chunks.
	sessionID, ok := claimUpload(h.server.ID(), path, h.uuid)
	if !ok {
		_ = h.SendJson(Message{Event: ErrorEvent, Args: []string{"an upload to this file is already in progress on another connection"}})
		return nil
	}

	h.uploading = true
	h.uploadPath = path
	h.uploadOffset = offset
	h.uploadSessionID = sessionID
	h.Connection.SetReadLimit(uploadReadLimit)

	_ = h.SendJson(Message{
		Event: UploadReadyEvent,
		Args:  []string{path, strconv.FormatInt(offset, 10), sessionID},
	})
	return nil
}

// handleUploadChunk writes one base64 encoded chunk at the tracked offset.
// The client must wait for the progress ack before sending the next chunk,
// which keeps ordering and offsets simple. Args are the session ID issued
// by upload ready, the offset the client believes it is writing at, and the
// base64 payload; both are verified against the tracked session state.
func (h *Handler) handleUploadChunk(m Message) error {
	if !h.uploading || len(m.Args) < 3 {
		_ = h.SendJson(Message{Event: ErrorEvent, Args: []string{"no upload in progress"}})
		return nil
	}

	// Reject chunks for a different session or a different connection
	// entirely, and chunks claiming an offset that does not match where the
	// session actually is. A stale chunk after a resume or truncation would
	// otherwise corrupt the file silently.
	if m.Args[0] != h.uploadSessionID || !touchUpload(h.server.ID(), h.uploadPath, m.Args[0], h.uuid) {
		_ = h.SendJson(Message{Event: ErrorEvent, Args: []string{"invalid upload session"}})
		return nil
	}
	clientOffset, err := strconv.ParseInt(m.Args[1], 10, 64)
	if err != nil || clientOffset != h.uploadOffset {
		_ = h.SendJson(Message{Event: ErrorEvent, Args: []string{"upload offset mismatch"}})
		return nil
	}

	if len(m.Args[2]) > maxUploadChunkEncoded {
		_ = h.SendJson(Message{Event: ErrorEvent, Args: []string{"upload chunk exceeds the maximum size"}})
		return nil
	}

	data, err := base64.StdEncoding.DecodeString(m.Args[2])
	if err != nil {
		_ = h.SendJson(Message{Event: ErrorEvent, Args: []string{"invalid upload chunk encoding"}})
		return nil
	}

	// Enforce the same per-file upload limit as the HTTP endpoints, keyed off
	// the accumulated offset so a chunked session cannot grow a file past it.
	maxFileSizeBytes := config.Get().Api.UploadLimit * 1024 * 1024
	if h.uploadOffset+int64(len(data)) > maxFileSizeBytes || h.uploadOffset+int64(len(data)) < 0 {
		_ = h.SendJson(Message{Event: ErrorEvent, Args: []string{"file exceeds the maximum upload size"}})
		return nil
	}

	n, err := h.server.Filesystem().WriteAt(h.uploadPath, bytes.NewReader(data), h.uploadOffset, 0o644)
	if err != nil {
		_ = h.SendJson(Message{Event: ErrorEvent, Args: []string{err.Error()}})
		return nil
	}
	h.uploadOffset += n

	_ = h.SendJson(Message{
		Event: UploadProgressEvent,
		Args:  []string{h.uploadPath, strconv.FormatInt(h.uploadOffset, 10)},
	})
	return nil
}

func (h *Handler) handleUploadFinish() error {
	if !h.uploading {
		_ = h.SendJson(Message{Event: ErrorEvent, Args: []string{"no upload in progress"}})
		return nil
	}

	path := h.uploadPath
	h.resetUpload()

	_ = h.SendJson(Message{Event: UploadCompleteEvent, Args: []string{path}})
	h.server.SaveActivity(h.ra, server.ActivityFileUploaded, models.ActivityMeta{
		"file":      filepath.Base(path),
		"directory": filepath.Dir(path),
	})
	return nil
}

func (h *Handler) handleUploadAbort() error {
	h.resetUpload()
	_ = h.SendJson(Message{Event: UploadAbortEvent})
	return nil
}

func (h *Handler) resetUpload() {
	if h.uploading && h.uploadSessionID != "" {
		releaseUpload(h.server.ID(), h.uploadPath, h.uploadSessionID, h.uuid)
	}
	h.uploading = false
	h.uploadPath = ""
	h.uploadOffset = 0
	h.uploadSessionID = ""
	h.Connection.SetReadLimit(defaultReadLimit)
}

// CloseUpload releases any open upload session. Called when the connection
// read loop exits so a dropped client does not hold the path claim until the
// session TTL expires.
func (h *Handler) CloseUpload() {
	h.resetUpload()
}

// IsUploading reports whether a chunked upload session is open on this
// connection. It is read from the connection read loop, the same goroutine
// that runs the upload handlers, so no locking is required.
func (h *Handler) IsUploading() bool {
	return h.uploading
}
