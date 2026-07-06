import {HttpErrorResponse} from '@angular/common/http';
import {ChangeDetectionStrategy, Component, inject, input, OnInit, output, signal} from '@angular/core';
import {FormBuilder, ReactiveFormsModule, Validators} from '@angular/forms';
import {PracticeParent} from '@app/models/practice-parent';
import {Tab, TabValidationError, TabValidationErrorResponse} from '@app/models/tab';
import {TabService} from '@app/services/tab.service';
import {TranslatePipe} from '@ngx-translate/core';

import {TabViewerComponent} from './tab-viewer.component';

/**
 * alphaTex editor: a plain textarea (monaco is removed) plus a validate/preview
 * action that submits to the API. On HTTP 422 the alphaTex validation errors are
 * shown inline (line/col) without navigating away; on success the preview re-renders.
 */
@Component({
    selector: 'uk-tab-editor',
    standalone: true,
    imports: [ReactiveFormsModule, TranslatePipe, TabViewerComponent],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './tab-editor.component.html',
    styleUrl: './tab-editor.component.scss',
})
export class TabEditorComponent implements OnInit {
    public readonly lectureId = input.required<number | string>();
    public readonly parent = input<PracticeParent>('lectures');
    public readonly tab = input<Tab | null>(null);

    public readonly saved = output<Tab>();
    public readonly cancelled = output<void>();

    private readonly fb = inject(FormBuilder);
    private readonly tabService = inject(TabService);

    protected readonly saving = signal(false);
    protected readonly errors = signal<TabValidationError[]>([]);
    protected readonly previewTex = signal<string | null>(null);

    protected readonly form = this.fb.nonNullable.group({
        name: ['', Validators.required],
        alphaTex: ['', Validators.required],
    });

    public ngOnInit(): void {
        const existing = this.tab();
        if (existing) {
            this.form.patchValue({name: existing.name, alphaTex: existing.alphatexContent});
            this.previewTex.set(existing.alphatexContent);
        }
    }

    protected async onSubmit(): Promise<void> {
        if (this.form.invalid) {
            return;
        }
        this.saving.set(true);
        this.errors.set([]);
        const payload = {
            name: this.form.getRawValue().name.trim(),
            alphaTex: this.form.getRawValue().alphaTex,
        };
        try {
            const existing = this.tab();
            const result = existing
                ? await this.tabService.updateTab(existing.id, payload, this.parent())
                : await this.tabService.createTab(this.lectureId(), payload, this.parent());
            this.previewTex.set(result.alphatexContent);
            this.saved.emit(result);
        } catch (err) {
            this.handleError(err);
        } finally {
            this.saving.set(false);
        }
    }

    protected onCancel(): void {
        this.cancelled.emit();
    }

    private handleError(err: unknown): void {
        if (err instanceof HttpErrorResponse && err.status === 422) {
            const body = err.error as TabValidationErrorResponse | null;
            if (body && Array.isArray(body.errors) && body.errors.length > 0) {
                this.errors.set(body.errors);
                return;
            }
        }
        // 502 (tab-service down) and other errors are surfaced by the global error interceptor.
    }
}
