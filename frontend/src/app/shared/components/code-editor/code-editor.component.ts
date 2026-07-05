import {
    AfterViewInit,
    ChangeDetectionStrategy,
    Component,
    effect,
    ElementRef,
    inject,
    input,
    model,
    OnDestroy,
    viewChild,
} from '@angular/core';
import {ThemeService} from '@app/services/theme.service';
import type * as Monaco from 'monaco-editor';

import {loadMonaco} from './monaco-loader';

@Component({
    selector: 'uk-code-editor',
    standalone: true,
    changeDetection: ChangeDetectionStrategy.OnPush,
    template: '<div #container class="code-editor-host"></div>',
    styles: [`
        :host { display: block; height: 100%; min-height: 0; }
        .code-editor-host { width: 100%; height: 100%; }
    `],
})
export class CodeEditorComponent implements AfterViewInit, OnDestroy {
    private readonly themeService = inject(ThemeService);

    public readonly value = model<string>('');
    public readonly language = input<string>('javascript');
    public readonly readOnly = input<boolean>(false);
    public readonly errorLine = input<number | null>(null);

    private readonly container = viewChild.required<ElementRef<HTMLElement>>('container');

    private monaco: typeof Monaco | null = null;
    private editor: Monaco.editor.IStandaloneCodeEditor | null = null;
    private decorations: Monaco.editor.IEditorDecorationsCollection | null = null;
    private suppressChange = false;

    public constructor() {
        // Keep the model in sync when the parent replaces the value (e.g. on load).
        effect(() => {
            const next = this.value();
            if (this.editor !== null && this.editor.getValue() !== next) {
                this.suppressChange = true;
                this.editor.setValue(next);
                this.suppressChange = false;
            }
        });

        effect(() => {
            const dark = this.themeService.resolvedTheme() === 'dark';
            this.monaco?.editor.setTheme(dark ? 'ukolio-dark' : 'ukolio-light');
        });

        effect(() => {
            const readOnly = this.readOnly();
            this.editor?.updateOptions({readOnly});
        });

        effect(() => {
            this.applyErrorLine(this.errorLine());
        });
    }

    public async ngAfterViewInit(): Promise<void> {
        const monaco = await loadMonaco();
        this.monaco = monaco;

        this.editor = monaco.editor.create(this.container().nativeElement, {
            value: this.value(),
            language: this.language(),
            readOnly: this.readOnly(),
            theme: this.themeService.resolvedTheme() === 'dark' ? 'ukolio-dark' : 'ukolio-light',
            automaticLayout: true,
            minimap: {enabled: false},
            fontSize: 13,
            lineHeight: 20,
            fontFamily: 'var(--font-mono, ui-monospace, SFMono-Regular, Menlo, Consolas, monospace)',
            scrollBeyondLastLine: false,
            tabSize: 2,
            renderLineHighlight: 'all',
            padding: {top: 12, bottom: 12},
            fixedOverflowWidgets: true,
        });

        this.decorations = this.editor.createDecorationsCollection();

        this.editor.onDidChangeModelContent(() => {
            if (this.suppressChange || this.editor === null) {
                return;
            }
            this.value.set(this.editor.getValue());
        });

        this.applyErrorLine(this.errorLine());
    }

    public ngOnDestroy(): void {
        this.editor?.getModel()?.dispose();
        this.editor?.dispose();
        this.editor = null;
    }

    /** Insert text at the current cursor position (used by the API reference panel). */
    public insertSnippet(text: string): void {
        if (this.editor === null) {
            return;
        }
        const selection = this.editor.getSelection();
        if (selection === null) {
            return;
        }
        this.editor.executeEdits('insert-snippet', [{range: selection, text: text + '\n', forceMoveMarkers: true}]);
        this.editor.focus();
    }

    private applyErrorLine(line: number | null): void {
        if (this.monaco === null || this.decorations === null) {
            return;
        }
        if (line === null || line < 1) {
            this.decorations.clear();
            return;
        }
        this.decorations.set([{
            range: new this.monaco.Range(line, 1, line, 1),
            options: {isWholeLine: true, className: 'code-editor-error-line', linesDecorationsClassName: 'code-editor-error-gutter'},
        }]);
        this.editor?.revealLineInCenter(line);
    }
}
