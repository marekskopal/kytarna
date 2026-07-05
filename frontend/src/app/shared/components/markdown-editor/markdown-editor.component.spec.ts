import {ChangeDetectionStrategy, Component, SecurityContext} from '@angular/core';
import {TestBed} from '@angular/core/testing';
import {commonTestProviders, provideTranslateStub} from '@app/testing/test-providers';
import {MarkdownComponent, provideMarkdown, SANITIZE} from 'ngx-markdown';
import {beforeEach, describe, expect, it} from 'vitest';

import {MarkdownEditorComponent} from './markdown-editor.component';

@Component({
    standalone: true,
    imports: [MarkdownComponent],
    changeDetection: ChangeDetectionStrategy.OnPush,
    template: '<markdown [data]="data" />',
})
class MarkdownHost {
    public data = '';
}

// ngx-markdown v22 parses asynchronously and writes innerHTML from a floating
// promise that Angular's whenStable() doesn't track, so let microtasks settle
// and run change detection once more before reading the DOM.
async function flushMarkdown(): Promise<void> {
    await new Promise<void>((resolve) => setTimeout(resolve));
}

async function renderMarkdown(source: string): Promise<HTMLElement> {
    const fixture = TestBed.createComponent(MarkdownHost);
    fixture.componentInstance.data = source;
    fixture.detectChanges();
    await fixture.whenStable();
    await flushMarkdown();
    fixture.detectChanges();
    return fixture.nativeElement as HTMLElement;
}

describe('Markdown rendering — XSS hardening', () => {
    beforeEach(() => {
        TestBed.configureTestingModule({
            providers: [
                ...commonTestProviders(),
                provideMarkdown({sanitize: {provide: SANITIZE, useValue: SecurityContext.HTML}}),
                provideTranslateStub(),
            ],
        });
    });

    it('strips <script> tags', async () => {
        const host = await renderMarkdown('hello <script>window.__pwned = true;</script> world');

        expect(host.querySelector('script')).toBeNull();
        expect(host.innerHTML).not.toContain('<script');
        expect((window as unknown as Record<string, unknown>).__pwned).toBeUndefined();
    });

    it('strips inline event handlers from raw HTML', async () => {
        const host = await renderMarkdown('<img src="x" onerror="window.__pwned = true">');

        expect(host.innerHTML).not.toMatch(/onerror/i);
        expect((window as unknown as Record<string, unknown>).__pwned).toBeUndefined();
    });

    it('neutralizes javascript: URLs in markdown links', async () => {
        const host = await renderMarkdown('[click](javascript:alert(1))');

        const anchor = host.querySelector('a');
        const href = (anchor?.getAttribute('href') ?? '').toLowerCase();
        expect(href.startsWith('javascript:')).toBe(false);
        expect(href).toMatch(/^unsafe:/);
    });

    it('neutralizes javascript: URLs in raw anchor tags', async () => {
        const host = await renderMarkdown('<a href="javascript:alert(1)">click</a>');

        const anchor = host.querySelector('a');
        const href = (anchor?.getAttribute('href') ?? '').toLowerCase();
        expect(href.startsWith('javascript:')).toBe(false);
        expect(href).toMatch(/^unsafe:/);
    });

    it('strips iframes from raw HTML', async () => {
        const host = await renderMarkdown('<iframe src="https://evil.example/"></iframe>');

        expect(host.querySelector('iframe')).toBeNull();
    });

    it('preserves benign markdown formatting', async () => {
        const host = await renderMarkdown('**bold** and [link](https://example.com/)');

        expect(host.querySelector('strong')).not.toBeNull();
        const anchor = host.querySelector('a');
        expect(anchor?.getAttribute('href')).toBe('https://example.com/');
    });
});

describe('MarkdownEditorComponent — preview tab sanitization', () => {
    beforeEach(() => {
        TestBed.configureTestingModule({
            providers: [
                ...commonTestProviders(),
                provideMarkdown({sanitize: {provide: SANITIZE, useValue: SecurityContext.HTML}}),
                provideTranslateStub(),
            ],
        });
    });

    it('sanitizes hostile markdown when rendered in the preview tab', async () => {
        const fixture = TestBed.createComponent(MarkdownEditorComponent);
        fixture.componentRef.setInput('initialTab', 'preview');
        fixture.componentInstance.writeValue(
            '<script>window.__pwnedPreview = true;</script>'
            + '<img src="x" onerror="window.__pwnedPreview = true">'
            + '[bad](javascript:alert(1))',
        );
        fixture.componentInstance.ngOnInit();
        fixture.detectChanges();
        await fixture.whenStable();
        await flushMarkdown();
        fixture.detectChanges();

        const host = fixture.nativeElement as HTMLElement;
        expect(host.querySelector('script')).toBeNull();
        expect(host.innerHTML).not.toMatch(/onerror/i);

        const anchor = host.querySelector('a');
        if (anchor) {
            const href = (anchor.getAttribute('href') ?? '').toLowerCase();
            expect(href.startsWith('javascript:')).toBe(false);
        }

        expect((window as unknown as Record<string, unknown>).__pwnedPreview).toBeUndefined();
    });
});
