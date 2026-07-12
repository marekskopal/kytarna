import {ChangeDetectionStrategy, Component, effect, ElementRef, inject, input, signal, viewChild} from '@angular/core';
import {AlphaTabHandle, AlphaTabPlayer, AlphaTabService} from '@app/services/alphatab.service';
import {ThemeService} from '@app/services/theme.service';
import {TranslatePipe} from '@ngx-translate/core';

/** Renders alphaTex via alphaTab (through the AlphaTabService wrapper) with an optional MIDI player. */
@Component({
    selector: 'uk-tab-viewer',
    standalone: true,
    imports: [TranslatePipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
    template: `
        @if (renderFailed()) {
            <p class="tab-viewer-error text-subtle">{{ 'app.tabs.viewer.error' | translate }}</p>
        }
        @if (player() && !renderFailed()) {
            <div class="tab-player">
                <button
                    type="button"
                    class="tab-player-btn"
                    [disabled]="!playerReady()"
                    (click)="togglePlay()"
                    [attr.aria-label]="(isPlaying() ? 'app.tabs.player.pause' : 'app.tabs.player.play') | translate"
                >{{ (isPlaying() ? 'app.tabs.player.pause' : 'app.tabs.player.play') | translate }}</button>
                <button
                    type="button"
                    class="tab-player-btn"
                    [disabled]="!playerReady()"
                    (click)="stop()"
                    [attr.aria-label]="'app.tabs.player.stop' | translate"
                >{{ 'app.tabs.player.stop' | translate }}</button>
                <input
                    type="range"
                    class="tab-player-seek"
                    min="0" max="1000" step="1"
                    [disabled]="!playerReady()"
                    [value]="progressPermille()"
                    (input)="onSeek($event)"
                    [attr.aria-label]="'app.tabs.player.seek' | translate"
                />
                <span class="tab-player-time text-subtle">{{ elapsed() }} / {{ total() }}</span>
                <label class="tab-player-speed text-subtle">
                    {{ 'app.tabs.player.speed' | translate }}
                    <select [disabled]="!playerReady()" (change)="onSpeed($event)">
                        @for (option of speedOptions; track option) {
                            <option [value]="option" [selected]="option === 1">{{ option }}×</option>
                        }
                    </select>
                </label>
            </div>
        }
        <div #container class="tab-viewer-surface" [class.is-hidden]="renderFailed()"></div>
    `,
    styles: [`
        :host { display: block; }
        .tab-viewer-surface { overflow-x: auto; }
        .tab-viewer-surface.is-hidden { display: none; }
        .tab-viewer-error { padding: 12px 0; }
        .tab-player {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }
        .tab-player-btn { cursor: pointer; }
        .tab-player-btn:disabled { cursor: default; opacity: 0.5; }
        .tab-player-seek { flex: 1 1 120px; min-width: 120px; }
        .tab-player-time { font-variant-numeric: tabular-nums; white-space: nowrap; }
        .tab-player-speed { display: inline-flex; align-items: center; gap: 6px; }

        /*
         * alphaTab tags the playback cursor and the currently-played beat's notes
         * (.at-cursor-bar / .at-cursor-beat / .at-highlight) but ships no colours, so
         * they are invisible by default. Paint them with the accent so the current bar,
         * the beat line, and the played notes stand out during playback. ::ng-deep is
         * required because alphaTab renders this SVG outside Angular's view encapsulation.
         */
        :host ::ng-deep .at-cursor-bar {
            background: color-mix(in srgb, var(--color-accent) 16%, transparent);
        }
        :host ::ng-deep .at-cursor-beat {
            background: var(--color-accent);
        }
        :host ::ng-deep .at-highlight * {
            fill: var(--color-accent);
            stroke: var(--color-accent);
        }
    `],
})
export class TabViewerComponent {
    public readonly alphaTex = input.required<string>();
    /** Enable the MIDI player (soundfont + playback controls). */
    public readonly player = input(false);

    protected readonly speedOptions = [0.25, 0.5, 0.75, 1, 1.25, 1.5];

    private readonly container = viewChild<ElementRef<HTMLElement>>('container');
    private readonly alphaTabService = inject(AlphaTabService);
    private readonly themeService = inject(ThemeService);

    protected readonly renderFailed = signal(false);
    protected readonly playerReady = signal(false);
    protected readonly isPlaying = signal(false);
    protected readonly progressPermille = signal(0);
    protected readonly elapsed = signal('0:00');
    protected readonly total = signal('0:00');

    private playerHandle: AlphaTabPlayer | null = null;

    public constructor() {
        effect((onCleanup) => {
            const tex = this.alphaTex();
            const withPlayer = this.player();
            const theme = this.themeService.resolvedTheme();
            const ref = this.container();
            if (!ref) {
                return;
            }
            const el = ref.nativeElement;
            el.innerHTML = '';
            this.renderFailed.set(false);
            this.resetPlayerState();

            let handle: AlphaTabHandle | null = null;
            let disposed = false;
            void this.alphaTabService
                .render(el, tex, {
                    theme,
                    player: withPlayer,
                    onError: () => this.renderFailed.set(true),
                    onPlayerReady: () => this.playerReady.set(true),
                    onStateChange: (state) => this.isPlaying.set(state === 'playing'),
                    onPositionChange: (pos) => this.updatePosition(pos.currentTime, pos.endTime),
                })
                .then((h) => {
                    if (disposed) {
                        h.destroy();
                    } else {
                        handle = h;
                        this.playerHandle = h.player;
                    }
                })
                .catch(() => this.renderFailed.set(true));

            onCleanup(() => {
                disposed = true;
                this.playerHandle = null;
                handle?.destroy();
            });
        });
    }

    protected togglePlay(): void {
        this.playerHandle?.playPause();
    }

    protected stop(): void {
        this.playerHandle?.stop();
    }

    protected onSeek(event: Event): void {
        const value = Number((event.target as HTMLInputElement).value);
        this.playerHandle?.seek(value / 1000);
    }

    protected onSpeed(event: Event): void {
        const value = Number((event.target as HTMLSelectElement).value);
        this.playerHandle?.setSpeed(value);
    }

    private resetPlayerState(): void {
        this.playerHandle = null;
        this.playerReady.set(false);
        this.isPlaying.set(false);
        this.progressPermille.set(0);
        this.elapsed.set('0:00');
        this.total.set('0:00');
    }

    private updatePosition(currentTime: number, endTime: number): void {
        this.progressPermille.set(endTime > 0 ? Math.round((currentTime / endTime) * 1000) : 0);
        this.elapsed.set(this.formatTime(currentTime));
        this.total.set(this.formatTime(endTime));
    }

    private formatTime(ms: number): string {
        const totalSeconds = Math.max(0, Math.floor(ms / 1000));
        const minutes = Math.floor(totalSeconds / 60);
        const seconds = totalSeconds % 60;
        return `${minutes}:${seconds.toString().padStart(2, '0')}`;
    }
}
