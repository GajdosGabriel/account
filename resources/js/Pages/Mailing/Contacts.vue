<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import InputError from '../../Components/InputError.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import Pagination from '../../Components/Pagination.vue';
import Icon from '../../Components/Icon.vue';
import { locale, t } from '../../Composables/useLang';

const props = defineProps({
    contacts: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    tags: { type: Array, default: () => [] },
    products: { type: Array, default: () => [] },
});

const filter = ref({ product: null, search: '', state: null, tag: null, ...props.filters });
const tagLabel = (key) => props.tags.find((tag) => tag.key === key)?.label ?? key;
let timer = null;

const submit = () => {
    const query = Object.fromEntries(Object.entries(filter.value).filter(([, v]) => v !== '' && v != null));
    router.get('/mailing/contacts', query, { preserveState: true, preserveScroll: true, replace: true });
};

watch(() => filter.value.search, () => {
    clearTimeout(timer);
    timer = setTimeout(submit, 350);
});

const panel = ref(null);

const add = useForm({ product_key: props.filters.product ?? props.products[0]?.key ?? null, emails: '', permission_note: '', confirmed: false });
const bounce = useForm({ product_key: props.filters.product ?? props.products[0]?.key ?? null, emails: '', type: 'hard', reason: '' });

const saveAdd = () => add.post('/mailing/contacts', { preserveScroll: true, onSuccess: () => { add.reset('emails', 'confirmed'); panel.value = null; } });
const saveBounce = () => bounce.post('/mailing/contacts/bounce', { preserveScroll: true, onSuccess: () => { bounce.reset('emails', 'reason'); panel.value = null; } });

const action = (contact, name) => router.post(`/mailing/contacts/${contact.id}/${name}`, {}, { preserveScroll: true });
const remove = (contact) => confirm(t('mailing.contacts.confirm_delete', { email: contact.email }))
    && router.delete(`/mailing/contacts/${contact.id}`, { preserveScroll: true });

const state = (c) => {
    if (c.unsubscribed_at) return { label: t('mailing.contacts.state.unsubscribed'), tone: 'slate' };
    if (c.bounced_at || c.suppression) return { label: t('mailing.contacts.state.bounced'), tone: 'orange' };
    if (!c.permission_at && !c.permission_note) return { label: t('mailing.contacts.state.service'), tone: 'sky' };

    return { label: t('mailing.contacts.state.active'), tone: 'emerald' };
};

const date = (iso) => (iso ? new Intl.DateTimeFormat(locale(), { dateStyle: 'short' }).format(new Date(iso)) : '');
</script>

<template>
    <Head :title="t('mailing.contacts.title')" />

    <div class="mb-3">
        <Link href="/mailing" class="text-sm text-slate-500 hover:text-slate-900">‹ {{ t('mailing.back') }}</Link>
    </div>

    <PageHeader :title="t('mailing.contacts.title')" :subtitle="t('mailing.contacts.subtitle')">
        <template #action>
            <button type="button" class="btn-secondary" @click="panel = panel === 'bounce' ? null : 'bounce'">{{ t('mailing.contacts.report_bounce') }}</button>
            <button type="button" class="btn-primary" @click="panel = panel === 'add' ? null : 'add'"><Icon name="plus" :size="16" /> {{ t('mailing.contacts.add') }}</button>
        </template>
    </PageHeader>

    <form v-if="panel === 'add'" class="card mb-5 space-y-4 p-5" @submit.prevent="saveAdd">
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label for="c_product">{{ t('mailing.fields.product') }}</label>
                <select id="c_product" v-model="add.product_key" required>
                    <option v-for="p in products" :key="p.key" :value="p.key">{{ p.name }}</option>
                </select>
            </div>
            <div>
                <label for="c_permission">{{ t('mailing.contacts.permission_note') }}</label>
                <input id="c_permission" v-model="add.permission_note" type="text" :placeholder="t('mailing.contacts.permission_placeholder')" required />
                <InputError :message="add.errors.permission_note" />
            </div>
        </div>
        <div>
            <label for="c_emails">{{ t('mailing.contacts.emails') }}</label>
            <textarea id="c_emails" v-model="add.emails" rows="6" :placeholder="t('mailing.contacts.emails_placeholder')" required></textarea>
            <InputError :message="add.errors.emails" />
        </div>
        <label class="flex items-start gap-2 text-sm">
            <input v-model="add.confirmed" type="checkbox" class="mt-1" />
            <span>{{ t('mailing.contacts.confirm_permission') }}</span>
        </label>
        <InputError :message="add.errors.confirmed" />
        <button type="submit" class="btn-primary" :disabled="add.processing">{{ t('mailing.contacts.add') }}</button>
    </form>

    <form v-if="panel === 'bounce'" class="card mb-5 space-y-4 p-5" @submit.prevent="saveBounce">
        <div class="grid gap-4 md:grid-cols-3">
            <div>
                <label for="b_product">{{ t('mailing.fields.product') }}</label>
                <select id="b_product" v-model="bounce.product_key" required>
                    <option v-for="p in products" :key="p.key" :value="p.key">{{ p.name }}</option>
                </select>
            </div>
            <div>
                <label for="b_type">{{ t('mailing.contacts.bounce_type') }}</label>
                <select id="b_type" v-model="bounce.type">
                    <option value="hard">{{ t('mailing.contacts.bounce_hard') }}</option>
                    <option value="soft">{{ t('mailing.contacts.bounce_soft') }}</option>
                </select>
            </div>
            <div>
                <label for="b_reason">{{ t('mailing.contacts.bounce_reason') }}</label>
                <input id="b_reason" v-model="bounce.reason" type="text" />
            </div>
        </div>
        <div>
            <label for="b_emails">{{ t('mailing.contacts.emails') }}</label>
            <textarea id="b_emails" v-model="bounce.emails" rows="4" required></textarea>
            <InputError :message="bounce.errors.emails" />
        </div>
        <button type="submit" class="btn-primary" :disabled="bounce.processing">{{ t('mailing.contacts.report_bounce') }}</button>
    </form>

    <div class="card mb-5 grid gap-3 p-4" :class="tags.length ? 'md:grid-cols-5' : 'md:grid-cols-4'">
        <input v-model="filter.search" type="search" class="md:col-span-2" :placeholder="t('mailing.contacts.search')" :aria-label="t('mailing.contacts.search')" />
        <select v-model="filter.product" :aria-label="t('mailing.fields.product')" @change="filter.tag = null; submit()">
            <option :value="null">{{ t('mailing.all_products') }}</option>
            <option v-for="p in products" :key="p.key" :value="p.key">{{ p.name }}</option>
        </select>
        <select v-if="tags.length" v-model="filter.tag" :aria-label="t('mailing.contacts.tags')" @change="submit">
            <option :value="null">{{ t('mailing.contacts.all_tags') }}</option>
            <option v-for="tag in tags" :key="tag.key" :value="tag.key">{{ tag.label }}</option>
        </select>
        <select v-model="filter.state" :aria-label="t('mailing.fields.status')" @change="submit">
            <option :value="null">{{ t('mailing.all') }}</option>
            <option value="active">{{ t('mailing.contacts.state.active') }}</option>
            <option value="service">{{ t('mailing.contacts.state.service') }}</option>
            <option value="unsubscribed">{{ t('mailing.contacts.state.unsubscribed') }}</option>
            <option value="bounced">{{ t('mailing.contacts.state.bounced') }}</option>
        </select>
    </div>

    <div class="card overflow-hidden">
        <table v-if="contacts.data.length" class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">{{ t('mailing.fields.recipient') }}</th>
                    <th class="px-4 py-3">{{ t('mailing.fields.product') }}</th>
                    <th class="px-4 py-3">{{ t('mailing.contacts.permission_note') }}</th>
                    <th class="px-4 py-3">{{ t('mailing.fields.status') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <tr v-for="c in contacts.data" :key="c.id">
                    <td class="px-4 py-2">
                        {{ c.email }}<span v-if="c.name" class="text-slate-500"> · {{ c.name }}</span>
                        <span v-if="c.tags.length" class="mt-1 flex flex-wrap gap-1">
                            <span v-for="tag in c.tags" :key="tag" class="rounded bg-slate-100 px-1.5 text-xs text-slate-600">{{ tagLabel(tag) }}</span>
                        </span>
                    </td>
                    <td class="px-4 py-2 text-slate-600">{{ c.product }}</td>
                    <td class="px-4 py-2 text-xs text-slate-600">{{ c.permission_note }}<span v-if="c.permission_at"> · {{ date(c.permission_at) }}</span></td>
                    <td class="px-4 py-2">
                        <StatusBadge :label="state(c).label" :tone="state(c).tone" />
                        <p v-if="c.bounce_reason" class="mt-1 text-xs text-slate-500">{{ c.bounce_reason }}</p>
                    </td>
                    <td class="whitespace-nowrap px-4 py-2 text-right">
                        <button v-if="!c.unsubscribed_at" type="button" class="link text-xs" @click="action(c, 'unsubscribe')">{{ t('mailing.contacts.unsubscribe') }}</button>
                        <button v-if="c.bounced_at || c.suppression === 'hard_bounce'" type="button" class="link ml-3 text-xs" @click="action(c, 'reactivate')">{{ t('mailing.contacts.reactivate') }}</button>
                        <button type="button" class="ml-3 text-xs text-rose-600 hover:underline" @click="remove(c)">{{ t('mailing.campaigns.delete') }}</button>
                    </td>
                </tr>
            </tbody>
        </table>
        <p v-else class="p-8 text-center text-sm text-slate-500">{{ t('mailing.contacts.empty') }}</p>
    </div>

    <Pagination :meta="contacts" :label="t('mailing.contacts.records')" />
</template>
