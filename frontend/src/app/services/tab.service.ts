import {HttpClient} from '@angular/common/http';
import {inject, Injectable} from '@angular/core';
import {PracticeParent} from '@app/models/practice-parent';
import {Tab} from '@app/models/tab';
import {environment} from '@environments/environment';
import {firstValueFrom} from 'rxjs';

export interface TabWritePayload {
    name: string;
    alphaTex: string;
}

@Injectable({providedIn: 'root'})
export class TabService {
    private readonly http = inject(HttpClient);

    public listTabs(parentId: number | string, parent: PracticeParent = 'lectures'): Promise<Tab[]> {
        return firstValueFrom(this.http.get<Tab[]>(`${environment.apiUrl}/${parent}/${parentId}/tabs`));
    }

    public getTab(tabId: number, parent: PracticeParent = 'lectures'): Promise<Tab> {
        return firstValueFrom(this.http.get<Tab>(`${environment.apiUrl}/${this.itemBase(parent)}/${tabId}`));
    }

    public createTab(parentId: number | string, payload: TabWritePayload, parent: PracticeParent = 'lectures'): Promise<Tab> {
        return firstValueFrom(this.http.post<Tab>(`${environment.apiUrl}/${parent}/${parentId}/tabs`, payload));
    }

    public updateTab(tabId: number, payload: TabWritePayload, parent: PracticeParent = 'lectures'): Promise<Tab> {
        return firstValueFrom(this.http.put<Tab>(`${environment.apiUrl}/${this.itemBase(parent)}/${tabId}`, payload));
    }

    public deleteTab(tabId: number, parent: PracticeParent = 'lectures'): Promise<void> {
        return firstValueFrom(this.http.delete<void>(`${environment.apiUrl}/${this.itemBase(parent)}/${tabId}`));
    }

    public importGpFile(parentId: number | string, file: File, name?: string, parent: PracticeParent = 'lectures'): Promise<Tab> {
        const data = new FormData();
        data.append('file', file, file.name);
        if (name !== undefined && name !== '') {
            data.append('name', name);
        }
        return firstValueFrom(this.http.post<Tab>(`${environment.apiUrl}/${parent}/${parentId}/tabs/import`, data));
    }

    /** Item routes diverge: lectures use `/tabs/{id}`, songs use `/song-tabs/{id}`. */
    private itemBase(parent: PracticeParent): string {
        return parent === 'songs' ? 'song-tabs' : 'tabs';
    }
}
