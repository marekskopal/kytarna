import {ChangeDetectionStrategy, Component, computed, inject, OnInit, signal} from '@angular/core';
import {FormsModule} from '@angular/forms';
import {RouterLink, RouterLinkActive} from '@angular/router';
import {WorkspaceMember} from '@app/models/workspace';
import {AdminService, AdminWorkspace, AdminWorkspaceDetail} from '@app/services/admin.service';
import {AlertService} from '@app/services/alert.service';
import {TranslatePipe, TranslateService} from '@ngx-translate/core';

@Component({
    selector: 'uk-admin-workspaces',
    standalone: true,
    imports: [FormsModule, RouterLink, RouterLinkActive, TranslatePipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './admin-workspaces.component.html',
    styleUrl: './admin.scss',
})
export class AdminWorkspacesComponent implements OnInit {
    private readonly adminService = inject(AdminService);
    private readonly alertService = inject(AlertService);
    private readonly translate = inject(TranslateService);

    protected readonly loading = signal(true);
    protected readonly query = signal('');
    protected readonly workspaces = signal<AdminWorkspace[]>([]);
    protected readonly filtered = computed<AdminWorkspace[]>(() => {
        const q = this.query().trim().toLowerCase();
        if (q === '') return this.workspaces();
        return this.workspaces().filter(
            (w) => w.name.toLowerCase().includes(q) || w.ownerEmail.toLowerCase().includes(q),
        );
    });

    protected readonly detail = signal<AdminWorkspaceDetail | null>(null);

    public async ngOnInit(): Promise<void> {
        await this.reload();
    }

    private async reload(): Promise<void> {
        this.loading.set(true);
        try {
            this.workspaces.set(await this.adminService.listWorkspaces());
        } finally {
            this.loading.set(false);
        }
    }

    protected updateQuery(value: string): void {
        this.query.set(value);
    }

    protected async openDetail(ws: AdminWorkspace): Promise<void> {
        try {
            this.detail.set(await this.adminService.getWorkspace(ws.id));
        } catch {
            // error interceptor
        }
    }

    protected closeDetail(): void {
        this.detail.set(null);
    }

    protected async renameWorkspace(ws: AdminWorkspace): Promise<void> {
        const promptText = await this.translate.instant('app.admin.workspaces.renamePrompt') as string;
        const name = prompt(promptText, ws.name);
        if (name === null || name.trim() === '' || name.trim() === ws.name) {
            return;
        }
        try {
            const updated = await this.adminService.renameWorkspace(ws.id, name.trim());
            this.workspaces.update((all) => all.map((w) => (w.id === ws.id ? {...w, name: updated.name} : w)));
            const current = this.detail();
            if (current && current.workspace.id === ws.id) {
                this.detail.set({...current, workspace: {...current.workspace, name: updated.name}});
            }
            this.alertService.success(await this.translate.instant('app.admin.workspaces.renamed') as string);
        } catch {
            // error interceptor
        }
    }

    protected async deleteWorkspace(ws: AdminWorkspace): Promise<void> {
        const confirmMessage = await this.translate.instant('app.admin.workspaces.deleteConfirm', {name: ws.name}) as string;
        if (!confirm(confirmMessage)) {
            return;
        }
        try {
            await this.adminService.deleteWorkspace(ws.id);
            this.workspaces.update((all) => all.filter((w) => w.id !== ws.id));
            if (this.detail()?.workspace.id === ws.id) {
                this.detail.set(null);
            }
            this.alertService.success(await this.translate.instant('app.admin.workspaces.deleted') as string);
        } catch {
            // error interceptor
        }
    }

    protected async removeMember(member: WorkspaceMember): Promise<void> {
        const current = this.detail();
        if (current === null) return;
        const confirmMessage = await this.translate.instant('app.admin.workspaces.removeMemberConfirm', {
            name: member.name,
            workspace: current.workspace.name,
        }) as string;
        if (!confirm(confirmMessage)) {
            return;
        }
        try {
            await this.adminService.removeMember(current.workspace.id, member.userId);
            this.detail.set({...current, members: current.members.filter((m) => m.userId !== member.userId)});
        } catch {
            // error interceptor
        }
    }

    protected async transferOwnership(member: WorkspaceMember): Promise<void> {
        const current = this.detail();
        if (current === null) return;
        const confirmMessage = await this.translate.instant('app.admin.workspaces.transferOwnershipConfirm', {
            name: member.name,
            workspace: current.workspace.name,
        }) as string;
        if (!confirm(confirmMessage)) {
            return;
        }
        try {
            await this.adminService.transferOwnership(current.workspace.id, member.userId);
            const refreshed = await this.adminService.getWorkspace(current.workspace.id);
            this.detail.set(refreshed);
            this.workspaces.update((all) => all.map((w) => (w.id === refreshed.workspace.id ? refreshed.workspace : w)));
            this.alertService.success(await this.translate.instant('app.admin.workspaces.ownershipTransferred') as string);
        } catch {
            // error interceptor
        }
    }
}
