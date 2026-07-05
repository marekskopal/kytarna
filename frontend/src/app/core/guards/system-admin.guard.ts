import {inject, Injectable} from '@angular/core';
import {Router} from '@angular/router';
import {CurrentUserService} from '@app/services/current-user.service';

@Injectable({providedIn: 'root'})
export class SystemAdminGuard {
    private readonly router = inject(Router);
    private readonly currentUserService = inject(CurrentUserService);

    public async canActivate(): Promise<boolean> {
        let user = this.currentUserService.currentUser();
        if (user === null) {
            try {
                user = await this.currentUserService.load();
            } catch {
                this.router.navigate(['/login']);
                return false;
            }
        }
        if (user.systemRole === 'SystemAdmin') {
            return true;
        }
        this.router.navigate(['/projects']);
        return false;
    }
}
