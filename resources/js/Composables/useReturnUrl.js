import { router } from '@inertiajs/vue3';

// Posledná adresa, z ktorej sa prišlo na aktuálnu stránku. Zmena len
// v query/po formulári (rovnaká cesta) ju neprepíše – inak by po uložení
// „predchádzajúca“ stránka bola tá istá.
let current = null;
let previous = null;

const pathOf = (url) => url.split('?')[0];

router.on('navigate', (event) => {
    const url = event.detail.page.url;
    if (current && pathOf(current) !== pathOf(url)) previous = current;
    current = url;
});

/** Adresa, kam sa vrátiť; `fallback`, ak sme na stránku prišli priamo. */
export const returnUrl = (fallback) => previous ?? fallback;
