import {inject, Injectable} from '@angular/core';
import {ActivatedRouteSnapshot, Router, RouterStateSnapshot} from '@angular/router';
import {AuthenticationService} from '@app/services/authentication.service';

@Injectable({providedIn: 'root'})
export class AuthGuard {
    private readonly router = inject(Router);
    private readonly authService = inject(AuthenticationService);

    public canActivate(_route: ActivatedRouteSnapshot, state: RouterStateSnapshot): boolean {
        if (this.authService.isLoggedIn()) {
            return true;
        }
        this.router.navigate(['/login'], {queryParams: {returnUrl: state.url}});
        return false;
    }
}
