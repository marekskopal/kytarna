import {Injectable} from '@angular/core';
import {ResolvedTheme} from '@app/services/theme.service';

/** A handle to a live alphaTab render, used to tear it down. */
export interface AlphaTabHandle {
    destroy(): void;
}

/**
 * Glyph/staff colors alphaTab renders with. Defaults are tuned for a light
 * background (near-black notes); on dark backgrounds the glyphs must be light or
 * they vanish. Values mirror the SCSS design tokens in _variables.scss.
 */
const THEME_RESOURCES: Record<ResolvedTheme, Record<string, string>> = {
    light: {
        mainGlyphColor: '#231d15',
        secondaryGlyphColor: '#5a4f3f',
        scoreInfoColor: '#231d15',
        staffLineColor: '#948976',
        barSeparatorColor: '#948976',
        barNumberColor: '#5a4f3f',
    },
    dark: {
        mainGlyphColor: '#f2ebdc',
        secondaryGlyphColor: '#cabfab',
        scoreInfoColor: '#f2ebdc',
        staffLineColor: '#9a8f7c',
        barSeparatorColor: '#9a8f7c',
        barNumberColor: '#cabfab',
    },
};

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
        theme: ResolvedTheme = 'light',
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
            display: {
                // Recolor glyphs/staff lines so notation stays legible in dark mode.
                resources: THEME_RESOURCES[theme],
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
