export interface LectureWatcher {
    userId: number;
    userName: string;
}

export interface LectureWatchers {
    watchers: LectureWatcher[];
    watching: boolean;
}
