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
