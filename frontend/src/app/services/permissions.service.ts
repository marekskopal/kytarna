import {computed, inject, Injectable} from '@angular/core';
import {WorkspaceMember, WorkspaceRole} from '@app/models/workspace';

import {CurrentUserService} from './current-user.service';

/**
 * Teacher / Student authorization, mirroring the backend PermissionChecker. The Teacher (workspace
 * owner) edits all content and manages members; a Student has read-only content and tracks their own
 * progress. SystemAdmin passes every check.
 */
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

    public isTeacher(members: WorkspaceMember[] | null | undefined): boolean {
        if (this.isSystemAdmin()) return true;
        return this.roleForCurrentUser(members) === 'Teacher';
    }

    public canManageWorkspace(members: WorkspaceMember[] | null | undefined): boolean {
        return this.isTeacher(members);
    }

    public canManageMembers(members: WorkspaceMember[] | null | undefined): boolean {
        return this.isTeacher(members);
    }

    public canManageCourses(members: WorkspaceMember[] | null | undefined): boolean {
        return this.isTeacher(members);
    }

    public canManageLectures(members: WorkspaceMember[] | null | undefined): boolean {
        return this.isTeacher(members);
    }

    public canManageSongs(members: WorkspaceMember[] | null | undefined): boolean {
        return this.isTeacher(members);
    }

    public canManageTags(members: WorkspaceMember[] | null | undefined): boolean {
        return this.isTeacher(members);
    }

    /** Any member (Teacher or Student) may track their own learning progress. */
    public canTrackProgress(members: WorkspaceMember[] | null | undefined): boolean {
        if (this.isSystemAdmin()) return true;
        return this.roleForCurrentUser(members) !== null;
    }

    public canRemoveMember(members: WorkspaceMember[] | null | undefined, target: WorkspaceMember): boolean {
        // The Teacher (owner) cannot be removed; a Student may remove themselves (leave).
        if (target.role === 'Teacher') return false;
        const user = this.currentUserService.currentUser();
        if (target.userId === user?.id) return true;
        return this.isTeacher(members);
    }
}
