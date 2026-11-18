<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import CardSection from '../../Components/CardSection.vue';
import InputError from '../../Components/InputError.vue';
import RowActions from '../../Components/RowActions.vue';
import PageHeader from '../../Components/PageHeader.vue';
import { t } from '../../Composables/useLang';
import { tokenMenu } from '../../Composables/useTokenActions';

const props = defineProps({
    products: { type: Array, default: () => [] },
    available_events: { type: Array, default: () => [] },
    selected: { type: String, default: null },
});

const openProduct = ref(props.selected);

const tokenForm = useForm({ product_key: props.selected ?? '', name: '' });
const webhookForm = useForm({ product_key: props.selected ?? '', url: '', events: [] });

// Výber ide do URL – inak by ho návrat z úpravy tokenu alebo obnovenie
// stránky zahodili a pokračoval by si v inom projekte, než si myslíš.
const selectProduct = (key) => {
    if (openProduct.value === key) return;

    openProduct.value = key;
    resetWebhookForm();
    router.get('/developers', { product: key }, { preserveState: true, preserveScroll: true, replace: true });
};

/* ---------- service tokeny ---------- */

// Úprava tokenu má vlastnú stránku – oprávnenia sa vyberajú zo zoznamu,
// na to je v riadku tabuľky málo miesta.
const createToken = (productKey) => {
    tokenForm.product_key = productKey;
    tokenForm.post('/developers/tokens', { preserveScroll: true, onSuccess: () => tokenForm.reset('name') });
};

/* ---------- webhooky ---------- */

const editingWebhook = ref(null);

const editWebhook = (hook) => {
    editingWebhook.value = hook.id;
    webhookForm.clearErrors();
    webhookForm.url = hook.url;
    webhookForm.events = [...hook.events].filter((event) => event !== '*');
};

const resetWebhookForm = () => {
    editingWebhook.value = null;
    webhookForm.reset('url', 'events');
    webhookForm.clearErrors();
};

const submitWebhook = (productKey) => {
    webhookForm.product_key = productKey;

    const options = { preserveScroll: true, onSuccess: () => resetWebhookForm() };

    editingWebhook.value
        ? webhookForm.patch(`/developers/webhooks/${editingWebhook.value}`, options)
        : webhookForm.post('/developers/webhooks', options);
};
</script>

<template>
    <Head :title="t('common.nav.developers')" />

    <div class="space-y-6">
        <PageHeader class="mb-0!" :title="t('common.nav.developers')" :subtitle="t('ui.developers.subtitle')" />

        <div class="flex flex-wrap gap-2">
            <button
                v-for="product in products"
                :key="product.key"
                type="button"
                class="btn-sm"
                :class="openProduct === product.key ? 'btn-primary' : 'btn-secondary'"
                @click="selectProduct(product.key)"
            >
                {{ product.name }}
            </button>
        </div>

        <div
            v-if="!openProduct"
            class="rounded-xl border border-dashed border-slate-300 px-6 py-12 text-center text-sm text-slate-500"
        >
            {{ t('ui.developers.pick_product') }}
        </div>

        <template v-for="product in products" :key="product.key">
            <div v-if="openProduct === product.key" class="space-y-6">
                <CardSection
                    :title="t('ui.developers.tokens_title', { product: product.name })"
                    :description="t('ui.developers.tokens_hint')"
                >
                    <table class="w-full text-left text-sm">
                        <thead class="text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="pb-2 font-medium">{{ t('ui.developers.col_name') }}</th>
                                <th class="pb-2 font-medium">{{ t('ui.developers.col_prefix') }}</th>
                                <th class="pb-2 font-medium">{{ t('ui.developers.col_abilities') }}</th>
                                <th class="pb-2 font-medium">{{ t('ui.developers.col_last_used') }}</th>
                                <th class="w-12 pb-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="token in product.tokens"
                                :key="token.id"
                                :class="token.revoked ? 'opacity-60' : ''"
                            >
                                <td class="py-2.5 font-medium text-slate-900">
                                    <Link :href="`/developers/tokens/${token.id}/edit`" class="hover:text-brand-700">
                                        {{ token.name }}
                                    </Link>
                                    <span v-if="token.revoked" class="ml-1.5 text-xs font-normal text-rose-500">{{ t('ui.developers.revoked') }}</span>
                                </td>
                                <td class="py-2.5 font-mono text-xs text-slate-600">{{ token.prefix }}…</td>
                                <td class="py-2.5 text-slate-600">{{ token.abilities.join(', ') }}</td>
                                <td class="py-2.5 text-slate-600">{{ token.last_used_at ?? t('ui.developers.never') }}</td>
                                <td class="py-2.5 text-right">
                                    <RowActions
                                        :abilities="token.can"
                                        :base="`/developers/tokens/${token.id}`"
                                        :edit-href="`/developers/tokens/${token.id}/edit`"
                                        :name="token.name"
                                        size="sm"
                                        :items="tokenMenu(token)"
                                    />
                                </td>
                            </tr>
                            <tr v-if="product.tokens.length === 0">
                                <td colspan="5" class="py-6 text-center text-slate-500">{{ t('ui.developers.no_tokens') }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <template #footer>
                        <form class="flex flex-wrap items-end gap-3" @submit.prevent="createToken(product.key)">
                            <div class="min-w-56 flex-1">
                                <label :for="`token_name_${product.key}`">{{ t('ui.developers.new_token') }}</label>
                                <input :id="`token_name_${product.key}`" v-model="tokenForm.name" type="text" :placeholder="t('ui.developers.new_token_placeholder')" required />
                                <InputError :message="tokenForm.errors.name" />
                            </div>
                            <button
                                type="submit"
                                class="btn-primary"
                                :disabled="tokenForm.processing"
                            >
                                {{ t('ui.developers.generate') }}
                            </button>
                        </form>
                    </template>
                </CardSection>

                <CardSection
                    :title="t('ui.developers.webhooks_title', { product: product.name })"
                    :description="t('ui.developers.webhooks_hint')"
                >
                    <ul class="divide-y divide-slate-100">
                        <li
                            v-for="hook in product.webhooks"
                            :key="hook.id"
                            class="flex items-center justify-between gap-4 py-3"
                        >
                            <div class="min-w-0">
                                <p class="truncate font-mono text-sm text-slate-900">
                                    {{ hook.url }}
                                </p>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    {{ hook.events.join(', ') }} · {{ t('ui.developers.secret') }} {{ hook.secret_preview }}
                                </p>
                            </div>
                            <RowActions
                                :abilities="hook.can"
                                :base="`/developers/webhooks/${hook.id}`"
                                :name="hook.url"
                                size="sm"
                                @edit="editWebhook(hook)"
                            />
                        </li>
                        <li v-if="product.webhooks.length === 0" class="py-6 text-center text-sm text-slate-500">
                            {{ t('ui.developers.no_webhooks') }}
                        </li>
                    </ul>

                    <template #footer>
                        <form class="space-y-3" @submit.prevent="submitWebhook(product.key)">
                            <div class="flex flex-wrap items-end gap-3">
                                <div class="min-w-64 flex-1">
                                    <label :for="`hook_url_${product.key}`">{{ t('ui.developers.endpoint') }}</label>
                                    <input :id="`hook_url_${product.key}`" v-model="webhookForm.url" type="url" placeholder="https://projekt-a.sk/webhooks/accounts" required />
                                    <InputError :message="webhookForm.errors.url" />
                                </div>
                                <button
                                    type="submit"
                                    class="btn-primary"
                                    :disabled="webhookForm.processing"
                                >
                                    {{ editingWebhook ? t('ui.settings.save') : t('ui.settings.add') }}
                                </button>
                                <button v-if="editingWebhook" type="button" class="btn-secondary" @click="resetWebhookForm">
                                    {{ t('common.form.cancel') }}
                                </button>
                            </div>

                            <div class="flex flex-wrap gap-3">
                                <label
                                    v-for="event in available_events"
                                    :key="event"
                                    class="flex items-center gap-2 text-sm font-normal text-slate-600"
                                >
                                    <input v-model="webhookForm.events" type="checkbox" :value="event" class="rounded border-slate-300" />
                                    {{ event }}
                                </label>
                            </div>
                        </form>
                    </template>
                </CardSection>
            </div>
        </template>
    </div>
</template>
