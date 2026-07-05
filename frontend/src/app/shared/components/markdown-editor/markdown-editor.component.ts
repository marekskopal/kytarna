import {ChangeDetectionStrategy, Component, forwardRef, input, OnInit, signal} from '@angular/core';
import {ControlValueAccessor, NG_VALUE_ACCESSOR} from '@angular/forms';
import {TranslatePipe} from '@ngx-translate/core';
import {MarkdownComponent} from 'ngx-markdown';

@Component({
    selector: 'uk-markdown-editor',
    standalone: true,
    imports: [MarkdownComponent, TranslatePipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './markdown-editor.component.html',
    styleUrl: './markdown-editor.component.scss',
    providers: [
        {
            provide: NG_VALUE_ACCESSOR,
            useExisting: forwardRef(() => MarkdownEditorComponent),
            multi: true,
        },
    ],
})
export class MarkdownEditorComponent implements ControlValueAccessor, OnInit {
    public readonly placeholder = input<string>('');
    public readonly rows = input<number>(8);
    public readonly inputId = input<string>('markdown-editor');
    public readonly initialTab = input<'edit' | 'preview'>('edit');

    protected readonly value = signal<string>('');
    protected readonly tab = signal<'edit' | 'preview'>('edit');
    protected readonly disabled = signal(false);

    private onChange: (value: string) => void = () => {};
    private onTouched: () => void = () => {};

    public ngOnInit(): void {
        this.tab.set(this.initialTab());
    }

    public writeValue(value: string | null): void {
        this.value.set(value ?? '');
    }

    public registerOnChange(fn: (value: string) => void): void {
        this.onChange = fn;
    }

    public registerOnTouched(fn: () => void): void {
        this.onTouched = fn;
    }

    public setDisabledState(isDisabled: boolean): void {
        this.disabled.set(isDisabled);
    }

    protected onInput(event: Event): void {
        const next = (event.target as HTMLTextAreaElement).value;
        this.value.set(next);
        this.onChange(next);
    }

    protected onBlur(): void {
        this.onTouched();
    }

    protected setTab(next: 'edit' | 'preview'): void {
        this.tab.set(next);
    }
}
