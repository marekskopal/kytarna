import {test} from '@playwright/test';

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
});
