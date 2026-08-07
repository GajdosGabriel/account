import { router } from '@inertiajs/vue3';

/**
 * Jedno miesto, kde je definované menu dokladu.
 *
 * Zoznam faktúr, detail aj karta na firme používajú ten istý zoznam položiek.
 * Ktoré sa naozaj zobrazia, rozhoduje `invoice.can` – mapa, ktorú zostavuje
 * InvoicePolicy na serveri. Pridanie akcie je tak zmena na jednom mieste
 * a nikdy nevznikne tlačidlo, ktoré backend odmietne.
 */
export function invoiceMenu(invoice, options = {}) {
    const url = (suffix = '') => `/invoices/${invoice.id}${suffix}`;
    const on = options.on ?? {};

    const items = [
        {
            label: 'Otvoriť detail',
            icon: 'eye',
            can: 'view',
            href: url(),
        },
        {
            label: 'Náhľad faktúry',
            icon: 'receipt',
            can: 'view',
            onSelect: () => window.open(url('/preview'), '_blank'),
        },
        {
            label: 'Stiahnuť PDF',
            icon: 'download',
            can: 'download',
            onSelect: () => window.open(url('/pdf'), '_blank'),
        },
        { separator: true },
        {
            label: 'Vystaviť doklad',
            icon: 'check',
            can: 'issue',
            method: 'post',
            url: url('/issue'),
            confirm: 'Vystavením sa doklad zamkne a dostane číslo. Pokračovať?',
        },
        {
            label: invoice.sent_count > 0 ? 'Poslať znovu e-mailom' : 'Poslať e-mailom',
            icon: 'send',
            can: 'send',
            badge: invoice.sent_count > 0 ? `${invoice.sent_count}×` : null,
            onSelect: on.send ? () => on.send(invoice) : () => router.post(url('/send'), {}, { preserveScroll: true }),
        },
        {
            label: 'Poslať upomienku',
            icon: 'mail',
            can: 'remind',
            badge: invoice.days_overdue ? `${invoice.days_overdue} dní` : null,
            method: 'post',
            url: url('/remind'),
            confirm: 'Odoslať zákazníkovi upomienku?',
        },
        {
            label: 'Zaznamenať úhradu',
            icon: 'receipt',
            can: 'pay',
            onSelect: on.pay ? () => on.pay(invoice) : () => router.post(url('/pay'), {}, { preserveScroll: true }),
        },
        { separator: true },
        {
            label: 'Upraviť koncept',
            icon: 'pencil',
            can: 'update',
            href: url(),
        },
        {
            label: 'Vytvoriť kópiu',
            icon: 'copy',
            can: 'duplicate',
            method: 'post',
            url: url('/duplicate'),
        },
        {
            label: 'Vystaviť faktúru zo zálohy',
            icon: 'invoice',
            can: 'convert',
            method: 'post',
            url: url('/convert'),
        },
        {
            label: 'Vystaviť dobropis',
            icon: 'invoice',
            can: 'credit',
            onSelect: on.credit
                ? () => on.credit(invoice)
                : () => router.post(url('/credit'), {}, { preserveScroll: true }),
        },
        { separator: true },
        {
            label: 'Stornovať',
            icon: 'ban',
            can: 'cancel',
            danger: true,
            method: 'post',
            url: url('/cancel'),
            confirm: 'Naozaj stornovať tento doklad?',
        },
        {
            label: 'Zmazať koncept',
            icon: 'trash',
            can: 'delete',
            danger: true,
            method: 'delete',
            url: url(),
            confirm: 'Presunúť koncept do koša? Dá sa vrátiť späť.',
        },
        {
            label: 'Obnoviť z koša',
            icon: 'refresh',
            can: 'restore',
            method: 'post',
            url: url('/restore'),
        },
        {
            label: 'Zmazať natrvalo',
            icon: 'trash',
            can: 'forceDelete',
            danger: true,
            method: 'delete',
            url: url('/force'),
            confirm: 'Nenávratné zmazanie. Naozaj pokračovať?',
        },
    ];

    return options.only
        ? items.filter((item) => item.separator || options.only.includes(item.can))
        : items;
}

/** "1 234,50 €" – rovnaký formát ako na faktúre. */
export function money(cents, currency = 'EUR') {
    return `${new Intl.NumberFormat('sk-SK', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(
        (cents ?? 0) / 100,
    )} ${currency}`;
}

/** "7. 8. 2026" */
export function shortDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('sk-SK', { day: 'numeric', month: 'numeric', year: 'numeric' });
}
