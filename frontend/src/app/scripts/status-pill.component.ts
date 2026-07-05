import {ChangeDetectionStrategy, Component, computed, input} from '@angular/core';
import {ScriptRunStatus} from '@app/models/script';
import {TranslatePipe} from '@ngx-translate/core';

@Component({
    selector: 'uk-status-pill',
    standalone: true,
    imports: [TranslatePipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
    template: `
        <span class="status-pill" [class]="'status-pill--' + tone()">
            <span class="status-pill-dot" [class.status-pill-dot--pulse]="status() === 'Running'"></span>
            {{ 'app.scripts.status.' + status() | translate }}
        </span>
    `,
    styleUrl: './status-pill.component.scss',
})
export class StatusPillComponent {
    public readonly status = input.required<ScriptRunStatus>();

    protected readonly tone = computed<string>(() => {
        switch (this.status()) {
            case 'Success': return 'success';
            case 'Error': return 'danger';
            case 'Timeout': return 'warn';
            case 'Running': return 'info';
        }
    });
}
