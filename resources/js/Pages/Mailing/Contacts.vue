<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import InputError from '../../Components/InputError.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import Pagination from '../../Components/Pagination.vue';
import Icon from '../../Components/Icon.vue';
import LabelPicker from '../../Components/LabelPicker.vue';
import { labelClass } from '../../Composables/useLabels';
import { locale, t } from '../../Composables/useLang';

const props = defineProps({
    contacts: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    tags: { type: Array, default: () => [] },
    labels: { type: Array, default: () => [] },
    colors: { type: Array, default: () => [] },
    products: { type: Array, default: () => [] },
});

const filter = ref({ product: null, search: '', state: null, tag: null, ...props.filters });
const tagLabel = (key) => props.tags.find((tag) => tag.key === key)?.label ?? key;
let timer = null;

const query = () => Object.fromEntries(Object.entries(filter.value).filter(([, v]) => v !== '' && v != null));
const submit = () => router.get('/mailing/contacts', query(), { preserveState: true, preserveScroll: true, replace: true });

watch(() => filter.value.search, () => {
    clearTimeout(timer);
    timer = setTimeout(submit, 350);
});

const showLabel = (label) => {
    filter.value = { product: label.product_key, search: '', state: null, tag: label.key };
    panel.value = null;
    submit();
};

// Štítky projektu na výber; bez zvoleného projektu všetky (podľa názvu bez duplicít).
const labelsOf = (productKey) => (productKey
    ? props.labels.filter((l) => l.product_key === productKey)
    : [...new Map(props.labels.map((l) => [l.name.toLowerCase(), l])).values()]);

const panel = ref(null);
const defaultProduct = props.filters.product ?? props.products[0]?.key ?? null;

/* ---------- Pridanie / import ---------- */

const add = useForm({ product_key: defaultProduct, emails: '', consent: true, permission_note: '', labels: [], with_names: true, with_file_labels: true });
const found = computed(() => add.emails.split(/\r?\n/).reduce((n, line) => n + (line.match(/[^\s<>"',;]+@[^\s<>"',;]+\.[^\s<>"',;]+/g)?.length ?? 0), 0));
const fileInput = ref(null);

const loadFile = (e) => {
    const file = e.target.files?.[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = () => {
        const text = String(reader.result ?? '');
        add.emails = add.emails.trim() ? `${add.emails.trim()}\n${text}` : text;
        if (fileInput.value) fileInput.value.value = '';
    };
    reader.readAsText(file);
};

const saveAdd = () => add.post('/mailing/contacts', {
    preserveScroll: true,
    onSuccess: () => { add.reset('emails', 'labels', 'permission_note'); add.consent = true; panel.value = null; },
});

/* ---------- Nedoručiteľné ---------- */

const bounce = useForm({ product_key: defaultProduct, emails: '', type: 'hard', reason: '' });
const saveBounce = () => bounce.post('/mailing/contacts/bounce', { preserveScroll: true, onSuccess: () => { bounce.reset('emails', 'reason'); panel.value = null; } });

/* ---------- Správa štítkov ---------- */

const labelForm = useForm({ product_key: defaultProduct, name: '', color: 'indigo' });
const saveLabel = () => labelForm.post('/mailing/labels', { preserveScroll: true, onSuccess: () => labelForm.reset('name') });

const editingLabel = ref(null);
const editLabelForm = useForm({ name: '', color: 'indigo' });
const editLabel = (label) => {
    editingLabel.value = label.id;
    editLabelForm.clearErrors();
    editLabelForm.name = label.name;
    editLabelForm.color = label.color;
};
const updateLabel = (label) => editLabelForm.patch(`/mailing/labels/${label.id}`, { preserveScroll: true, onSuccess: () => { editingLabel.value = null; } });
const removeLabel = (label) => confirm(t('mailing.labels.confirm_delete', { name: label.name, count: label.count }))
    && router.delete(`/mailing/labels/${label.id}`, { preserveScroll: true });

const managedLabels = computed(() => (filter.value.product ? props.labels.filter((l) => l.product_key === filter.value.product) : props.labels));
const projectTags = computed(() => props.tags.filter((tag) => !tag.manual));

/* ---------- Štítky jedného kontaktu ---------- */

const editing = ref(null);
const tagsForm = useForm({ labels: [] });
const editTags = (contact) => {
    editing.value = contact.id;
    tagsForm.clearErrors();
    tagsForm.labels = contact.manual_tags.map((l) => l.name);
};
const saveTags = (contact) => tagsForm.put(`/mailing/contacts/${contact.id}/tags`, { preserveScroll: true, onSuccess: () => { editing.value = null; } });

/* ---------- Výber a hromadné akcie ---------- */

const selected = ref(new Set());
const allMatching = ref(false);
const pageIds = computed(() => props.contacts.data.map((c) => c.id));
const pageSelected = computed(() => pageIds.value.length > 0 && pageIds.value.every((id) => selected.value.has(id)));
const selectedCount = computed(() => (allMatching.value ? props.contacts.total : selected.value.size));

const toggle = (id) => {
    const next = new Set(selected.value);
    next.has(id) ? next.delete(id) : next.add(id);
    selected.value = next;
    allMatching.value = false;
};
const togglePage = () => {
    const next = new Set(selected.value);
    pageIds.value.forEach((id) => (pageSelected.value ? next.delete(id) : next.add(id)));
    selected.value = next;
    allMatching.value = false;
};
const clearSelection = () => { selected.value = new Set(); allMatching.value = false; };

// Iný filter = iný výber.
watch(() => props.filters, clearSelection, { deep: true });

const bulk = useForm({ action: 'add_labels', labels: [], product_key: null, with_labels: true, consent: true });
const bulkNeedsLabels = computed(() => ['add_labels', 'remove_labels'].includes(bulk.action));
const copyTargets = computed(() => props.products.filter((p) => p.key !== filter.value.product));
const productName = (key) => props.products.find((p) => p.key === key)?.name ?? key;

watch(() => bulk.action, (action) => {
    bulk.labels = [];
    if (action === 'copy' && !copyTargets.value.some((p) => p.key === bulk.product_key)) bulk.product_key = copyTargets.value[0]?.key ?? null;
});

// Export: vybrané kontakty, alebo všetky podľa filtra.
const exportUrl = (ids = []) => {
    const params = new URLSearchParams(query());
    ids.forEach((id) => params.append('ids[]', id));
    const qs = params.toString();

    return `/mailing/contacts/export${qs ? `?${qs}` : ''}`;
};
const exportSelectedUrl = computed(() => exportUrl(allMatching.value ? [] : [...selected.value]));

const runBulk = () => {
    const count = selectedCount.value;
    const ask = { delete: 'confirm_delete', unsubscribe: 'confirm_unsubscribe', consent: 'confirm_consent', copy: 'confirm_copy' }[bulk.action];
    if (ask && !confirm(t(`mailing.contacts.bulk.${ask}`, { count, product: productName(bulk.product_key) }))) return;

    bulk.transform((data) => ({
        ...data,
        all: allMatching.value,
        ids: allMatching.value ? [] : [...selected.value],
        filters: query(),
    })).post('/mailing/contacts/bulk', {
        preserveScroll: true,
        onSuccess: () => { clearSelection(); bulk.reset('labels'); },
    });
};

/* ---------- Zobrazenie ---------- */

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
            <a :href="exportUrl()" class="btn-secondary" :title="t('mailing.contacts.export_hint')">{{ t('mailing.contacts.export') }}</a>
            <button type="button" class="btn-secondary" @click="panel = panel === 'labels' ? null : 'labels'">{{ t('mailing.labels.manage') }}</button>
            <button type="button" class="btn-secondary" @click="panel = panel === 'bounce' ? null : 'bounce'">{{ t('mailing.contacts.report_bounce') }}</button>
            <button type="button" class="btn-primary" @click="panel = panel === 'add' ? null : 'add'"><Icon name="plus" :size="16" /> {{ t('mailing.contacts.add') }}</button>
        </template>
    </PageHeader>

    <!-- Pridanie / import -->
    <form v-if="panel === 'add'" class="card mb-5 space-y-4 p-5" @submit.prevent="saveAdd">
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label for="c_product">{{ t('mailing.fields.product') }}</label>
                <select id="c_product" v-model="add.product_key" required @change="add.labels = []">
                    <option v-for="p in products" :key="p.key" :value="p.key">{{ p.name }}</option>
                </select>
            </div>
            <div>
                <label for="c_labels">{{ t('mailing.contacts.labels_for_import') }}</label>
                <LabelPicker id="c_labels" v-model="add.labels" :options="labelsOf(add.product_key)" />
                <p class="mt-1 text-xs text-slate-500">{{ t('mailing.contacts.labels_import_hint') }}</p>
                <InputError :message="add.errors.labels" />
            </div>
        </div>
        <div>
            <div class="flex flex-wrap items-end justify-between gap-2">
                <label for="c_emails">{{ t('mailing.contacts.emails') }}</label>
                <label class="link cursor-pointer text-xs">
                    {{ t('mailing.contacts.csv') }}
                    <input ref="fileInput" type="file" accept=".csv,.txt,text/csv,text/plain" class="hidden" @change="loadFile" />
                </label>
            </div>
            <textarea id="c_emails" v-model="add.emails" rows="8" :placeholder="t('mailing.contacts.emails_placeholder')" required></textarea>
            <div class="mt-1 flex flex-wrap justify-between gap-2 text-xs text-slate-500">
                <span>{{ t('mailing.contacts.csv_hint') }}</span>
                <span v-if="add.emails" class="font-medium text-slate-700">{{ t('mailing.contacts.found', { count: found }) }}</span>
            </div>
            <InputError :message="add.errors.emails" />
            <fieldset class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm">
                <legend class="sr-only">{{ t('mailing.contacts.import_what') }}</legend>
                <span class="font-medium text-slate-700">{{ t('mailing.contacts.import_what') }}</span>
                <label class="flex items-center gap-2 text-slate-500"><input type="checkbox" checked disabled /> {{ t('mailing.contacts.import_emails') }}</label>
                <label class="flex items-center gap-2"><input v-model="add.with_names" type="checkbox" /> {{ t('mailing.contacts.import_names') }}</label>
                <label class="flex items-center gap-2"><input v-model="add.with_file_labels" type="checkbox" /> {{ t('mailing.contacts.import_file_labels') }}</label>
            </fieldset>
        </div>
        <div class="grid gap-4 rounded-md bg-slate-50 p-4 md:grid-cols-2">
            <div>
                <label class="flex items-start gap-2 text-sm font-medium">
                    <input v-model="add.consent" type="checkbox" class="mt-1" />
                    <span>{{ t('mailing.contacts.consent') }}</span>
                </label>
                <p class="mt-1 text-xs text-slate-500">{{ t('mailing.contacts.consent_hint') }}</p>
            </div>
            <div v-if="add.consent">
                <label for="c_permission">{{ t('mailing.contacts.permission_note') }}</label>
                <input id="c_permission" v-model="add.permission_note" type="text" :placeholder="t('mailing.contacts.permission_placeholder')" />
                <p class="mt-1 text-xs text-slate-500">{{ t('mailing.contacts.permission_optional') }}</p>
                <InputError :message="add.errors.permission_note" />
            </div>
        </div>
        <button type="submit" class="btn-primary" :disabled="add.processing">{{ t('mailing.contacts.add') }}<template v-if="found"> ({{ found }})</template></button>
    </form>

    <!-- Nedoručiteľné -->
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

    <!-- Správa štítkov -->
    <div v-if="panel === 'labels'" class="card mb-5 space-y-4 p-5">
        <div>
            <h2 class="text-base font-semibold text-slate-900">{{ t('mailing.labels.title') }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ t('mailing.labels.hint') }}</p>
        </div>

        <form class="grid items-end gap-3 md:grid-cols-[1fr_1.5fr_auto_auto]" @submit.prevent="saveLabel">
            <div>
                <label for="l_product">{{ t('mailing.fields.product') }}</label>
                <select id="l_product" v-model="labelForm.product_key" required>
                    <option v-for="p in products" :key="p.key" :value="p.key">{{ p.name }}</option>
                </select>
            </div>
            <div>
                <label for="l_name">{{ t('mailing.labels.new') }}</label>
                <input id="l_name" v-model="labelForm.name" type="text" maxlength="60" required :placeholder="t('mailing.contacts.tags_placeholder')" />
            </div>
            <div>
                <span class="mb-1 block text-sm font-medium text-slate-700">{{ t('mailing.labels.color') }}</span>
                <div class="flex h-10 items-center gap-1">
                    <button v-for="color in colors" :key="color" type="button" class="h-5 w-5 rounded-full ring-offset-2" :class="[labelClass(color, 'dot'), labelForm.color === color ? 'ring-2 ring-slate-500' : '']" :aria-label="color" :aria-pressed="labelForm.color === color" @click="labelForm.color = color"></button>
                </div>
            </div>
            <button type="submit" class="btn-primary" :disabled="labelForm.processing">{{ t('mailing.labels.create') }}</button>
            <InputError :message="labelForm.errors.name" class="md:col-span-4" />
        </form>

        <table v-if="managedLabels.length" class="w-full text-sm">
            <tbody class="divide-y divide-slate-100">
                <tr v-for="label in managedLabels" :key="label.id">
                    <td class="py-2 pr-3">
                        <form v-if="editingLabel === label.id" class="flex flex-wrap items-center gap-2" @submit.prevent="updateLabel(label)">
                            <input v-model="editLabelForm.name" type="text" maxlength="60" class="!py-1 md:w-56" :aria-label="t('mailing.labels.name')" />
                            <span class="flex items-center gap-1">
                                <button v-for="color in colors" :key="color" type="button" class="h-4 w-4 rounded-full ring-offset-1" :class="[labelClass(color, 'dot'), editLabelForm.color === color ? 'ring-2 ring-slate-500' : '']" :aria-label="color" @click="editLabelForm.color = color"></button>
                            </span>
                            <button type="submit" class="link text-xs" :disabled="editLabelForm.processing">{{ t('mailing.labels.save') }}</button>
                            <button type="button" class="text-xs text-slate-500 hover:underline" @click="editingLabel = null">{{ t('mailing.contacts.cancel') }}</button>
                            <InputError :message="editLabelForm.errors.name" class="w-full" />
                        </form>
                        <span v-else class="rounded px-2 py-0.5 text-xs font-medium" :class="labelClass(label.color)">{{ label.name }}</span>
                    </td>
                    <td class="py-2 pr-3 text-slate-500">{{ label.product }}</td>
                    <td class="py-2 pr-3">
                        <button type="button" class="link text-xs" @click="showLabel(label)">{{ label.count }} {{ t('mailing.labels.contacts') }}</button>
                    </td>
                    <td class="whitespace-nowrap py-2 text-right">
                        <button type="button" class="link mr-3 text-xs" @click="editLabel(label)">{{ t('mailing.labels.rename') }}</button>
                        <button type="button" class="text-xs text-rose-600 hover:underline" @click="removeLabel(label)">{{ t('mailing.labels.delete') }}</button>
                    </td>
                </tr>
            </tbody>
        </table>
        <p v-else class="text-sm text-slate-500">{{ t('mailing.labels.empty') }}</p>

        <div v-if="projectTags.length" class="border-t border-slate-100 pt-3">
            <p class="mb-2 text-xs text-slate-500">{{ t('mailing.contacts.project_tag') }}</p>
            <div class="flex flex-wrap gap-1">
                <span v-for="tag in projectTags" :key="tag.key" class="rounded bg-slate-100 px-1.5 text-xs text-slate-600" :title="tag.description || tag.key">{{ tag.label }}</span>
            </div>
        </div>
    </div>

    <!-- Filter -->
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

    <!-- Hromadné akcie -->
    <div v-if="selectedCount" class="card sticky top-2 z-10 mb-3 space-y-2 border-brand-200 bg-brand-50/60 p-3">
        <form class="flex flex-wrap items-center gap-2" @submit.prevent="runBulk">
            <span class="text-sm font-medium text-slate-800">{{ t('mailing.contacts.selected', { count: selectedCount }) }}</span>
            <select v-model="bulk.action" class="!w-auto !py-1.5 text-sm" :aria-label="t('mailing.contacts.bulk.apply')">
                <option value="add_labels">{{ t('mailing.contacts.bulk.add_labels') }}</option>
                <option value="remove_labels">{{ t('mailing.contacts.bulk.remove_labels') }}</option>
                <option value="consent">{{ t('mailing.contacts.bulk.consent') }}</option>
                <option value="unsubscribe">{{ t('mailing.contacts.bulk.unsubscribe') }}</option>
                <option value="copy">{{ t('mailing.contacts.bulk.copy') }}</option>
                <option value="delete">{{ t('mailing.contacts.bulk.delete') }}</option>
            </select>
            <LabelPicker v-if="bulkNeedsLabels" v-model="bulk.labels" :options="labelsOf(filter.product)" :creatable="bulk.action === 'add_labels'" class="min-w-[16rem] flex-1" />
            <template v-if="bulk.action === 'copy'">
                <select v-model="bulk.product_key" class="!w-auto !py-1.5 text-sm" :aria-label="t('mailing.contacts.bulk.copy_target')" required>
                    <option v-for="p in copyTargets" :key="p.key" :value="p.key">{{ p.name }}</option>
                </select>
                <LabelPicker v-model="bulk.labels" :options="labelsOf(bulk.product_key)" :placeholder="t('mailing.contacts.bulk.copy_labels')" class="min-w-[14rem] flex-1" />
            </template>
            <button type="submit" class="btn-primary !py-1.5" :class="bulk.action === 'delete' ? '!bg-rose-600 hover:!bg-rose-700' : ''" :disabled="bulk.processing || (bulkNeedsLabels && !bulk.labels.length) || (bulk.action === 'copy' && !bulk.product_key)">{{ t('mailing.contacts.bulk.apply') }}</button>
            <a :href="exportSelectedUrl" class="link text-xs">{{ t('mailing.contacts.export_selected') }}</a>
            <button type="button" class="text-xs text-slate-500 hover:underline" @click="clearSelection">{{ t('mailing.contacts.clear_selection') }}</button>
        </form>
        <div v-if="bulk.action === 'copy'" class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-600">
            <label class="flex items-center gap-1.5"><input v-model="bulk.with_labels" type="checkbox" /> {{ t('mailing.contacts.bulk.copy_with_labels') }}</label>
            <label class="flex items-center gap-1.5"><input v-model="bulk.consent" type="checkbox" /> {{ t('mailing.contacts.bulk.copy_consent') }}</label>
            <span class="text-slate-500">{{ t('mailing.contacts.bulk.copy_hint') }}</span>
        </div>
        <p v-if="allMatching" class="text-xs text-slate-600">{{ t('mailing.contacts.all_matching_selected', { count: contacts.total }) }}</p>
        <p v-else-if="pageSelected && contacts.total > pageIds.length" class="text-xs">
            <button type="button" class="link" @click="allMatching = true">{{ t('mailing.contacts.select_all_matching', { count: contacts.total }) }}</button>
        </p>
        <InputError :message="bulk.errors.labels || bulk.errors.ids || bulk.errors.action || bulk.errors.product_key" />
    </div>

    <div class="card overflow-hidden">
        <table v-if="contacts.data.length" class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="w-8 py-3 pl-4">
                        <input type="checkbox" :checked="pageSelected" :aria-label="t('mailing.contacts.select_all_page')" @change="togglePage" />
                    </th>
                    <th class="px-4 py-3">{{ t('mailing.fields.recipient') }}</th>
                    <th class="px-4 py-3">{{ t('mailing.fields.product') }}</th>
                    <th class="px-4 py-3">{{ t('mailing.contacts.permission_note') }}</th>
                    <th class="px-4 py-3">{{ t('mailing.fields.status') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <tr v-for="c in contacts.data" :key="c.id" :class="allMatching || selected.has(c.id) ? 'bg-brand-50/40' : ''">
                    <td class="py-2 pl-4 align-top">
                        <input type="checkbox" class="mt-0.5" :checked="allMatching || selected.has(c.id)" :disabled="allMatching" :aria-label="c.email" @change="toggle(c.id)" />
                    </td>
                    <td class="px-4 py-2">
                        {{ c.email }}<span v-if="c.name" class="text-slate-500"> · {{ c.name }}</span>
                        <form v-if="editing === c.id" class="mt-1 flex flex-wrap items-center gap-2" @submit.prevent="saveTags(c)">
                            <LabelPicker v-model="tagsForm.labels" :options="labelsOf(c.product_key)" class="min-w-[16rem] flex-1 md:max-w-md" />
                            <button type="submit" class="link text-xs" :disabled="tagsForm.processing">{{ t('mailing.contacts.save_tags') }}</button>
                            <button type="button" class="text-xs text-slate-500 hover:underline" @click="editing = null">{{ t('mailing.contacts.cancel') }}</button>
                            <InputError :message="tagsForm.errors.labels" class="w-full" />
                        </form>
                        <span v-if="c.tags.length || (c.manual_tags.length && editing !== c.id)" class="mt-1 flex flex-wrap gap-1">
                            <span v-for="tag in c.tags" :key="'p-' + tag" class="rounded bg-slate-100 px-1.5 text-xs text-slate-600" :title="t('mailing.contacts.project_tag')">{{ tagLabel(tag) }}</span>
                            <template v-if="editing !== c.id">
                                <span v-for="tag in c.manual_tags" :key="'m-' + tag.key" class="rounded px-1.5 text-xs" :class="labelClass(tag.color)" :title="t('mailing.contacts.manual_tag')">{{ tag.name }}</span>
                            </template>
                        </span>
                    </td>
                    <td class="px-4 py-2 text-slate-600">{{ c.product }}</td>
                    <td class="px-4 py-2 text-xs text-slate-600">{{ c.permission_note }}<span v-if="c.permission_at"> · {{ date(c.permission_at) }}</span></td>
                    <td class="px-4 py-2">
                        <StatusBadge :label="state(c).label" :tone="state(c).tone" />
                        <p v-if="c.bounce_reason" class="mt-1 text-xs text-slate-500">{{ c.bounce_reason }}</p>
                    </td>
                    <td class="whitespace-nowrap px-4 py-2 text-right">
                        <button type="button" class="link mr-3 text-xs" @click="editTags(c)">{{ t('mailing.contacts.edit_tags') }}</button>
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
