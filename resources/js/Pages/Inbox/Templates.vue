<script setup>
import { computed, nextTick, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import InputError from '../../Components/InputError.vue';
import Icon from '../../Components/Icon.vue';
import { t, tc } from '../../Composables/useLang';
import { excerpt, renderTemplate, searchTemplates } from '../../Composables/useReplyTemplates';

const props = defineProps({
    templates: { type: Array, default: () => [] },
    products: { type: Array, default: () => [] },
    variables: { type: Array, default: () => [] },
    sample: { type: Object, default: () => ({}) },
});

const blank = () => ({
    name: '',
    shortcut: '',
    product_key: '',
    subject: '',
    body: '',
    private: false,
});

const form = useForm(blank());
const editing = ref(null);
const open = ref(false);
const formEl = ref(null);
const bodyEl = ref(null);

const reveal = async () => {
    open.value = true;
    await nextTick();
    formEl.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    formEl.value?.querySelector('input')?.focus({ preventScroll: true });
};

const create = () => {
    editing.value = null;
    form.defaults(blank());
    form.reset();
    form.clearErrors();
    reveal();
};

const fillFrom = (tpl) => ({
    name: tpl.name,
    shortcut: tpl.shortcut ?? '',
    product_key: tpl.product_key ?? '',
    subject: tpl.subject ?? '',
    body: tpl.body,
    private: tpl.private,
});

const edit = (tpl) => {
    editing.value = tpl;
    form.clearErrors();
    Object.assign(form, fillFrom(tpl));
    reveal();
};

// Kópia ako východisko pre podobnú odpoveď (iný projekt, iný jazyk…).
const duplicate = (tpl) => {
    editing.value = null;
    form.clearErrors();
    Object.assign(form, { ...fillFrom(tpl), name: t('messaging.templates.copy_of', { name: tpl.name }), shortcut: '' });
    reveal();
};

const close = () => {
    open.value = false;
    editing.value = null;
};

const submit = () => {
    const options = { preserveScroll: true, onSuccess: close };

    editing.value
        ? form.patch(`/inbox/templates/${editing.value.id}`, options)
        : form.post('/inbox/templates', options);
};

const destroy = (tpl) => {
    if (confirm(t('messaging.templates.confirm_delete', { name: tpl.name }))) {
        router.delete(`/inbox/templates/${tpl.id}`, { preserveScroll: true });
    }
};

// Premenná sa vloží tam, kde je kurzor v texte.
const insertVariable = async (name) => {
    const el = bodyEl.value;
    const token = `{${name}}`;
    const start = el?.selectionStart ?? form.body.length;
    const end = el?.selectionEnd ?? form.body.length;
    form.body = form.body.slice(0, start) + token + form.body.slice(end);
    await nextTick();
    el?.focus();
    el?.setSelectionRange(start + token.length, start + token.length);
};

const preview = computed(() => renderTemplate(form.body, { ...props.sample, product: productName(form.product_key) || props.sample.product }).text);
const previewSubject = computed(() => renderTemplate(form.subject, props.sample).text);

const productName = (key) => props.products.find((p) => p.key === key)?.name ?? '';

// Zoznam – hľadanie a filter projektu.
const search = ref('');
const product = ref('');

const filtered = computed(() => searchTemplates(
    props.templates.filter((tpl) => !product.value
        || (product.value === '_all' ? !tpl.product_key : tpl.product_key === product.value)),
    search.value,
));

const groups = computed(() => {
    const map = new Map();
    for (const tpl of filtered.value) {
        const key = tpl.product_key ?? '';
        if (!map.has(key)) map.set(key, { key, name: tpl.product ?? t('messaging.templates.all_products'), items: [] });
        map.get(key).items.push(tpl);
    }

    return [...map.values()];
});
</script>

<template>
    <Head :title="t('messaging.templates.title')" />

    <div class="mb-3">
        <Link href="/inbox" class="text-sm text-slate-500 hover:text-slate-900">‹ {{ t('messaging.inbox.back') }}</Link>
    </div>

    <PageHeader :title="t('messaging.templates.title')" :subtitle="t('messaging.templates.subtitle')">
        <template #action>
            <button v-if="!open" type="button" class="btn-primary" @click="create">
                <Icon name="plus" :size="16" />
                {{ t('messaging.templates.add') }}
            </button>
        </template>
    </PageHeader>

    <!-- Formulár -->
    <form v-if="open" ref="formEl" class="card mb-6 scroll-mt-24 p-5" @submit.prevent="submit" @keydown.esc="close">
        <div class="mb-5 flex items-start justify-between gap-4">
            <h2 class="font-semibold text-slate-900">
                {{ editing ? t('messaging.templates.edit') : t('messaging.templates.add') }}
            </h2>
            <button type="button" class="text-slate-400 hover:text-slate-700" :title="t('messaging.templates.cancel')" :aria-label="t('messaging.templates.cancel')" @click="close">
                <Icon name="close" :size="18" />
            </button>
        </div>

        <div class="grid gap-6 lg:grid-cols-5">
            <div class="space-y-4 lg:col-span-3">
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="sm:col-span-2">
                        <label for="tpl_name">{{ t('messaging.templates.name') }}</label>
                        <input id="tpl_name" v-model="form.name" type="text" required maxlength="120" :placeholder="t('messaging.templates.name_placeholder')" />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div>
                        <label for="tpl_shortcut">{{ t('messaging.templates.shortcut') }}</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">/</span>
                            <input id="tpl_shortcut" v-model="form.shortcut" type="text" maxlength="40" class="pl-6" placeholder="cena" autocomplete="off" />
                        </div>
                        <InputError :message="form.errors.shortcut" />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label for="tpl_product">{{ t('messaging.templates.product') }}</label>
                        <select id="tpl_product" v-model="form.product_key">
                            <option value="">{{ t('messaging.templates.all_products') }}</option>
                            <option v-for="p in products" :key="p.key" :value="p.key">{{ p.name }}</option>
                        </select>
                        <InputError :message="form.errors.product_key" />
                    </div>
                    <div class="sm:col-span-2">
                        <label for="tpl_subject">{{ t('messaging.templates.subject') }}</label>
                        <input id="tpl_subject" v-model="form.subject" type="text" maxlength="255" :placeholder="t('messaging.templates.subject_placeholder')" />
                        <InputError :message="form.errors.subject" />
                    </div>
                </div>

                <div>
                    <label for="tpl_body">{{ t('messaging.templates.body') }}</label>
                    <textarea id="tpl_body" ref="bodyEl" v-model="form.body" rows="10" required></textarea>
                    <InputError :message="form.errors.body" />
                </div>

                <div>
                    <p class="mb-1.5 text-xs font-medium text-slate-500">{{ t('messaging.templates.variables') }}</p>
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="v in variables"
                            :key="v"
                            type="button"
                            class="rounded-md border border-slate-200 bg-white px-2 py-1 text-xs text-slate-700 hover:border-brand-300 hover:bg-brand-50"
                            :title="`{${v}}`"
                            @click="insertVariable(v)"
                        >
                            {{ t(`messaging.templates.var.${v}`) }}
                        </button>
                    </div>
                    <p class="mt-2 text-xs text-slate-500">{{ t('messaging.templates.variables_hint') }}</p>
                </div>

                <label class="mb-0 flex items-center gap-2 font-normal">
                    <input v-model="form.private" type="checkbox" />
                    {{ t('messaging.templates.private') }}
                </label>
            </div>

            <!-- Náhľad s ukážkovými údajmi -->
            <div class="lg:col-span-2">
                <p class="mb-1.5 text-xs font-medium text-slate-500">{{ t('messaging.templates.preview') }}</p>
                <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-4 text-sm">
                    <p v-if="previewSubject" class="mb-2 font-medium text-slate-800">{{ previewSubject }}</p>
                    <div v-if="preview" class="whitespace-pre-line break-words leading-relaxed text-slate-800">{{ preview }}</div>
                    <p v-else class="text-slate-400">{{ t('messaging.templates.preview_empty') }}</p>
                </div>
                <p class="mt-2 text-xs text-slate-500">{{ t('messaging.templates.preview_hint') }}</p>
            </div>
        </div>

        <div class="mt-6 flex justify-end gap-2 border-t border-slate-100 pt-4">
            <button type="button" class="btn-secondary" @click="close">{{ t('messaging.templates.cancel') }}</button>
            <button type="submit" class="btn-primary" :disabled="form.processing">{{ t('messaging.templates.save') }}</button>
        </div>
    </form>

    <!-- Prázdny stav -->
    <div v-if="templates.length === 0" class="card flex flex-col items-center gap-3 px-4 py-12 text-center">
        <span class="chip h-11 w-11 bg-brand-50 text-brand-600"><Icon name="bolt" :size="20" /></span>
        <p class="max-w-md text-sm text-slate-500">{{ t('messaging.templates.empty') }}</p>
        <button v-if="!open" type="button" class="btn-primary btn-sm" @click="create">
            <Icon name="plus" :size="14" />
            {{ t('messaging.templates.add') }}
        </button>
    </div>

    <template v-else>
        <div class="card mb-5 flex flex-wrap gap-3 p-4">
            <div class="relative min-w-56 flex-1">
                <Icon name="search" :size="16" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                <input v-model="search" type="search" class="pl-9" :placeholder="t('messaging.templates.search')" :aria-label="t('messaging.templates.search')" />
            </div>
            <select v-model="product" class="w-auto" :aria-label="t('messaging.templates.product')">
                <option value="">{{ t('messaging.inbox.all_products') }}</option>
                <option value="_all">{{ t('messaging.templates.all_products') }}</option>
                <option v-for="p in products" :key="p.key" :value="p.key">{{ p.name }}</option>
            </select>
        </div>

        <p v-if="!groups.length" class="card px-4 py-10 text-center text-sm text-slate-500">{{ t('messaging.templates.no_results') }}</p>

        <section v-for="group in groups" :key="group.key" class="mb-5">
            <h2 class="mb-2 px-1 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ group.name }}</h2>
            <div class="card overflow-hidden">
                <ul class="divide-y divide-slate-100">
                    <li
                        v-for="tpl in group.items"
                        :key="tpl.id"
                        class="flex flex-wrap items-start gap-4 px-4 py-3.5"
                        :class="{ 'bg-brand-50/40': editing?.id === tpl.id }"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="flex flex-wrap items-center gap-1.5 text-sm font-medium text-slate-900">
                                <span>{{ tpl.name }}</span>
                                <code v-if="tpl.shortcut" class="rounded bg-slate-100 px-1.5 py-0.5 text-xs font-normal text-slate-600">/{{ tpl.shortcut }}</code>
                                <span v-if="tpl.private" class="rounded bg-amber-50 px-1.5 py-0.5 text-xs font-medium text-amber-700">{{ t('messaging.templates.private_badge') }}</span>
                            </p>
                            <p class="mt-0.5 line-clamp-2 text-xs text-slate-500">
                                <span v-if="tpl.subject" class="font-medium text-slate-600">{{ tpl.subject }} — </span>{{ excerpt(tpl.body, {}, 220) }}
                            </p>
                            <p class="mt-1 text-xs text-slate-400">
                                {{ tc('messaging.templates.uses', tpl.uses) }}
                                <template v-if="tpl.last_used_at"> · {{ t('messaging.templates.last_used', { time: tpl.last_used_at }) }}</template>
                                <template v-if="tpl.author"> · {{ tpl.author }}</template>
                            </p>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            <button type="button" class="btn-secondary btn-sm" :title="t('actions.edit')" :aria-label="`${t('actions.edit')} ${tpl.name}`" @click="edit(tpl)">
                                <Icon name="pencil" :size="14" />
                            </button>
                            <button type="button" class="btn-secondary btn-sm" :title="t('messaging.templates.duplicate')" :aria-label="`${t('messaging.templates.duplicate')} ${tpl.name}`" @click="duplicate(tpl)">
                                <Icon name="copy" :size="14" />
                            </button>
                            <button type="button" class="btn-secondary btn-sm text-rose-600 hover:bg-rose-50" :title="t('messaging.templates.delete')" :aria-label="`${t('messaging.templates.delete')} ${tpl.name}`" @click="destroy(tpl)">
                                <Icon name="trash" :size="14" />
                            </button>
                        </div>
                    </li>
                </ul>
            </div>
        </section>
    </template>
</template>
