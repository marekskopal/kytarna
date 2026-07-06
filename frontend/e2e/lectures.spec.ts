import {test} from '@playwright/test';

import {AddEditCoursePage} from './pages/add-edit-course.page';
import {BoardPage} from './pages/board.page';
import {CoursesPage} from './pages/courses.page';
import {LecturePage} from './pages/lecture-page.page';

test.describe('Lecture CRUD', () => {
    test('create a lecture, edit it, move it across statuses, then delete it', async ({page}) => {
        const courses = new CoursesPage(page);
        const courseForm = new AddEditCoursePage(page);
        const board = new BoardPage(page);
        const lecture = new LecturePage(page);

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

        // Create — new lectures default to the "To Learn" status.
        await board.openNewLecture();
        await lecture.fillName(lectureName);
        await lecture.fillDescription('Body for the e2e lecture.');
        await lecture.save();
        await lecture.backToBoard();
        await board.expectLectureInColumn(lectureName, 'To Learn');

        // Edit (rename on the lecture page)
        await board.openLecture(lectureName);
        await lecture.fillName(editedName);
        await lecture.save();
        await lecture.backToBoard();
        await board.expectLectureInColumn(editedName, 'To Learn');

        // Move across statuses via the status radiogroup
        await board.openLecture(editedName);
        await lecture.selectStatus('Learning');
        await lecture.save();
        await lecture.backToBoard();
        await board.expectLectureInColumn(editedName, 'Learning');

        await board.openLecture(editedName);
        await lecture.selectStatus('Mastered');
        await lecture.save();
        await lecture.backToBoard();
        await board.expectLectureInColumn(editedName, 'Mastered');

        // Delete (returns to the board)
        await board.openLecture(editedName);
        await lecture.delete();
        await board.expectLectureAbsent(editedName);

        // Cleanup course
        await courses.goto();
        await courses.deleteCourse(courseName);
    });
});
