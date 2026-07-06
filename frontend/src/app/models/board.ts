import {Course} from './course';
import {Lecture} from './lecture';
import {Song} from './song';
import {LearningStatus} from './status';

export interface Board {
    course: Course;
    statuses: LearningStatus[];
    lectures: Lecture[];
    songs: Song[];
}
