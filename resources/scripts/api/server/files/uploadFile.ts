import axios from 'axios';
import http from '@/api/http';
import getFileUploadUrl from '@/api/server/files/getFileUploadUrl';
import { getGlobalDaemonType } from '@/api/server/getServer';

// Decoded bytes per websocket frame. The daemon raises its frame read
// limit to 8MiB while an upload session is open.
const WS_CHUNK_SIZE = 3 * 1024 * 1024;
const WS_EVENT_TIMEOUT = 10_000;

const daemonType = (): string => getGlobalDaemonType();

/**
 * Converts an ArrayBuffer to base64 without blowing the call stack on
 * large buffers.
 */
const toBase64 = (buffer: ArrayBuffer): string => {
    const bytes = new Uint8Array(buffer);
    let binary = '';
    const step = 8192;
    for (let i = 0; i < bytes.length; i += step) {
        binary += String.fromCharCode(...bytes.subarray(i, i + step));
    }
    return btoa(binary);
};

interface SocketCredentials {
    token: string;
    socket: string;
}

const getSocketCredentials = async (uuid: string): Promise<SocketCredentials> => {
    const { data } = await http.get(`/api/client/servers/${daemonType()}/${uuid}/websocket`);
    return data.data;
};

interface SocketMessage {
    event: string;
    args?: string[];
}

/**
 * Waits for a specific event on the socket, rejecting on error events or
 * timeout so the caller can fall back to HTTP.
 */
const awaitEvent = (ws: WebSocket, wanted: string[], signal: AbortSignal): Promise<SocketMessage> =>
    new Promise((resolve, reject) => {
        const timer = setTimeout(() => reject(new Error('websocket event timeout')), WS_EVENT_TIMEOUT);

        const onMessage = (raw: MessageEvent) => {
            let msg: SocketMessage;
            try {
                msg = JSON.parse(raw.data);
            } catch {
                return;
            }
            if (msg.event === 'daemon error' || msg.event === 'jwt error') {
                cleanup();
                reject(new Error(msg.args?.join(' ') ?? 'daemon error'));
            } else if (wanted.includes(msg.event)) {
                cleanup();
                resolve(msg);
            }
        };
        const onClose = () => {
            cleanup();
            reject(new Error('websocket closed while waiting for an event'));
        };
        const onAbort = () => {
            cleanup();
            reject(new DOMException('Aborted', 'AbortError'));
        };
        const cleanup = () => {
            clearTimeout(timer);
            ws.removeEventListener('message', onMessage);
            ws.removeEventListener('close', onClose);
            signal.removeEventListener('abort', onAbort);
        };

        // An already-aborted signal never fires the abort listener.
        if (signal.aborted) {
            reject(new DOMException('Aborted', 'AbortError'));
            return;
        }

        ws.addEventListener('message', onMessage);
        ws.addEventListener('close', onClose);
        signal.addEventListener('abort', onAbort);
    });

const sendEvent = (ws: WebSocket, event: string, args: string[] = []) => ws.send(JSON.stringify({ event, args }));

/**
 * Uploads a file over the server websocket using the chunked upload
 * protocol. Resolves when the daemon confirms completion. Rejects when
 * the daemon does not support the events or the connection drops, so the
 * caller can fall back to the resumable HTTP endpoint.
 */
const uploadOverSocket = async (
    uuid: string,
    file: File,
    directory: string,
    signal: AbortSignal,
    onProgress: (loaded: number) => void,
): Promise<void> => {
    const { token, socket } = await getSocketCredentials(uuid);
    const path = `${directory.replace(/\/+$/, '')}/${file.name}`;
    let completed = false;

    const ws = new WebSocket(socket);
    try {
        await new Promise<void>((resolve, reject) => {
            const timer = setTimeout(() => reject(new Error('websocket connect timeout')), WS_EVENT_TIMEOUT);
            ws.addEventListener('open', () => {
                clearTimeout(timer);
                resolve();
            });
            ws.addEventListener('error', () => {
                clearTimeout(timer);
                reject(new Error('websocket connect failed'));
            });
        });

        sendEvent(ws, 'auth', [token]);
        await awaitEvent(ws, ['auth success'], signal);

        sendEvent(ws, 'file upload start', [path]);
        const ready = await awaitEvent(ws, ['upload ready'], signal);
        let offset = Number(ready.args?.[1] ?? 0);
        if (!Number.isFinite(offset) || offset < 0) {
            offset = 0;
        }
        if (offset > file.size) {
            // A stale partial larger than this upload would be left in
            // place. The websocket protocol can only append, so restart
            // through the HTTP path which truncates on offset 0.
            throw new Error('stored upload offset exceeds the file size');
        }
        onProgress(offset);

        while (offset < file.size) {
            const buffer = await file.slice(offset, offset + WS_CHUNK_SIZE).arrayBuffer();
            sendEvent(ws, 'file upload chunk', [toBase64(buffer)]);

            const ack = await awaitEvent(ws, ['upload progress'], signal);
            const acked = Number(ack.args?.[1] ?? 0);
            if (acked <= offset) {
                throw new Error('upload did not advance');
            }
            offset = acked;
            onProgress(offset);
        }

        sendEvent(ws, 'file upload finish', [path]);
        await awaitEvent(ws, ['upload complete'], signal);
        completed = true;
    } finally {
        if (!completed) {
            try {
                sendEvent(ws, 'file upload abort', []);
            } catch {
                // Socket may already be closed.
            }
        }
        ws.close();
    }
};

/**
 * Resumable HTTP upload against the daemon's PUT endpoint. HEAD reports
 * the stored offset after an interruption so retries continue where they
 * left off.
 */
const uploadOverHttp = async (
    uuid: string,
    file: File,
    directory: string,
    signal: AbortSignal,
    onProgress: (loaded: number) => void,
): Promise<void> => {
    const url = await getFileUploadUrl(uuid);
    const params = { directory, name: file.name };

    let offset = 0;
    try {
        const head = await axios.head(url, { params, signal });
        offset = Number(head.headers['x-upload-offset'] ?? 0) || 0;
        if (!Number.isFinite(offset) || offset < 0 || offset > file.size) {
            // A stored offset beyond the file size means a stale larger
            // partial sits at the path; rewriting from zero truncates it.
            offset = 0;
        }
    } catch {
        // Daemons without resume support just get a full PUT below.
        offset = 0;
    }
    onProgress(offset);

    let attempts = 0;
    while (offset < file.size) {
        try {
            const response = await axios.put(url, file.slice(offset), {
                params,
                signal,
                headers: { 'Content-Range': `bytes ${offset}-${file.size - 1}/${file.size}` },
                onUploadProgress: (event) => onProgress(offset + event.loaded),
            });
            const stored = Number(response.headers['x-upload-offset'] ?? 0);
            offset = stored > offset ? stored : file.size;
            attempts = 0;
        } catch (error) {
            if (signal.aborted || attempts++ >= 3) {
                throw error;
            }
            const head = await axios.head(url, { params, signal }).catch(() => null);
            offset = Number(head?.headers['x-upload-offset'] ?? offset) || offset;
        }
    }
    onProgress(file.size);
};

/**
 * Uploads a single file. Comet daemons get the websocket channel first and
 * the resumable PUT endpoint as fallback; Wings and Elytra keep the
 * original multipart POST.
 */
export default async function uploadFile(
    uuid: string,
    file: File,
    directory: string,
    signal: AbortSignal,
    onProgress: (loaded: number) => void,
): Promise<void> {
    if (daemonType() !== 'comet') {
        const url = await getFileUploadUrl(uuid);
        await axios.post(
            url,
            { files: file },
            {
                signal,
                headers: { 'Content-Type': 'multipart/form-data' },
                params: { directory },
                onUploadProgress: (event) => onProgress(event.loaded),
            },
        );
        return;
    }

    try {
        await uploadOverSocket(uuid, file, directory, signal, onProgress);
    } catch (error) {
        if (signal.aborted) {
            throw error;
        }
        await uploadOverHttp(uuid, file, directory, signal, onProgress);
    }
}
