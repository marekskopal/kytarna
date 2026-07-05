import {expect, test} from '@playwright/test';

import {AddEditCoursePage} from './pages/add-edit-course.page';
import {CoursesPage} from './pages/courses.page';
import {WorkflowPage} from './pages/workflow.page';

test.describe('Workflow status CRUD', () => {
    test('add, rename, recolour, and delete a workflow status', async ({page}) => {
        const courses = new CoursesPage(page);
        const courseForm = new AddEditCoursePage(page);
        const workflow = new WorkflowPage(page);

        const stamp = Date.now();
        const courseName = `Workflow CRUD ${stamp}`;

        // Seed a course so we have a workflow to mutate.
        await courses.goto();
        await courses.gotoNew();
        await courseForm.fillName(courseName);
        await courseForm.submit();
        await courses.openWorkflow(courseName);
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

        // Cleanup: delete course
        await courses.goto();
        await courses.deleteCourse(courseName);
    });
});
