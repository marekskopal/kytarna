import {ChangeDetectionStrategy, Component, inject, signal} from '@angular/core';
import {FormsModule} from '@angular/forms';
import {Router} from '@angular/router';
import {PublicWorkspace} from '@app/models/workspace';
import {AlertService} from '@app/services/alert.service';
import {CurrentUserService} from '@app/services/current-user.service';
import {OnboardingService} from '@app/services/onboarding.service';
import {WorkspaceService} from '@app/services/workspace.service';
import {TranslatePipe, TranslateService} from '@ngx-translate/core';

type OnboardingChoice = 'teacher' | 'student';

@Component({
    selector: 'uk-onboarding-step-1',
    standalone: true,
    imports: [FormsModule, TranslatePipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './step-1.component.html',
    styleUrl: './step-shared.scss',
})
export class OnboardingStep1Component {
    private readonly router = inject(Router);
    private readonly workspaceService = inject(WorkspaceService);
    private readonly currentUserService = inject(CurrentUserService);
    private readonly onboardingService = inject(OnboardingService);
    private readonly alertService = inject(AlertService);
    private readonly translate = inject(TranslateService);

    protected readonly choice = signal<OnboardingChoice | null>(null);
    protected readonly saving = signal(false);

    // Teacher path.
    protected readonly workspaceName = signal('');

    // Student path.
    protected readonly directory = signal<PublicWorkspace[]>([]);
    protected readonly directoryLoading = signal(false);
    protected readonly joinCode = signal('');

    protected async chooseTeacher(): Promise<void> {
        this.choice.set('teacher');
    }

    protected async chooseStudent(): Promise<void> {
        this.choice.set('student');
        this.directoryLoading.set(true);
        try {
            this.directory.set(await this.workspaceService.discover());
        } catch {
            this.directory.set([]);
        } finally {
            this.directoryLoading.set(false);
        }
    }

    protected back(): void {
        this.choice.set(null);
    }

    protected async createWorkspace(): Promise<void> {
        const name = this.workspaceName().trim();
        if (name === '' || this.saving()) {
            return;
        }
        this.saving.set(true);
        try {
            const ws = await this.workspaceService.create(name);
            await this.workspaceService.switchTo(ws.id);
            await this.finish();
        } catch {
            this.alertService.error(await this.translate.instant('app.onboarding.step1.errorCreate') as string);
        } finally {
            this.saving.set(false);
        }
    }

    protected async joinPublic(workspace: PublicWorkspace): Promise<void> {
        if (this.saving()) {
            return;
        }
        this.saving.set(true);
        try {
            const ws = await this.workspaceService.joinPublic(workspace.id);
            await this.workspaceService.switchTo(ws.id);
            await this.finish();
        } catch {
            this.alertService.error(await this.translate.instant('app.onboarding.step1.errorJoin') as string);
        } finally {
            this.saving.set(false);
        }
    }

    protected async joinWithCode(): Promise<void> {
        const code = this.joinCode().trim();
        if (code === '' || this.saving()) {
            return;
        }
        this.saving.set(true);
        try {
            const ws = await this.workspaceService.joinByCode(code);
            await this.workspaceService.switchTo(ws.id);
            await this.finish();
        } catch {
            this.alertService.error(await this.translate.instant('app.onboarding.step1.errorJoin') as string);
        } finally {
            this.saving.set(false);
        }
    }

    private async finish(): Promise<void> {
        await this.onboardingService.complete();
        await this.currentUserService.load();
        await this.router.navigateByUrl('/courses');
    }
}
