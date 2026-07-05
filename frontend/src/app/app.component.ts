import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {RouterOutlet} from '@angular/router';
import {AuthenticationService} from '@app/services/authentication.service';
import {CurrentUserService} from '@app/services/current-user.service';
import {LanguageService} from '@app/services/language.service';
import {ThemeService} from '@app/services/theme.service';
import {AlertComponent} from '@app/shared/components/alert/alert.component';

@Component({
    selector: 'uk-app',
    standalone: true,
    imports: [RouterOutlet, AlertComponent],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './app.component.html',
})
export class AppComponent {
    private readonly languageService = inject(LanguageService);
    private readonly themeService = inject(ThemeService);
    private readonly authenticationService = inject(AuthenticationService);
    private readonly currentUserService = inject(CurrentUserService);

    public constructor() {
        this.languageService.init();
        this.themeService.init();

        if (this.authenticationService.isLoggedIn()) {
            this.currentUserService.load()
                .then((user) => {
                    if (this.languageService.isSupported(user.locale)) {
                        this.languageService.use(user.locale, {persist: true, sync: false});
                    }
                    if (this.themeService.isSupported(user.theme)) {
                        this.themeService.use(user.theme, {persist: true, sync: false});
                    }
                })
                .catch(() => {
                    // Interceptor handles 401 -> refresh / logout
                });
        }
    }
}
