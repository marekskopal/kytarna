import * as alphaTab from '@coderline/alphatab';

// Keep stdout limited to our own request log; alphaTab otherwise logs import
// failures (expected on /convert with bad input) to the console.
alphaTab.Logger.logLevel = alphaTab.LogLevel.None;

const { AlphaTexImporter, ScoreLoader } = alphaTab.importer;
const { AlphaTexExporter } = alphaTab.exporter;
const SEVERITY_ERROR = alphaTab.importer.alphaTex.AlphaTexDiagnosticsSeverity.Error;

export const alphaTabVersion = alphaTab.meta.version;

function midiToNoteName(midi) {
    // e.g. 64 -> "E4", 40 -> "E2"
    return alphaTab.model.Tuning.getTextForTuning(midi, true);
}

export function extractMetadata(score) {
    return {
        title: score.title || null,
        artist: score.artist || null,
        album: score.album || null,
        tempo: score.tempo,
        trackCount: score.tracks.length,
        tracks: score.tracks.map((track) => {
            // Tuning lives on the staff; use the first staff of each track.
            // Non-stringed tracks (piano, drums) have no tuning -> [].
            const tunings = track.staves[0]?.stringTuning?.tunings ?? [];
            return {
                name: track.name,
                stringCount: tunings.length,
                tuning: Array.from(tunings, midiToNoteName),
            };
        }),
    };
}

function collectAlphaTexErrors(error) {
    // AlphaTexImporter throws UnsupportedFormatError whose `cause` is an
    // AlphaTexErrorWithDiagnostics carrying lexer/parser/semantic diagnostic
    // bags (iterables of { code, message, severity, start: { line, col, offset } }).
    const inner = error?.cause ?? error;
    const errors = [];
    for (const bag of [inner?.lexerDiagnostics, inner?.parserDiagnostics, inner?.semanticDiagnostics]) {
        if (!bag) {
            continue;
        }
        try {
            for (const diagnostic of bag) {
                if (diagnostic.severity !== SEVERITY_ERROR) {
                    continue;
                }
                errors.push({
                    message: diagnostic.message,
                    line: diagnostic.start?.line ?? null,
                    col: diagnostic.start?.col ?? null,
                    offset: diagnostic.start?.offset ?? null,
                });
            }
        } catch {
            // bag not iterable in this alphaTab version -> fall through
        }
    }
    if (errors.length === 0) {
        errors.push({ message: error?.message ?? 'Unknown alphaTex parse error', line: null, col: null, offset: null });
    }
    return errors;
}

export function validateAlphaTex(alphaTex) {
    const settings = new alphaTab.Settings();
    try {
        const importer = new AlphaTexImporter();
        importer.initFromString(alphaTex, settings);
        const score = importer.readScore();
        return { valid: true, metadata: extractMetadata(score) };
    } catch (error) {
        return { valid: false, errors: collectAlphaTexErrors(error) };
    }
}

export function convertGuitarPro(bytes) {
    const settings = new alphaTab.Settings();
    // Detects and parses gp3/gp4/gp5/gpx/gp (and other formats alphaTab knows).
    // Throws (UnsupportedFormatError or a low-level reader error) on bad input.
    const score = ScoreLoader.loadScoreFromBytes(new Uint8Array(bytes), settings);
    const alphaTex = new AlphaTexExporter().exportToString(score, settings);
    return { alphaTex, metadata: extractMetadata(score) };
}
