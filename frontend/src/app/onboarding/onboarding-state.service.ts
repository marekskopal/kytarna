import {Injectable, signal} from '@angular/core';

@Injectable({providedIn: 'root'})
export class OnboardingStateService {
    public readonly courseId = signal<number | null>(null);
    public readonly courseName = signal<string>('');
    public readonly invitesSent = signal<number>(0);

    public reset(): void {
        this.courseId.set(null);
        this.courseName.set('');
        this.invitesSent.set(0);
    }
}
