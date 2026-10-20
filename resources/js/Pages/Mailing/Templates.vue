<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import InputError from '../../Components/InputError.vue';
import EmailHtmlFrame from '../../Components/EmailHtmlFrame.vue';
import HtmlEditor from '../../Components/HtmlEditor.vue';
import Icon from '../../Components/Icon.vue';
import { t } from '../../Composables/useLang';

const props = defineProps({
    templates: { type: Array, default: () => [] },
    layouts: { type: Array, default: () => [] },
    default_layout: { type: String, default: '' },
    filters: { type: Object, default: () => ({}) },
    products: { type: Array, default: () => [] },
});

const productFilter = ref(props.filters.product ?? null);
const applyFilter = () => router.get('/mailing/templates', productFilter.value ? { product: productFilter.value } : {}, { preserveState: true, replace: true });

const firstProduct = () => productFilter.value ?? props.products[0]?.key ?? null;

/* ---------- šablóny ---------- */

const blankTemplate = () => ({ product_key: firstProduct(), layout_id: null, name: '', subject: '', heading: '', body: '', button_label: '', button_url: '' });
const template = useForm(blankTemplate());
const editingTemplate = ref(undefined);

const openTemplate = (tpl = null) => {
    editingTemplate.value = tpl;
    template.clearErrors();
    Object.assign(template, tpl ? { ...blankTemplate(), ...tpl } : blankTemplate());
};

const saveTemplate = () => {
    const options = { preserveScroll: true, onSuccess: () => { editingTemplate.value = undefined; } };
    editingTemplate.value
        ? template.patch(`/mailing/templates/${editingTemplate.value.id}`, options)
        : template.post('/mailing/templates', options);
};

const destroyTemplate = (tpl) => confirm(t('mailing.templates.confirm_delete', { name: tpl.name }))
    && router.delete(`/mailing/templates/${tpl.id}`, { preserveScroll: true });

const templateLayouts = computed(() => props.layouts.filter((l) => l.product_key === template.product_key));

/* ---------- layouty ---------- */

const blankLayout = () => ({ product_key: firstProduct(), name: '', html: props.default_layout });
const layout = useForm(blankLayout());
const editingLayout = ref(undefined);

const openLayout = (item = null) => {
    editingLayout.value = item;
    layout.clearErrors();
    Object.assign(layout, item ? { product_key: item.product_key, name: item.name, html: item.html } : blankLayout());
};

const saveLayout = () => {
    const options = { preserveScroll: true, onSuccess: () => { editingLayout.value = undefined; } };
    editingLayout.value
        ? layout.patch(`/mailing/layouts/${editingLayout.value.id}`, options)
        : layout.post('/mailing/layouts', options);
};

const destroyLayout = (item) => confirm(t('mailing.layouts.confirm_delete', { name: item.name }))
    && router.delete(`/mailing/layouts/${item.id}`, { preserveScroll: true });

// Náhľad layoutu s ukážkovým obsahom – značky sa nahradia len tu v prehliadači.
const layoutPreview = computed(() => layout.html
    .replace(/\{\{\{\s*content\s*\}\}\}/g, `<p>${t('mailing.layouts.sample_content')}</p>`)
    .replace(/\{\{[#^/]\s*\w+\s*\}\}/g, '')
    .replace(/\{\{\{?\s*(\w+)\s*\}?\}\}/g, (_, key) => ({ heading: t('mailing.layouts.sample_heading'), unsubscribe_url: '#' })[key] ?? `{${key}}`));
</script>

<template>
    <Head :title="t('mailing.templates.title')" />

    <div class="mb-3">
        <Link href="/mailing" class="text-sm text-slate-500 hover:text-slate-900">‹ {{ t('mailing.back') }}</Link>
    </div>

    <PageHeader :title="t('mailing.templates.title')" :subtitle="t('mailing.templates.subtitle')">
        <template #action>
            <select v-model="productFilter" :aria-label="t('mailing.fields.product')" @change="applyFilter">
                <option :value="null">{{ t('mailing.all_products') }}</option>
                <option v-for="p in products" :key="p.key" :value="p.key">{{ p.name }}</option>
            </select>
        </template>
    </PageHeader>

    <!-- Šablóny -->
    <section class="mb-10">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-slate-900">{{ t('mailing.templates.list') }}</h2>
            <button type="button" class="btn-secondary" @click="openTemplate()"><Icon name="plus" :size="16" /> {{ t('mailing.templates.add') }}</button>
        </div>

        <form v-if="editingTemplate !== undefined" class="card mb-4 space-y-4 p-5" @submit.prevent="saveTemplate">
            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <label for="t_product">{{ t('mailing.fields.product') }}</label>
                    <select id="t_product" v-model="template.product_key" required>
                        <option v-for="p in products" :key="p.key" :value="p.key">{{ p.name }}</option>
                    </select>
                </div>
                <div>
                    <label for="t_name">{{ t('mailing.fields.name') }}</label>
                    <input id="t_name" v-model="template.name" type="text" required />
                    <InputError :message="template.errors.name" />
                </div>
                <div>
                    <label for="t_layout">{{ t('mailing.fields.layout') }}</label>
                    <select id="t_layout" v-model="template.layout_id">
                        <option :value="null">{{ t('mailing.fields.layout_default') }}</option>
                        <option v-for="l in templateLayouts" :key="l.id" :value="l.id">{{ l.name }}</option>
                    </select>
                </div>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label for="t_subject">{{ t('mailing.fields.subject') }}</label>
                    <input id="t_subject" v-model="template.subject" type="text" />
                </div>
                <div>
                    <label for="t_heading">{{ t('mailing.fields.heading') }}</label>
                    <input id="t_heading" v-model="template.heading" type="text" />
                </div>
            </div>
            <div>
                <label for="t_body">{{ t('mailing.fields.body') }}</label>
                <HtmlEditor id="t_body" v-model="template.body" :placeholder="t('mailing.editor.placeholder')" />
                <InputError :message="template.errors.body" />
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label for="t_btn">{{ t('mailing.fields.button_label') }}</label>
                    <input id="t_btn" v-model="template.button_label" type="text" />
                </div>
                <div>
                    <label for="t_url">{{ t('mailing.fields.button_url') }}</label>
                    <input id="t_url" v-model="template.button_url" type="url" />
                    <InputError :message="template.errors.button_url" />
                </div>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="btn-primary" :disabled="template.processing">{{ t('mailing.campaigns.save') }}</button>
                <button type="button" class="btn-secondary" @click="editingTemplate = undefined">{{ t('mailing.cancel') }}</button>
            </div>
        </form>

        <div class="card divide-y divide-slate-100">
            <div v-for="tpl in templates" :key="tpl.id" class="flex flex-wrap items-center justify-between gap-3 p-4">
                <div class="min-w-0">
                    <p class="font-medium text-slate-900">{{ tpl.name }}</p>
                    <p class="truncate text-xs text-slate-500">{{ tpl.product }} · {{ tpl.subject }}<span v-if="tpl.layout"> · {{ tpl.layout }}</span></p>
                </div>
                <div class="flex gap-2">
                    <Link :href="`/mailing/create?template=${tpl.id}`" class="btn-primary btn-sm">{{ t('mailing.templates.use') }}</Link>
                    <button type="button" class="btn-secondary btn-sm" @click="openTemplate(tpl)">{{ t('mailing.campaigns.edit') }}</button>
                    <button type="button" class="btn-danger btn-sm" @click="destroyTemplate(tpl)">{{ t('mailing.campaigns.delete') }}</button>
                </div>
            </div>
            <p v-if="!templates.length" class="p-6 text-center text-sm text-slate-500">{{ t('mailing.templates.empty') }}</p>
        </div>
    </section>

    <!-- Layouty -->
    <section>
        <div class="mb-3 flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">{{ t('mailing.layouts.title') }}</h2>
                <p class="text-sm text-slate-500">{{ t('mailing.layouts.subtitle') }}</p>
            </div>
            <button type="button" class="btn-secondary" @click="openLayout()"><Icon name="plus" :size="16" /> {{ t('mailing.layouts.add') }}</button>
        </div>

        <form v-if="editingLayout !== undefined" class="card mb-4 grid gap-4 p-5 lg:grid-cols-2" @submit.prevent="saveLayout">
            <div class="space-y-4">
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label for="l_product">{{ t('mailing.fields.product') }}</label>
                        <select id="l_product" v-model="layout.product_key" required>
                            <option v-for="p in products" :key="p.key" :value="p.key">{{ p.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label for="l_name">{{ t('mailing.fields.name') }}</label>
                        <input id="l_name" v-model="layout.name" type="text" required />
                        <InputError :message="layout.errors.name" />
                    </div>
                </div>
                <div>
                    <label for="l_html">HTML</label>
                    <textarea id="l_html" v-model="layout.html" rows="18" class="font-mono text-xs" required></textarea>
                    <p class="mt-1 text-xs text-slate-500">{{ t('mailing.layouts.tags') }}</p>
                    <InputError :message="layout.errors.html" />
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="btn-primary" :disabled="layout.processing">{{ t('mailing.campaigns.save') }}</button>
                    <button type="button" class="btn-secondary" @click="editingLayout = undefined">{{ t('mailing.cancel') }}</button>
                </div>
            </div>
            <EmailHtmlFrame :html="layoutPreview" />
        </form>

        <div class="card divide-y divide-slate-100">
            <div v-for="item in layouts" :key="item.id" class="flex items-center justify-between gap-3 p-4">
                <div>
                    <p class="font-medium text-slate-900">{{ item.name }}</p>
                    <p class="text-xs text-slate-500">{{ item.product }}</p>
                </div>
                <div class="flex gap-2">
                    <button type="button" class="btn-secondary btn-sm" @click="openLayout(item)">{{ t('mailing.campaigns.edit') }}</button>
                    <button type="button" class="btn-danger btn-sm" @click="destroyLayout(item)">{{ t('mailing.campaigns.delete') }}</button>
                </div>
            </div>
            <p v-if="!layouts.length" class="p-6 text-center text-sm text-slate-500">{{ t('mailing.layouts.empty') }}</p>
        </div>
    </section>
</template>
