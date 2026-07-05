import {computed, inject, Injectable} from '@angular/core';
import {WorkspaceMember, WorkspaceRole} from '@app/models/workspace';

import {CurrentUserService} from './current-user.service';

@Injectable({providedIn: 'root'})
export class PermissionsService {
    private readonly currentUserService = inject(CurrentUserService);

    public readonly isSystemAdmin = computed<boolean>(() => {
        return this.currentUserService.currentUser()?.systemRole === 'SystemAdmin';
    });

    public roleForCurrentUser(members: WorkspaceMember[] | null | undefined): WorkspaceRole | null {
        const user = this.currentUserService.currentUser();
        if (user === null || !members) {
            return null;
        }
        return members.find((m) => m.userId === user.id)?.role ?? null;
    }

    public canManageWorkspace(members: WorkspaceMember[] | null | undefined): boolean {
        if (this.isSystemAdmin()) return true;
        return this.roleForCurrentUser(members) === 'Owner';
    }

    public canManageMembers(members: WorkspaceMember[] | null | undefined): boolean {
        if (this.isSystemAdmin()) return true;
        const role = this.roleForCurrentUser(members);
        return role === 'Owner' || role === 'Admin';
    }

    public canManageProjects(members: WorkspaceMember[] | null | undefined): boolean {
        if (this.isSystemAdmin()) return true;
        const role = this.roleForCurrentUser(members);
        return role === 'Owner' || role === 'Admin';
    }

    public canManageFields(members: WorkspaceMember[] | null | undefined): boolean {
        if (this.isSystemAdmin()) return true;
        const role = this.roleForCurrentUser(members);
        return role === 'Owner' || role === 'Admin';
    }

    public canManageTags(members: WorkspaceMember[] | null | undefined): boolean {
        if (this.isSystemAdmin()) return true;
        const role = this.roleForCurrentUser(members);
        return role === 'Owner' || role === 'Admin';
    }

    public canManagePriorities(members: WorkspaceMember[] | null | undefined): boolean {
        if (this.isSystemAdmin()) return true;
        const role = this.roleForCurrentUser(members);
        return role === 'Owner' || role === 'Admin';
    }

    public canManageScripts(members: WorkspaceMember[] | null | undefined): boolean {
        if (this.isSystemAdmin()) return true;
        const role = this.roleForCurrentUser(members);
        return role === 'Owner' || role === 'Admin';
    }

    public canChangeRoleOf(members: WorkspaceMember[] | null | undefined, target: WorkspaceMember): boolean {
        if (target.role === 'Owner') return false;
        return this.canManageMembers(members);
    }

    public canRemoveMember(members: WorkspaceMember[] | null | undefined, target: WorkspaceMember): boolean {
        const user = this.currentUserService.currentUser();
        if (target.userId === user?.id) {
            return target.role !== 'Owner';
        }
        if (this.isSystemAdmin()) {
            return target.role !== 'Owner';
        }
        const role = this.roleForCurrentUser(members);
        if (role === 'Owner') return target.role !== 'Owner';
        if (role === 'Admin') return target.role === 'Member';
        return false;
    }

    public canTransferOwnership(members: WorkspaceMember[] | null | undefined): boolean {
        if (this.isSystemAdmin()) return true;
        return this.roleForCurrentUser(members) === 'Owner';
    }

    public invitableRoles(members: WorkspaceMember[] | null | undefined): WorkspaceRole[] {
        if (this.isSystemAdmin()) return ['Admin', 'Member'];
        const role = this.roleForCurrentUser(members);
        if (role === 'Owner') return ['Admin', 'Member'];
        if (role === 'Admin') return ['Member'];
        return [];
    }
}
