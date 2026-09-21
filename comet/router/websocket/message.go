package websocket

type Event string

const (
	AuthenticationSuccessEvent = Event("auth success")
	TokenExpiringEvent         = Event("token expiring")
	TokenExpiredEvent          = Event("token expired")
	AuthenticationEvent        = Event("auth")
	SetStateEvent              = Event("set state")
	SendServerLogsEvent        = Event("send logs")
	SendCommandEvent           = Event("send command")
	SendStatsEvent             = Event("send stats")
	UploadStartEvent           = Event("file upload start")
	UploadChunkEvent           = Event("file upload chunk")
	UploadFinishEvent          = Event("file upload finish")
	UploadAbortEvent           = Event("file upload abort")
	UploadReadyEvent           = Event("upload ready")
	UploadProgressEvent        = Event("upload progress")
	UploadCompleteEvent        = Event("upload complete")
	ErrorEvent                 = Event("daemon error")
	JwtErrorEvent              = Event("jwt error")
	ThrottledEvent             = Event("throttled")
)

type Message struct {
	// The event to perform.
	Event Event `json:"event"`

	// The data to pass along, only used by power/command currently. Other requests
	// should either omit the field or pass an empty value as it is ignored.
	Args []string `json:"args,omitempty"`
}
