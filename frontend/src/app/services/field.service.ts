import {HttpClient} from '@angular/common/http';
import {inject, Injectable} from '@angular/core';
import {Field, FieldType, ProjectField} from '@app/models/field';
import {environment} from '@environments/environment';
import {firstValueFrom} from 'rxjs';

export interface FieldWritePayload {
    name: string;
    type: FieldType;
    required: boolean;
    defaultValue: string | null;
    options: string[] | null;
}

const SEMVER_REGEX = /^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?$/;

@Injectable({providedIn: 'root'})
export class FieldService {
    private readonly http = inject(HttpClient);

    public listWorkspaceFields(workspaceId: number): Promise<Field[]> {
        return firstValueFrom(this.http.get<Field[]>(`${environment.apiUrl}/workspaces/${workspaceId}/fields`));
    }

    public createField(workspaceId: number, payload: FieldWritePayload): Promise<Field> {
        return firstValueFrom(this.http.post<Field>(`${environment.apiUrl}/workspaces/${workspaceId}/fields`, payload));
    }

    public updateField(workspaceId: number, fieldId: number, payload: FieldWritePayload): Promise<Field> {
        return firstValueFrom(
            this.http.put<Field>(`${environment.apiUrl}/workspaces/${workspaceId}/fields/${fieldId}`, payload),
        );
    }

    public deleteField(workspaceId: number, fieldId: number): Promise<void> {
        return firstValueFrom(
            this.http.delete<void>(`${environment.apiUrl}/workspaces/${workspaceId}/fields/${fieldId}`),
        );
    }

    public listProjectFields(projectId: number): Promise<ProjectField[]> {
        return firstValueFrom(this.http.get<ProjectField[]>(`${environment.apiUrl}/projects/${projectId}/fields`));
    }

    public setProjectFields(projectId: number, fieldIds: number[]): Promise<ProjectField[]> {
        return firstValueFrom(
            this.http.put<ProjectField[]>(`${environment.apiUrl}/projects/${projectId}/fields`, {fieldIds}),
        );
    }

    public isValidSemver(value: string): boolean {
        return SEMVER_REGEX.test(value);
    }

    public compareSemver(a: string, b: string): number {
        const [mainA, preA] = this.splitMainAndPrerelease(a);
        const [mainB, preB] = this.splitMainAndPrerelease(b);
        const mainCmp = this.compareMain(mainA, mainB);
        if (mainCmp !== 0) {
            return mainCmp;
        }
        if (preA === null && preB === null) {
            return 0;
        }
        if (preA === null) {
            return 1;
        }
        if (preB === null) {
            return -1;
        }
        return this.comparePrerelease(preA, preB);
    }

    public sortVersionsDescending(versions: string[]): string[] {
        return [...versions].sort((a, b) => this.compareSemver(b, a));
    }

    private splitMainAndPrerelease(value: string): [string, string | null] {
        const buildIdx = value.indexOf('+');
        const stripped = buildIdx >= 0 ? value.substring(0, buildIdx) : value;
        const preIdx = stripped.indexOf('-');
        if (preIdx < 0) {
            return [stripped, null];
        }
        return [stripped.substring(0, preIdx), stripped.substring(preIdx + 1)];
    }

    private compareMain(a: string, b: string): number {
        const partsA = a.split('.').map((p) => parseInt(p, 10));
        const partsB = b.split('.').map((p) => parseInt(p, 10));
        for (let i = 0; i < 3; i++) {
            const diff = (partsA[i] ?? 0) - (partsB[i] ?? 0);
            if (diff !== 0) {
                return diff;
            }
        }
        return 0;
    }

    private comparePrerelease(a: string, b: string): number {
        const partsA = a.split('.');
        const partsB = b.split('.');
        const len = Math.max(partsA.length, partsB.length);
        for (let i = 0; i < len; i++) {
            if (partsA[i] === undefined) return -1;
            if (partsB[i] === undefined) return 1;
            const cmp = this.comparePrereleasePart(partsA[i], partsB[i]);
            if (cmp !== 0) return cmp;
        }
        return 0;
    }

    private comparePrereleasePart(a: string, b: string): number {
        const aNum = /^\d+$/.test(a);
        const bNum = /^\d+$/.test(b);
        if (aNum && bNum) return parseInt(a, 10) - parseInt(b, 10);
        if (aNum) return -1;
        if (bNum) return 1;
        return a < b ? -1 : a > b ? 1 : 0;
    }
}
