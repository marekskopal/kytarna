import {HttpClient} from '@angular/common/http';
import {inject, Injectable} from '@angular/core';
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

    public listTabs(lectureId: number | string): Promise<Tab[]> {
        return firstValueFrom(this.http.get<Tab[]>(`${environment.apiUrl}/lectures/${lectureId}/tabs`));
    }

    public getTab(tabId: number): Promise<Tab> {
        return firstValueFrom(this.http.get<Tab>(`${environment.apiUrl}/tabs/${tabId}`));
    }

    public createTab(lectureId: number | string, payload: TabWritePayload): Promise<Tab> {
        return firstValueFrom(this.http.post<Tab>(`${environment.apiUrl}/lectures/${lectureId}/tabs`, payload));
    }

    public updateTab(tabId: number, payload: TabWritePayload): Promise<Tab> {
        return firstValueFrom(this.http.put<Tab>(`${environment.apiUrl}/tabs/${tabId}`, payload));
    }

    public deleteTab(tabId: number): Promise<void> {
        return firstValueFrom(this.http.delete<void>(`${environment.apiUrl}/tabs/${tabId}`));
    }

    public importGpFile(lectureId: number | string, file: File, name?: string): Promise<Tab> {
        const data = new FormData();
        data.append('file', file, file.name);
        if (name !== undefined && name !== '') {
            data.append('name', name);
        }
        return firstValueFrom(this.http.post<Tab>(`${environment.apiUrl}/lectures/${lectureId}/tabs/import`, data));
    }
}
