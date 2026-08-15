import { usePage } from '@inertiajs/vue3';

/**
 * Preklady zo servera.
 *
 * Texty žijú v `lang/{locale}/…`, HandleInertiaRequests ich posiela do
 * props ako `translations`. Frontend si teda nedrží vlastnú kópiu
 * popiskov – zmena v lang súbore sa prejaví aj v menu.
 *
 *   t('actions.delete')
 *   t('actions.confirm.delete', { name: 'produkčný server' })
 *   tc('invoices.documents', 3)          // „3 doklady“
 *
 * Chýbajúci kľúč sa vráti tak, ako prišiel. Je to viditeľné pri prvom
 * pohľade na obrazovku a nezhodí to stránku.
 */
export function t(key, replace = {}) {
    const text = line(key);

    return text === null ? key : fill(text, replace);
}

/**
 * Preklad s počtom – rovnaký zápis ako `trans_choice` v Laraveli:
 *
 *   '{1} :count doklad|[2,4] :count doklady|[0,*] :count dokladov'
 *
 * Slovenčina a čeština majú tri tvary, nemčina a angličtina dva; bez
 * výberu tvaru by v zozname stálo „1 dokladov“.
 */
export function tc(key, count, replace = {}) {
    const text = line(key);

    return text === null ? key : fill(choose(text, count), { count, ...replace });
}

/** Aktuálny jazyk rozhrania, napríklad pre Intl formátovanie. */
export function locale() {
    return usePage().props.locale ?? 'sk';
}

/** Surový riadok z prekladov, alebo null, ak kľúč nikam nevedie. */
function line(key) {
    const value = key
        .split('.')
        .reduce((carry, part) => (carry == null ? null : carry[part]), usePage().props.translations ?? {});

    return typeof value === 'string' ? value : null;
}

function fill(text, replace) {
    return Object.entries(replace).reduce(
        (carry, [name, value]) => carry.replaceAll(`:${name}`, value ?? ''),
        text,
    );
}

/**
 * Vyberie tvar podľa počtu. Berie `{n}` aj `[od,do]` (s `*` ako
 * nekonečnom); segmenty bez podmienky sa správajú ako „jednotné|množné“.
 */
function choose(text, count) {
    const segments = text.split('|');
    const plain = [];

    for (const segment of segments) {
        const exact = segment.match(/^\{(\S+)\}\s*([\s\S]*)$/);

        if (exact) {
            if (exact[1] === String(count)) return exact[2];
            continue;
        }

        const range = segment.match(/^\[(\S+?),(\S+?)\]\s*([\s\S]*)$/);

        if (range) {
            const from = range[1] === '*' ? -Infinity : Number(range[1]);
            const to = range[2] === '*' ? Infinity : Number(range[2]);

            if (count >= from && count <= to) return range[3];
            continue;
        }

        plain.push(segment);
    }

    if (plain.length) return count === 1 ? plain[0] : plain[plain.length - 1];

    // Žiadny rozsah nesedel – radšej posledný tvar než prázdny text.
    return segments[segments.length - 1].replace(/^(\{\S+\}|\[\S+?,\S+?\])\s*/, '');
}
