import {expect, test} from '@playwright/test';

import {AddEditProjectPage} from './pages/add-edit-project.page';
import {BoardPage} from './pages/board.page';
import {ProjectsPage} from './pages/projects.page';
import {TaskDrawerPage} from './pages/task-drawer.page';
import {TasksPage} from './pages/tasks.page';

function iso(d: Date): string {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

test.describe('Calendar view', () => {
    test('places a due-dated task on the right day, opens it, and navigates months', async ({page}) => {
        const projects = new ProjectsPage(page);
        const projectForm = new AddEditProjectPage(page);
        const board = new BoardPage(page);
        const drawer = new TaskDrawerPage(page);
        const tasks = new TasksPage(page);

        const stamp = Date.now();
        const projectName = `Calendar ${stamp}`;
        const taskName = `Cal task ${stamp}`;

        // Due on the 15th of the current month — always inside the default (current-month) grid.
        const now = new Date();
        const dueIso = iso(new Date(now.getFullYear(), now.getMonth(), 15));

        // Seed the task with a due date via the board drawer.
        await projects.goto();
        await projects.gotoNew();
        await projectForm.fillName(projectName);
        await projectForm.submit();
        await projects.openBoard(projectName);
        await board.expectVisible();
        await board.openNewTask();
        await drawer.fillName(taskName);
        await drawer.setDueDate(dueIso);
        await drawer.save();

        // Calendar renders the chip in the cell for the 15th.
        await tasks.goto();
        await tasks.switchView('Calendar');
        await tasks.expectCalendarVisible();
        await expect(tasks.calendarChip(taskName)).toBeVisible();
        await expect(tasks.calendarChipDay(taskName)).toHaveText('15');

        // Clicking the chip opens the drawer for that task with the right due date.
        await tasks.calendarChip(taskName).click();
        await drawer.expectOpen();
        await expect(page.locator('#task-name')).toHaveValue(taskName);
        await expect(page.locator('#task-due-date')).toHaveValue(dueIso);
        await drawer.cancel();

        // Next month hides it; Today brings it back.
        await tasks.calendarNext();
        await expect(tasks.calendarChip(taskName)).toHaveCount(0);
        await tasks.calendarToday();
        await expect(tasks.calendarChip(taskName)).toBeVisible();

        // Cleanup.
        await projects.goto();
        await projects.deleteProject(projectName);
    });
});
