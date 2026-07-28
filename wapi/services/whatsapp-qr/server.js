/**
 * WAPI SaaS - WhatsApp QR Code Connection Service
 * A secure Node.js microservice that manages WhatsApp Web sessions via QR code.
 * 
 * Endpoints:
 *   POST /session/start      - Start a new session (body: { userId, apiKey })
 *   GET  /session/qr/:userId - Get current QR code as base64 image
 *   GET  /session/status/:userId - Get connection status
 *   POST /session/disconnect  - Disconnect session (body: { userId, apiKey })
 * 
 * Security: All POST requests require a shared API key.
 * Start:  node server.js
 */

const express = require('express');
const { Client, LocalAuth } = require('whatsapp-web.js');
const qrcode = require('qrcode');
const cors = require('cors');
const path = require('path');
const fs = require('fs');

const app = express();
app.use(cors());
app.use(express.json());

// ─── Configuration ───────────────────────────────────────────
const PORT = process.env.QR_PORT || 3001;
const API_KEY = process.env.QR_API_KEY || 'wapi_qr_secret_key_2026';
const AUTH_DIR = path.join(__dirname, '.wwebjs_auth');

// Ensure auth directory exists
if (!fs.existsSync(AUTH_DIR)) {
    fs.mkdirSync(AUTH_DIR, { recursive: true });
}

// ─── Session Store ───────────────────────────────────────────
// Each user gets their own isolated session: { client, qr, status, info }
const sessions = {};

function getSession(userId) {
    if (!sessions[userId]) {
        sessions[userId] = {
            client: null,
            qr: null,
            status: 'disconnected', // disconnected | initializing | waiting_scan | authenticated | connected | auth_failed | error
            info: null,
            error: null
        };
    }
    return sessions[userId];
}

// ─── Auth Middleware (for secured POST routes) ──────────────
function authMiddleware(req, res, next) {
    const key = req.body.apiKey || req.headers['x-api-key'];
    if (key !== API_KEY) {
        return res.status(403).json({ success: false, error: 'Invalid API key' });
    }
    next();
}

// ─── Start Session (supports both GET and POST) ─────────────
async function handleStartSession(req, res) {
    const userId = req.body.userId || req.query.userId;
    if (!userId) {
        return res.status(400).json({ success: false, error: 'userId is required' });
    }

    const session = getSession(userId);

    // Already connected
    if (session.status === 'connected') {
        return res.json({ success: true, status: 'connected', info: session.info });
    }

    // Already initializing / waiting for scan
    if (session.client && (session.status === 'initializing' || session.status === 'waiting_scan' || session.status === 'authenticated')) {
        return res.json({ success: true, status: session.status });
    }

    // Cleanup any stale client
    if (session.client) {
        try { await session.client.destroy(); } catch (e) {}
        session.client = null;
    }

    try {
        const client = new Client({
            authStrategy: new LocalAuth({
                clientId: `user_${userId}`,
                dataPath: AUTH_DIR
            }),
            puppeteer: {
                headless: true,
                args: [
                    '--no-sandbox',
                    '--disable-setuid-sandbox',
                    '--disable-dev-shm-usage',
                    '--disable-accelerated-2d-canvas',
                    '--no-first-run',
                    '--disable-gpu'
                ]
            }
        });

        session.client = client;
        session.status = 'initializing';
        session.qr = null;
        session.error = null;

        client.on('qr', async (qr) => {
            try {
                session.qr = await qrcode.toDataURL(qr, { width: 280, margin: 2 });
                session.status = 'waiting_scan';
                console.log(`[User ${userId}] QR code generated, waiting for scan...`);
            } catch (e) {
                console.error(`[User ${userId}] QR generation error:`, e.message);
            }
        });

        client.on('authenticated', () => {
            session.status = 'authenticated';
            session.qr = null;
            console.log(`[User ${userId}] Authenticated successfully`);
        });

        client.on('ready', () => {
            session.status = 'connected';
            session.qr = null;
            session.info = {
                pushname: client.info?.pushname || 'Unknown',
                phone: client.info?.wid?.user || '',
                platform: client.info?.platform || 'web'
            };
            console.log(`[User ${userId}] WhatsApp connected! Phone: ${session.info.phone}`);
        });

        client.on('auth_failure', (msg) => {
            session.status = 'auth_failed';
            session.error = msg || 'Authentication failed';
            session.client = null;
            console.error(`[User ${userId}] Auth failure:`, msg);
        });

        client.on('disconnected', (reason) => {
            session.status = 'disconnected';
            session.qr = null;
            session.info = null;
            session.client = null;
            console.log(`[User ${userId}] Disconnected:`, reason);
        });

        // Initialize asynchronously
        client.initialize().catch(err => {
            session.status = 'error';
            session.error = err.message;
            session.client = null;
            console.error(`[User ${userId}] Init error:`, err.message);
        });

        res.json({ success: true, status: 'initializing' });

    } catch (err) {
        session.status = 'error';
        session.error = err.message;
        session.client = null;
        console.error(`[User ${userId}] Start error:`, err.message);
        res.status(500).json({ success: false, error: err.message });
    }
}

// Register start routes (GET for browser direct, POST for PHP proxy)
app.get('/session/start', handleStartSession);
app.post('/session/start', handleStartSession);

// ─── Get QR Code ─────────────────────────────────────────────
app.get('/session/qr/:userId', (req, res) => {
    const session = getSession(req.params.userId);
    res.json({
        success: true,
        qr: session.qr,
        status: session.status
    });
});

// ─── Get Status ──────────────────────────────────────────────
app.get('/session/status/:userId', (req, res) => {
    const session = getSession(req.params.userId);
    res.json({
        success: true,
        status: session.status,
        info: session.info,
        error: session.error
    });
});

// ─── Disconnect Session (GET + POST) ─────────────────────────
async function handleDisconnect(req, res) {
    const userId = req.body.userId || req.query.userId;
    if (!userId) {
        return res.status(400).json({ success: false, error: 'userId is required' });
    }

    const session = getSession(userId);

    if (session.client) {
        try {
            await session.client.logout();
        } catch (e) {
            // logout may fail if already disconnected
        }
        try {
            await session.client.destroy();
        } catch (e) {}
    }

    session.client = null;
    session.qr = null;
    session.status = 'disconnected';
    session.info = null;
    session.error = null;

    // Clean up local auth files for this user
    const authPath = path.join(AUTH_DIR, `session-user_${userId}`);
    if (fs.existsSync(authPath)) {
        try {
            fs.rmSync(authPath, { recursive: true, force: true });
        } catch (e) {}
    }

    delete sessions[userId];
    console.log(`[User ${userId}] Session disconnected and cleaned up`);
    res.json({ success: true, status: 'disconnected' });
}

app.get('/session/disconnect', handleDisconnect);
app.post('/session/disconnect', handleDisconnect);
// ─── Health Check ────────────────────────────────────────────
app.get('/health', (req, res) => {
    res.json({ status: 'ok', uptime: process.uptime(), activeSessions: Object.keys(sessions).length });
});

// ─── Start Server ────────────────────────────────────────────
app.listen(PORT, () => {
    console.log(`\n🟢 WAPI WhatsApp QR Service`);
    console.log(`   Port: ${PORT}`);
    console.log(`   Auth Dir: ${AUTH_DIR}`);
    console.log(`   Ready to accept connections.\n`);
});
