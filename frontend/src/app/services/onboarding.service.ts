import {HttpClient} from '@angular/common/http';
import {inject, Injectable} from '@angular/core';
import {User} from '@app/models/user';
import {CurrentUserService} from '@app/services/current-user.service';
import {environment} from '@environments/environment';
import {firstValueFrom} from 'rxjs';

@Injectable({providedIn: 'root'})
export class OnboardingService {
    private readonly http = inject(HttpClient);
    private readonly currentUserService = inject(CurrentUserService);

    public async complete(): Promise<User> {
        const user = await firstValueFrom(
            this.http.post<User>(`${environment.apiUrl}/current-user/onboarding-complete`, {}),
        );
        this.currentUserService.currentUser.set(user);
        return user;
    }
}
