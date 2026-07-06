import {DatePipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, computed, inject, OnInit, signal} from '@angular/core';
import {FormsModule} from '@angular/forms';
import {WorkspaceMcpClient} from '@app/models/event';
import {Tag} from '@app/models/tag';
import {User} from '@app/models/user';
import {Invitation, Workspace, WorkspaceMember} from '@app/models/workspace';
import {AlertService} from '@app/services/alert.service';
import {CurrentUserService} from '@app/services/current-user.service';
import {EventService} from '@app/services/event.service';
import {PermissionsService} from '@app/services/permissions.service';
import {TagService} from '@app/services/tag.service';
import {WorkspaceService} from '@app/services/workspace.service';
import {pickReadableForeground} from '@app/shared/color-contrast';
import {TranslatePipe, TranslateService} from '@ngx-translate/core';

interface TagEditorState {
    id: number | null;
    name: string;
    color: string;
}

type WorkspaceTab = 'general' | 'members' | 'tags' | 'mcp';

const DEFAULT_TAG_COLOR = '#3b82f6';

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
    private readonly tagService = inject(TagService);
    private readonly eventService = inject(EventService);

    protected readonly loading = signal(true);
    protected readonly workspaces = this.workspaceService.workspaces;
    protected readonly user = signal<User | null>(null);
    protected readonly selected = signal<Workspace | null>(null);
    protected readonly members = signal<WorkspaceMember[]>([]);
    protected readonly invitations = signal<Invitation[]>([]);
    protected readonly inviteEmail = signal('');
    protected readonly mcpClients = signal<WorkspaceMcpClient[]>([]);
    protected readonly tags = signal<Tag[]>([]);
    protected readonly tagEditor = signal<TagEditorState | null>(null);
    protected readonly tagSaving = signal(false);

    protected readonly activeTab = signal<WorkspaceTab>('members');

    protected readonly isSystemAdmin = this.permissionsService.isSystemAdmin;
    protected readonly canManageWorkspace = computed<boolean>(() => this.permissionsService.canManageWorkspace(this.members()));
    protected readonly canManageMembers = computed<boolean>(() => this.permissionsService.canManageMembers(this.members()));
    protected readonly canManageTags = computed<boolean>(() => this.permissionsService.canManageTags(this.members()));
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

    protected canRemoveMember(member: WorkspaceMember): boolean {
        return this.permissionsService.canRemoveMember(this.members(), member);
    }

    protected async select(ws: Workspace): Promise<void> {
        this.selected.set(ws);
        this.tagEditor.set(null);
        const [members, invitations, tags, mcpClients] = await Promise.all([
            this.workspaceService.getMembers(ws.id),
            this.workspaceService.getInvitations(ws.id).catch(() => []),
            this.tagService.loadWorkspaceTags(ws.id, true).catch(() => [] as Tag[]),
            this.eventService.getWorkspaceMcpClients(ws.id).catch(() => [] as WorkspaceMcpClient[]),
        ]);
        this.members.set(members);
        this.invitations.set(invitations);
        this.tags.set(tags);
        this.mcpClients.set(mcpClients);
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
            const updated = await this.workspaceService.update(ws.id, {name: name.trim()});
            this.selected.set(updated);
            this.alertService.success(await this.translate.instant('app.workspaces.renamed') as string);
        } catch {
            // error interceptor
        }
    }

    protected async togglePublic(): Promise<void> {
        const ws = this.selected();
        if (ws === null || !this.canManageWorkspace()) {
            return;
        }
        try {
            const updated = await this.workspaceService.update(ws.id, {isPublic: !ws.isPublic});
            this.selected.set(updated);
        } catch {
            // error interceptor
        }
    }

    protected async rotateJoinCode(): Promise<void> {
        const ws = this.selected();
        if (ws === null || !this.canManageWorkspace()) {
            return;
        }
        try {
            const updated = await this.workspaceService.rotateJoinCode(ws.id);
            this.selected.set(updated);
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
        try {
            const invitation = await this.workspaceService.createInvitation(ws.id, email);
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
}
