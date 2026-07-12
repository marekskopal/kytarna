import {ChangeDetectionStrategy, Component, input} from '@angular/core';
import {TranslatePipe} from '@ngx-translate/core';

@Component({
    selector: 'uk-brand-logo',
    standalone: true,
    imports: [TranslatePipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
    template: `
        <span class="brand-logo" [style.--brand-h.px]="height()">
            <span class="brand-logo__mark" aria-hidden="true">
                <svg viewBox="0 0 32 32" fill="none">
                    <circle cx="16" cy="16" r="10.5" stroke="currentColor" stroke-width="2.6" />
                    <line x1="11" y1="3" x2="11" y2="29" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                    <line x1="16" y1="2" x2="16" y2="30" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                    <line x1="21" y1="3" x2="21" y2="29" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                </svg>
            </span>
            <span class="brand-logo__name">{{ 'app.brand' | translate }}</span>
        </span>
    `,
    styles: `
        :host {
            display: inline-flex;
        }
        .brand-logo {
            display: inline-flex;
            align-items: center;
            gap: calc(var(--brand-h) * 0.34);
            line-height: 1;
        }
        .brand-logo__mark {
            width: var(--brand-h);
            height: var(--brand-h);
            flex: 0 0 auto;
            border-radius: calc(var(--brand-h) * 0.28);
            background: var(--color-accent);
            color: var(--color-accent-fg);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px var(--color-accent-soft);
        }
        .brand-logo__mark svg {
            width: 66%;
            height: 66%;
        }
        .brand-logo__name {
            font-family: 'Instrument Serif', 'Hanken Grotesk', Georgia, 'Times New Roman', serif;
            font-weight: 400;
            font-size: calc(var(--brand-h) * 0.86);
            letter-spacing: 0.2px;
            color: var(--color-text);
        }
    `,
})
export class BrandLogoComponent {
    public readonly height = input(22);
}
