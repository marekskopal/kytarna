import {Injectable} from '@angular/core';

@Injectable({providedIn: 'root'})
export class RealtimeOriginService {
    public readonly id = crypto.randomUUID();
}
