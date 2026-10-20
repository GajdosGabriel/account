<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import Pagination from '../../Components/Pagination.vue';
import { locale, t } from '../../Composables/useLang';
import { campaignTone, delayLabel } from './mailing';

const props = defineProps({
    campaign: { type: Object, required: true },
    counts: { type: Object, default: () => ({}) },
    engagement: { type: Object, default: () => ({}) },
    links: { type: Array, default: () => [] },
    deliveries: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const statuses = ['pending', 'sending', 'sent', 'failed', 'bounced', 'skipped'];
const sent = computed(() => props.counts.sent ?? 0);
const percent = (part) => (sent.value ? `${Math.round((part / sent.value) * 100)} %` : '–');

const filter = (status) => router.get(`/mailing/${props.campaign.id}`, status ? { status } : {}, { preserveScroll: true, replace: true });

const when = (iso) => (iso ? new Intl.DateTimeFormat(locale(), { dateStyle: 'short', timeStyle: 'short' }).format(new Date(iso)) : '');

const post = (action) => router.post(`/mailing/${props.campaign.id}/${action}`, {}, { preserveScroll: true });

const cancel = () => confirm(t('mailing.campaigns.confirm_cancel')) && post('cancel');
const remove = () => confirm(t('mailing.campaigns.confirm_delete', { name: props.campaign.name })) && router.delete(`/mailing/${props.campaign.id}`);
</script>

<template>
    <Head :title="campaign.name" />

    <div class="mb-3">
        <Link href="/mailing" class="text-sm text-slate-500 hover:text-slate-900">‹ {{ t('mailing.back') }}</Link>
    </div>

    <PageHeader :title="campaign.name" :subtitle="`${campaign.product} · ${campaign.trigger_event ? `${campaign.trigger_event} · ${delayLabel(campaign.delay_hours)}` : t('mailing.modes.broadcast')}`">
        <template #action>
            <StatusBadge :status="campaign.status" :label="t(`mailing.status.${campaign.status}`)" :tone="campaignTone(campaign.status)" />
            <Link :href="`/mailing/${campaign.id}/edit`" class="btn-secondary">{{ campaign.status === 'draft' ? t('mailing.campaigns.edit') : t('mailing.campaigns.view_content') }}</Link>
            <button type="button" class="btn-secondary" @click="post('duplicate')">{{ t('mailing.campaigns.duplicate') }}</button>
            <button type="button" class="btn-secondary" @click="post('template')">{{ t('mailing.campaigns.save_template') }}</button>
            <button v-if="campaign.status === 'queued'" type="button" class="btn-danger" @click="cancel">{{ t('mailing.campaigns.cancel') }}</button>
            <button v-else type="button" class="btn-danger" @click="remove">{{ t('mailing.campaigns.delete') }}</button>
        </template>
    </PageHeader>

    <div class="mb-6 grid grid-cols-2 gap-3 md:grid-cols-4">
        <div class="card p-4">
            <p class="text-xs text-slate-500">{{ t('mailing.stats.sent') }}</p>
            <p class="mt-1 text-xl font-semibold">{{ sent }}</p>
        </div>
        <div class="card p-4">
            <p class="text-xs text-slate-500">{{ t('mailing.stats.opened') }}</p>
            <p class="mt-1 text-xl font-semibold">{{ engagement.opened }}</p>
            <p class="text-xs text-slate-500">{{ campaign.track_opens ? percent(engagement.opened) : t('mailing.stats.not_tracked') }}</p>
        </div>
        <div class="card p-4">
            <p class="text-xs text-slate-500">{{ t('mailing.stats.clicked') }}</p>
            <p class="mt-1 text-xl font-semibold">{{ engagement.clicked }}</p>
            <p class="text-xs text-slate-500">{{ campaign.track_clicks ? percent(engagement.clicked) : t('mailing.stats.not_tracked') }}</p>
        </div>
        <div class="card p-4">
            <p class="text-xs text-slate-500">{{ t('mailing.stats.pending') }}</p>
            <p class="mt-1 text-xl font-semibold">{{ counts.pending ?? 0 }}</p>
        </div>
    </div>

    <div v-if="links.length" class="card mb-6 overflow-hidden">
        <h2 class="border-b border-slate-100 px-4 py-3 font-semibold text-slate-900">{{ t('mailing.campaigns.links') }}</h2>
        <table class="w-full text-sm">
            <tbody class="divide-y divide-slate-100">
                <tr v-for="link in links" :key="link.url">
                    <td class="max-w-0 truncate px-4 py-2"><a :href="link.url" target="_blank" rel="noopener noreferrer" class="link">{{ link.url }}</a></td>
                    <td class="px-4 py-2 text-right tabular-nums">{{ t('mailing.campaigns.link_clicks', { clicks: link.clicks, messages: link.messages }) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="card overflow-hidden">
        <div class="flex flex-wrap gap-2 border-b border-slate-100 p-3">
            <button type="button" class="btn btn-sm" :class="!filters.status ? 'bg-brand-600 text-white' : 'border border-slate-200'" @click="filter(null)">{{ t('mailing.all') }}</button>
            <button v-for="s in statuses" :key="s" type="button" class="btn btn-sm" :class="filters.status === s ? 'bg-brand-600 text-white' : 'border border-slate-200'" @click="filter(s)">
                {{ t(`mailing.status.${s}`) }} <span class="text-xs opacity-75">{{ counts[s] ?? 0 }}</span>
            </button>
        </div>
        <table v-if="deliveries.data.length" class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">{{ t('mailing.fields.recipient') }}</th>
                    <th class="px-4 py-3">{{ t('mailing.fields.ref') }}</th>
                    <th class="px-4 py-3">{{ t('mailing.fields.status') }}</th>
                    <th class="px-4 py-3">{{ t('mailing.fields.when') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <tr v-for="d in deliveries.data" :key="d.id">
                    <td class="px-4 py-2">
                        {{ d.email }}<span v-if="d.name" class="text-slate-500"> · {{ d.name }}</span>
                    </td>
                    <td class="px-4 py-2 text-slate-600">
                        <a v-if="d.ref_url" :href="d.ref_url" target="_blank" rel="noopener" class="link">{{ d.ref }}</a>
                        <span v-else>{{ d.ref }}</span>
                    </td>
                    <td class="px-4 py-2">
                        <StatusBadge :status="d.status" :label="t(`mailing.status.${d.status}`)" :tone="campaignTone(d.status)" />
                        <p v-if="d.error" class="mt-1 text-xs text-slate-500">{{ d.error }}</p>
                    </td>
                    <td class="px-4 py-2 text-xs text-slate-600">
                        <span v-if="d.sent_at">{{ when(d.sent_at) }}</span>
                        <span v-else-if="d.due_at">{{ t('mailing.campaigns.due', { date: when(d.due_at) }) }}</span>
                        <span v-if="d.opened_at" class="chip ml-1">{{ t('mailing.stats.opened') }}</span>
                        <span v-if="d.clicked_at" class="chip ml-1">{{ t('mailing.stats.clicked') }}</span>
                    </td>
                </tr>
            </tbody>
        </table>
        <p v-else class="p-8 text-center text-sm text-slate-500">{{ t('mailing.campaigns.no_deliveries') }}</p>
    </div>

    <Pagination :meta="deliveries" :label="t('mailing.campaigns.delivery_records')" />
</template>
