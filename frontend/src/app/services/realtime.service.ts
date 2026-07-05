import {DestroyRef, effect, inject, Injectable, signal} from '@angular/core';
import {RealtimeEvent} from '@app/models/realtime-event';
import {CurrentUserService} from '@app/services/current-user.service';
import {RealtimeOriginService} from '@app/services/realtime-origin.service';
import {WorkspaceService} from '@app/services/workspace.service';
import {environment} from '@environments/environment';
import {Subject} from 'rxjs';

const TOPIC_PREFIX = 'ukolio/workspaces/';
const USER_TOPIC_PREFIX = 'ukolio/users/';
const RECONNECT_INITIAL_DELAY_MS = 500;
const RECONNECT_MAX_DELAY_MS = 30000;

@Injectable({providedIn: 'root'})
export class RealtimeService {
    private readonly origin = inject(RealtimeOriginService);
    private readonly workspaceService = inject(WorkspaceService);
    private readonly currentUserService = inject(CurrentUserService);
    private readonly destroyRef = inject(DestroyRef);

    private readonly eventSubject = new Subject<RealtimeEvent>();
    public readonly events$ = this.eventSubject.asObservable();
    public readonly connected = signal<boolean>(false);

    private source: EventSource | null = null;
    private currentWorkspaceId: number | null = null;
    private currentUserId: number | null = null;
    private reconnectDelay = RECONNECT_INITIAL_DELAY_MS;
    private reconnectTimer: ReturnType<typeof setTimeout> | null = null;
    private explicitlyClosed = false;

    public constructor() {
        this.destroyRef.onDestroy(() => this.close());

        effect(() => {
            const workspaceId = this.workspaceService.currentWorkspaceId();
            // Track the signed-in user so the connection reopens (and subscribes to their private
            // notification topic) once the user is loaded after login.
            const userId = this.currentUserService.currentUser()?.id ?? null;
            if (workspaceId === null) {
                this.close();
            } else {
                this.open(workspaceId, userId);
            }
        });
    }

    public open(workspaceId: number, userId: number | null = null): void {
        if (this.currentWorkspaceId === workspaceId && this.currentUserId === userId && this.source !== null) {
            return;
        }
        this.closeInternal();
        this.currentWorkspaceId = workspaceId;
        this.currentUserId = userId;
        this.explicitlyClosed = false;
        this.connect();
    }

    public reopen(workspaceId: number): void {
        const userId = this.currentUserId;
        this.close();
        this.open(workspaceId, userId);
    }

    public close(): void {
        this.explicitlyClosed = true;
        this.currentWorkspaceId = null;
        this.closeInternal();
    }

    private closeInternal(): void {
        if (this.reconnectTimer !== null) {
            clearTimeout(this.reconnectTimer);
            this.reconnectTimer = null;
        }
        if (this.source !== null) {
            this.source.close();
            this.source = null;
        }
        this.connected.set(false);
    }

    private connect(): void {
        const workspaceId = this.currentWorkspaceId;
        if (workspaceId === null) {
            return;
        }

        const topics = [`${TOPIC_PREFIX}${workspaceId}`];
        if (this.currentUserId !== null) {
            topics.push(`${USER_TOPIC_PREFIX}${this.currentUserId}`);
        }
        const query = topics.map((topic) => `topic=${encodeURIComponent(topic)}`).join('&');
        const url = `${environment.mercureHubUrl}?${query}`;

        const source = new EventSource(url, {withCredentials: true});
        this.source = source;

        source.onopen = (): void => {
            this.connected.set(true);
            if (this.reconnectDelay !== RECONNECT_INITIAL_DELAY_MS) {
                // Coming back from a drop — let consumers refetch full state.
                this.eventSubject.next(this.reconnectedEvent(workspaceId));
            }
            this.reconnectDelay = RECONNECT_INITIAL_DELAY_MS;
        };

        source.onmessage = (event: MessageEvent<string>): void => {
            const parsed = this.parsePayload(event.data);
            if (parsed === null) {
                return;
            }
            if (parsed.originClientId !== null && parsed.originClientId === this.origin.id) {
                return;
            }
            this.eventSubject.next(parsed);
        };

        source.onerror = (): void => {
            // EventSource normally retries on its own, but our subscriber JWT cookie can expire and
            // Mercure will keep returning 401s. Close + reconnect with backoff to give time for the
            // cookie to be refreshed by the next REST call.
            if (this.explicitlyClosed) {
                return;
            }
            this.source = null;
            this.connected.set(false);
            source.close();
            this.scheduleReconnect();
        };
    }

    private scheduleReconnect(): void {
        if (this.reconnectTimer !== null) {
            return;
        }
        const delay = this.reconnectDelay;
        this.reconnectTimer = setTimeout(() => {
            this.reconnectTimer = null;
            if (this.explicitlyClosed || this.currentWorkspaceId === null) {
                return;
            }
            this.reconnectDelay = Math.min(this.reconnectDelay * 2, RECONNECT_MAX_DELAY_MS);
            this.connect();
        }, delay);
    }

    private parsePayload(data: string): RealtimeEvent | null {
        try {
            const obj = JSON.parse(data) as Partial<RealtimeEvent>;
            if (typeof obj.type !== 'string' || typeof obj.workspaceId !== 'number') {
                return null;
            }
            return {
                type: obj.type as RealtimeEvent['type'],
                workspaceId: obj.workspaceId,
                projectId: typeof obj.projectId === 'number' ? obj.projectId : null,
                taskId: typeof obj.taskId === 'number' ? obj.taskId : null,
                commentId: typeof obj.commentId === 'number' ? obj.commentId : null,
                fileId: typeof obj.fileId === 'number' ? obj.fileId : null,
                relationId: typeof obj.relationId === 'number' ? obj.relationId : null,
                userId: typeof obj.userId === 'number' ? obj.userId : null,
                originClientId: typeof obj.originClientId === 'string' ? obj.originClientId : null,
            };
        } catch {
            return null;
        }
    }

    private reconnectedEvent(workspaceId: number): RealtimeEvent {
        return {
            type: 'RealtimeReconnected',
            workspaceId,
            projectId: null,
            taskId: null,
            commentId: null,
            fileId: null,
            relationId: null,
            userId: null,
            originClientId: null,
        };
    }
}
