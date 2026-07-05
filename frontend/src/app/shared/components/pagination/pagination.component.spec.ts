import {provideZonelessChangeDetection} from '@angular/core';
import {TestBed} from '@angular/core/testing';
import {provideTranslateStub} from '@app/testing/test-providers';
import {beforeEach, describe, expect, it} from 'vitest';

import {PaginationComponent} from './pagination.component';

type PageToken = number | 'ellipsis-left' | 'ellipsis-right';

interface PaginationInternals {
    totalPages: () => number;
    pages: () => PageToken[];
    goTo: (page: number) => void;
    rangeStart: () => number;
    rangeEnd: () => number;
    hasPrev: () => boolean;
    hasNext: () => boolean;
}

function internals(component: PaginationComponent): PaginationInternals {
    return component as unknown as PaginationInternals;
}

interface MakeOptions {
    totalItems: number;
    pageSize?: number;
    currentPage?: number;
    maxPageButtons?: number;
}

function make(options: MakeOptions): {component: PaginationComponent; inner: PaginationInternals} {
    const fixture = TestBed.createComponent(PaginationComponent);
    fixture.componentRef.setInput('totalItems', options.totalItems);
    if (options.pageSize !== undefined) {
        fixture.componentRef.setInput('pageSize', options.pageSize);
    }
    if (options.currentPage !== undefined) {
        fixture.componentRef.setInput('currentPage', options.currentPage);
    }
    if (options.maxPageButtons !== undefined) {
        fixture.componentRef.setInput('maxPageButtons', options.maxPageButtons);
    }
    return {component: fixture.componentInstance, inner: internals(fixture.componentInstance)};
}

describe('PaginationComponent', () => {
    beforeEach(() => {
        TestBed.configureTestingModule({
            providers: [provideZonelessChangeDetection(), provideTranslateStub()],
        });
    });

    it('computes totalPages from totalItems and pageSize', () => {
        const {inner} = make({totalItems: 100, pageSize: 25});
        expect(inner.totalPages()).toBe(4);
    });

    it('reports at least one page when totalItems is zero', () => {
        const {inner} = make({totalItems: 0, pageSize: 50});
        expect(inner.totalPages()).toBe(1);
    });

    it('returns the full page list when count fits within the button budget', () => {
        const {inner} = make({totalItems: 200, pageSize: 50, maxPageButtons: 7});
        expect(inner.pages()).toEqual([1, 2, 3, 4]);
    });

    it('elides middle pages around the current page when count exceeds the budget', () => {
        const {inner} = make({totalItems: 1000, pageSize: 10, currentPage: 50, maxPageButtons: 7});
        expect(inner.pages()).toEqual([1, 'ellipsis-left', 48, 49, 50, 51, 52, 'ellipsis-right', 100]);
    });

    it('pins the window to the start when current page is near the beginning', () => {
        const {inner} = make({totalItems: 1000, pageSize: 10, currentPage: 2, maxPageButtons: 7});
        const tokens = inner.pages();
        expect(tokens[0]).toBe(1);
        expect(tokens[tokens.length - 1]).toBe(100);
        expect(tokens).toContain(2);
        expect(tokens).toContain('ellipsis-right');
    });

    it('pins the window to the end when current page is near the last page', () => {
        const {inner} = make({totalItems: 1000, pageSize: 10, currentPage: 99, maxPageButtons: 7});
        const tokens = inner.pages();
        expect(tokens[0]).toBe(1);
        expect(tokens[tokens.length - 1]).toBe(100);
        expect(tokens).toContain(99);
        expect(tokens).toContain('ellipsis-left');
    });

    it('rangeStart and rangeEnd reflect the current page window', () => {
        const {inner} = make({totalItems: 95, pageSize: 25, currentPage: 4});
        expect(inner.rangeStart()).toBe(76);
        expect(inner.rangeEnd()).toBe(95);
    });

    it('goTo clamps out-of-range values and only emits when the target differs', () => {
        const {component, inner} = make({totalItems: 100, pageSize: 10, currentPage: 1});
        const emitted: number[] = [];
        component.pageChange.subscribe((page) => emitted.push(page));

        inner.goTo(5);
        inner.goTo(999);
        inner.goTo(-3);
        inner.goTo(1);

        expect(emitted).toEqual([5, 10]);
    });

    it('hasPrev/hasNext reflect window position', () => {
        const first = make({totalItems: 100, pageSize: 10, currentPage: 1});
        expect(first.inner.hasPrev()).toBe(false);
        expect(first.inner.hasNext()).toBe(true);

        const last = make({totalItems: 100, pageSize: 10, currentPage: 10});
        expect(last.inner.hasPrev()).toBe(true);
        expect(last.inner.hasNext()).toBe(false);
    });
});
