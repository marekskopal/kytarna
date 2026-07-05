import {ChangeDetectionStrategy, Component, computed, inject, OnInit, signal} from '@angular/core';
import {FormsModule} from '@angular/forms';
import {RouterLink, RouterLinkActive} from '@angular/router';
import {SystemRole} from '@app/models/user';
import {AdminService, AdminUser} from '@app/services/admin.service';
import {AlertService} from '@app/services/alert.service';
import {CurrentUserService} from '@app/services/current-user.service';
import {TranslatePipe, TranslateService} from '@ngx-translate/core';

@Component({
    selector: 'uk-admin-users',
    standalone: true,
    imports: [FormsModule, RouterLink, RouterLinkActive, TranslatePipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './admin-users.component.html',
    styleUrl: './admin.scss',
})
export class AdminUsersComponent implements OnInit {
    private readonly adminService = inject(AdminService);
    private readonly currentUserService = inject(CurrentUserService);
    private readonly alertService = inject(AlertService);
    private readonly translate = inject(TranslateService);

    protected readonly loading = signal(true);
    protected readonly query = signal('');
    protected readonly users = signal<AdminUser[]>([]);
    protected readonly filtered = computed<AdminUser[]>(() => {
        const q = this.query().trim().toLowerCase();
        if (q === '') return this.users();
        return this.users().filter((u) => u.email.toLowerCase().includes(q) || u.name.toLowerCase().includes(q));
    });

    public async ngOnInit(): Promise<void> {
        await this.reload();
    }

    private async reload(): Promise<void> {
        this.loading.set(true);
        try {
            this.users.set(await this.adminService.listUsers());
        } finally {
            this.loading.set(false);
        }
    }

    protected updateQuery(value: string): void {
        this.query.set(value);
    }

    protected async toggleSystemRole(user: AdminUser): Promise<void> {
        const newRole: SystemRole = user.systemRole === 'SystemAdmin' ? 'User' : 'SystemAdmin';
        try {
            const updated = await this.adminService.updateUser(user.id, {systemRole: newRole});
            this.users.update((all) => all.map((u) => (u.id === user.id ? updated : u)));
            this.alertService.success(await this.translate.instant('app.admin.users.roleUpdated') as string);
        } catch {
            // error interceptor
        }
    }

    protected async deleteUser(user: AdminUser): Promise<void> {
        const me = this.currentUserService.currentUser();
        if (me?.id === user.id) {
            this.alertService.error(await this.translate.instant('app.admin.users.cannotDeleteSelf') as string);
            return;
        }
        const confirmMessage = await this.translate.instant('app.admin.users.deleteConfirm', {email: user.email}) as string;
        if (!confirm(confirmMessage)) {
            return;
        }
        try {
            await this.adminService.deleteUser(user.id);
            this.users.update((all) => all.filter((u) => u.id !== user.id));
            this.alertService.success(await this.translate.instant('app.admin.users.deleted') as string);
        } catch {
            // error interceptor
        }
    }
}
