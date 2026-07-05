import {ChangeDetectionStrategy, Component, computed, inject, signal} from '@angular/core';
import {Router} from '@angular/router';
import {OnboardingStateService} from '@app/onboarding/onboarding-state.service';
import {AlertService} from '@app/services/alert.service';
import {OnboardingService} from '@app/services/onboarding.service';
import {WorkspaceService} from '@app/services/workspace.service';
import {TranslatePipe, TranslateService} from '@ngx-translate/core';

@Component({
    selector: 'uk-onboarding-step-3',
    standalone: true,
    imports: [TranslatePipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './step-3.component.html',
    styleUrl: './step-shared.scss',
})
export class OnboardingStep3Component {
    private readonly router = inject(Router);
    private readonly onboardingService = inject(OnboardingService);
    private readonly workspaceService = inject(WorkspaceService);
    private readonly alertService = inject(AlertService);
    private readonly translate = inject(TranslateService);
    protected readonly state = inject(OnboardingStateService);

    protected readonly finishing = signal(false);

    protected readonly mcpUrl = `${window.location.origin}/mcp`;

    protected readonly workspaceName = computed(() => {
        const wsId = this.workspaceService.currentWorkspaceId();
        if (wsId === null) {
            return '';
        }
        return this.workspaceService.workspaces().find((w) => w.id === wsId)?.name ?? '';
    });

    protected readonly claudeSnippet = computed(() =>
        `{
  "mcpServers": {
    "ukolio": {
      "url": "${this.mcpUrl}"
    }
  }
}`,
    );

    protected readonly cursorSnippet = computed(() =>
        `{
  "mcp.servers": {
    "ukolio": {
      "url": "${this.mcpUrl}"
    }
  }
}`,
    );

    protected async copy(text: string): Promise<void> {
        try {
            await navigator.clipboard.writeText(text);
            this.alertService.success(await this.translate.instant('app.onboarding.step3.copied') as string);
        } catch {
            this.alertService.error(await this.translate.instant('app.onboarding.step3.copyFailed') as string);
        }
    }

    protected async onFinish(): Promise<void> {
        if (this.finishing()) {
            return;
        }
        this.finishing.set(true);
        try {
            await this.onboardingService.complete();
            await this.router.navigateByUrl('/projects');
        } finally {
            this.finishing.set(false);
        }
    }

    protected async onBack(): Promise<void> {
        await this.router.navigateByUrl('/onboarding/step-2');
    }
}
