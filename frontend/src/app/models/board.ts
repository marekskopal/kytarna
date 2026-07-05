import {Project} from './project';
import {Status} from './status';
import {Task} from './task';
import {Workflow} from './workflow';

export interface Board {
    project: Project;
    workflow: Workflow;
    statuses: Status[];
    tasks: Task[];
}
