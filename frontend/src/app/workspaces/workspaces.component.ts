import {DatePipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, computed, inject, OnInit, signal} from '@angular/core';
import {FormsModule} from '@angular/forms';
import {WorkspaceMcpClient} from '@app/models/event';
import {Field, FieldType} from '@app/models/field';
import {Priority} from '@app/models/priority';
import {Tag} from '@app/models/tag';
import {User} from '@app/models/user';
import {Invitation, Workspace, WorkspaceMember, WorkspaceRole} from '@app/models/workspace';
import {AlertService} from '@app/services/alert.service';
import {CurrentUserService} from '@app/services/current-user.service';
import {EventService} from '@app/services/event.service';
import {FieldService} from '@app/services/field.service';
import {PermissionsService} from '@app/services/permissions.service';
import {PriorityService} from '@app/services/priority.service';
import {TagService} from '@app/services/tag.service';
import {WorkspaceService} from '@app/services/workspace.service';
import {pickReadableForeground} from '@app/shared/color-contrast';
import {TranslatePipe, TranslateService} from '@ngx-translate/core';

interface FieldEditorState {
    id: number | null;
    name: string;
    type: FieldType;
    required: boolean;
    defaultValue: string;
    options: string[];
}

interface TagEditorState {
    id: number | null;
    name: string;
    color: string;
}

interface PriorityEditorState {
    id: number | null;
    name: string;
    color: string;
    isDefault: boolean;
}

type WorkspaceTab = 'general' | 'members' | 'tags' | 'priorities' | 'fields' | 'mcp';

const FIELD_TYPES: FieldType[] = ['Text', 'Textarea', 'Select', 'Version'];

const DEFAULT_TAG_COLOR = '#3b82f6';
const DEFAULT_PRIORITY_COLOR = '#fbf2dd';

@Component({
    selector: 'uk-workspaces',
    standalone: true,
    imports: [DatePipe, FormsModule, TranslatePipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './workspaces.component.html',
    styleUrl: './workspaces.component.scss',
})
export class WorkspacesComponent implements OnInit {
    private readonly workspaceService = inject(WorkspaceService);
    private readonly currentUserService = inject(CurrentUserService);
    private readonly permissionsService = inject(PermissionsService);
    private readonly alertService = inject(AlertService);
    private readonly translate = inject(TranslateService);
    private readonly fieldService = inject(FieldService);
    private readonly tagService = inject(TagService);
    private readonly priorityService = inject(PriorityService);
    private readonly eventService = inject(EventService);

    protected readonly loading = signal(true);
    protected readonly workspaces = this.workspaceService.workspaces;
    protected readonly user = signal<User | null>(null);
    protected readonly selected = signal<Workspace | null>(null);
    protected readonly members = signal<WorkspaceMember[]>([]);
    protected readonly invitations = signal<Invitation[]>([]);
    protected readonly inviteEmail = signal('');
    protected readonly inviteRole = signal<WorkspaceRole>('Member');
    protected readonly fields = signal<Field[]>([]);
    protected readonly mcpClients = signal<WorkspaceMcpClient[]>([]);
    protected readonly fieldEditor = signal<FieldEditorState | null>(null);
    protected readonly fieldSaving = signal(false);
    protected readonly fieldTypes = FIELD_TYPES;
    protected readonly tags = signal<Tag[]>([]);
    protected readonly tagEditor = signal<TagEditorState | null>(null);
    protected readonly tagSaving = signal(false);
    protected readonly priorities = signal<Priority[]>([]);
    protected readonly priorityEditor = signal<PriorityEditorState | null>(null);
    protected readonly prioritySaving = signal(false);

    protected readonly activeTab = signal<WorkspaceTab>('members');

    protected readonly isSystemAdmin = this.permissionsService.isSystemAdmin;
    protected readonly canManageWorkspace = computed<boolean>(() => this.permissionsService.canManageWorkspace(this.members()));
    protected readonly canManageMembers = computed<boolean>(() => this.permissionsService.canManageMembers(this.members()));
    protected readonly canManageFields = computed<boolean>(() => this.permissionsService.canManageFields(this.members()));
    protected readonly canManageTags = computed<boolean>(() => this.permissionsService.canManageTags(this.members()));
    protected readonly canManagePriorities = computed<boolean>(() => this.permissionsService.canManagePriorities(this.members()));
    protected readonly canTransferOwnership = computed<boolean>(() => this.permissionsService.canTransferOwnership(this.members()));
    protected readonly invitableRoles = computed<WorkspaceRole[]>(() => this.permissionsService.invitableRoles(this.members()));
    protected readonly editorHasOptions = computed<boolean>(() => {
        const ed = this.fieldEditor();
        return ed !== null && (ed.type === 'Select' || ed.type === 'Version');
    });
    protected readonly totalAuthorizations = computed<number>(() => this.mcpClients().reduce((sum, c) => sum + c.totalAuthorizations, 0));

    protected readonly availableTabs = computed<WorkspaceTab[]>(() => {
        const tabs: WorkspaceTab[] = [];
        if (this.canManageWorkspace()) {
            tabs.push('general');
        }
        tabs.push('members');
        if (this.canManageTags()) {
            tabs.push('tags');
        }
        if (this.canManagePriorities()) {
            tabs.push('priorities');
        }
        if (this.canManageFields()) {
            tabs.push('fields');
        }
        tabs.push('mcp');
        return tabs;
    });

    public async ngOnInit(): Promise<void> {
        this.loading.set(true);
        try {
            const [user] = await Promise.all([this.currentUserService.load(), this.workspaceService.loadAll()]);
            this.user.set(user);
            const current = this.workspaces().find((w) => w.id === user.currentWorkspaceId) ?? this.workspaces()[0] ?? null;
            if (current !== null) {
                await this.select(current);
            }
        } finally {
            this.loading.set(false);
        }
    }

    protected canChangeRoleOf(member: WorkspaceMember): boolean {
        return this.permissionsService.canChangeRoleOf(this.members(), member);
    }

    protected canRemoveMember(member: WorkspaceMember): boolean {
        return this.permissionsService.canRemoveMember(this.members(), member);
    }

    protected async select(ws: Workspace): Promise<void> {
        this.selected.set(ws);
        this.fieldEditor.set(null);
        this.tagEditor.set(null);
        this.priorityEditor.set(null);
        const [members, invitations, fields, tags, priorities, mcpClients] = await Promise.all([
            this.workspaceService.getMembers(ws.id),
            this.workspaceService.getInvitations(ws.id).catch(() => []),
            this.fieldService.listWorkspaceFields(ws.id).catch(() => [] as Field[]),
            this.tagService.loadWorkspaceTags(ws.id, true).catch(() => [] as Tag[]),
            this.priorityService.loadWorkspacePriorities(ws.id, true).catch(() => [] as Priority[]),
            this.eventService.getWorkspaceMcpClients(ws.id).catch(() => [] as WorkspaceMcpClient[]),
        ]);
        this.members.set(members);
        this.invitations.set(invitations);
        this.fields.set(fields);
        this.tags.set(tags);
        this.priorities.set(priorities);
        this.mcpClients.set(mcpClients);
        const allowed = this.permissionsService.invitableRoles(members);
        if (!allowed.includes(this.inviteRole())) {
            this.inviteRole.set(allowed[0] ?? 'Member');
        }
        if (!this.availableTabs().includes(this.activeTab())) {
            this.activeTab.set('members');
        }
    }

    protected setTab(tab: WorkspaceTab): void {
        this.activeTab.set(tab);
    }

    protected async rename(): Promise<void> {
        const ws = this.selected();
        if (ws === null || !this.canManageWorkspace()) {
            return;
        }
        const promptText = await this.translate.instant('app.workspaces.renamePrompt') as string;
        const name = prompt(promptText, ws.name);
        if (name === null || name.trim() === '' || name.trim() === ws.name) {
            return;
        }
        try {
            const updated = await this.workspaceService.update(ws.id, name.trim());
            this.selected.set(updated);
            this.alertService.success(await this.translate.instant('app.workspaces.renamed') as string);
        } catch {
            // error interceptor
        }
    }

    protected async invite(): Promise<void> {
        const ws = this.selected();
        const email = this.inviteEmail().trim();
        if (ws === null || email === '' || !this.canManageMembers()) {
            return;
        }
        const role = this.inviteRole();
        try {
            const invitation = await this.workspaceService.createInvitation(ws.id, email, role);
            this.invitations.update((all) => [invitation, ...all]);
            this.inviteEmail.set('');
            this.alertService.success(await this.translate.instant('app.workspaces.invitationSent', {email}) as string);
        } catch {
            // error interceptor
        }
    }

    protected async cancelInvitation(invitation: Invitation): Promise<void> {
        const confirmMessage = await this.translate.instant('app.workspaces.cancelInvitationConfirm', {email: invitation.email}) as string;
        if (!confirm(confirmMessage)) {
            return;
        }
        try {
            await this.workspaceService.deleteInvitation(invitation.id);
            this.invitations.update((all) => all.filter((i) => i.id !== invitation.id));
        } catch {
            // error interceptor
        }
    }

    protected async changeMemberRole(member: WorkspaceMember, role: WorkspaceRole): Promise<void> {
        const ws = this.selected();
        if (ws === null || role === member.role) {
            return;
        }
        try {
            const updated = await this.workspaceService.changeMemberRole(ws.id, member.userId, role);
            this.members.update((all) => all.map((m) => (m.userId === member.userId ? updated : m)));
            this.alertService.success(await this.translate.instant('app.workspaces.roleChanged') as string);
        } catch {
            // error interceptor
        }
    }

    protected async transferOwnership(member: WorkspaceMember): Promise<void> {
        const ws = this.selected();
        if (ws === null || !this.canTransferOwnership()) {
            return;
        }
        const confirmMessage = await this.translate.instant('app.workspaces.transferOwnershipConfirm', {
            name: member.name,
            workspace: ws.name,
        }) as string;
        if (!confirm(confirmMessage)) {
            return;
        }
        try {
            const updated = await this.workspaceService.transferOwnership(ws.id, member.userId);
            this.selected.set(updated);
            await this.workspaceService.loadAll();
            const refreshed = await this.workspaceService.getMembers(ws.id);
            this.members.set(refreshed);
            this.alertService.success(await this.translate.instant('app.workspaces.ownershipTransferred') as string);
        } catch {
            // error interceptor
        }
    }

    protected async removeMember(member: WorkspaceMember): Promise<void> {
        const ws = this.selected();
        if (ws === null) {
            return;
        }
        const confirmMessage = await this.translate.instant('app.workspaces.removeMemberConfirm', {
            name: member.name,
            workspace: ws.name,
        }) as string;
        if (!confirm(confirmMessage)) {
            return;
        }
        try {
            await this.workspaceService.removeMember(ws.id, member.userId);
            this.members.update((all) => all.filter((m) => m.userId !== member.userId));
            this.alertService.success(await this.translate.instant('app.workspaces.memberRemoved') as string);
        } catch {
            // error interceptor
        }
    }

    protected async revokeMcpClient(client: WorkspaceMcpClient): Promise<void> {
        const ws = this.selected();
        if (ws === null || !this.canManageMembers()) {
            return;
        }
        const confirmMessage = await this.translate.instant('app.workspaces.mcp.revokeConfirm', {
            name: client.clientName,
        }) as string;
        if (!confirm(confirmMessage)) {
            return;
        }
        try {
            await this.eventService.revokeWorkspaceMcpClient(ws.id, client.clientId);
            this.mcpClients.set(await this.eventService.getWorkspaceMcpClients(ws.id).catch(() => [] as WorkspaceMcpClient[]));
            this.alertService.success(await this.translate.instant('app.workspaces.mcp.revoked') as string);
        } catch {
            // error interceptor
        }
    }

    protected async deleteWorkspace(): Promise<void> {
        const ws = this.selected();
        if (ws === null || !this.canManageWorkspace()) {
            return;
        }
        const confirmMessage = await this.translate.instant('app.workspaces.deleteConfirm', {name: ws.name}) as string;
        if (!confirm(confirmMessage)) {
            return;
        }
        try {
            await this.workspaceService.delete(ws.id);
            this.alertService.success(await this.translate.instant('app.workspaces.deleted') as string);
            this.selected.set(null);
            const next = this.workspaces()[0];
            if (next !== undefined) {
                await this.select(next);
            }
        } catch {
            // error interceptor
        }
    }

    protected updateInviteEmail(value: string): void {
        this.inviteEmail.set(value);
    }

    protected updateInviteRole(value: string): void {
        if (value === 'Admin' || value === 'Member') {
            this.inviteRole.set(value);
        }
    }

    protected openCreateField(): void {
        this.fieldEditor.set({
            id: null,
            name: '',
            type: 'Text',
            required: false,
            defaultValue: '',
            options: [],
        });
    }

    protected openEditField(field: Field): void {
        this.fieldEditor.set({
            id: field.id,
            name: field.name,
            type: field.type,
            required: field.required,
            defaultValue: field.defaultValue ?? '',
            options: field.options ? [...field.options] : [],
        });
    }

    protected closeFieldEditor(): void {
        this.fieldEditor.set(null);
    }

    protected updateEditor<K extends keyof FieldEditorState>(key: K, value: FieldEditorState[K]): void {
        this.fieldEditor.update((ed) => (ed === null ? ed : {...ed, [key]: value}));
    }

    protected updateEditorType(value: string): void {
        if (FIELD_TYPES.includes(value as FieldType)) {
            this.fieldEditor.update((ed) => {
                if (ed === null) return ed;
                const type = value as FieldType;
                const options = type === 'Select' || type === 'Version' ? ed.options : [];
                return {...ed, type, options};
            });
        }
    }

    protected addOption(): void {
        this.fieldEditor.update((ed) => (ed === null ? ed : {...ed, options: [...ed.options, '']}));
    }

    protected updateOption(index: number, value: string): void {
        this.fieldEditor.update((ed) => {
            if (ed === null) return ed;
            const next = [...ed.options];
            next[index] = value;
            return {...ed, options: next};
        });
    }

    protected removeOption(index: number): void {
        this.fieldEditor.update((ed) => {
            if (ed === null) return ed;
            return {...ed, options: ed.options.filter((_, i) => i !== index)};
        });
    }

    protected async saveField(): Promise<void> {
        const ws = this.selected();
        const ed = this.fieldEditor();
        if (ws === null || ed === null || !this.canManageFields()) {
            return;
        }
        const name = ed.name.trim();
        if (name === '') {
            return;
        }
        const hasOptions = ed.type === 'Select' || ed.type === 'Version';
        const options = hasOptions ? ed.options.map((o) => o.trim()).filter((o) => o !== '') : null;
        if (hasOptions && (options === null || options.length === 0)) {
            this.alertService.error(await this.translate.instant('app.fields.optionsRequired') as string);
            return;
        }
        if (ed.type === 'Version' && options !== null) {
            for (const opt of options) {
                if (!this.fieldService.isValidSemver(opt)) {
                    this.alertService.error(await this.translate.instant('app.fields.invalidSemver', {value: opt}) as string);
                    return;
                }
            }
        }
        const defaultValue = ed.defaultValue.trim() === '' ? null : ed.defaultValue.trim();
        const payload = {name, type: ed.type, required: ed.required, defaultValue, options};

        this.fieldSaving.set(true);
        try {
            const saved = ed.id === null
                ? await this.fieldService.createField(ws.id, payload)
                : await this.fieldService.updateField(ws.id, ed.id, payload);
            this.fields.update((all) => {
                const filtered = all.filter((f) => f.id !== saved.id);
                return [...filtered, saved].sort((a, b) => a.name.localeCompare(b.name));
            });
            const messageKey = ed.id === null ? 'app.fields.fieldCreated' : 'app.fields.fieldUpdated';
            this.alertService.success(await this.translate.instant(messageKey) as string);
            this.fieldEditor.set(null);
        } catch {
            // error interceptor
        } finally {
            this.fieldSaving.set(false);
        }
    }

    protected async deleteField(field: Field): Promise<void> {
        const ws = this.selected();
        if (ws === null || !this.canManageFields()) {
            return;
        }
        const confirmMessage = await this.translate.instant('app.fields.deleteConfirm', {name: field.name}) as string;
        if (!confirm(confirmMessage)) {
            return;
        }
        try {
            await this.fieldService.deleteField(ws.id, field.id);
            this.fields.update((all) => all.filter((f) => f.id !== field.id));
            if (this.fieldEditor()?.id === field.id) {
                this.fieldEditor.set(null);
            }
            this.alertService.success(await this.translate.instant('app.fields.fieldDeleted') as string);
        } catch {
            // error interceptor
        }
    }

    protected openCreateTag(): void {
        this.tagEditor.set({id: null, name: '', color: DEFAULT_TAG_COLOR});
    }

    protected openEditTag(tag: Tag): void {
        this.tagEditor.set({id: tag.id, name: tag.name, color: tag.color});
    }

    protected closeTagEditor(): void {
        this.tagEditor.set(null);
    }

    protected updateTagEditor<K extends keyof TagEditorState>(key: K, value: TagEditorState[K]): void {
        this.tagEditor.update((ed) => (ed === null ? ed : {...ed, [key]: value}));
    }

    protected tagForeground(color: string): string {
        return pickReadableForeground(color);
    }

    protected async saveTag(): Promise<void> {
        const ws = this.selected();
        const ed = this.tagEditor();
        if (ws === null || ed === null || !this.canManageTags()) {
            return;
        }
        const name = ed.name.trim();
        if (name === '') {
            return;
        }
        const payload = {name, color: ed.color};

        this.tagSaving.set(true);
        try {
            const saved = ed.id === null
                ? await this.tagService.createTag(ws.id, payload)
                : await this.tagService.updateTag(ws.id, ed.id, payload);
            this.tags.update((all) => {
                const filtered = all.filter((t) => t.id !== saved.id);
                return [...filtered, saved].sort((a, b) => a.name.localeCompare(b.name));
            });
            const messageKey = ed.id === null ? 'app.tags.tagCreated' : 'app.tags.tagUpdated';
            this.alertService.success(await this.translate.instant(messageKey) as string);
            this.tagEditor.set(null);
        } catch {
            // error interceptor
        } finally {
            this.tagSaving.set(false);
        }
    }

    protected async deleteTag(tag: Tag): Promise<void> {
        const ws = this.selected();
        if (ws === null || !this.canManageTags()) {
            return;
        }
        const confirmMessage = await this.translate.instant('app.tags.deleteConfirm', {name: tag.name}) as string;
        if (!confirm(confirmMessage)) {
            return;
        }
        try {
            await this.tagService.deleteTag(ws.id, tag.id);
            this.tags.update((all) => all.filter((t) => t.id !== tag.id));
            if (this.tagEditor()?.id === tag.id) {
                this.tagEditor.set(null);
            }
            this.alertService.success(await this.translate.instant('app.tags.tagDeleted') as string);
        } catch {
            // error interceptor
        }
    }

    protected openCreatePriority(): void {
        this.priorityEditor.set({id: null, name: '', color: DEFAULT_PRIORITY_COLOR, isDefault: false});
    }

    protected openEditPriority(priority: Priority): void {
        this.priorityEditor.set({
            id: priority.id,
            name: priority.name,
            color: priority.color,
            isDefault: priority.isDefault,
        });
    }

    protected closePriorityEditor(): void {
        this.priorityEditor.set(null);
    }

    protected updatePriorityEditor<K extends keyof PriorityEditorState>(key: K, value: PriorityEditorState[K]): void {
        this.priorityEditor.update((ed) => (ed === null ? ed : {...ed, [key]: value}));
    }

    protected priorityForeground(color: string): string {
        return pickReadableForeground(color);
    }

    protected async savePriority(): Promise<void> {
        const ws = this.selected();
        const ed = this.priorityEditor();
        if (ws === null || ed === null || !this.canManagePriorities()) {
            return;
        }
        const name = ed.name.trim();
        if (name === '') {
            return;
        }
        const payload = {name, color: ed.color, isDefault: ed.isDefault};

        this.prioritySaving.set(true);
        try {
            const saved = ed.id === null
                ? await this.priorityService.createPriority(ws.id, payload)
                : await this.priorityService.updatePriority(ws.id, ed.id, payload);
            // Re-fetch to keep the default flag + positions canonical.
            const refreshed = await this.priorityService.loadWorkspacePriorities(ws.id, true);
            this.priorities.set(refreshed);
            void saved;
            const messageKey = ed.id === null ? 'app.priorities.priorityCreated' : 'app.priorities.priorityUpdated';
            this.alertService.success(await this.translate.instant(messageKey) as string);
            this.priorityEditor.set(null);
        } catch {
            // error interceptor
        } finally {
            this.prioritySaving.set(false);
        }
    }

    protected async deletePriority(priority: Priority): Promise<void> {
        const ws = this.selected();
        if (ws === null || !this.canManagePriorities()) {
            return;
        }
        const confirmMessage = await this.translate.instant('app.priorities.deleteConfirm', {name: priority.name}) as string;
        if (!confirm(confirmMessage)) {
            return;
        }
        try {
            await this.priorityService.deletePriority(ws.id, priority.id);
            this.priorities.update((all) => all.filter((p) => p.id !== priority.id));
            if (this.priorityEditor()?.id === priority.id) {
                this.priorityEditor.set(null);
            }
            this.alertService.success(await this.translate.instant('app.priorities.priorityDeleted') as string);
        } catch (err: unknown) {
            const message = this.extractPriorityDeleteError(err)
                ?? await this.translate.instant('app.priorities.deleteError') as string;
            this.alertService.error(message);
        }
    }

    protected async movePriorityUp(priority: Priority): Promise<void> {
        if (priority.position <= 0 || !this.canManagePriorities()) {
            return;
        }
        await this.movePriorityTo(priority, priority.position - 1);
    }

    protected async movePriorityDown(priority: Priority): Promise<void> {
        const all = this.priorities();
        if (priority.position >= all.length - 1 || !this.canManagePriorities()) {
            return;
        }
        await this.movePriorityTo(priority, priority.position + 1);
    }

    private async movePriorityTo(priority: Priority, newPosition: number): Promise<void> {
        const ws = this.selected();
        if (ws === null) {
            return;
        }
        try {
            await this.priorityService.movePriority(ws.id, priority.id, newPosition);
            const refreshed = await this.priorityService.loadWorkspacePriorities(ws.id, true);
            this.priorities.set(refreshed);
        } catch {
            // error interceptor
        }
    }

    private extractPriorityDeleteError(err: unknown): string | null {
        if (typeof err !== 'object' || err === null || !('error' in err)) {
            return null;
        }
        const inner = (err as {error: unknown}).error;
        if (typeof inner !== 'object' || inner === null) {
            return null;
        }
        const dependentRaw = (inner as {dependentTaskCount?: unknown}).dependentTaskCount;
        const dependentCount = typeof dependentRaw === 'number' ? dependentRaw : null;
        if (dependentCount !== null && dependentCount > 0) {
            // We can't await inside a sync helper; build the localized message lazily.
            const tpl = this.translate.instant('app.priorities.deleteBlocked', {count: dependentCount});
            if (typeof tpl === 'string' && tpl !== '') {
                return tpl;
            }
        }
        const message = (inner as {message?: unknown}).message;
        return typeof message === 'string' && message !== '' ? message : null;
    }
}
