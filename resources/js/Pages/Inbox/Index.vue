<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import Pagination from '../../Components/Pagination.vue';
import Icon from '../../Components/Icon.vue';
import { locale, t } from '../../Composables/useLang';

const props = defineProps({
    conversations: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    products: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    counts: { type: Object, default: () => ({}) },
});

const form = ref({ search: '', product: null, status: null, ...props.filters });
let timer = null;

// Hľadanie sa odosiela s oneskorením, filtre hneď.
watch(
    () => form.value.search,
    () => {
        clearTimeout(timer);
        timer = setTimeout(submit, 350);
    },
);

const submit = () => {
    const query = Object.fromEntries(Object.entries(form.value).filter(([, v]) => v !== '' && v != null));
    router.get('/inbox', query, { preserveState: true, preserveScroll: true, replace: true });
};

// Záložky sa správajú ako záložky – klik na aktívnu nič nezruší.
const setStatus = (status) => {
    if (form.value.status === status) return;
    form.value.status = status;
    submit();
};

const clearFilters = () => {
    const hadSearch = form.value.search !== '';
    form.value = { search: '', product: null, status: null };
    // Zmenu hľadania odošle watcher, inak treba odoslať hneď.
    if (!hadSearch) submit();
};

const hasFilters = computed(() => Object.values(form.value).some((v) => v !== '' && v != null));

const tabs = computed(() => [
    { value: null, label: t('messaging.inbox.all') },
    { value: 'active', label: t('messaging.inbox.active') },
    { value: 'unread', label: t('messaging.inbox.unread'), count: props.counts.unread },
    { value: 'unassigned', label: t('messaging.inbox.unassigned'), count: props.counts.unassigned },
]);

// Dnes len čas, tento rok bez roka, staršie s celým rokom.
const when = (iso) => {
    if (!iso) return '';
    const date = new Date(iso);
    const now = new Date();
    const today = now.toDateString() === date.toDateString();
    const thisYear = now.getFullYear() === date.getFullYear();

    return new Intl.DateTimeFormat(locale(), today
        ? { hour: '2-digit', minute: '2-digit' }
        : { day: 'numeric', month: 'numeric', ...(thisYear ? {} : { year: 'numeric' }), hour: '2-digit', minute: '2-digit' }).format(date);
};

// Predmet „Re: Objednávka …“ len zopakuje názov veci – vtedy ho netreba.
const normalize = (text) => (text ?? '').replace(/^\s*((re|fw|fwd|aw|wg|odp|tr)\s*:\s*)+/i, '').trim().toLowerCase();
const showSubject = (c) => c.subject && (!c.assigned || !normalize(c.ref_label).includes(normalize(c.subject)));
</script>

<template>
    <Head :title="t('messaging.inbox.title')" />

    <PageHeader :title="t('messaging.inbox.title')" :subtitle="t('messaging.inbox.subtitle')">
        <template #action>
            <Link href="/inbox/mailboxes" class="btn-secondary">
                <Icon name="mail" :size="16" />
                {{ t('messaging.inbox.mailboxes') }}
            </Link>
        </template>
    </PageHeader>

    <div class="card mb-5 p-4">
        <div class="mb-3 flex flex-wrap gap-2">
            <button
                v-for="tab in tabs"
                :key="tab.label"
                type="button"
                class="btn btn-sm"
                :class="form.status === tab.value
                    ? 'bg-brand-600 text-white'
                    : 'border border-slate-200 text-slate-700 hover:bg-slate-50'"
                :aria-pressed="form.status === tab.value"
                @click="setStatus(tab.value)"
            >
                {{ tab.label }}
                <span
                    v-if="tab.count"
                    class="rounded-full px-1.5 text-xs font-semibold"
                    :class="form.status === tab.value ? 'bg-white/25' : 'bg-amber-100 text-amber-800'"
                >{{ tab.count }}</span>
            </button>
        </div>

        <div class="grid gap-3 md:grid-cols-3">
            <div class="md:col-span-2">
                <input v-model="form.search" type="search" :placeholder="t('messaging.inbox.search_placeholder')" :aria-label="t('messaging.inbox.search_placeholder')" />
            </div>
            <select v-model="form.product" :aria-label="t('messaging.inbox.product')" @change="submit">
                <option :value="null">{{ t('messaging.inbox.all_products') }}</option>
                <option v-for="p in products" :key="p.key" :value="p.key">{{ p.name }}</option>
            </select>
        </div>

        <p v-if="hasFilters" class="mt-3 flex items-center gap-3 text-sm text-slate-500">
            {{ t('messaging.inbox.found', { count: conversations.total }) }}
            <button type="button" class="link" @click="clearFilters">{{ t('messaging.inbox.clear_filters') }}</button>
        </p>
    </div>

    <div class="card overflow-hidden">
        <ul class="divide-y divide-slate-100">
            <li v-for="c in conversations.data" :key="c.id">
                <Link
                    :href="`/inbox/${c.id}`"
                    class="flex items-start gap-4 px-4 py-3.5 transition hover:bg-slate-50/70"
                    :class="c.unread ? 'bg-amber-50/40' : ''"
                >
                    <span
                        class="mt-1.5 h-2 w-2 shrink-0 rounded-full"
                        :class="c.unread ? 'bg-amber-500' : 'bg-transparent'"
                    ><span v-if="c.unread" class="sr-only">{{ t('messaging.inbox.unread') }}</span></span>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-baseline gap-x-2">
                            <span class="truncate text-sm text-slate-900" :class="c.unread ? 'font-semibold' : 'font-medium'">
                                {{ c.contact_name || c.contact_email }}
                            </span>
                            <span v-if="c.contact_name" class="truncate text-xs text-slate-400">{{ c.contact_email }}</span>
                        </div>
                        <div class="mt-0.5 truncate text-sm text-slate-600">
                            <span v-if="c.assigned" class="font-medium text-slate-700">{{ c.ref_label || `${c.ref_type}:${c.ref_id}` }}</span>
                            <span v-else class="rounded bg-rose-50 px-1.5 py-0.5 text-xs font-medium text-rose-700">
                                {{ t('messaging.inbox.unassigned_badge') }}
                            </span>
                            <span v-if="showSubject(c)" class="text-slate-500"> · {{ c.subject }}</span>
                        </div>
                        <div v-if="c.organization" class="mt-0.5 truncate text-xs text-slate-400">{{ c.organization }}</div>
                    </div>

                    <div class="flex shrink-0 flex-col items-end gap-1.5">
                        <time :datetime="c.last_message_at" class="text-xs text-slate-500">{{ when(c.last_message_at) }}</time>
                        <div class="flex items-center gap-1.5">
                            <span class="rounded-md bg-slate-100 px-1.5 py-0.5 text-xs font-medium text-slate-600">{{ c.product }}</span>
                            <StatusBadge :status="c.status" :label="c.status_label" :tone="c.status_tone" />
                        </div>
                    </div>
                </Link>
            </li>
            <li v-if="conversations.data.length === 0" class="px-4 py-12 text-center text-sm text-slate-500">
                <span class="chip mx-auto mb-3 h-11 w-11 bg-slate-100 text-slate-400"><Icon name="inbox" :size="20" /></span>
                {{ hasFilters ? t('messaging.inbox.empty_filtered') : t('messaging.inbox.empty') }}
                <div v-if="hasFilters" class="mt-3">
                    <button type="button" class="btn-secondary btn-sm" @click="clearFilters">{{ t('messaging.inbox.clear_filters') }}</button>
                </div>
            </li>
        </ul>

        <Pagination :meta="conversations" :label="t('messaging.inbox.records')" />
    </div>
</template>
