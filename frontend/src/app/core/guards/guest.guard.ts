import {inject, Injectable} from '@angular/core';
import {Router} from '@angular/router';
import {AuthenticationService} from '@app/services/authentication.service';

@Injectable({providedIn: 'root'})
export class GuestGuard {
    private readonly router = inject(Router);
    private readonly authService = inject(AuthenticationService);

    public canActivate(): boolean {
        if (this.authService.isLoggedIn()) {
            this.router.navigate(['/projects']);
            return false;
        }
        return true;
    }
}
