import { router } from '@inertiajs/vue3';
import { t } from './useLang';

/**
 * Jedno miesto, kde je definované menu dokladu.
 *
 * Zoznam faktúr, detail aj karta na firme používajú ten istý zoznam položiek.
 * Ktoré sa naozaj zobrazia, rozhoduje `invoice.can` – mapa, ktorú zostavuje
 * InvoicePolicy na serveri. Pridanie akcie je tak zmena na jednom mieste
 * a nikdy nevznikne tlačidlo, ktoré backend odmietne.
 *
 * Popisky sú z lang/{locale}/actions.php – rovnako ako v RowActions.
 */
export function invoiceMenu(invoice, options = {}) {
    const url = (suffix = '') => `/invoices/${invoice.id}${suffix}`;
    const on = options.on ?? {};

    const items = [
        {
            label: t('actions.invoice.view'),
            icon: 'eye',
            can: 'view',
            href: url(),
        },
        {
            label: t('actions.invoice.preview'),
            icon: 'receipt',
            can: 'view',
            onSelect: () => window.open(url('/preview'), '_blank'),
        },
        {
            label: t('actions.invoice.download'),
            icon: 'download',
            can: 'download',
            onSelect: () => window.open(url('/pdf'), '_blank'),
        },
        { separator: true },
        {
            label: t('actions.invoice.issue'),
            icon: 'check',
            can: 'issue',
            method: 'post',
            url: url('/issue'),
            confirm: t('actions.invoice.confirm.issue'),
        },
        {
            label: invoice.sent_count > 0 ? t('actions.invoice.resend') : t('actions.invoice.send'),
            icon: 'send',
            can: 'send',
            badge: invoice.sent_count > 0 ? `${invoice.sent_count}×` : null,
            onSelect: on.send ? () => on.send(invoice) : () => router.post(url('/send'), {}, { preserveScroll: true }),
        },
        {
            label: t('actions.invoice.remind'),
            icon: 'mail',
            can: 'remind',
            badge: invoice.days_overdue ? t('actions.invoice.days', { count: invoice.days_overdue }) : null,
            method: 'post',
            url: url('/remind'),
            confirm: t('actions.invoice.confirm.remind'),
        },
        {
            label: t('actions.invoice.pay'),
            icon: 'receipt',
            can: 'pay',
            onSelect: on.pay ? () => on.pay(invoice) : () => router.post(url('/pay'), {}, { preserveScroll: true }),
        },
        { separator: true },
        {
            label: t('actions.invoice.edit'),
            icon: 'pencil',
            can: 'update',
            href: url(),
        },
        {
            label: t('actions.invoice.duplicate'),
            icon: 'copy',
            can: 'duplicate',
            method: 'post',
            url: url('/duplicate'),
        },
        {
            label: t('actions.invoice.convert'),
            icon: 'invoice',
            can: 'convert',
            method: 'post',
            url: url('/convert'),
        },
        {
            label: t('actions.invoice.credit'),
            icon: 'invoice',
            can: 'credit',
            onSelect: on.credit
                ? () => on.credit(invoice)
                : () => router.post(url('/credit'), {}, { preserveScroll: true }),
        },
        { separator: true },
        {
            label: t('actions.invoice.cancel'),
            icon: 'ban',
            can: 'cancel',
            danger: true,
            method: 'post',
            url: url('/cancel'),
            confirm: t('actions.invoice.confirm.cancel'),
        },
        {
            label: t('actions.invoice.delete'),
            icon: 'trash',
            can: 'delete',
            danger: true,
            method: 'delete',
            url: url(),
            confirm: t('actions.invoice.confirm.delete'),
        },
        {
            label: t('actions.invoice.restore'),
            icon: 'refresh',
            can: 'restore',
            method: 'post',
            url: url('/restore'),
        },
        {
            label: t('actions.invoice.force_delete'),
            icon: 'trash',
            can: 'forceDelete',
            danger: true,
            method: 'delete',
            url: url('/force'),
            confirm: t('actions.invoice.confirm.force_delete'),
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
