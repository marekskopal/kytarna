// WCAG luminance check — returns a black/white text color readable against the given hex.
export function pickReadableForeground(hex: string): string {
    const normalized = hex.startsWith('#') ? hex.substring(1) : hex;
    if (normalized.length !== 6) {
        return '#000000';
    }
    const r = parseInt(normalized.substring(0, 2), 16);
    const g = parseInt(normalized.substring(2, 4), 16);
    const b = parseInt(normalized.substring(4, 6), 16);
    if (Number.isNaN(r) || Number.isNaN(g) || Number.isNaN(b)) {
        return '#000000';
    }
    const channel = (c: number): number => {
        const v = c / 255;
        return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
    };
    const luminance = 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b);
    return luminance > 0.5 ? '#111111' : '#ffffff';
}
