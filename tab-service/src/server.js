import { createApp, DEFAULT_MAX_BODY_BYTES, DEFAULT_PORT } from './app.js';
import { alphaTabVersion } from './tab.js';

const port = Number(process.env.TAB_SERVICE_PORT ?? DEFAULT_PORT);
const maxBodyBytes = Number(process.env.TAB_SERVICE_MAX_BODY_BYTES ?? DEFAULT_MAX_BODY_BYTES);

if (!Number.isInteger(port) || port <= 0 || port > 65535) {
    console.error(`Invalid TAB_SERVICE_PORT: ${process.env.TAB_SERVICE_PORT}`);
    process.exit(1);
}
if (!Number.isInteger(maxBodyBytes) || maxBodyBytes <= 0) {
    console.error(`Invalid TAB_SERVICE_MAX_BODY_BYTES: ${process.env.TAB_SERVICE_MAX_BODY_BYTES}`);
    process.exit(1);
}

const server = createApp({ maxBodyBytes });
server.listen(port, () => {
    console.log(`${new Date().toISOString()} tab-service listening on :${port} (alphaTab ${alphaTabVersion}, max body ${maxBodyBytes} bytes)`);
});

for (const signal of ['SIGTERM', 'SIGINT']) {
    process.on(signal, () => {
        server.close(() => process.exit(0));
        // Force-exit if connections do not drain in time.
        setTimeout(() => process.exit(0), 5000).unref();
    });
}
