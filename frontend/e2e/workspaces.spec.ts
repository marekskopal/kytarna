import {expect, test} from '@playwright/test';

import {LayoutPage} from './pages/layout.page';
import {WorkspacesPage} from './pages/workspaces.page';

test.describe('Workspace switching', () => {
    test('user creates a second workspace and the topbar switcher moves between them', async ({page}) => {
        const layout = new LayoutPage(page);
        await page.goto('projects');
        await layout.expectVisible();
        const original = await layout.currentWorkspaceName();

        const stamp = Date.now();
        const secondName = `Side Workspace ${stamp}`;
        await layout.createWorkspace(secondName);
        expect(await layout.currentWorkspaceName()).toBe(secondName);

        // Switch back to the original personal workspace.
        await layout.switchTo(original);
        expect(await layout.currentWorkspaceName()).toBe(original);

        // The /workspaces page should reflect the change.
        const workspaces = new WorkspacesPage(page);
        await workspaces.goto();
        await workspaces.expectCurrent(original);
    });
});
