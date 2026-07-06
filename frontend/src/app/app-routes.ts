import {Routes} from '@angular/router';
import {AuthGuard} from '@app/core/guards/auth.guard';
import {GuestGuard} from '@app/core/guards/guest.guard';
import {OnboardingGuard} from '@app/core/guards/onboarding.guard';
import {SystemAdminGuard} from '@app/core/guards/system-admin.guard';

export const appRoutes: Routes = [
    {
        path: 'login',
        canActivate: [GuestGuard],
        loadComponent: () => import('@app/authentication/login.component').then((m) => m.LoginComponent),
    },
    {
        path: 'sign-up',
        loadComponent: () => import('@app/authentication/sign-up.component').then((m) => m.SignUpComponent),
    },
    {
        path: 'forgot-password',
        loadComponent: () => import('@app/authentication/forgot-password.component').then((m) => m.ForgotPasswordComponent),
    },
    {
        path: 'reset-password',
        loadComponent: () => import('@app/authentication/reset-password.component').then((m) => m.ResetPasswordComponent),
    },
    {
        path: 'verify-email',
        loadComponent: () => import('@app/authentication/verify-email.component').then((m) => m.VerifyEmailComponent),
    },
    {
        path: 'invitations/accept',
        loadComponent: () => import('@app/invitations/accept-invitation.component').then((m) => m.AcceptInvitationComponent),
    },
    {
        path: 'oauth/authorize',
        loadComponent: () => import('@app/oauth/oauth-authorize.component').then((m) => m.OAuthAuthorizeComponent),
    },
    {
        path: 'onboarding',
        canActivate: [AuthGuard, OnboardingGuard],
        loadComponent: () => import('@app/onboarding/onboarding-shell.component').then((m) => m.OnboardingShellComponent),
        children: [
            {path: '', redirectTo: 'step-1', pathMatch: 'full'},
            {
                path: 'step-1',
                loadComponent: () => import('@app/onboarding/step-1.component').then((m) => m.OnboardingStep1Component),
            },
            {
                path: 'step-2',
                loadComponent: () => import('@app/onboarding/step-2.component').then((m) => m.OnboardingStep2Component),
            },
            {
                path: 'step-3',
                loadComponent: () => import('@app/onboarding/step-3.component').then((m) => m.OnboardingStep3Component),
            },
        ],
    },
    {
        path: '',
        canActivate: [AuthGuard],
        loadComponent: () => import('@app/shared/components/layout/layout.component').then((m) => m.LayoutComponent),
        children: [
            {path: '', redirectTo: 'courses', pathMatch: 'full'},
            {
                path: 'courses',
                loadComponent: () => import('@app/courses/courses.component').then((m) => m.CoursesComponent),
            },
            {
                path: 'lectures',
                loadComponent: () => import('@app/lectures/lectures-grid.component').then((m) => m.LecturesGridComponent),
            },
            {
                path: 'songs',
                loadComponent: () => import('@app/songs/songs-grid.component').then((m) => m.SongsGridComponent),
            },
            {
                path: 'songs/new',
                loadComponent: () => import('@app/songs/song-page.component').then((m) => m.SongPageComponent),
            },
            {
                path: 'songs/:id',
                loadComponent: () => import('@app/songs/song-page.component').then((m) => m.SongPageComponent),
            },
            {
                path: 'agents',
                loadComponent: () => import('@app/agents/agents.component').then((m) => m.AgentsComponent),
            },
            {
                path: 'courses/new',
                loadComponent: () => import('@app/courses/add-edit-course.component').then((m) => m.AddEditCourseComponent),
            },
            {
                path: 'courses/:id/edit',
                loadComponent: () => import('@app/courses/add-edit-course.component').then((m) => m.AddEditCourseComponent),
            },
            {
                path: 'courses/:id/board',
                loadComponent: () => import('@app/board/board.component').then((m) => m.BoardComponent),
            },
            {
                path: 'courses/:id/lectures/new',
                loadComponent: () => import('@app/board/lecture-page.component').then((m) => m.LecturePageComponent),
            },
            {
                path: 'courses/:id/lectures/:lectureId',
                loadComponent: () => import('@app/board/lecture-page.component').then((m) => m.LecturePageComponent),
            },
            {
                path: 'courses/:id/events',
                loadComponent: () => import('@app/events/events.component').then((m) => m.EventsComponent),
            },
            {
                path: 'workspaces',
                loadComponent: () => import('@app/workspaces/workspaces.component').then((m) => m.WorkspacesComponent),
            },
            {
                path: 'settings',
                loadComponent: () => import('@app/settings/settings.component').then((m) => m.SettingsComponent),
            },
            {
                path: 'admin',
                canActivate: [SystemAdminGuard],
                children: [
                    {path: '', redirectTo: 'users', pathMatch: 'full'},
                    {
                        path: 'users',
                        loadComponent: () => import('@app/admin/admin-users.component').then((m) => m.AdminUsersComponent),
                    },
                    {
                        path: 'workspaces',
                        loadComponent: () => import('@app/admin/admin-workspaces.component').then((m) => m.AdminWorkspacesComponent),
                    },
                ],
            },
        ],
    },
    {path: '**', redirectTo: ''},
];
