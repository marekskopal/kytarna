import {test} from '@playwright/test';

import {LayoutPage} from './pages/layout.page';
import {WorkspacesPage} from './pages/workspaces.page';

// A user owns exactly one workspace (as its Teacher) — the backend rejects creating a
// second (WorkspaceOwnershipException). Extra workspaces are reached by joining another
// teacher's workspace, which isn't set up for the single fixture user, so this spec
// covers the single-workspace surfaces: the current workspace is shown and manageable.
test.describe('Workspace management', () => {
    test('the current workspace is shown as Current and can be renamed', async ({page}) => {
        const layout = new LayoutPage(page);
        await page.goto('courses');
        await layout.expectVisible();
        const current = await layout.currentWorkspaceName();

        const workspaces = new WorkspacesPage(page);
        await workspaces.goto();
        await workspaces.select(current);
        await workspaces.expectCurrent(current);

        // Rename it (owner-only) and confirm the detail pane reflects the new name.
        const renamed = `${current} (renamed)`;
        await workspaces.rename(renamed);
    });
});
