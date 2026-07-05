import * as fs from 'fs';
import * as path from 'path';

interface Credentials {
    email: string;
    password: string;
    name: string;
}

const CREDENTIALS_PATH = path.resolve(__dirname, '../.auth/credentials.json');

export function readFixtureCredentials(): Credentials {
    if (!fs.existsSync(CREDENTIALS_PATH)) {
        throw new Error(`Fixture credentials not found at ${CREDENTIALS_PATH} — the setup project must run first.`);
    }
    return JSON.parse(fs.readFileSync(CREDENTIALS_PATH, 'utf-8')) as Credentials;
}
