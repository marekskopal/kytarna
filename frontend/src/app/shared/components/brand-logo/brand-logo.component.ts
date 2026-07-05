import {NgOptimizedImage} from '@angular/common';
import {ChangeDetectionStrategy, Component, computed, inject, input} from '@angular/core';
import {ThemeService} from '@app/services/theme.service';
import {TranslatePipe} from '@ngx-translate/core';

@Component({
    selector: 'uk-brand-logo',
    standalone: true,
    imports: [NgOptimizedImage, TranslatePipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
    template: `
        <img
            class="brand-logo-img"
            [ngSrc]="src()"
            width="330"
            height="120"
            [style.height.px]="height()"
            [alt]="'app.brand' | translate"
        />
    `,
    styles: `
        .brand-logo-img {
            width: auto;
            display: block;
        }
    `,
})
export class BrandLogoComponent {
    private readonly themeService = inject(ThemeService);

    public readonly height = input(22);

    protected readonly src = computed(() =>
        this.themeService.resolvedTheme() === 'dark'
            ? 'assets/brand/logo-wordmark-inverse.svg'
            : 'assets/brand/logo-wordmark.svg',
    );
}
