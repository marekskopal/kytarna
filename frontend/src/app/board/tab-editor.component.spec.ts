import {HttpErrorResponse} from '@angular/common/http';
import {ComponentFixture, TestBed} from '@angular/core/testing';
import {Tab} from '@app/models/tab';
import {AlphaTabService} from '@app/services/alphatab.service';
import {TabService} from '@app/services/tab.service';
import {commonTestProviders, provideTranslateStub} from '@app/testing/test-providers';
import {beforeEach, describe, expect, it, vi} from 'vitest';

import {TabEditorComponent} from './tab-editor.component';

interface EditorInternals {
    form: {setValue(v: {name: string; alphaTex: string}): void};
    onSubmit(): Promise<void>;
    errors(): {message: string; line: number | null; col: number | null}[];
    previewTex(): string | null;
}

const savedTab: Tab = {
    id: 1,
    lectureId: 7,
    name: 'Intro',
    alphatexContent: '. 3.3.4',
    sourceType: 'authored',
    originalFileId: null,
    tempo: null,
    tuning: null,
    trackCount: 1,
    createdAt: '2026-01-01T00:00:00+00:00',
    updatedAt: '2026-01-01T00:00:00+00:00',
};

describe('TabEditorComponent', () => {
    let fixture: ComponentFixture<TabEditorComponent>;
    let createTab: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        createTab = vi.fn();
        TestBed.resetTestingModule();
        TestBed.configureTestingModule({
            imports: [TabEditorComponent],
            providers: [
                ...commonTestProviders(),
                provideTranslateStub(),
                {provide: TabService, useValue: {createTab, updateTab: vi.fn()}},
                {provide: AlphaTabService, useValue: {render: vi.fn().mockResolvedValue({destroy: vi.fn()})}},
            ],
        });
        fixture = TestBed.createComponent(TabEditorComponent);
        fixture.componentRef.setInput('lectureId', 'MP-3');
        fixture.componentRef.setInput('tab', null);
        fixture.detectChanges();
    });

    it('emits saved and shows the preview on success', async () => {
        createTab.mockResolvedValueOnce(savedTab);
        const saved: Tab[] = [];
        fixture.componentInstance.saved.subscribe((t) => saved.push(t));

        const c = fixture.componentInstance as unknown as EditorInternals;
        c.form.setValue({name: 'Intro', alphaTex: '. 3.3.4'});
        await c.onSubmit();
        fixture.detectChanges();

        expect(createTab).toHaveBeenCalledWith('MP-3', {name: 'Intro', alphaTex: '. 3.3.4'}, 'lectures');
        expect(saved).toEqual([savedTab]);
        expect(c.errors().length).toBe(0);
        expect(c.previewTex()).toBe('. 3.3.4');
    });

    it('shows inline validation errors on HTTP 422 and does not emit saved', async () => {
        const err = new HttpErrorResponse({
            status: 422,
            error: {message: 'alphaTex validation failed.', errors: [{message: 'Unexpected token', line: 2, col: 5, offset: 18}]},
        });
        createTab.mockRejectedValueOnce(err);
        const saved: Tab[] = [];
        fixture.componentInstance.saved.subscribe((t) => saved.push(t));

        const c = fixture.componentInstance as unknown as EditorInternals;
        c.form.setValue({name: 'Bad', alphaTex: 'nonsense'});
        await c.onSubmit();
        fixture.detectChanges();

        expect(saved).toEqual([]);
        expect(c.errors().length).toBe(1);
        expect(c.errors()[0].line).toBe(2);
        expect(fixture.nativeElement.querySelector('.tab-errors')).toBeTruthy();
    });
});
