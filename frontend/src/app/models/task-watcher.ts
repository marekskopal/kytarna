export interface TaskWatcher {
    userId: number;
    userName: string;
}

export interface TaskWatchers {
    watchers: TaskWatcher[];
    watching: boolean;
}
