/**
 * Premenné v šablónach odpovedí.
 *
 *   Dobrý deň {first_name|pani/pán},     → „Dobrý deň Jana,“ alebo s náhradou
 *   {cursor}                             → sem skočí kurzor po vložení
 *
 * Neznáma premenná ostane v texte tak, ako je – operátor ju uvidí a opraví.
 */
const PATTERN = /\{\s*([a-z_]+)\s*(?:\|([^{}\n]*))?\}/g;

export const CURSOR = 'cursor';

/**
 * @param {string} text
 * @param {Record<string, string>} context
 * @returns {{ text: string, cursor: number|null }}
 */
export function renderTemplate(text, context = {}) {
    let cursor = null;
    let out = '';
    let last = 0;

    for (const match of (text ?? '').matchAll(PATTERN)) {
        const [whole, key, fallback] = match;
        out += text.slice(last, match.index);
        last = match.index + whole.length;

        if (key === CURSOR) {
            cursor ??= out.length;
            continue;
        }

        if (!(key in context)) {
            out += whole;
            continue;
        }

        const value = String(context[key] ?? '').trim();
        out += value !== '' ? value : (fallback ?? '').trim();
    }

    out += (text ?? '').slice(last);

    return { text: out, cursor };
}

/** Text do náhľadu v zozname – jeden riadok, bez premenných kurzora. */
export function excerpt(text, context = {}, length = 140) {
    const plain = renderTemplate(text, context).text.replace(/\s+/g, ' ').trim();

    return plain.length > length ? `${plain.slice(0, length - 1)}…` : plain;
}

/** Bez diakritiky a veľkých písmen – „cena“ nájde „Cenová ponuka“. */
export function normalize(text) {
    return (text ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
}

/**
 * Šablóny zodpovedajúce hľadaniu: skratka má prednosť pred názvom,
 * názov pred textom. Prázdne hľadanie vráti pôvodné poradie.
 */
export function searchTemplates(templates, query) {
    const q = normalize(query).trim();
    if (!q) return templates;

    const score = (tpl) => {
        const shortcut = normalize(tpl.shortcut);
        const name = normalize(tpl.name);
        if (shortcut && shortcut === q) return 0;
        if (shortcut && shortcut.startsWith(q)) return 1;
        if (name.startsWith(q)) return 2;
        if (name.includes(q)) return 3;
        if (normalize(tpl.subject).includes(q) || normalize(tpl.body).includes(q)) return 4;

        return null;
    };

    return templates
        .map((tpl, index) => ({ tpl, index, score: score(tpl) }))
        .filter((row) => row.score !== null)
        .sort((a, b) => a.score - b.score || a.index - b.index)
        .map((row) => row.tpl);
}
