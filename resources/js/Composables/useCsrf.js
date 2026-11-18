/**
 * Hlavičky pre fetch() mimo Inertie.
 *
 * Meta tag csrf-token sa nastaví len pri prvom načítaní stránky. Po prihlásení
 * cez Inertiu sa session obnoví a token v meta tagu je už neplatný (server
 * vráti 419). Cookie XSRF-TOKEN Laravel posiela s každou odpoveďou, preto je
 * vždy aktuálna – rovnako ju používa aj Inertia (axios).
 */
export function csrfHeaders() {
    const cookie = document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='));

    return cookie
        ? { 'X-XSRF-TOKEN': decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) }
        : { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' };
}
