import {ChangeDetectionStrategy, Component, effect, ElementRef, inject, input, signal, viewChild} from '@angular/core';
import {AlphaTabHandle, AlphaTabService} from '@app/services/alphatab.service';
import {TranslatePipe} from '@ngx-translate/core';

/** Renders alphaTex read-only via alphaTab (through the AlphaTabService wrapper). */
@Component({
    selector: 'uk-tab-viewer',
    standalone: true,
    imports: [TranslatePipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
    template: `
        @if (renderFailed()) {
            <p class="tab-viewer-error text-subtle">{{ 'app.tabs.viewer.error' | translate }}</p>
        }
        <div #container class="tab-viewer-surface" [class.is-hidden]="renderFailed()"></div>
    `,
    styles: [`
        :host { display: block; }
        .tab-viewer-surface { overflow-x: auto; }
        .tab-viewer-surface.is-hidden { display: none; }
        .tab-viewer-error { padding: 12px 0; }
    `],
})
export class TabViewerComponent {
    public readonly alphaTex = input.required<string>();

    private readonly container = viewChild<ElementRef<HTMLElement>>('container');
    private readonly alphaTabService = inject(AlphaTabService);

    protected readonly renderFailed = signal(false);

    public constructor() {
        effect((onCleanup) => {
            const tex = this.alphaTex();
            const ref = this.container();
            if (!ref) {
                return;
            }
            const el = ref.nativeElement;
            el.innerHTML = '';
            this.renderFailed.set(false);

            let handle: AlphaTabHandle | null = null;
            let disposed = false;
            void this.alphaTabService
                .render(el, tex, () => this.renderFailed.set(true))
                .then((h) => {
                    if (disposed) {
                        h.destroy();
                    } else {
                        handle = h;
                    }
                })
                .catch(() => this.renderFailed.set(true));

            onCleanup(() => {
                disposed = true;
                handle?.destroy();
            });
        });
    }
}
