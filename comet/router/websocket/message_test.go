package websocket

import "testing"

func TestMessage_IsUploadEvent(t *testing.T) {
	for _, e := range []Event{UploadStartEvent, UploadChunkEvent, UploadFinishEvent, UploadAbortEvent} {
		if !(Message{Event: e}).IsUploadEvent() {
			t.Errorf("expected %q to be classified as an upload event", e)
		}
	}
	for _, e := range []Event{AuthenticationEvent, SetStateEvent, SendCommandEvent, SendStatsEvent, SendServerLogsEvent, UploadReadyEvent, UploadProgressEvent, UploadCompleteEvent, ErrorEvent} {
		if (Message{Event: e}).IsUploadEvent() {
			t.Errorf("expected %q to not be classified as an upload event", e)
		}
	}
}

// The read loop caps inbound frames at 32KiB unless an upload session is
// open; this guard documents that the cap must fit an encoded chunk plus
// the JSON envelope or every chunk would be silently dropped.
func TestMaxUploadFrameFitsChunk(t *testing.T) {
	if MaxUploadFrame <= maxUploadChunkEncoded {
		t.Fatalf("MaxUploadFrame %d must exceed maxUploadChunkEncoded %d", MaxUploadFrame, maxUploadChunkEncoded)
	}
}
