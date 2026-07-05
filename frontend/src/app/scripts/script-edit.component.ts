import {DatePipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, computed, inject, OnInit, signal, viewChild} from '@angular/core';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {Script, ScriptRun, ScriptTrigger, ScriptWritePayload} from '@app/models/script';
import {AlertService} from '@app/services/alert.service';
import {ScriptService} from '@app/services/script.service';
import {WorkspaceService} from '@app/services/workspace.service';
import {CodeEditorComponent} from '@app/shared/components/code-editor/code-editor.component';
import {TranslatePipe, TranslateService} from '@ngx-translate/core';

import {StatusPillComponent} from './status-pill.component';
import {ApiEntry, UKOLIO_API_GROUPS} from './ukolio-api';

const DEFAULT_SOURCE = '// Automate with the `ukolio` API. Example:\n'
    + '// const tasks = ukolio.tasks.list({ onlyActive: true });\n'
    + '// ukolio.log(`open tasks: ${tasks.length}`);\n';

const CPU_LIMIT_MS = 5000;
const HTTP_LIMIT = 20;
const API_LIMIT = 200;

interface EventOption {
    type: string;
    labelKey: string;
}

interface CronPreset {
    cron: string;
    labelKey: string;
}

type RightTab = 'api' | 'trigger';
type BottomTab = 'output' | 'problems' | 'runs';

@Component({
    selector: 'uk-script-edit',
    standalone: true,
    imports: [DatePipe, RouterLink, TranslatePipe, CodeEditorComponent, StatusPillComponent],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './script-edit.component.html',
    styleUrl: './script-edit.component.scss',
})
export class ScriptEditComponent implements OnInit {
    private readonly route = inject(ActivatedRoute);
    private readonly router = inject(Router);
    private readonly scriptService = inject(ScriptService);
    private readonly workspaceService = inject(WorkspaceService);
    private readonly alertService = inject(AlertService);
    private readonly translate = inject(TranslateService);

    private readonly codeEditor = viewChild<CodeEditorComponent>('codeEditor');

    protected readonly scriptId = signal<number | null>(null);
    protected readonly saving = signal(false);
    protected readonly running = signal(false);
    protected readonly runs = signal<ScriptRun[]>([]);

    // Script fields as signals.
    protected readonly name = signal('');
    protected readonly active = signal(true);
    protected readonly source = signal(DEFAULT_SOURCE);
    protected readonly cron = signal('0 9 * * 1');
    protected readonly trigger = signal<ScriptTrigger>('Manual');
    protected readonly selectedEvents = signal<string[]>([]);
    protected readonly dirty = signal(false);

    // Panel UI state.
    protected readonly showPanel = signal(true);
    protected readonly rightTab = signal<RightTab>('api');
    protected readonly bottomTab = signal<BottomTab>('output');
    protected readonly selectedRunId = signal<number | null>(null);
    protected readonly expandedApi = signal<Set<string>>(new Set());
    protected readonly errorLine = signal<number | null>(null);

    protected readonly isEdit = computed<boolean>(() => this.scriptId() !== null);
    protected readonly lineCount = computed<number>(() => this.source().split('\n').length);

    protected readonly selectedRun = computed<ScriptRun | null>(() => {
        const runs = this.runs();
        const id = this.selectedRunId();
        if (id !== null) {
            return runs.find((r) => r.id === id) ?? null;
        }
        return runs[0] ?? null;
    });

    protected readonly logLines = computed<string[]>(() => {
        const logs = this.selectedRun()?.logs;
        return logs !== null && logs !== undefined && logs !== '' ? logs.split('\n') : [];
    });

    protected readonly problemCount = computed<number>(() => (this.selectedRun()?.error != null ? 1 : 0));

    protected readonly cpuLimit = CPU_LIMIT_MS;
    protected readonly httpLimit = HTTP_LIMIT;
    protected readonly apiLimit = API_LIMIT;

    protected readonly apiGroups = UKOLIO_API_GROUPS;

    protected readonly triggers: ScriptTrigger[] = ['Manual', 'Scheduled', 'Event'];
    protected readonly cronPresets: CronPreset[] = [
        {cron: '0 9 * * 1', labelKey: 'app.scripts.cronPreset.weekly'},
        {cron: '0 9 * * *', labelKey: 'app.scripts.cronPreset.daily'},
        {cron: '0 * * * *', labelKey: 'app.scripts.cronPreset.hourly'},
        {cron: '*/15 * * * *', labelKey: 'app.scripts.cronPreset.quarterHour'},
        {cron: '0 2 1 * *', labelKey: 'app.scripts.cronPreset.monthly'},
    ];
    protected readonly eventOptions: EventOption[] = [
        {type: 'TaskCreated', labelKey: 'app.scripts.events.TaskCreated'},
        {type: 'TaskUpdated', labelKey: 'app.scripts.events.TaskUpdated'},
        {type: 'TaskMoved', labelKey: 'app.scripts.events.TaskMoved'},
        {type: 'TaskArchived', labelKey: 'app.scripts.events.TaskArchived'},
        {type: 'TaskUnarchived', labelKey: 'app.scripts.events.TaskUnarchived'},
        {type: 'TaskDeleted', labelKey: 'app.scripts.events.TaskDeleted'},
        {type: 'TaskCommentAdded', labelKey: 'app.scripts.events.TaskCommentAdded'},
    ];

    public ngOnInit(): void {
        const idParam = this.route.snapshot.paramMap.get('id');
        if (idParam !== null) {
            const id = Number(idParam);
            this.scriptId.set(id);
            void this.load(id);
        }
    }

    protected setName(value: string): void {
        this.name.set(value);
        this.dirty.set(true);
    }

    protected setSource(value: string): void {
        this.source.set(value);
        this.dirty.set(true);
    }

    protected setCron(value: string): void {
        this.cron.set(value);
        this.dirty.set(true);
    }

    protected toggleActive(): void {
        this.active.update((v) => !v);
        this.dirty.set(true);
    }

    protected onTriggerChange(value: ScriptTrigger): void {
        this.trigger.set(value);
        this.dirty.set(true);
    }

    protected applyCronPreset(cron: string): void {
        this.cron.set(cron);
        this.dirty.set(true);
    }

    protected toggleEvent(type: string): void {
        this.selectedEvents.update((events) => (events.includes(type) ? events.filter((e) => e !== type) : [...events, type]));
        this.dirty.set(true);
    }

    protected isEventSelected(type: string): boolean {
        return this.selectedEvents().includes(type);
    }

    protected togglePanel(): void {
        this.showPanel.update((v) => !v);
    }

    protected setRightTab(tab: RightTab): void {
        this.rightTab.set(tab);
        this.showPanel.set(true);
    }

    protected setBottomTab(tab: BottomTab): void {
        this.bottomTab.set(tab);
    }

    protected selectRun(run: ScriptRun): void {
        this.selectedRunId.set(run.id);
        this.bottomTab.set(run.error != null ? 'problems' : 'output');
    }

    protected toggleApiRow(signature: string): void {
        this.expandedApi.update((set) => {
            const next = new Set(set);
            if (next.has(signature)) {
                next.delete(signature);
            } else {
                next.add(signature);
            }
            return next;
        });
    }

    protected isApiRowExpanded(signature: string): boolean {
        return this.expandedApi().has(signature);
    }

    protected insertSnippet(entry: ApiEntry): void {
        this.codeEditor()?.insertSnippet(entry.snippet);
    }

    protected errorLineOf(run: ScriptRun | null): number | null {
        const error = run?.error;
        if (error == null) {
            return null;
        }
        const match = /script\.js:(\d+)/.exec(error);
        return match !== null ? Number(match[1]) : null;
    }

    protected goToErrorLine(): void {
        const line = this.errorLineOf(this.selectedRun());
        if (line !== null) {
            this.errorLine.set(line);
        }
    }

    protected triggerBadgeLabel(): string {
        const trigger = this.trigger();
        if (trigger === 'Scheduled') {
            return this.translate.instant('app.scripts.trigger.Scheduled') as string + ' · ' + this.cron();
        }
        if (trigger === 'Event') {
            return this.translate.instant('app.scripts.trigger.Event') as string + ' · ' + String(this.selectedEvents().length);
        }
        return this.translate.instant('app.scripts.trigger.Manual') as string;
    }

    protected meterPercent(value: number, limit: number): number {
        return Math.min(100, Math.round((value / limit) * 100));
    }

    protected async save(): Promise<void> {
        if (this.name().trim() === '' || this.source().trim() === '') {
            this.alertService.error(this.translate.instant('app.scripts.validationRequired') as string);
            return;
        }
        const payload = this.buildPayload();
        this.saving.set(true);
        try {
            const id = this.scriptId();
            if (id !== null) {
                await this.scriptService.updateScript(id, payload);
            } else {
                const workspaceId = this.workspaceService.currentWorkspaceId();
                if (workspaceId === null) {
                    return;
                }
                await this.scriptService.createScript(workspaceId, payload);
            }
            this.dirty.set(false);
            this.alertService.success(this.translate.instant('app.scripts.saved') as string);
            await this.router.navigate(['/settings/scripts']);
        } catch {
            // error interceptor surfaces validation messages (e.g. invalid cron)
        } finally {
            this.saving.set(false);
        }
    }

    protected async runNow(): Promise<void> {
        const id = this.scriptId();
        if (id === null) {
            return;
        }
        this.running.set(true);
        this.bottomTab.set('output');
        try {
            await this.scriptService.runScript(id);
            this.alertService.success(this.translate.instant('app.scripts.runQueued') as string);
            // Give the worker a moment, then poll the run history.
            window.setTimeout(() => void this.refreshRuns(), 1200);
        } catch {
            // error interceptor surfaces the message
        } finally {
            this.running.set(false);
        }
    }

    protected async refreshRuns(): Promise<void> {
        const id = this.scriptId();
        if (id === null) {
            return;
        }
        try {
            this.runs.set(await this.scriptService.listRuns(id));
            this.selectedRunId.set(null);
        } catch {
            // error interceptor surfaces the message
        }
    }

    private buildPayload(): ScriptWritePayload {
        const trigger = this.trigger();
        let triggerConfig: string | null = null;
        if (trigger === 'Scheduled') {
            triggerConfig = this.cron().trim();
        } else if (trigger === 'Event') {
            triggerConfig = JSON.stringify(this.selectedEvents());
        }

        return {
            name: this.name().trim(),
            source: this.source(),
            trigger,
            triggerConfig,
            active: this.active(),
        };
    }

    private async load(id: number): Promise<void> {
        try {
            const script = await this.scriptService.getScript(id);
            this.applyScript(script);
            await this.refreshRuns();
        } catch {
            // error interceptor surfaces the message
        }
    }

    private applyScript(script: Script): void {
        this.name.set(script.name);
        this.active.set(script.active);
        this.source.set(script.source);
        this.trigger.set(script.trigger);
        if (script.trigger === 'Scheduled') {
            this.cron.set(script.triggerConfig ?? this.cron());
        }
        if (script.trigger === 'Event') {
            this.selectedEvents.set(this.parseEvents(script.triggerConfig));
        }
        this.dirty.set(false);
    }

    private parseEvents(triggerConfig: string | null): string[] {
        if (triggerConfig === null) {
            return [];
        }
        try {
            const parsed: unknown = JSON.parse(triggerConfig);
            return Array.isArray(parsed) ? parsed.filter((item): item is string => typeof item === 'string') : [];
        } catch {
            return [];
        }
    }
}
