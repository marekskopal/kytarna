import {ChangeDetectionStrategy, Component, input} from '@angular/core';
import {TranslatePipe} from '@ngx-translate/core';

@Component({
    selector: 'uk-brand-logo',
    standalone: true,
    imports: [TranslatePipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
    template: `
        <svg class="brand-logo" [style.--brand-h.px]="height()" viewBox="0 0 210 48" fill="none" [attr.aria-label]="'app.brand' | translate">
            <g transform="translate(0,4) scale(1.25)">
                <rect width="32" height="32" rx="8" fill="#c2410c" />
                <circle cx="16" cy="16" r="9" fill="none" stroke="#fff" stroke-width="2.4" />
                <line x1="11.5" y1="5" x2="11.5" y2="27" stroke="#fff" stroke-width="1.6" stroke-linecap="round" />
                <line x1="16" y1="4.5" x2="16" y2="27.5" stroke="#fff" stroke-width="1.6" stroke-linecap="round" />
                <line x1="20.5" y1="5" x2="20.5" y2="27" stroke="#fff" stroke-width="1.6" stroke-linecap="round" />
            </g>
            <text
                x="52"
                y="34"
                font-family="'Instrument Serif','Hanken Grotesk',Georgia,'Times New Roman',serif"
                font-size="34"
                fill="currentColor"
            >{{ 'app.brand' | translate }}</text>
        </svg>
    `,
    styles: `
        :host {
            display: inline-flex;
        }
        .brand-logo {
            display: block;
            height: var(--brand-h);
            width: auto;
            color: var(--color-text);
        }
    `,
})
export class BrandLogoComponent {
    public readonly height = input(22);
}
