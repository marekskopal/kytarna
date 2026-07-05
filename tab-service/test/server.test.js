import assert from 'node:assert/strict';
import { after, before, describe, it } from 'node:test';
import * as alphaTab from '@coderline/alphatab';
import { createApp } from '../src/app.js';

// A real riff: two bars of an E-minor pentatonic run, standard tuning, 96 bpm.
const VALID_ALPHATEX = `
\\title "Kytario Test Riff"
\\artist "Kytario"
\\album "Fixtures"
\\tempo 96
.
\\track "Lead Guitar"
\\tuning E4 B3 G3 D3 A2 E2
0.6.8 3.6.8 5.6.8 0.5.8 2.5.8 0.5.8 5.6.8 3.6.8 |
0.6.4 (0.6 2.5 2.4).2 r.4
`;

const BROKEN_ALPHATEX = '\\title "Unterminated . 0.6.zz | ((';

/** Builds a genuine Guitar Pro 7/8 (.gp) file in-memory from alphaTex. */
function buildGpFixture() {
    const settings = new alphaTab.Settings();
    const importer = new alphaTab.importer.AlphaTexImporter();
    importer.initFromString(VALID_ALPHATEX, settings);
    const score = importer.readScore();
    return new alphaTab.exporter.Gp7Exporter().export(score, settings);
}

describe('tab-service', () => {
    let server;
    let baseUrl;

    before(async () => {
        server = createApp({ maxBodyBytes: 65536, log: () => {} });
        await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
        baseUrl = `http://127.0.0.1:${server.address().port}`;
    });

    after(() => new Promise((resolve) => server.close(resolve)));

    it('GET /health reports ok and the alphaTab version', async () => {
        const res = await fetch(`${baseUrl}/health`);
        assert.equal(res.status, 200);
        assert.equal(res.headers.get('content-type'), 'application/json; charset=utf-8');
        const body = await res.json();
        assert.equal(body.status, 'ok');
        assert.match(body.alphaTabVersion, /^\d+\.\d+\.\d+/);
    });

    it('POST /validate accepts a correct alphaTex riff and returns metadata', async () => {
        const res = await fetch(`${baseUrl}/validate`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ alphaTex: VALID_ALPHATEX }),
        });
        assert.equal(res.status, 200);
        const body = await res.json();
        assert.equal(body.valid, true);
        assert.deepEqual(body.metadata, {
            title: 'Kytario Test Riff',
            artist: 'Kytario',
            album: 'Fixtures',
            tempo: 96,
            trackCount: 1,
            tracks: [
                {
                    name: 'Lead Guitar',
                    stringCount: 6,
                    tuning: ['E4', 'B3', 'G3', 'D3', 'A2', 'E2'],
                },
            ],
        });
    });

    it('POST /validate reports broken alphaTex as invalid with error details', async () => {
        const res = await fetch(`${baseUrl}/validate`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ alphaTex: BROKEN_ALPHATEX }),
        });
        assert.equal(res.status, 200);
        const body = await res.json();
        assert.equal(body.valid, false);
        assert.ok(Array.isArray(body.errors) && body.errors.length >= 1);
        for (const error of body.errors) {
            assert.equal(typeof error.message, 'string');
            assert.ok(error.message.length > 0);
        }
        // alphaTab exposes diagnostic positions; at least one error carries them.
        assert.ok(body.errors.some((e) => Number.isInteger(e.line) && Number.isInteger(e.col)));
    });

    it('POST /validate rejects malformed request bodies with 400', async () => {
        const malformed = await fetch(`${baseUrl}/validate`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: 'this is not json',
        });
        assert.equal(malformed.status, 400);
        assert.ok((await malformed.json()).errors[0].message.length > 0);

        const missingField = await fetch(`${baseUrl}/validate`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ tab: 'wrong key' }),
        });
        assert.equal(missingField.status, 400);
    });

    it('POST /convert converts a genuine Guitar Pro file to alphaTex (round-trip)', async () => {
        const gpBytes = buildGpFixture();
        const res = await fetch(`${baseUrl}/convert`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/octet-stream' },
            body: gpBytes,
        });
        assert.equal(res.status, 200);
        const body = await res.json();
        assert.equal(typeof body.alphaTex, 'string');
        assert.match(body.alphaTex, /\\title "Kytario Test Riff"/);
        assert.match(body.alphaTex, /\\tuning \(E4 B3 G3 D3 A2 E2\)/);
        assert.equal(body.metadata.title, 'Kytario Test Riff');
        assert.equal(body.metadata.artist, 'Kytario');
        assert.equal(body.metadata.tempo, 96);
        assert.equal(body.metadata.trackCount, 1);
        assert.deepEqual(body.metadata.tracks[0].tuning, ['E4', 'B3', 'G3', 'D3', 'A2', 'E2']);

        // The exported alphaTex must itself validate (full round-trip).
        const revalidate = await fetch(`${baseUrl}/validate`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ alphaTex: body.alphaTex }),
        });
        const revalidated = await revalidate.json();
        assert.equal(revalidated.valid, true);
        assert.equal(revalidated.metadata.title, 'Kytario Test Riff');
    });

    it('POST /convert rejects garbage bytes with 422', async () => {
        const res = await fetch(`${baseUrl}/convert`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/octet-stream' },
            body: Buffer.from('definitely not a guitar pro file'),
        });
        assert.equal(res.status, 422);
        const body = await res.json();
        assert.ok(Array.isArray(body.errors) && body.errors.length >= 1);
        assert.equal(typeof body.errors[0].message, 'string');
    });

    it('rejects oversized bodies with 413', async () => {
        const res = await fetch(`${baseUrl}/convert`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/octet-stream' },
            body: Buffer.alloc(65537),
        });
        assert.equal(res.status, 413);
        const body = await res.json();
        assert.match(body.errors[0].message, /exceeds limit/);
    });

    it('returns 404 JSON for unknown routes', async () => {
        const res = await fetch(`${baseUrl}/nope`);
        assert.equal(res.status, 404);
        assert.deepEqual(await res.json(), { errors: [{ message: 'Not found' }] });
    });
});
