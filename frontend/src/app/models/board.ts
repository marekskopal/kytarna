import {Course} from './course';
import {Lecture} from './lecture';
import {Status} from './status';
import {Workflow} from './workflow';

export interface Board {
    course: Course;
    workflow: Workflow;
    statuses: Status[];
    lectures: Lecture[];
}
