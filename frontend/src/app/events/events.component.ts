import {ChangeDetectionStrategy, Component, inject, OnInit, signal} from '@angular/core';
import {ActivatedRoute, RouterLink} from '@angular/router';
import {AuditEvent} from '@app/models/event';
import {EventService} from '@app/services/event.service';
import {TranslatePipe, TranslateService} from '@ngx-translate/core';

@Component({
    selector: 'uk-events',
    standalone: true,
    imports: [RouterLink, TranslatePipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './events.component.html',
    styleUrl: './events.component.scss',
})
export class EventsComponent implements OnInit {
    private readonly route = inject(ActivatedRoute);
    private readonly eventService = inject(EventService);
    private readonly translate = inject(TranslateService);

    protected readonly loading = signal(true);
    protected readonly events = signal<AuditEvent[]>([]);
    protected readonly projectId = signal<number | null>(null);

    public async ngOnInit(): Promise<void> {
        const id = Number(this.route.snapshot.paramMap.get('id'));
        this.projectId.set(id);
        try {
            this.events.set(await this.eventService.getEvents(id));
        } finally {
            this.loading.set(false);
        }
    }

    protected describe(event: AuditEvent): string {
        const md = event.metadata;
        const t = (key: string, params?: Record<string, unknown>): string =>
            this.translate.instant(key, params) as string;

        switch (event.type) {
            case 'ProjectCreated': return t('app.events.types.ProjectCreated');
            case 'ProjectUpdated': return t('app.events.types.ProjectUpdated');
            case 'ProjectDeleted': return t('app.events.types.ProjectDeleted');
            case 'WorkflowUpdated': return t('app.events.types.WorkflowUpdated');
            case 'StatusCreated': return t('app.events.types.StatusCreated', {name: String(md['name'] ?? '')});
            case 'StatusUpdated': return t('app.events.types.StatusUpdated');
            case 'StatusDeleted': return t('app.events.types.StatusDeleted');
            case 'StatusMoved': return t('app.events.types.StatusMoved');
            case 'TaskCreated': return t('app.events.types.TaskCreated', {name: String(md['name'] ?? '')});
            case 'TaskUpdated': return t('app.events.types.TaskUpdated', {name: String(md['name'] ?? '')});
            case 'TaskDeleted': return t('app.events.types.TaskDeleted', {name: String(md['name'] ?? '')});
            case 'TaskArchived': return t('app.events.types.TaskArchived', {name: String(md['name'] ?? '')});
            case 'TaskUnarchived': return t('app.events.types.TaskUnarchived', {name: String(md['name'] ?? '')});
            case 'TaskMoved':
                return t('app.events.types.TaskMoved', {
                    name: String(md['taskName'] ?? ''),
                    from: String(md['fromStatusName'] ?? '?'),
                    to: String(md['toStatusName'] ?? '?'),
                });
            default:
                return t(`app.agents.verb.${event.type}`);
        }
    }

    protected formatRelative(iso: string): string {
        const then = new Date(iso).getTime();
        const diff = Math.max(0, Date.now() - then);
        const s = Math.round(diff / 1000);
        const t = (key: string, params?: Record<string, unknown>): string =>
            this.translate.instant(key, params) as string;
        if (s < 60) return t('app.events.timeAgo.seconds', {n: s});
        const m = Math.round(s / 60);
        if (m < 60) return t('app.events.timeAgo.minutes', {n: m});
        const h = Math.round(m / 60);
        if (h < 24) return t('app.events.timeAgo.hours', {n: h});
        return new Date(iso).toLocaleDateString();
    }
}
