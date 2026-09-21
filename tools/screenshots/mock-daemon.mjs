// Mock Comet daemon for screenshot captures. Speaks just enough of the
// daemon wire protocol to make the panel UI look alive: a resource stats
// endpoint, plus a websocket that accepts any JWT and streams console
// output, stats, and power state.
//
// Usage: node mock-daemon.mjs [port]

import http from 'node:http';
import { WebSocketServer } from 'ws';

const PORT = Number(process.argv[2] ?? process.env.MOCK_DAEMON_PORT ?? 8898);
const STARTED = Date.now();

const send = (ws, event, ...args) => {
    if (ws.readyState === ws.OPEN) {
        ws.send(JSON.stringify({ event, args }));
    }
};

// Slightly different resources per server so screenshots do not all show
// identical numbers. Derived from the UUID string for stability.
// Flat payload matching the daemon stats websocket event.
const statsFor = (uuid, tick) => {
    const seed = [...uuid].reduce((a, c) => a + c.charCodeAt(0), 0);
    const elapsed = (Date.now() - STARTED) / 1000;
    const wave = Math.sin((tick + seed) / 4);

    return {
        memory_bytes: Math.round(2_400_000_000 + wave * 800_000_000 + seed * 1000),
        memory_limit_bytes: 8_589_934_592,
        cpu_absolute: Math.round((18 + wave * 9 + (seed % 7)) * 10) / 10,
        disk_bytes: 14_200_000_000 + seed * 100_000,
        network: {
            rx_bytes: Math.round(4_800_000_000 + elapsed * 140_000),
            tx_bytes: Math.round(1_200_000_000 + elapsed * 35_000),
        },
        state: 'running',
        uptime: Math.round(86_400_000 + elapsed * 1000),
    };
};

// REST shape for the panel-side resource polling endpoint. The panel
// decodes the body verbatim and reads state plus utilization keys.
const restStatsFor = (uuid, tick) => {
    const flat = statsFor(uuid, tick);

    return {
        state: flat.state,
        is_suspended: false,
        utilization: {
            memory_bytes: flat.memory_bytes,
            memory_limit_bytes: flat.memory_limit_bytes,
            cpu_absolute: flat.cpu_absolute,
            disk_bytes: flat.disk_bytes,
            network: flat.network,
            uptime: flat.uptime,
        },
    };
};

const logLines = (uuid) => {
    const seed = [...uuid].reduce((a, c) => a + c.charCodeAt(0), 0);
    const players = ['Steve', 'Alex', 'Herobrine', 'Notch', 'Creeper42', 'xX_Builder_Xx'];

    return [
        '[ServerMain/INFO]: Starting minecraft server version 1.21.4',
        '[ServerMain/INFO]: Loading properties',
        '[ServerMain/INFO]: Default game type: SURVIVAL',
        '[ServerMain/INFO]: Preparing level "world"',
        '[Server thread/INFO]: Preparing spawn area: 100%',
        '[Server thread/INFO]: Done (4.231s)! For help, type "help"',
        `[Server thread/INFO]: ${players[seed % players.length]} joined the game`,
        '[Server thread/INFO]: There are 14 of a max of 100 players online',
        '[Server thread/INFO]: Autosave completed',
    ];
};

const server = http.createServer((req, res) => {
    const url = new URL(req.url, `http://${req.headers.host}`);

    const match = url.pathname.match(/^\/api\/servers\/([0-9a-f-]+)$/i);
    if (match && req.method === 'GET') {
        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify(restStatsFor(match[1], Date.now() / 1500)));
        return;
    }

    // Accept anything else with an empty object so stray panel calls do not
    // hang the capture.
    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end('{}');
});

const wss = new WebSocketServer({ server, path: undefined });

wss.on('connection', (ws, req) => {
    const match = req.url.match(/^\/api\/servers\/([0-9a-f-]+)\/ws/i);
    if (!match) {
        ws.close();
        return;
    }

    const uuid = match[1];
    let authed = false;
    let tick = 0;
    let statsTimer = null;
    let logTimer = null;
    const lines = logLines(uuid);

    const pushStats = () => send(ws, 'stats', JSON.stringify(statsFor(uuid, tick++)));
    const pushLog = () => send(ws, 'console output', lines[tick % lines.length]);

    const beginStream = () => {
        statsTimer = setInterval(pushStats, 1500);
        logTimer = setInterval(pushLog, 2200);
        pushStats();
        send(ws, 'status', 'running');
        lines.slice(0, 6).forEach((line) => send(ws, 'console output', line));
    };

    ws.on('message', (raw) => {
        let msg;
        try {
            msg = JSON.parse(raw.toString());
        } catch {
            return;
        }

        switch (msg.event) {
            case 'auth':
                authed = true;
                send(ws, 'auth success');
                beginStream();
                break;
            case 'send stats':
                pushStats();
                break;
            case 'send logs':
                lines.forEach((line) => send(ws, 'console output', line));
                break;
            case 'send command':
                send(ws, 'console output', `[Server thread/INFO]: ${msg.args?.[0] ?? ''}`);
                break;
            case 'set state':
                send(ws, 'status', msg.args?.[0] === 'stop' ? 'stopping' : 'running');
                break;
            default:
                break;
        }
    });

    ws.on('close', () => {
        clearInterval(statsTimer);
        clearInterval(logTimer);
    });
});

server.listen(PORT, '127.0.0.1', () => {
    console.log(`mock daemon listening on http://127.0.0.1:${PORT}`);
});
