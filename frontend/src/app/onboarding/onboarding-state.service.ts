import {Injectable, signal} from '@angular/core';

export type WorkflowTemplate = 'kanban' | 'engineering' | 'simple' | 'blank';

export interface TemplateColumn {
    name: string;
    color: string;
    type: 'Start' | 'Normal' | 'Finish';
}

export const WORKFLOW_TEMPLATES: Record<WorkflowTemplate, TemplateColumn[]> = {
    kanban: [
        {name: 'To Do', color: '#94a3a8', type: 'Start'},
        {name: 'In Progress', color: '#c98a14', type: 'Normal'},
        {name: 'In Review', color: '#4a8fd6', type: 'Normal'},
        {name: 'Done', color: '#16794a', type: 'Finish'},
    ],
    engineering: [
        {name: 'Backlog', color: '#94a3a8', type: 'Start'},
        {name: 'Spec', color: '#6f4ed3', type: 'Normal'},
        {name: 'Building', color: '#c98a14', type: 'Normal'},
        {name: 'Review', color: '#4a8fd6', type: 'Normal'},
        {name: 'Shipped', color: '#16794a', type: 'Finish'},
    ],
    simple: [
        {name: 'Open', color: '#94a3a8', type: 'Start'},
        {name: 'Done', color: '#16794a', type: 'Finish'},
    ],
    blank: [
        {name: 'To Do', color: '#94a3a8', type: 'Start'},
        {name: 'Done', color: '#16794a', type: 'Finish'},
    ],
};

@Injectable({providedIn: 'root'})
export class OnboardingStateService {
    public readonly projectId = signal<number | null>(null);
    public readonly projectName = signal<string>('');
    public readonly invitesSent = signal<number>(0);

    public reset(): void {
        this.projectId.set(null);
        this.projectName.set('');
        this.invitesSent.set(0);
    }
}
