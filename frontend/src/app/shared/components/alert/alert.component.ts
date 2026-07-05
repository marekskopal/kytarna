import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {AlertService} from '@app/services/alert.service';

@Component({
    selector: 'uk-alert',
    standalone: true,
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './alert.component.html',
    styleUrl: './alert.component.scss',
})
export class AlertComponent {
    protected readonly alertService = inject(AlertService);
}
