import {Injectable, signal} from '@angular/core';

export type AlertKind = 'success' | 'error' | 'info';

export interface Alert {
    id: number;
    kind: AlertKind;
    message: string;
}

@Injectable({providedIn: 'root'})
export class AlertService {
    private nextId = 1;
    public readonly alerts = signal<Alert[]>([]);

    public success(message: string): void {
        this.push('success', message);
    }

    public error(message: string): void {
        this.push('error', message);
    }

    public info(message: string): void {
        this.push('info', message);
    }

    public dismiss(id: number): void {
        this.alerts.update((all) => all.filter((a) => a.id !== id));
    }

    private push(kind: AlertKind, message: string): void {
        const id = this.nextId++;
        this.alerts.update((all) => [...all, {id, kind, message}]);
        setTimeout(() => this.dismiss(id), kind === 'error' ? 6000 : 3500);
    }
}
