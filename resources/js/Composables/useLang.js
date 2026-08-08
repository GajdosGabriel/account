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
 *
 * Chýbajúci kľúč sa vráti tak, ako prišiel. Je to viditeľné pri prvom
 * pohľade na obrazovku a nezhodí to stránku.
 */
export function t(key, replace = {}) {
    const line = key
        .split('.')
        .reduce((carry, part) => (carry == null ? null : carry[part]), usePage().props.translations ?? {});

    if (typeof line !== 'string') return key;

    return Object.entries(replace).reduce(
        (text, [name, value]) => text.replaceAll(`:${name}`, value ?? ''),
        line,
    );
}

/** Aktuálny jazyk rozhrania, napríklad pre Intl formátovanie. */
export function locale() {
    return usePage().props.locale ?? 'sk';
}
