import {Injectable} from '@angular/core';

/** A handle to a live alphaTab render, used to tear it down. */
export interface AlphaTabHandle {
    destroy(): void;
}

/**
 * Thin wrapper around @coderline/alphatab so the rest of the app (and its tests)
 * never import the library directly. alphaTab needs a real DOM + fonts and cannot
 * run under jsdom, so components depend on this service and mock it in unit tests.
 *
 * The library is dynamically imported inside {@link render} to keep it out of the
 * initial bundle and out of the test module graph.
 */
@Injectable({providedIn: 'root'})
export class AlphaTabService {
    /** Renders alphaTex into the given element in read mode (no player). */
    public async render(
        element: HTMLElement,
        alphaTex: string,
        onError?: (error: unknown) => void,
    ): Promise<AlphaTabHandle> {
        const alphaTab = await import('@coderline/alphatab');
        const api = new alphaTab.AlphaTabApi(element, {
            core: {
                // Fonts are copied into assets by angular.json; resolve against <base href>.
                fontDirectory: this.fontDirectory(),
                // Run on the main thread: avoids shipping/resolving a separate worker script
                // for the esbuild bundle. Fine for MVP read-mode rendering.
                useWorkers: false,
            },
            player: {
                enablePlayer: false,
            },
        });
        if (onError) {
            api.error.on((error) => onError(error));
        }
        api.tex(alphaTex);
        return {destroy: (): void => api.destroy()};
    }

    private fontDirectory(): string {
        const base = document.baseURI.replace(/\/$/, '');
        return `${base}/assets/alphatab/font/`;
    }
}
