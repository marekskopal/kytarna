import {ComponentFixture, TestBed} from '@angular/core/testing';
import {AlphaTabService} from '@app/services/alphatab.service';
import {commonTestProviders, provideTranslateStub} from '@app/testing/test-providers';
import {beforeEach, describe, expect, it, vi} from 'vitest';

import {TabViewerComponent} from './tab-viewer.component';

describe('TabViewerComponent', () => {
    let fixture: ComponentFixture<TabViewerComponent>;
    let render: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        render = vi.fn().mockResolvedValue({destroy: vi.fn()});
        TestBed.resetTestingModule();
        TestBed.configureTestingModule({
            imports: [TabViewerComponent],
            providers: [
                ...commonTestProviders(),
                provideTranslateStub(),
                {provide: AlphaTabService, useValue: {render}},
            ],
        });
        fixture = TestBed.createComponent(TabViewerComponent);
    });

    it('renders the alphaTex through the wrapper service', async () => {
        fixture.componentRef.setInput('alphaTex', '. 3.3.4');
        fixture.detectChanges();
        await fixture.whenStable();

        expect(render).toHaveBeenCalledTimes(1);
        expect(render.mock.calls[0][1]).toBe('. 3.3.4');
    });

    it('enables the player and renders playback controls when player=true', async () => {
        const player = {playPause: vi.fn(), stop: vi.fn(), setSpeed: vi.fn(), seek: vi.fn()};
        render.mockImplementationOnce((_el, _tex, options) => {
            options.onPlayerReady?.();
            return Promise.resolve({destroy: vi.fn(), player});
        });
        fixture.componentRef.setInput('alphaTex', '. 3.3.4');
        fixture.componentRef.setInput('player', true);
        fixture.detectChanges();
        await fixture.whenStable();
        fixture.detectChanges();

        expect(render.mock.calls[0][2]).toMatchObject({player: true});
        const controls = fixture.nativeElement.querySelector('.tab-player');
        expect(controls).toBeTruthy();

        controls.querySelector('.tab-player-btn').click();
        expect(player.playPause).toHaveBeenCalledTimes(1);
    });

    it('does not render playback controls when player is disabled', async () => {
        fixture.componentRef.setInput('alphaTex', '. 3.3.4');
        fixture.detectChanges();
        await fixture.whenStable();

        expect(render.mock.calls[0][2]).toMatchObject({player: false});
        expect(fixture.nativeElement.querySelector('.tab-player')).toBeNull();
    });

    it('shows an error message when rendering rejects', async () => {
        render.mockRejectedValueOnce(new Error('boom'));
        fixture.componentRef.setInput('alphaTex', 'bad');
        fixture.detectChanges();
        // Let the rejected render promise's catch handler settle (microtask + macrotask).
        await new Promise((resolve) => setTimeout(resolve, 0));
        fixture.detectChanges();

        expect(fixture.nativeElement.querySelector('.tab-viewer-error')).toBeTruthy();
    });
});
