import {expect, test} from '@playwright/test';

import {AddEditProjectPage} from './pages/add-edit-project.page';
import {BoardPage} from './pages/board.page';
import {ProjectsPage} from './pages/projects.page';
import {TaskDrawerPage} from './pages/task-drawer.page';

test.describe('Task CRUD', () => {
    test('create a task, edit it, move it across statuses, then delete it', async ({page}) => {
        const projects = new ProjectsPage(page);
        const projectForm = new AddEditProjectPage(page);
        const board = new BoardPage(page);
        const drawer = new TaskDrawerPage(page);

        const stamp = Date.now();
        const projectName = `Task CRUD ${stamp}`;
        const taskName = `E2E task ${stamp}`;
        const editedName = `${taskName} (edited)`;

        // Seed project
        await projects.goto();
        await projects.gotoNew();
        await projectForm.fillName(projectName);
        await projectForm.submit();
        await projects.openBoard(projectName);
        await board.expectVisible();

        // Create
        await board.openNewTask();
        await drawer.fillName(taskName);
        await drawer.fillDescription('Body for the e2e task.');
        await drawer.save();
        await board.expectTaskInColumn(taskName, 'To Do');

        // Edit (rename via the drawer)
        await board.openTask(taskName);
        await drawer.fillName(editedName);
        await drawer.save();
        await board.expectTaskInColumn(editedName, 'To Do');

        // Move across statuses by changing the status select in the drawer
        await board.openTask(editedName);
        await drawer.selectStatus('In Progress');
        await drawer.save();
        await board.expectTaskInColumn(editedName, 'In Progress');

        await board.openTask(editedName);
        await drawer.selectStatus('Done');
        await drawer.save();
        await board.expectTaskInColumn(editedName, 'Done');

        // Delete
        await board.openTask(editedName);
        await drawer.delete();
        await board.expectTaskAbsent(editedName);

        // Cleanup project
        await projects.goto();
        await projects.deleteProject(projectName);
    });
});
