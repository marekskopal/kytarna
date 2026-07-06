export type LearningStatus = 'ToLearn' | 'Learning' | 'Mastered';

/** Fixed, ordered list of learning statuses (matches the board column order). */
export const LEARNING_STATUSES: readonly LearningStatus[] = ['ToLearn', 'Learning', 'Mastered'];

/** i18n key for a status label (see `app.status.*`). */
export function statusLabelKey(status: LearningStatus): string {
    switch (status) {
        case 'Learning':
            return 'app.status.learning';
        case 'Mastered':
            return 'app.status.mastered';
        case 'ToLearn':
        default:
            return 'app.status.toLearn';
    }
}

/** Theme-aware CSS color token for a status dot/pill (flips in dark mode). */
export function statusColorVar(status: LearningStatus): string {
    switch (status) {
        case 'Learning':
            return 'var(--color-status-doing)';
        case 'Mastered':
            return 'var(--color-status-done)';
        case 'ToLearn':
        default:
            return 'var(--color-status-todo)';
    }
}
