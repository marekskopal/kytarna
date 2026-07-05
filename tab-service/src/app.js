import { createServer } from 'node:http';
import { alphaTabVersion, convertGuitarPro, validateAlphaTex } from './tab.js';

export const DEFAULT_PORT = 8080;
export const DEFAULT_MAX_BODY_BYTES = 10485760;

class HttpError extends Error {
    constructor(status, body) {
        super(body?.errors?.[0]?.message ?? `HTTP ${status}`);
        this.status = status;
        this.body = body;
    }
}

function readBody(req, maxBodyBytes) {
    return new Promise((resolve, reject) => {
        const declared = Number(req.headers['content-length']);
        if (Number.isFinite(declared) && declared > maxBodyBytes) {
            reject(new HttpError(413, { errors: [{ message: `Request body exceeds limit of ${maxBodyBytes} bytes` }] }));
            return;
        }
        const chunks = [];
        let size = 0;
        let settled = false;
        req.on('data', (chunk) => {
            if (settled) {
                return;
            }
            size += chunk.length;
            if (size > maxBodyBytes) {
                settled = true;
                reject(new HttpError(413, { errors: [{ message: `Request body exceeds limit of ${maxBodyBytes} bytes` }] }));
                return;
            }
            chunks.push(chunk);
        });
        req.on('end', () => {
            if (!settled) {
                settled = true;
                resolve(Buffer.concat(chunks));
            }
        });
        req.on('error', (error) => {
            if (!settled) {
                settled = true;
                reject(error);
            }
        });
    });
}

async function handleValidate(req, maxBodyBytes) {
    const body = await readBody(req, maxBodyBytes);
    let payload;
    try {
        payload = JSON.parse(body.toString('utf8'));
    } catch {
        throw new HttpError(400, { errors: [{ message: 'Request body must be valid JSON' }] });
    }
    if (typeof payload?.alphaTex !== 'string' || payload.alphaTex.length === 0) {
        throw new HttpError(400, { errors: [{ message: 'Field "alphaTex" must be a non-empty string' }] });
    }
    return { status: 200, body: validateAlphaTex(payload.alphaTex) };
}

async function handleConvert(req, maxBodyBytes) {
    const body = await readBody(req, maxBodyBytes);
    if (body.length === 0) {
        throw new HttpError(400, { errors: [{ message: 'Request body must contain Guitar Pro file bytes' }] });
    }
    try {
        return { status: 200, body: convertGuitarPro(body) };
    } catch (error) {
        throw new HttpError(422, { errors: [{ message: `Unable to parse file as Guitar Pro: ${error?.message ?? 'unknown error'}` }] });
    }
}

async function route(req, maxBodyBytes) {
    const path = new URL(req.url, 'http://localhost').pathname;
    if (path === '/health') {
        if (req.method !== 'GET') {
            throw new HttpError(405, { errors: [{ message: 'Method not allowed, use GET' }] });
        }
        return { status: 200, body: { status: 'ok', alphaTabVersion } };
    }
    if (path === '/validate') {
        if (req.method !== 'POST') {
            throw new HttpError(405, { errors: [{ message: 'Method not allowed, use POST' }] });
        }
        return handleValidate(req, maxBodyBytes);
    }
    if (path === '/convert') {
        if (req.method !== 'POST') {
            throw new HttpError(405, { errors: [{ message: 'Method not allowed, use POST' }] });
        }
        return handleConvert(req, maxBodyBytes);
    }
    throw new HttpError(404, { errors: [{ message: 'Not found' }] });
}

export function createApp({ maxBodyBytes = DEFAULT_MAX_BODY_BYTES, log = console.log } = {}) {
    return createServer(async (req, res) => {
        const startedAt = process.hrtime.bigint();
        let status;
        try {
            const result = await route(req, maxBodyBytes);
            status = result.status;
            sendJson(res, result.status, result.body);
        } catch (error) {
            if (error instanceof HttpError) {
                status = error.status;
                sendJson(res, error.status, error.body);
            } else {
                status = 500;
                sendJson(res, 500, { errors: [{ message: 'Internal server error' }] });
                console.error(error);
            }
        }
        const durationMs = Number(process.hrtime.bigint() - startedAt) / 1e6;
        log(`${new Date().toISOString()} ${req.method} ${req.url} ${status} ${durationMs.toFixed(1)}ms`);
    });
}

function sendJson(res, status, body) {
    if (res.headersSent) {
        return;
    }
    const payload = JSON.stringify(body);
    res.writeHead(status, {
        'Content-Type': 'application/json; charset=utf-8',
        'Content-Length': Buffer.byteLength(payload),
    });
    res.end(payload);
}
