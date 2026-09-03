<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import CardSection from '../../Components/CardSection.vue';
import InputError from '../../Components/InputError.vue';
import RowActions from '../../Components/RowActions.vue';
import { tokenMenu } from '../../Composables/useTokenActions';

const props = defineProps({
    products: { type: Array, default: () => [] },
    available_events: { type: Array, default: () => [] },
});

const openProduct = ref(props.products[0]?.key ?? null);

const tokenForm = useForm({ product_key: props.products[0]?.key ?? '', name: '' });
const webhookForm = useForm({ product_key: props.products[0]?.key ?? '', url: '', events: [] });

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
    <Head title="Projekty a API" />

    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">API a webhooky</h1>
            <p class="mt-1.5 text-sm text-slate-500">
                Service tokeny, ktorými sa projekty autentifikujú, a odosielanie udalostí.
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <button
                v-for="product in products"
                :key="product.key"
                type="button"
                class="btn btn-sm"
                :class="openProduct === product.key
                    ? 'bg-brand-600 text-white'
                    : 'border border-slate-300 text-slate-700 hover:bg-white'"
                @click="openProduct = product.key"
            >
                {{ product.name }}
            </button>
        </div>

        <template v-for="product in products" :key="product.key">
            <div v-if="openProduct === product.key" class="space-y-6">
                <CardSection
                    title="Service tokeny"
                    description="Server-to-server prístup na /api/v1. Token sa zobrazí iba raz pri vytvorení."
                >
                    <table class="w-full text-left text-sm">
                        <thead class="text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="pb-2 font-medium">Popis</th>
                                <th class="pb-2 font-medium">Prefix</th>
                                <th class="pb-2 font-medium">Oprávnenia</th>
                                <th class="pb-2 font-medium">Naposledy</th>
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
                                    <span v-if="token.revoked" class="ml-1.5 text-xs font-normal text-rose-500">zrušený</span>
                                </td>
                                <td class="py-2.5 font-mono text-xs text-slate-600">{{ token.prefix }}…</td>
                                <td class="py-2.5 text-slate-600">{{ token.abilities.join(', ') }}</td>
                                <td class="py-2.5 text-slate-600">{{ token.last_used_at ?? 'nikdy' }}</td>
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
                                <td colspan="5" class="py-6 text-center text-slate-500">Žiadne tokeny.</td>
                            </tr>
                        </tbody>
                    </table>

                    <template #footer>
                        <form class="flex flex-wrap items-end gap-3" @submit.prevent="createToken(product.key)">
                            <div class="min-w-56 flex-1">
                                <label :for="`token_name_${product.key}`">Popis nového tokenu</label>
                                <input :id="`token_name_${product.key}`" v-model="tokenForm.name" type="text" placeholder="produkčný server" required />
                                <InputError :message="tokenForm.errors.name" />
                            </div>
                            <button
                                type="submit"
                                class="btn-primary"
                                :disabled="tokenForm.processing"
                            >
                                Vygenerovať
                            </button>
                        </form>
                    </template>
                </CardSection>

                <CardSection
                    title="Webhooky"
                    description="Sem posielame zmeny organizácie a predplatného. Podpis nájdete v hlavičke X-Accounts-Signature."
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
                                    {{ hook.events.join(', ') }} · kľúč {{ hook.secret_preview }}
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
                            Žiadne webhooky.
                        </li>
                    </ul>

                    <template #footer>
                        <form class="space-y-3" @submit.prevent="submitWebhook(product.key)">
                            <div class="flex flex-wrap items-end gap-3">
                                <div class="min-w-64 flex-1">
                                    <label :for="`hook_url_${product.key}`">URL endpointu</label>
                                    <input :id="`hook_url_${product.key}`" v-model="webhookForm.url" type="url" placeholder="https://projekt-a.sk/webhooks/accounts" required />
                                    <InputError :message="webhookForm.errors.url" />
                                </div>
                                <button
                                    type="submit"
                                    class="btn-primary"
                                    :disabled="webhookForm.processing"
                                >
                                    {{ editingWebhook ? 'Uložiť zmeny' : 'Pridať' }}
                                </button>
                                <button v-if="editingWebhook" type="button" class="btn-secondary" @click="resetWebhookForm">
                                    Zrušiť
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
