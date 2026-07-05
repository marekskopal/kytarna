import {expect, test} from '@playwright/test';

import {AddEditCoursePage} from './pages/add-edit-course.page';
import {BoardPage} from './pages/board.page';
import {CoursesPage} from './pages/courses.page';
import {LectureDrawerPage} from './pages/lecture-drawer.page';

test.describe('Lecture CRUD', () => {
    test('create a lecture, edit it, move it across statuses, then delete it', async ({page}) => {
        const courses = new CoursesPage(page);
        const courseForm = new AddEditCoursePage(page);
        const board = new BoardPage(page);
        const drawer = new LectureDrawerPage(page);

        const stamp = Date.now();
        const courseName = `Lecture CRUD ${stamp}`;
        const lectureName = `E2E lecture ${stamp}`;
        const editedName = `${lectureName} (edited)`;

        // Seed course
        await courses.goto();
        await courses.gotoNew();
        await courseForm.fillName(courseName);
        await courseForm.submit();
        await courses.openBoard(courseName);
        await board.expectVisible();

        // Create
        await board.openNewLecture();
        await drawer.fillName(lectureName);
        await drawer.fillDescription('Body for the e2e lecture.');
        await drawer.save();
        await board.expectLectureInColumn(lectureName, 'To Do');

        // Edit (rename via the drawer)
        await board.openLecture(lectureName);
        await drawer.fillName(editedName);
        await drawer.save();
        await board.expectLectureInColumn(editedName, 'To Do');

        // Move across statuses by changing the status select in the drawer
        await board.openLecture(editedName);
        await drawer.selectStatus('In Progress');
        await drawer.save();
        await board.expectLectureInColumn(editedName, 'In Progress');

        await board.openLecture(editedName);
        await drawer.selectStatus('Done');
        await drawer.save();
        await board.expectLectureInColumn(editedName, 'Done');

        // Delete
        await board.openLecture(editedName);
        await drawer.delete();
        await board.expectLectureAbsent(editedName);

        // Cleanup course
        await courses.goto();
        await courses.deleteCourse(courseName);
    });
});
