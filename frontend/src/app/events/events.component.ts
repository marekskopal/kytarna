import {ChangeDetectionStrategy, Component, inject, OnInit, signal} from '@angular/core';
import {ActivatedRoute, RouterLink} from '@angular/router';
import {AuditEvent} from '@app/models/event';
import {LearningStatus, statusLabelKey} from '@app/models/status';
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
    protected readonly courseId = signal<number | null>(null);

    public async ngOnInit(): Promise<void> {
        const id = Number(this.route.snapshot.paramMap.get('id'));
        this.courseId.set(id);
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
        const st = (v: unknown): string =>
            typeof v === 'string' ? t(statusLabelKey(v as LearningStatus)) : '?';

        switch (event.type) {
            case 'CourseCreated': return t('app.events.types.CourseCreated');
            case 'CourseUpdated': return t('app.events.types.CourseUpdated');
            case 'CourseDeleted': return t('app.events.types.CourseDeleted');
            case 'LectureCreated': return t('app.events.types.LectureCreated', {name: String(md['name'] ?? '')});
            case 'LectureUpdated': return t('app.events.types.LectureUpdated', {name: String(md['name'] ?? '')});
            case 'LectureDeleted': return t('app.events.types.LectureDeleted', {name: String(md['name'] ?? '')});
            case 'LectureArchived': return t('app.events.types.LectureArchived', {name: String(md['name'] ?? '')});
            case 'LectureUnarchived': return t('app.events.types.LectureUnarchived', {name: String(md['name'] ?? '')});
            case 'LectureMoved':
                return t('app.events.types.LectureMoved', {
                    name: String(md['lectureName'] ?? md['name'] ?? ''),
                    from: st(md['fromStatus']),
                    to: st(md['toStatus']),
                });
            case 'SongCreated': return t('app.events.types.SongCreated', {name: String(md['name'] ?? '')});
            case 'SongUpdated': return t('app.events.types.SongUpdated', {name: String(md['name'] ?? '')});
            case 'SongDeleted': return t('app.events.types.SongDeleted', {name: String(md['name'] ?? '')});
            case 'SongArchived': return t('app.events.types.SongArchived', {name: String(md['name'] ?? '')});
            case 'SongUnarchived': return t('app.events.types.SongUnarchived', {name: String(md['name'] ?? '')});
            case 'SongAddedToCourse':
                return t('app.events.types.SongAddedToCourse', {name: String(md['name'] ?? ''), course: String(md['courseName'] ?? '')});
            case 'SongRemovedFromCourse': return t('app.events.types.SongRemovedFromCourse', {name: String(md['name'] ?? '')});
            case 'SongMoved':
                return t('app.events.types.SongMoved', {
                    name: String(md['name'] ?? ''),
                    from: st(md['fromStatus']),
                    to: st(md['toStatus']),
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
