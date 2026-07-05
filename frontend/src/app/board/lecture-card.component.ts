import {ChangeDetectionStrategy, Component, computed, input} from '@angular/core';
import {Difficulty, Lecture} from '@app/models/lecture';
import {Status} from '@app/models/status';
import {Tag} from '@app/models/tag';
import {TranslatePipe} from '@ngx-translate/core';

const MAX_VISIBLE_TAGS = 3;

@Component({
    selector: 'uk-lecture-card',
    standalone: true,
    imports: [TranslatePipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './lecture-card.component.html',
    styleUrl: './lecture-card.component.scss',
})
export class LectureCardComponent {
    public readonly lecture = input.required<Lecture>();
    public readonly status = input<Status | null>(null);
    public readonly workspaceTags = input<Tag[]>([]);

    // A lecture is "mastered" when it sits in a workflow Finish-type status.
    protected readonly isMastered = computed<boolean>(() => this.status()?.type === 'Finish');

    protected readonly visibleTags = computed<Tag[]>(() => this.lectureTags().slice(0, MAX_VISIBLE_TAGS));

    protected readonly hiddenTagCount = computed<number>(() => Math.max(0, this.lectureTags().length - MAX_VISIBLE_TAGS));

    private readonly lectureTags = computed<Tag[]>(() => {
        const ids = new Set(this.lecture().tagIds ?? []);
        if (ids.size === 0) {
            return [];
        }
        const byId = new Map(this.workspaceTags().map((t) => [t.id, t]));
        const result: Tag[] = [];
        for (const id of ids) {
            const tag = byId.get(id);
            if (tag) result.push(tag);
        }
        return result;
    });

    protected stripMd(s: string): string {
        return s.replace(/[#*_`~>-]/g, '').replace(/\s+/g, ' ').trim();
    }

    // Difficulty hue rule (theme-aware tokens):
    //   Advanced → accent · Intermediate → gold/warn · Beginner → olive/success
    protected difficultyColor(difficulty: Difficulty): string {
        switch (difficulty) {
            case 'Advanced':
                return 'var(--color-accent)';
            case 'Intermediate':
                return 'var(--color-warn)';
            default:
                return 'var(--color-success)';
        }
    }
}
