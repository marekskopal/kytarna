import {expect, test} from '@playwright/test';

import {AddEditProjectPage} from './pages/add-edit-project.page';
import {ProjectsPage} from './pages/projects.page';

test.describe('Project CRUD', () => {
    test('owner can create, rename, and delete a project', async ({page}) => {
        const projects = new ProjectsPage(page);
        const form = new AddEditProjectPage(page);

        const stamp = Date.now();
        const original = `E2E Project ${stamp}`;
        const renamed = `${original} (renamed)`;

        // Create
        await projects.goto();
        await projects.gotoNew();
        await form.fillName(original);
        await form.fillDescription('Created by Playwright.');
        await form.submit();
        await projects.expectProjectVisible(original);

        // Rename
        await projects.openEdit(original);
        await form.fillName(renamed);
        await form.submit();
        await projects.expectProjectVisible(renamed);
        await projects.expectProjectAbsent(original);

        // Delete
        await projects.deleteProject(renamed);
        await projects.expectProjectAbsent(renamed);
    });

    test('creating a project seeds the default To Do / In Progress / Done workflow', async ({page}) => {
        const projects = new ProjectsPage(page);
        const form = new AddEditProjectPage(page);

        const stamp = Date.now();
        const name = `Default Workflow ${stamp}`;

        await projects.goto();
        await projects.gotoNew();
        await form.fillName(name);
        await form.submit();

        await projects.openWorkflow(name);
        await expect(page.locator('.status-row')).toHaveCount(3, {timeout: 10_000});
        const statusNames = await page.locator('.status-row input.status-name').evaluateAll(
            (nodes) => nodes.map((n) => (n as HTMLInputElement).value),
        );
        expect(statusNames).toEqual(['To Do', 'In Progress', 'Done']);

        // Cleanup so subsequent runs stay tidy.
        await projects.goto();
        await projects.deleteProject(name);
    });
});
