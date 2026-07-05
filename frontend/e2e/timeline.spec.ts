import {expect, test} from '@playwright/test';

import {AddEditProjectPage} from './pages/add-edit-project.page';
import {BoardPage} from './pages/board.page';
import {ProjectsPage} from './pages/projects.page';
import {TaskDrawerPage} from './pages/task-drawer.page';
import {TasksPage} from './pages/tasks.page';

function iso(d: Date): string {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

test.describe('Timeline view', () => {
    test('renders a project-grouped bar and resizing its end extends the due date', async ({page}) => {
        const projects = new ProjectsPage(page);
        const projectForm = new AddEditProjectPage(page);
        const board = new BoardPage(page);
        const drawer = new TaskDrawerPage(page);
        const tasks = new TasksPage(page);

        const stamp = Date.now();
        const projectName = `Timeline ${stamp}`;
        const taskName = `TL task ${stamp}`;

        // A short span inside the current week — comfortably within the default 5-week window.
        const now = new Date();
        const startIso = iso(now);
        const due = new Date(now);
        due.setDate(due.getDate() + 2);
        const dueIso = iso(due);

        await projects.goto();
        await projects.gotoNew();
        await projectForm.fillName(projectName);
        await projectForm.submit();
        await projects.openBoard(projectName);
        await board.expectVisible();
        await board.openNewTask();
        await drawer.fillName(taskName);
        await drawer.setStartDate(startIso);
        await drawer.setDueDate(dueIso);
        await drawer.save();

        // Timeline groups the bar under its project.
        await tasks.goto();
        await tasks.switchView('Timeline');
        await tasks.expectTimelineVisible();
        await expect(tasks.timelineGroup(projectName)).toBeVisible();
        const bar = tasks.timelineBar(taskName);
        await expect(bar).toBeVisible();

        // Drag the right edge rightwards by ~3 days → due date should advance, start unchanged.
        const box = await bar.boundingBox();
        const track = await tasks.timelineTrack().boundingBox();
        if (box === null || track === null) {
            throw new Error('Timeline bar/track not measurable');
        }
        const dayWidth = track.width / 35;
        const edgeX = box.x + box.width - 2;
        const midY = box.y + box.height / 2;
        await page.mouse.move(edgeX, midY);
        await page.mouse.down();
        await page.mouse.move(edgeX + dayWidth * 4, midY, {steps: 15});
        // The bar persists asynchronously on pointer-up — wait for the PUT before reading the result.
        const [putResponse] = await Promise.all([
            page.waitForResponse((r) => r.request().method() === 'PUT' && /\/api\/tasks\/\d+$/.test(r.url())),
            page.mouse.up(),
        ]);
        expect(putResponse.ok()).toBeTruthy();

        // Confirm via the List view drawer.
        await tasks.switchView('List');
        await tasks.openGridRow(taskName);
        await drawer.expectOpen();
        const newDue = await drawer.dueDateValue();
        expect(newDue > dueIso).toBeTruthy();
        expect(await drawer.startDateValue()).toBe(startIso);
        await drawer.cancel();

        // Cleanup.
        await projects.goto();
        await projects.deleteProject(projectName);
    });
});
