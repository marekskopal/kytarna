import {UKOLIO_DTS} from '@app/scripts/ukolio-api';
import type * as Monaco from 'monaco-editor';

// Monaco is loaded as its prebuilt AMD bundle from /assets/monaco (copied by the
// angular.json asset glob) rather than bundled through esbuild — esbuild can't
// handle Monaco's web-worker entries, codicon .ttf and injected CSS. This keeps
// Monaco fully out of the app bundle and loads it on demand, once.
//
// CSP: the worker is started from a real same-origin script (worker-proxy.js),
// NOT a blob/data URL, so the relaxed CSP nginx serves for /assets/monaco/*
// (which permits the eval the TypeScript worker needs) governs the worker —
// the main document keeps its strict, eval-free policy.

let monacoPromise: Promise<typeof Monaco> | null = null;
let configured = false;

interface AmdRequire {
    config(options: {paths: Record<string, string>}): void;
    (dependencies: string[], onLoad: () => void): void;
}

interface MonacoWindow extends Window {
    monaco?: typeof Monaco;
    require?: AmdRequire;
    MonacoEnvironment?: {getWorkerUrl(moduleId: string, label: string): string};
}

export function loadMonaco(): Promise<typeof Monaco> {
    if (monacoPromise !== null) {
        return monacoPromise;
    }

    monacoPromise = new Promise<typeof Monaco>((resolve, reject) => {
        const win = window as MonacoWindow;
        if (win.monaco !== undefined) {
            configure(win.monaco);
            resolve(win.monaco);
            return;
        }

        // Resolve against the document base href (the app is served under /app/),
        // not the origin, so the loader, workers and AMD paths all line up.
        const baseUrl = new URL('assets/monaco', document.baseURI).href.replace(/\/$/, '');
        win.MonacoEnvironment = {
            getWorkerUrl(): string {
                return `${baseUrl}/worker-proxy.js`;
            },
        };

        const script = document.createElement('script');
        script.src = `${baseUrl}/vs/loader.js`;
        script.onload = (): void => {
            const amdRequire = win.require;
            if (amdRequire === undefined) {
                reject(new Error('Monaco AMD loader unavailable.'));
                return;
            }
            amdRequire.config({paths: {vs: `${baseUrl}/vs`}});
            amdRequire(['vs/editor/editor.main'], () => {
                const monaco = win.monaco;
                if (monaco === undefined) {
                    reject(new Error('Monaco failed to initialise.'));
                    return;
                }
                configure(monaco);
                resolve(monaco);
            });
        };
        script.onerror = (): void => reject(new Error('Failed to load Monaco loader script.'));
        document.body.appendChild(script);
    });

    return monacoPromise;
}

function configure(monaco: typeof Monaco): void {
    if (configured) {
        return;
    }
    configured = true;

    monaco.languages.typescript.javascriptDefaults.addExtraLib(UKOLIO_DTS, 'ts:ukolio.d.ts');

    monaco.editor.defineTheme('ukolio-light', {
        base: 'vs',
        inherit: true,
        rules: [
            {token: 'comment', foreground: '8a8a92', fontStyle: 'italic'},
            {token: 'string', foreground: '16794a'},
            {token: 'number', foreground: 'a35c00'},
            {token: 'keyword', foreground: '6f4ed3'},
            {token: 'type', foreground: '0f766e'},
            {token: 'identifier', foreground: '1e58b6'},
        ],
        colors: {
            'editor.background': '#ffffff',
            'editorLineNumber.foreground': '#c2c2c9',
            'editorLineNumber.activeForeground': '#8a8a92',
        },
    });

    monaco.editor.defineTheme('ukolio-dark', {
        base: 'vs-dark',
        inherit: true,
        rules: [
            {token: 'comment', foreground: '6b7280', fontStyle: 'italic'},
            {token: 'string', foreground: '7ee0a1'},
            {token: 'number', foreground: 'fbbf24'},
            {token: 'keyword', foreground: 'b9a4f5'},
            {token: 'type', foreground: '5ad4cf'},
            {token: 'identifier', foreground: '7fb4ff'},
        ],
        colors: {
            'editor.background': '#161619',
            'editorLineNumber.foreground': '#3a3a44',
            'editorLineNumber.activeForeground': '#7a7a86',
        },
    });
}
