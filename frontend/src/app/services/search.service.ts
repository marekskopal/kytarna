import {HttpClient, HttpParams} from '@angular/common/http';
import {inject, Injectable} from '@angular/core';
import {SearchParams, SearchResult} from '@app/models/search';
import {environment} from '@environments/environment';
import {firstValueFrom} from 'rxjs';

@Injectable({providedIn: 'root'})
export class SearchService {
    private readonly http = inject(HttpClient);

    public search(params: SearchParams): Promise<SearchResult> {
        let httpParams = new HttpParams().set('q', params.q);
        if (params.limit !== undefined) {
            httpParams = httpParams.set('limit', params.limit);
        }
        if (params.offset !== undefined) {
            httpParams = httpParams.set('offset', params.offset);
        }
        if (params.projectId !== undefined) {
            httpParams = httpParams.set('projectId', params.projectId);
        }
        if (params.statusIds && params.statusIds.length > 0) {
            httpParams = httpParams.set('statusIds', params.statusIds.join('|'));
        }
        if (params.onlyActive) {
            httpParams = httpParams.set('onlyActive', '1');
        }
        return firstValueFrom(this.http.get<SearchResult>(`${environment.apiUrl}/search`, {params: httpParams}));
    }
}
