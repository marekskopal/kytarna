import {ChangeDetectionStrategy, Component, computed, effect, inject, input, OnDestroy, signal} from '@angular/core';
import {Difficulty} from '@app/models/lecture';
import {Song} from '@app/models/song';
import {SongService} from '@app/services/song.service';
import {TranslatePipe} from '@ngx-translate/core';

@Component({
    selector: 'uk-song-card',
    standalone: true,
    imports: [TranslatePipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './song-card.component.html',
    styleUrl: './song-card.component.scss',
})
export class SongCardComponent implements OnDestroy {
    private readonly songService = inject(SongService);

    public readonly song = input.required<Song>();

    protected readonly coverUrl = signal<string | null>(null);

    protected readonly byline = computed<string>(() => {
        const s = this.song();
        return [s.authorName, s.albumName].filter((v) => v !== null && v !== '').join(' · ');
    });

    public constructor() {
        effect((onCleanup) => {
            const s = this.song();
            this.revokeCover();
            if (!s.hasCover) {
                this.coverUrl.set(null);
                return;
            }
            let active = true;
            void this.songService.downloadCover(s.id).then((blob) => {
                if (!active) {
                    return;
                }
                this.coverUrl.set(URL.createObjectURL(blob));
            }).catch(() => {
                if (active) {
                    this.coverUrl.set(null);
                }
            });
            onCleanup(() => { active = false; });
        });
    }

    public ngOnDestroy(): void {
        this.revokeCover();
    }

    private revokeCover(): void {
        const url = this.coverUrl();
        if (url !== null) {
            URL.revokeObjectURL(url);
            this.coverUrl.set(null);
        }
    }

    protected stripMd(s: string): string {
        return s.replace(/[#*_`~>-]/g, '').replace(/\s+/g, ' ').trim();
    }

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
