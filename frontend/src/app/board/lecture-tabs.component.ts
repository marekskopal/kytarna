import {ChangeDetectionStrategy, Component, computed, inject, input, OnInit, signal} from '@angular/core';
import {Tab} from '@app/models/tab';
import {AlertService} from '@app/services/alert.service';
import {TabService} from '@app/services/tab.service';
import {TranslatePipe, TranslateService} from '@ngx-translate/core';

import {TabEditorComponent} from './tab-editor.component';
import {TabViewerComponent} from './tab-viewer.component';

type Mode = 'view' | 'edit' | 'create';

/** Lists a lecture's tabs, lets the user switch/view/edit/import them. */
@Component({
    selector: 'uk-lecture-tabs',
    standalone: true,
    imports: [TranslatePipe, TabViewerComponent, TabEditorComponent],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './lecture-tabs.component.html',
    styleUrl: './lecture-tabs.component.scss',
})
export class LectureTabsComponent implements OnInit {
    public readonly lectureId = input.required<number | string>();

    private readonly tabService = inject(TabService);
    private readonly alertService = inject(AlertService);
    private readonly translate = inject(TranslateService);

    protected readonly tabs = signal<Tab[]>([]);
    protected readonly loading = signal(true);
    protected readonly importing = signal(false);
    protected readonly selectedTabId = signal<number | null>(null);
    protected readonly mode = signal<Mode>('view');

    protected readonly selectedTab = computed<Tab | null>(() => {
        const id = this.selectedTabId();
        return this.tabs().find((t) => t.id === id) ?? null;
    });

    public ngOnInit(): void {
        void this.reload();
    }

    private async reload(selectId?: number): Promise<void> {
        this.loading.set(true);
        try {
            const tabs = await this.tabService.listTabs(this.lectureId());
            this.tabs.set(tabs);
            const targetId = selectId ?? this.selectedTabId() ?? tabs[0]?.id ?? null;
            this.selectedTabId.set(tabs.some((t) => t.id === targetId) ? targetId : tabs[0]?.id ?? null);
        } catch {
            this.tabs.set([]);
            this.selectedTabId.set(null);
        } finally {
            this.loading.set(false);
        }
    }

    protected select(tab: Tab): void {
        this.selectedTabId.set(tab.id);
        this.mode.set('view');
    }

    protected startCreate(): void {
        this.mode.set('create');
    }

    protected startEdit(): void {
        if (this.selectedTab()) {
            this.mode.set('edit');
        }
    }

    protected cancelEdit(): void {
        this.mode.set('view');
    }

    protected async onSaved(tab: Tab): Promise<void> {
        await this.reload(tab.id);
        this.mode.set('view');
    }

    protected async onDelete(): Promise<void> {
        const tab = this.selectedTab();
        if (!tab) {
            return;
        }
        const message = await this.translate.instant('app.tabs.deleteConfirm', {name: tab.name}) as string;
        if (!confirm(message)) {
            return;
        }
        try {
            await this.tabService.deleteTab(tab.id);
            this.selectedTabId.set(null);
            await this.reload();
        } catch {
            // error interceptor
        }
    }

    protected async onImportSelected(event: Event): Promise<void> {
        const target = event.target as HTMLInputElement;
        const file = target.files?.[0];
        target.value = '';
        if (!file) {
            return;
        }
        this.importing.set(true);
        try {
            const tab = await this.tabService.importGpFile(this.lectureId(), file);
            this.alertService.success(await this.translate.instant('app.tabs.imported') as string);
            await this.reload(tab.id);
            this.mode.set('view');
        } catch {
            // error interceptor (422 invalid file / 502 service down)
        } finally {
            this.importing.set(false);
        }
    }
}
