/**
 * Spoločné pravidlá pre vizuálne editory e-mailov – musia sedieť
 * s App\Services\Mailing\HtmlSanitizer::cleanStyle().
 */
const ALLOWED_STYLES = ['color', 'background-color', 'text-align', 'font-size', 'font-weight', 'font-style', 'text-decoration'];
const STYLE_VALUE = /^(#[0-9a-f]{3,8}|rgba?\(\s*[\d.]+%?\s*,\s*[\d.]+%?\s*,\s*[\d.]+%?\s*(,\s*[\d.]+\s*)?\)|[a-z-]+|\d+(\.\d+)?(px|em|rem|%))$/i;

// Prehliadač ukladá farby ako rgb(…); niektorí e-mailoví klienti chcú #hex.
export const rgbToHex = (html) => String(html ?? '').replace(
    /rgb\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*\)/gi,
    (_, r, g, b) => `#${[r, g, b].map((n) => Number(n).toString(16).padStart(2, '0')).join('')}`,
);

export const cleanStyle = (style) => rgbToHex(style)
    .split(';')
    .map((declaration) => {
        const at = declaration.indexOf(':');
        if (at < 0) return null;
        const property = declaration.slice(0, at).trim().toLowerCase();
        const value = declaration.slice(at + 1).trim();
        return ALLOWED_STYLES.includes(property) && STYLE_VALUE.test(value) ? `${property}: ${value}` : null;
    })
    .filter(Boolean)
    .join('; ');

export const COLORS = [
    '#0f172a', '#475569', '#94a3b8', '#ffffff',
    '#dc2626', '#ea580c', '#ca8a04', '#16a34a',
    '#0d9488', '#2563eb', '#7c3aed', '#db2777',
];

export const HIGHLIGHTS = [
    '#fef08a', '#fed7aa', '#fecaca', '#bbf7d0',
    '#a5f3fc', '#bfdbfe', '#ddd6fe', '#f1f5f9',
];

// execCommand('fontSize') berie 1–7; so styleWithCSS z toho vznikne font-size: small … xx-large.
export const SIZES = [
    { key: 'small', value: '2' },
    { key: 'normal', value: '3' },
    { key: 'large', value: '5' },
    { key: 'huge', value: '6' },
];
