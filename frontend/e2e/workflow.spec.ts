import {expect, test} from '@playwright/test';

import {AddEditProjectPage} from './pages/add-edit-project.page';
import {ProjectsPage} from './pages/projects.page';
import {WorkflowPage} from './pages/workflow.page';

test.describe('Workflow status CRUD', () => {
    test('add, rename, recolour, and delete a workflow status', async ({page}) => {
        const projects = new ProjectsPage(page);
        const projectForm = new AddEditProjectPage(page);
        const workflow = new WorkflowPage(page);

        const stamp = Date.now();
        const projectName = `Workflow CRUD ${stamp}`;

        // Seed a project so we have a workflow to mutate.
        await projects.goto();
        await projects.gotoNew();
        await projectForm.fillName(projectName);
        await projectForm.submit();
        await projects.openWorkflow(projectName);
        await workflow.expectVisible();

        const baselineCount = await workflow.statusCount();
        expect(baselineCount).toBe(3);

        // Add
        const newStatusName = `In Review ${stamp}`;
        await workflow.addStatus(newStatusName);
        expect(await workflow.statusCount()).toBe(baselineCount + 1);
        expect(await workflow.statusNameAt(baselineCount)).toBe(newStatusName);

        // Rename (on the newly added row at the end)
        const renamed = `${newStatusName} – Edited`;
        await workflow.renameStatusAt(baselineCount, renamed);
        expect(await workflow.statusNameAt(baselineCount)).toBe(renamed);

        // Recolour
        await workflow.changeColorAt(baselineCount, '#ff8800');

        // Delete
        await workflow.deleteStatusAt(baselineCount);
        expect(await workflow.statusCount()).toBe(baselineCount);

        // Cleanup: delete project
        await projects.goto();
        await projects.deleteProject(projectName);
    });
});
