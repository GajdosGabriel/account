<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import Pagination from '../../Components/Pagination.vue';
import Icon from '../../Components/Icon.vue';
import { locale, t } from '../../Composables/useLang';
import { campaignTone, delayLabel } from './mailing';

const props = defineProps({
    campaigns: { type: Object, required: true },
    stats: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    products: { type: Array, default: () => [] },
    settings: { type: Object, default: () => ({}) },
});

const form = ref({ ...props.filters });

const submit = () => {
    const query = Object.fromEntries(Object.entries(form.value).filter(([, v]) => v !== '' && v != null));
    router.get('/mailing', query, { preserveState: true, preserveScroll: true, replace: true });
};

const percent = (part) => (props.stats.sent ? `${Math.round((part / props.stats.sent) * 100)} %` : '–');

const tiles = computed(() => [
    { label: t('mailing.stats.sent'), value: props.stats.sent },
    { label: t('mailing.stats.opened'), value: props.stats.opened, hint: percent(props.stats.opened) },
    { label: t('mailing.stats.clicked'), value: props.stats.clicked, hint: percent(props.stats.clicked) },
    { label: t('mailing.stats.pending'), value: props.stats.pending },
    { label: t('mailing.stats.failed'), value: props.stats.failed },
    { label: t('mailing.stats.active'), value: props.stats.active, hint: t('mailing.stats.contacts_hint', { unsubscribed: props.stats.unsubscribed, bounced: props.stats.bounced }) },
]);

const when = (iso) => (iso ? new Intl.DateTimeFormat(locale(), { dateStyle: 'short', timeStyle: 'short' }).format(new Date(iso)) : '');
</script>

<template>
    <Head :title="t('mailing.title')" />

    <PageHeader :title="t('mailing.title')" :subtitle="t('mailing.subtitle')">
        <template #action>
            <Link href="/mailing/contacts" class="btn-secondary">
                <Icon name="user" :size="16" />
                {{ t('mailing.nav.contacts') }}
            </Link>
            <Link href="/mailing/templates" class="btn-secondary">
                <Icon name="copy" :size="16" />
                {{ t('mailing.nav.templates') }}
            </Link>
            <Link :href="form.product ? `/mailing/create?product=${form.product}` : '/mailing/create'" class="btn-primary">
                <Icon name="plus" :size="16" />
                {{ t('mailing.campaigns.new') }}
            </Link>
        </template>
    </PageHeader>

    <div v-if="!settings.enabled || settings.dry_run" class="mb-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        {{ !settings.enabled ? t('mailing.settings.disabled') : t('mailing.settings.dry_run') }}
    </div>

    <div class="card mb-5 grid gap-3 p-4 md:grid-cols-4">
        <select v-model="form.product" :aria-label="t('mailing.fields.product')" @change="submit">
            <option :value="null">{{ t('mailing.all_products') }}</option>
            <option v-for="p in products" :key="p.key" :value="p.key">{{ p.name }}</option>
        </select>
        <input v-model="form.from" type="date" :aria-label="t('mailing.stats.from')" @change="submit" />
        <input v-model="form.to" type="date" :aria-label="t('mailing.stats.to')" @change="submit" />
        <p class="self-center text-xs text-slate-500">{{ t('mailing.settings.limit', { limit: settings.hourly_limit }) }}</p>
    </div>

    <div class="mb-6 grid grid-cols-2 gap-3 md:grid-cols-6">
        <div v-for="tile in tiles" :key="tile.label" class="card p-4">
            <p class="text-xs text-slate-500">{{ tile.label }}</p>
            <p class="mt-1 text-xl font-semibold text-slate-900">{{ tile.value }}</p>
            <p v-if="tile.hint" class="mt-0.5 text-xs text-slate-500">{{ tile.hint }}</p>
        </div>
    </div>

    <div class="card overflow-hidden">
        <table v-if="campaigns.data.length" class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">{{ t('mailing.fields.name') }}</th>
                    <th class="px-4 py-3">{{ t('mailing.fields.product') }}</th>
                    <th class="px-4 py-3">{{ t('mailing.fields.mode') }}</th>
                    <th class="px-4 py-3">{{ t('mailing.fields.status') }}</th>
                    <th class="px-4 py-3 text-right">{{ t('mailing.stats.sent') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <tr v-for="c in campaigns.data" :key="c.id" class="hover:bg-slate-50">
                    <td class="px-4 py-3">
                        <Link :href="c.status === 'draft' ? `/mailing/${c.id}/edit` : `/mailing/${c.id}`" class="font-medium text-slate-900 hover:underline">{{ c.name }}</Link>
                        <p v-if="c.subject" class="truncate text-xs text-slate-500">{{ c.subject }}</p>
                    </td>
                    <td class="px-4 py-3 text-slate-600">{{ c.product }}</td>
                    <td class="px-4 py-3 text-slate-600">
                        <template v-if="c.trigger_event">
                            {{ c.trigger_label }} · {{ delayLabel(c.delay_hours) }}
                            <span v-if="c.reply_in_thread" class="chip ml-1">{{ t('mailing.fields.reply_short') }}</span>
                        </template>
                        <template v-else>
                            {{ t('mailing.modes.broadcast') }}<span v-if="c.scheduled_at"> · {{ when(c.scheduled_at) }}</span>
                        </template>
                    </td>
                    <td class="px-4 py-3"><StatusBadge :status="c.status" :label="t(`mailing.status.${c.status}`)" :tone="campaignTone(c.status)" /></td>
                    <td class="px-4 py-3 text-right tabular-nums text-slate-600">
                        {{ c.sent_count }}<span v-if="c.pending_count" class="text-slate-400"> / +{{ c.pending_count }}</span>
                    </td>
                </tr>
            </tbody>
        </table>
        <p v-else class="p-8 text-center text-sm text-slate-500">{{ t('mailing.campaigns.empty') }}</p>
    </div>

    <Pagination :meta="campaigns" :label="t('mailing.campaigns.records')" />
</template>
