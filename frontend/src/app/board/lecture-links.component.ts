import {ChangeDetectionStrategy, Component, inject, input, OnInit, signal} from '@angular/core';
import {FormBuilder, ReactiveFormsModule, Validators} from '@angular/forms';
import {LectureLink, LectureLinkKind} from '@app/models/lecture-link';
import {LinkService} from '@app/services/link.service';
import {TranslatePipe, TranslateService} from '@ngx-translate/core';

/** Lists a lecture's links and lets the user add/remove them. YouTube links open out (CSP-safe). */
@Component({
    selector: 'uk-lecture-links',
    standalone: true,
    imports: [ReactiveFormsModule, TranslatePipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './lecture-links.component.html',
    styleUrl: './lecture-links.component.scss',
})
export class LectureLinksComponent implements OnInit {
    public readonly lectureId = input.required<number | string>();

    private readonly fb = inject(FormBuilder);
    private readonly linkService = inject(LinkService);
    private readonly translate = inject(TranslateService);

    protected readonly links = signal<LectureLink[]>([]);
    protected readonly loading = signal(true);
    protected readonly saving = signal(false);

    protected readonly form = this.fb.nonNullable.group({
        url: ['', Validators.required],
        label: [''],
        timestampSeconds: [null as number | null],
    });

    public ngOnInit(): void {
        void this.reload();
    }

    private async reload(): Promise<void> {
        this.loading.set(true);
        try {
            this.links.set(await this.linkService.listLinks(this.lectureId()));
        } catch {
            this.links.set([]);
        } finally {
            this.loading.set(false);
        }
    }

    protected async onSubmit(): Promise<void> {
        if (this.form.invalid) {
            return;
        }
        this.saving.set(true);
        const raw = this.form.getRawValue();
        const url = raw.url.trim();
        try {
            await this.linkService.addLink(this.lectureId(), {
                url,
                label: raw.label.trim() === '' ? null : raw.label.trim(),
                kind: this.detectKind(url),
                timestampSeconds: raw.timestampSeconds !== null ? Number(raw.timestampSeconds) : null,
            });
            this.form.reset({url: '', label: '', timestampSeconds: null});
            await this.reload();
        } catch {
            // error interceptor
        } finally {
            this.saving.set(false);
        }
    }

    protected async onDelete(link: LectureLink): Promise<void> {
        const message = await this.translate.instant('app.links.deleteConfirm') as string;
        if (!confirm(message)) {
            return;
        }
        try {
            await this.linkService.deleteLink(this.lectureId(), link.id);
            await this.reload();
        } catch {
            // error interceptor
        }
    }

    /**
     * Builds the outbound URL. For YouTube we link out (open in a new tab) rather than
     * embedding, because the /app CSP frame-src does not allow youtube. The start time is
     * carried via the `t` query param.
     */
    protected outboundUrl(link: LectureLink): string {
        if (link.kind !== 'youtube' || link.timestampSeconds === null || link.timestampSeconds <= 0) {
            return link.url;
        }
        const sep = link.url.includes('?') ? '&' : '?';
        return `${link.url}${sep}t=${link.timestampSeconds}`;
    }

    /** Short glyph label for the tile of a non-YouTube link (PDF documents vs. generic references). */
    protected glyphLabel(link: LectureLink): string {
        return /\.pdf(\?|#|$)/i.test(link.url) ? 'PDF' : 'REF';
    }

    protected formatTimestamp(seconds: number): string {
        const m = Math.floor(seconds / 60);
        const s = seconds % 60;
        return `${m}:${s.toString().padStart(2, '0')}`;
    }

    private detectKind(url: string): LectureLinkKind {
        return /(?:youtube\.com|youtu\.be)/i.test(url) ? 'youtube' : 'other';
    }
}
