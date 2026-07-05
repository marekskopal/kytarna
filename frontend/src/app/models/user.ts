export type Locale = 'en' | 'cs';

export type Theme = 'system' | 'light' | 'dark';

export type SystemRole = 'User' | 'SystemAdmin';

export interface User {
    id: number;
    email: string;
    name: string;
    locale: Locale;
    theme: Theme;
    currentWorkspaceId: number | null;
    defaultSavedViewId: number | null;
    systemRole: SystemRole;
    emailVerified: boolean;
    onboardingCompletedAt: string | null;
}
