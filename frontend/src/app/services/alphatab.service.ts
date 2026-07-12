import {Injectable} from '@angular/core';
import {ResolvedTheme} from '@app/services/theme.service';

/** Whether the player is currently sounding or halted. */
export type PlaybackState = 'paused' | 'playing';

/** Live playback position, in milliseconds. */
export interface PlaybackPosition {
    /** Current position within the song. */
    currentTime: number;
    /** Total length of the song. */
    endTime: number;
}

/** Playback controls over a live alphaTab render (present only when the player is enabled). */
export interface AlphaTabPlayer {
    /** Toggles between play and pause. */
    playPause(): void;
    /** Stops playback and rewinds to the start. */
    stop(): void;
    /** Sets the playback speed multiplier (1 = original tempo). */
    setSpeed(speed: number): void;
    /** Seeks to a fraction (0–1) of the song. */
    seek(fraction: number): void;
}

/** Options for {@link AlphaTabService.render}. */
export interface RenderOptions {
    onError?: (error: unknown) => void;
    theme?: ResolvedTheme;
    /** Enable MIDI playback: load the soundfont, cursor highlighting and playback controls. */
    player?: boolean;
    /** Fired once the soundfont has loaded and playback is available. */
    onPlayerReady?: () => void;
    /** Fired when playback starts, pauses or stops. */
    onStateChange?: (state: PlaybackState) => void;
    /** Fired continuously while playing (and on seek). */
    onPositionChange?: (position: PlaybackPosition) => void;
}

/** A handle to a live alphaTab render, used to control playback and tear it down. */
export interface AlphaTabHandle {
    destroy(): void;
    /** Player controls, present only when {@link RenderOptions.player} was set and the player initialised. */
    readonly player: AlphaTabPlayer | null;
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
    /**
     * Renders alphaTex into the given element. Read-only by default; pass
     * `options.player` to also enable MIDI playback (soundfont + cursor).
     */
    public async render(element: HTMLElement, alphaTex: string, options: RenderOptions = {}): Promise<AlphaTabHandle> {
        const {onError, theme = 'light', player: withPlayer = false} = options;
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
            player: withPlayer
                ? {
                    enablePlayer: true,
                    enableCursor: true,
                    // Load the bundled soundfont; resolve against <base href> like the fonts.
                    soundFont: this.soundFontFile(),
                    // Follow the cursor by scrolling the tab surface itself, not the page.
                    scrollElement: element,
                    scrollMode: alphaTab.ScrollMode.Continuous,
                }
                : {
                    enablePlayer: false,
                },
        });
        if (onError) {
            api.error.on((error) => onError(error));
        }

        const player = withPlayer ? this.wirePlayer(alphaTab, api, options) : null;

        api.tex(alphaTex);
        return {
            player,
            destroy: (): void => api.destroy(),
        };
    }

    /** Subscribes to alphaTab player events and returns the control surface. */
    private wirePlayer(
        alphaTab: typeof import('@coderline/alphatab'),
        api: import('@coderline/alphatab').AlphaTabApi,
        options: RenderOptions,
    ): AlphaTabPlayer {
        // Track the song length (in ticks) so seek() can map a 0–1 fraction to a tick position.
        let endTick = 0;

        if (options.onPlayerReady) {
            api.playerReady.on(() => options.onPlayerReady?.());
        }
        if (options.onStateChange) {
            api.playerStateChanged.on((args) => {
                options.onStateChange?.(args.state === alphaTab.synth.PlayerState.Playing ? 'playing' : 'paused');
            });
        }
        api.playerPositionChanged.on((args) => {
            endTick = args.endTick;
            options.onPositionChange?.({currentTime: args.currentTime, endTime: args.endTime});
        });

        return {
            playPause: (): void => api.playPause(),
            stop: (): void => api.stop(),
            setSpeed: (speed: number): void => {
                api.playbackSpeed = speed;
            },
            seek: (fraction: number): void => {
                if (endTick > 0) {
                    api.tickPosition = Math.max(0, Math.min(1, fraction)) * endTick;
                }
            },
        };
    }

    private fontDirectory(): string {
        return `${this.assetBase()}/assets/alphatab/font/`;
    }

    private soundFontFile(): string {
        return `${this.assetBase()}/assets/alphatab/soundfont/sonivox.sf2`;
    }

    private assetBase(): string {
        return document.baseURI.replace(/\/$/, '');
    }
}
