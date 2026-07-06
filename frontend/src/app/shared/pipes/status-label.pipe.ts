import {Pipe, PipeTransform} from '@angular/core';
import {LearningStatus, statusLabelKey} from '@app/models/status';

/**
 * Maps a {@link LearningStatus} to its `app.status.*` i18n key. Chain with the
 * ngx-translate pipe so the label stays locale-reactive: `status | statusLabel | translate`.
 */
@Pipe({name: 'statusLabel', standalone: true})
export class StatusLabelPipe implements PipeTransform {
    public transform(value: LearningStatus): string {
        return statusLabelKey(value);
    }
}
