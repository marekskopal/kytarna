/**
 * Which resource a practice sub-feature (tabs / links / progress / watchers) hangs off.
 * Lecture and song endpoints share the same collection shape (`/{parent}/{id}/...`) but
 * diverge on the item routes (`/tabs/{id}` vs `/song-tabs/{id}`, `/progress/{id}` vs
 * `/song-progress/{id}`), so services thread this to build the right URL.
 */
export type PracticeParent = 'lectures' | 'songs';
