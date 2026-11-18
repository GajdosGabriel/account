import { t, tc } from '../../Composables/useLang';
import { csrfHeaders } from '../../Composables/useCsrf';

/** Farba stavu kampane alebo doručenia v StatusBadge. */
export function campaignTone(status) {
    return {
        draft: 'slate',
        queued: 'sky',
        completed: 'emerald',
        cancelled: 'rose',
        pending: 'slate',
        sending: 'amber',
        sent: 'emerald',
        failed: 'rose',
        bounced: 'orange',
        skipped: 'slate',
    }[status] ?? 'slate';
}

/** Oneskorenie sa ukladá v hodinách; celé dni sa zobrazia ako dni. */
export function delayLabel(hours) {
    if (!hours) return t('mailing.delay.none');

    return hours % 24 === 0
        ? tc('mailing.delay.days', hours / 24)
        : tc('mailing.delay.hours', hours);
}

/** POST s JSON odpoveďou mimo Inertie (náhľad, test). */
export async function postJson(url, body) {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...csrfHeaders(),
        },
        credentials: 'same-origin',
        body: JSON.stringify(body),
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        if (response.status === 419) throw new Error(t('mailing.session_expired'));
        const first = data.errors ? Object.values(data.errors)[0]?.[0] : null;
        throw new Error(first || data.message || t('mailing.request_failed', { status: response.status }));
    }

    return data;
}
