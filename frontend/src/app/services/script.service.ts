import {HttpClient} from '@angular/common/http';
import {inject, Injectable} from '@angular/core';
import {Script, ScriptRun, ScriptVariable, ScriptVariablePayload, ScriptWritePayload} from '@app/models/script';
import {environment} from '@environments/environment';
import {firstValueFrom} from 'rxjs';

@Injectable({providedIn: 'root'})
export class ScriptService {
    private readonly http = inject(HttpClient);

    public listScripts(workspaceId: number): Promise<Script[]> {
        return firstValueFrom(this.http.get<Script[]>(`${environment.apiUrl}/workspaces/${workspaceId}/scripts`));
    }

    public getScript(scriptId: number): Promise<Script> {
        return firstValueFrom(this.http.get<Script>(`${environment.apiUrl}/scripts/${scriptId}`));
    }

    public createScript(workspaceId: number, payload: ScriptWritePayload): Promise<Script> {
        return firstValueFrom(this.http.post<Script>(`${environment.apiUrl}/workspaces/${workspaceId}/scripts`, payload));
    }

    public updateScript(scriptId: number, payload: ScriptWritePayload): Promise<Script> {
        return firstValueFrom(this.http.put<Script>(`${environment.apiUrl}/scripts/${scriptId}`, payload));
    }

    public deleteScript(scriptId: number): Promise<void> {
        return firstValueFrom(this.http.delete<void>(`${environment.apiUrl}/scripts/${scriptId}`));
    }

    public runScript(scriptId: number): Promise<{queued: boolean}> {
        return firstValueFrom(this.http.post<{queued: boolean}>(`${environment.apiUrl}/scripts/${scriptId}/run`, {}));
    }

    public listRuns(scriptId: number, limit = 25, offset = 0): Promise<ScriptRun[]> {
        return firstValueFrom(
            this.http.get<ScriptRun[]>(`${environment.apiUrl}/scripts/${scriptId}/runs?limit=${limit}&offset=${offset}`),
        );
    }

    public listVariables(workspaceId: number): Promise<ScriptVariable[]> {
        return firstValueFrom(this.http.get<ScriptVariable[]>(`${environment.apiUrl}/workspaces/${workspaceId}/script-variables`));
    }

    public upsertVariable(workspaceId: number, payload: ScriptVariablePayload): Promise<ScriptVariable> {
        return firstValueFrom(
            this.http.put<ScriptVariable>(`${environment.apiUrl}/workspaces/${workspaceId}/script-variables`, payload),
        );
    }

    public deleteVariable(workspaceId: number, variableId: number): Promise<void> {
        return firstValueFrom(
            this.http.delete<void>(`${environment.apiUrl}/workspaces/${workspaceId}/script-variables/${variableId}`),
        );
    }
}
