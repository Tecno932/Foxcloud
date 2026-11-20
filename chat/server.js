'use strict';

const fs = require('node:fs');
const crypto = require('node:crypto');
const net = require('node:net');
const mysql = require('mysql2/promise');
const { WebSocketServer, WebSocket } = require('ws');

const CONFIG_FILE = '/etc/foxcraft/chat.env';

function loadConfig(path) {
    const values = {};

    for (const line of fs.readFileSync(path, 'utf8').split(/\r?\n/)) {
        const trimmed = line.trim();

        if (!trimmed || trimmed.startsWith('#')) continue;

        const separator = trimmed.indexOf('=');
        if (separator < 1) continue;

        values[trimmed.slice(0, separator).trim()] =
            trimmed.slice(separator + 1).trim();
    }

    const required = [
        'CHAT_DB_SOCKET',
        'CHAT_DB_NAME',
        'CHAT_DB_USER',
        'CHAT_DB_PASSWORD',
        'CHAT_TOKEN_SECRET',
    ];

    for (const key of required) {
        if (!values[key]) throw new Error(`Falta la configuración ${key}`);
    }

    return values;
}

const config = loadConfig(CONFIG_FILE);

const pool = mysql.createPool({
    socketPath: config.CHAT_DB_SOCKET,
    user: config.CHAT_DB_USER,
    password: config.CHAT_DB_PASSWORD,
    database: config.CHAT_DB_NAME,
    charset: 'utf8mb4',
    timezone: 'Z',
    waitForConnections: true,
    connectionLimit: 10,
    queueLimit: 20,
});

const HOST = '127.0.0.1';
const PORT = 8091;
const HISTORY_LIMIT = 50;
const MESSAGE_MAX_LENGTH = 500;

const RATE_LIMIT_COUNT = 5;
const RATE_LIMIT_WINDOW = 10_000;

const GUEST_DAILY_LIMIT = 20;
const GUEST_COOLDOWN_SECONDS = 60;
const SITE_TIMEZONE = 'America/Argentina/Buenos_Aires';

const secret = Buffer.from(config.CHAT_TOKEN_SECRET, 'utf8');

const dateFormatter = new Intl.DateTimeFormat('en-CA', {
    timeZone: SITE_TIMEZONE,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
});

function currentSiteDate() {
    const parts = dateFormatter.formatToParts(new Date());
    const values = Object.fromEntries(
        parts.map((part) => [part.type, part.value])
    );

    return `${values.year}-${values.month}-${values.day}`;
}

function databaseDate(value) {
    if (value instanceof Date) {
        return [
            value.getUTCFullYear(),
            String(value.getUTCMonth() + 1).padStart(2, '0'),
            String(value.getUTCDate()).padStart(2, '0'),
        ].join('-');
    }

    return String(value).slice(0, 10);
}

function send(socket, data) {
    if (socket.readyState === WebSocket.OPEN) {
        socket.send(JSON.stringify(data));
    }
}

function verifyToken(token) {
    if (typeof token !== 'string' || token.length > 2048) return null;

    const parts = token.split('.');
    if (parts.length !== 2) return null;

    const [payload, signature] = parts;

    if (
        !/^[A-Za-z0-9_-]+$/.test(payload) ||
        !/^[A-Za-z0-9_-]+$/.test(signature)
    ) {
        return null;
    }

    let receivedSignature;

    try {
        receivedSignature = Buffer.from(signature, 'base64url');
    } catch {
        return null;
    }

    const expectedSignature = crypto
        .createHmac('sha256', secret)
        .update(payload)
        .digest();

    if (
        receivedSignature.length !== expectedSignature.length ||
        !crypto.timingSafeEqual(receivedSignature, expectedSignature)
    ) {
        return null;
    }

    try {
        const data = JSON.parse(
            Buffer.from(payload, 'base64url').toString('utf8')
        );

        if (
            !data ||
            !/^[1-9]\d*$/.test(String(data.user_id)) ||
            !Number.isInteger(data.exp) ||
            data.exp <= Math.floor(Date.now() / 1000)
        ) {
            return null;
        }

        return { userId: String(data.user_id) };
    } catch {
        return null;
    }
}

async function authenticate(token) {
    const verified = verifyToken(token);
    if (!verified) return null;

    const [rows] = await pool.execute(
        `SELECT
            u.id,
            u.username,
            p.display_name,
            p.avatar_path
         FROM users u
         LEFT JOIN profiles p ON p.user_id = u.id
         WHERE u.id = ?
           AND u.status = 'active'
         LIMIT 1`,
        [verified.userId]
    );

    if (!rows.length) return null;

    return {
        id: String(rows[0].id),
        username: rows[0].username,
        displayName: rows[0].display_name || rows[0].username,
        avatarPath: rows[0].avatar_path || null,
        guest: false,
    };
}

function isLoopback(address) {
    return address === '127.0.0.1' ||
        address === '::1' ||
        address === '::ffff:127.0.0.1';
}

function getClientIp(request) {
    const remoteAddress = request.socket.remoteAddress;

    // Solo se confía en X-Real-IP cuando la conexión viene de Nginx local.
    if (!isLoopback(remoteAddress)) return null;

    const forwarded = request.headers['x-real-ip'];

    if (typeof forwarded !== 'string' || net.isIP(forwarded) === 0) {
        return null;
    }

    return forwarded.toLowerCase();
}

function getGuestId(request) {
    const ip = getClientIp(request);
    if (!ip) return null;

    return crypto
        .createHmac('sha256', secret)
        .update(`foxcraft-chat-guest:${ip}`)
        .digest('hex');
}

function validOrigin(request) {
    const origin = request.headers.origin;
    const host = request.headers.host;

    if (
        typeof origin !== 'string' ||
        typeof host !== 'string' ||
        origin.length > 512
    ) {
        return false;
    }

    try {
        const parsed = new URL(origin);

        return (
            (parsed.protocol === 'http:' || parsed.protocol === 'https:') &&
            parsed.host === host &&
            parsed.username === '' &&
            parsed.password === ''
        );
    } catch {
        return false;
    }
}

async function loadHistory() {
    const [rows] = await pool.execute(
        `SELECT
            m.id,
            m.message,
            m.created_at,
            m.user_id,
            m.guest_name,
            u.username,
            u.status,
            p.display_name,
            p.avatar_path
         FROM chat_messages m
         LEFT JOIN users u ON u.id = m.user_id
         LEFT JOIN profiles p ON p.user_id = u.id
         WHERE
            (m.user_id IS NULL)
            OR (u.status = 'active')
         ORDER BY m.id DESC
         LIMIT ${HISTORY_LIMIT}`
    );

    return rows.reverse().map((row) => {
        const guest = row.user_id === null ||
            row.username === null;

        return {
            id: String(row.id),
            username: guest ? 'Visitante' : row.username,
            displayName: guest
                ? (row.guest_name || 'Visitante')
                : (row.display_name || row.username),
            avatarPath: guest ? null : (row.avatar_path || null),
            message: row.message,
            createdAt: row.created_at,
        };
    });
}

function isRateLimited(socket) {
    const now = Date.now();

    socket.messageTimestamps = socket.messageTimestamps.filter(
        (timestamp) => now - timestamp < RATE_LIMIT_WINDOW
    );

    if (socket.messageTimestamps.length >= RATE_LIMIT_COUNT) {
        return true;
    }

    socket.messageTimestamps.push(now);
    return false;
}

async function getGuestStatus(guestId) {
    const today = currentSiteDate();

    const [rows] = await pool.execute(
        `SELECT
            message_date,
            message_count,
            CASE
                WHEN last_message_at IS NULL THEN NULL
                ELSE TIMESTAMPDIFF(
                    SECOND,
                    last_message_at,
                    UTC_TIMESTAMP()
                )
            END AS elapsed_seconds
         FROM chat_guest_limits
         WHERE guest_id = ?
         LIMIT 1`,
        [guestId]
    );

    if (!rows.length || databaseDate(rows[0].message_date) !== today) {
        return {
            remaining: GUEST_DAILY_LIMIT,
            waitSeconds: 0,
        };
    }

    const count = Number(rows[0].message_count);
    const elapsed = rows[0].elapsed_seconds === null
        ? null
        : Number(rows[0].elapsed_seconds);

    return {
        remaining: Math.max(0, GUEST_DAILY_LIMIT - count),
        waitSeconds: elapsed === null
            ? 0
            : Math.max(0, GUEST_COOLDOWN_SECONDS - elapsed),
    };
}

async function saveGuestMessage(guestId, message) {
    const connection = await pool.getConnection();
    const today = currentSiteDate();

    try {
        await connection.beginTransaction();

        await connection.execute(
            `INSERT INTO chat_guest_limits
                (guest_id, message_date, message_count)
             VALUES (?, ?, 0)
             ON DUPLICATE KEY UPDATE guest_id = VALUES(guest_id)`,
            [guestId, today]
        );

        const [rows] = await connection.execute(
            `SELECT
                message_date,
                message_count,
                CASE
                    WHEN last_message_at IS NULL THEN NULL
                    ELSE TIMESTAMPDIFF(
                        SECOND,
                        last_message_at,
                        UTC_TIMESTAMP()
                    )
                END AS elapsed_seconds
             FROM chat_guest_limits
             WHERE guest_id = ?
             FOR UPDATE`,
            [guestId]
        );

        let count = Number(rows[0].message_count);
        let waitSeconds = 0;

        if (databaseDate(rows[0].message_date) !== today) {
            count = 0;

            await connection.execute(
                `UPDATE chat_guest_limits
                 SET message_date = ?,
                     message_count = 0,
                     last_message_at = NULL
                 WHERE guest_id = ?`,
                [today, guestId]
            );
        } else if (rows[0].elapsed_seconds !== null) {
            waitSeconds = Math.max(
                0,
                GUEST_COOLDOWN_SECONDS -
                    Number(rows[0].elapsed_seconds)
            );
        }

        if (count >= GUEST_DAILY_LIMIT) {
            await connection.rollback();

            return {
                ok: false,
                remaining: 0,
                waitSeconds: 0,
                error: 'Alcanzaste el límite de 20 mensajes diarios para visitantes.',
            };
        }

        if (waitSeconds > 0) {
            await connection.rollback();

            return {
                ok: false,
                remaining: GUEST_DAILY_LIMIT - count,
                waitSeconds,
                error: `Esperá ${waitSeconds} segundos antes de enviar otro mensaje.`,
            };
        }

        const [result] = await connection.execute(
            `INSERT INTO chat_messages
                (user_id, guest_id, guest_name, message)
             VALUES (NULL, ?, 'Visitante', ?)`,
            [guestId, message]
        );

        await connection.execute(
            `UPDATE chat_guest_limits
             SET message_date = ?,
                 message_count = message_count + 1,
                 last_message_at = UTC_TIMESTAMP()
             WHERE guest_id = ?`,
            [today, guestId]
        );

        const [messageRows] = await connection.execute(
            `SELECT created_at
             FROM chat_messages
             WHERE id = ?
             LIMIT 1`,
            [result.insertId]
        );

        await connection.commit();

        return {
            ok: true,
            id: String(result.insertId),
            createdAt: messageRows[0].created_at,
            remaining: GUEST_DAILY_LIMIT - count - 1,
            waitSeconds: GUEST_COOLDOWN_SECONDS,
        };
    } catch (error) {
        await connection.rollback();
        throw error;
    } finally {
        connection.release();
    }
}

const wss = new WebSocketServer({
    host: HOST,
    port: PORT,
    path: '/',
    maxPayload: 2048,
    perMessageDeflate: false,
});

wss.on('connection', (socket, request) => {
    if (!validOrigin(request)) {
        socket.close(1008, 'Origen no permitido');
        return;
    }

    socket.authenticated = false;
    socket.authenticating = false;
    socket.user = null;
    socket.guestId = null;
    socket.messageTimestamps = [];
    socket.processing = Promise.resolve();

    socket.on('message', (raw) => {
        socket.processing = socket.processing
            .then(async () => {
                let data;

                try {
                    data = JSON.parse(raw.toString('utf8'));
                } catch {
                    send(socket, {
                        type: 'error',
                        message: 'Formato de mensaje inválido.',
                    });
                    return;
                }

                if (
                    !data ||
                    typeof data !== 'object' ||
                    Array.isArray(data)
                ) {
                    send(socket, {
                        type: 'error',
                        message: 'Formato de mensaje inválido.',
                    });
                    return;
                }

                if (!socket.authenticated) {
                    if (socket.authenticating || data.type !== 'auth') {
                        socket.close(1008, 'Autenticación requerida');
                        return;
                    }

                    socket.authenticating = true;

                    try {
                        let user = null;

                        if (typeof data.token === 'string') {
                            user = await authenticate(data.token);

                            if (!user) {
                                socket.close(1008, 'Token inválido o expirado');
                                return;
                            }

                            socket.user = user;
                        } else {
                            const guestId = getGuestId(request);

                            if (!guestId) {
                                socket.close(1008, 'No se pudo identificar al visitante');
                                return;
                            }

                            socket.guestId = guestId;
                            socket.user = {
                                id: null,
                                username: 'Visitante',
                                displayName: 'Visitante',
                                avatarPath: null,
                                guest: true,
                            };
                        }

                        if (socket.readyState !== WebSocket.OPEN) return;

                        socket.authenticated = true;

                        const history = await loadHistory();

                        if (socket.readyState !== WebSocket.OPEN) return;

                        const guestStatus = socket.guestId
                            ? await getGuestStatus(socket.guestId)
                            : null;

                        send(socket, {
                            type: 'auth_ok',
                            user: socket.user,
                            guest: Boolean(socket.guestId),
                            ...(guestStatus || {}),
                        });

                        send(socket, {
                            type: 'history',
                            messages: history,
                        });
                    } catch (error) {
                        console.error('Error de autenticación:', error.message);
                        socket.close(1011, 'Error interno');
                    }

                    return;
                }

                if (data.type !== 'message') return;

                if (typeof data.message !== 'string') return;

                const message = data.message.trim();

                if (
                    !message ||
                    Array.from(message).length > MESSAGE_MAX_LENGTH
                ) {
                    send(socket, {
                        type: 'error',
                        message: 'El mensaje debe tener entre 1 y 500 caracteres.',
                    });
                    return;
                }

                try {
                    let outgoing;

                    if (socket.guestId) {
                        const result = await saveGuestMessage(
                            socket.guestId,
                            message
                        );

                        if (!result.ok) {
                            send(socket, {
                                type: 'error',
                                message: result.error,
                                remaining: result.remaining,
                                waitSeconds: result.waitSeconds,
                            });
                            return;
                        }

                        outgoing = {
                            id: result.id,
                            username: 'Visitante',
                            displayName: 'Visitante',
                            avatarPath: null,
                            message,
                            createdAt: result.createdAt,
                        };

                        send(socket, {
                            type: 'guest_status',
                            remaining: result.remaining,
                            waitSeconds: result.waitSeconds,
                        });
                    } else {
                        if (isRateLimited(socket)) {
                            send(socket, {
                                type: 'error',
                                message: 'Estás enviando mensajes demasiado rápido.',
                            });
                            return;
                        }

                        const [result] = await pool.execute(
                            `INSERT INTO chat_messages (user_id, message)
                             VALUES (?, ?)`,
                            [socket.user.id, message]
                        );

                        const [rows] = await pool.execute(
                            `SELECT created_at
                             FROM chat_messages
                             WHERE id = ?
                             LIMIT 1`,
                            [result.insertId]
                        );

                        outgoing = {
                            id: String(result.insertId),
                            username: socket.user.username,
                            displayName: socket.user.displayName,
                            avatarPath: socket.user.avatarPath,
                            message,
                            createdAt: rows[0].created_at,
                        };
                    }

                    const payload = JSON.stringify({
                        type: 'message',
                        message: outgoing,
                    });

                    for (const client of wss.clients) {
                        if (
                            client.authenticated &&
                            client.readyState === WebSocket.OPEN
                        ) {
                            client.send(payload);
                        }
                    }
                } catch (error) {
                    console.error('Error guardando mensaje:', error.message);

                    send(socket, {
                        type: 'error',
                        message: 'No se pudo guardar el mensaje.',
                    });
                }
            })
            .catch((error) => {
                console.error(
                    'Error procesando mensaje WebSocket:',
                    error.message
                );

                if (socket.readyState === WebSocket.OPEN) {
                    socket.close(1011, 'Error interno');
                }
            });
    });

    socket.on('error', (error) => {
        console.error('Error WebSocket:', error.message);
    });
});

wss.on('listening', () => {
    console.log(`Servidor de chat escuchando en ${HOST}:${PORT}`);
});

wss.on('error', (error) => {
    console.error('Error del servidor WebSocket:', error.message);
});

async function shutdown() {
    console.log('Cerrando servidor de chat...');

    wss.close();

    for (const socket of wss.clients) {
        socket.close(1001, 'Servidor apagándose');
    }

    await pool.end();
    process.exit(0);
}

process.on('SIGTERM', shutdown);
process.on('SIGINT', shutdown);
