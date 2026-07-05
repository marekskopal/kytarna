import {inject, Injectable} from '@angular/core';
import {Router} from '@angular/router';
import {CurrentUserService} from '@app/services/current-user.service';

@Injectable({providedIn: 'root'})
export class OnboardingGuard {
    private readonly router = inject(Router);
    private readonly currentUserService = inject(CurrentUserService);

    public async canActivate(): Promise<boolean> {
        const user = this.currentUserService.currentUser() ?? (await this.currentUserService.load());
        if (user.onboardingCompletedAt !== null) {
            this.router.navigate(['/projects']);
            return false;
        }
        return true;
    }
}
