import {expect, test} from '@playwright/test';

import {AddEditCoursePage} from './pages/add-edit-course.page';
import {CoursesPage} from './pages/courses.page';

test.describe('Course CRUD', () => {
    test('owner can create, rename, and delete a course', async ({page}) => {
        const courses = new CoursesPage(page);
        const form = new AddEditCoursePage(page);

        const stamp = Date.now();
        const original = `E2E Course ${stamp}`;
        const renamed = `${original} (renamed)`;

        // Create
        await courses.goto();
        await courses.gotoNew();
        await form.fillName(original);
        await form.fillDescription('Created by Playwright.');
        await form.submit();
        await courses.expectCourseVisible(original);

        // Rename
        await courses.openEdit(original);
        await form.fillName(renamed);
        await form.submit();
        await courses.expectCourseVisible(renamed);
        await courses.expectCourseAbsent(original);

        // Delete
        await courses.deleteCourse(renamed);
        await courses.expectCourseAbsent(renamed);
    });

    test('creating a course seeds the default To Do / In Progress / Done workflow', async ({page}) => {
        const courses = new CoursesPage(page);
        const form = new AddEditCoursePage(page);

        const stamp = Date.now();
        const name = `Default Workflow ${stamp}`;

        await courses.goto();
        await courses.gotoNew();
        await form.fillName(name);
        await form.submit();

        await courses.openWorkflow(name);
        await expect(page.locator('.status-row')).toHaveCount(3, {timeout: 10_000});
        const statusNames = await page.locator('.status-row input.status-name').evaluateAll(
            (nodes) => nodes.map((n) => (n as HTMLInputElement).value),
        );
        expect(statusNames).toEqual(['To Do', 'In Progress', 'Done']);

        // Cleanup so subsequent runs stay tidy.
        await courses.goto();
        await courses.deleteCourse(name);
    });
});
