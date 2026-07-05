// Projects have no colour of their own (see `Project` model), but the date views
// (Calendar / Timeline) lean on a stable per-project hue to tell tasks apart at a
// glance. Derive one deterministically from the project id so the same project
// always renders in the same colour without a backend change.
const PROJECT_PALETTE = [
    '#5e6ad2', // accent / indigo
    '#16794a', // success / green
    '#a35c00', // warn / amber
    '#4a8fd6', // info / blue
    '#b4456f', // pink
    '#6f4ed3', // ai / violet
    '#0f7d8c', // teal
    '#c2410c', // orange
    '#7a8a00', // olive
    '#52525b', // slate
];

export function projectColor(projectId: number): string {
    // Multiply by a large odd constant to spread sequential ids across the palette.
    const idx = Math.abs(Math.imul(projectId, 2654435761)) % PROJECT_PALETTE.length;
    return PROJECT_PALETTE[idx];
}
