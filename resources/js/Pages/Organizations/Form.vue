<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputError from '../../Components/InputError.vue';
import CardSection from '../../Components/CardSection.vue';
import { t } from '../../Composables/useLang';

const props = defineProps({
    organization: { type: Object, default: null },
    legal_forms: { type: Array, default: () => [] },
    subject_types: { type: Array, default: () => [] },
    vat_modes: { type: Array, default: () => [] },
});

const isEdit = Boolean(props.organization);
const o = props.organization ?? {};

const form = useForm({
    // identifikácia
    subject_type: o.subject_type ?? 'company',
    name: o.name ?? '',
    legal_name: o.legal_name ?? '',
    legal_form: o.legal_form ?? '',
    ico: o.ico ?? '',
    dic: o.dic ?? '',
    ic_dph: o.ic_dph ?? '',
    vat_mode: o.vat_mode ?? 'non_payer',
    oss_registered: o.oss_registered ?? false,
    // zápis v registri
    register_court: o.register_court ?? '',
    register_section: o.register_section ?? '',
    register_insert: o.register_insert ?? '',
    established_at: o.established_at ?? '',
    // sídlo
    street: o.street ?? '',
    street_no: o.street_no ?? '',
    city: o.city ?? '',
    postal_code: o.postal_code ?? '',
    region: o.region ?? '',
    country: o.country ?? 'SK',
    // kontakt
    email: o.email ?? '',
    billing_email: o.billing_email ?? '',
    phone: o.phone ?? '',
    website: o.website ?? '',
    // banka
    bank_name: o.bank_name ?? '',
    iban: o.iban ?? '',
    swift: o.swift ?? '',
    // fakturácia
    currency: o.currency ?? 'EUR',
    payment_terms_days: o.payment_terms_days ?? 14,
    payment_method: o.payment_method ?? 'transfer',
    invoice_language: o.invoice_language ?? 'sk',
    invoice_delivery: o.invoice_delivery ?? 'email',
    supplier_number: o.supplier_number ?? '',
    // interné
    status: o.status ?? 'active',
    note: o.note ?? '',
});

const lookupState = ref({ loading: false, message: null, ok: false });
// firma, ktorú s týmto IČO už máme – ponúkneme na ňu odkaz namiesto duplikátu
const existing = ref(null);
const vatState = ref({ loading: false, message: null, ok: false });

const vatDescription = computed(
    () => props.vat_modes.find((m) => m.value === form.vat_mode)?.description ?? '',
);

const needsVatNumber = computed(() => form.vat_mode !== 'non_payer');

/**
 * Súkromná osoba nemá IČO, DIČ ani zápis v registri – tie polia sa jej
 * nezobrazujú. Backend ich pri uložení aj tak vyprázdni, takže tu nejde
 * o kontrolu, ale o to, aby sa nikto nesnažil vypĺňať niečo, čo nemá.
 */
const isPerson = computed(() => form.subject_type === 'person');

const post = async (url, body) => {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        },
        credentials: 'same-origin',
        body: JSON.stringify(body),
    });

    return response.json();
};

// Najprv naša databáza, až potom register – SK z RPO, CZ z ARES
const lookupIco = async () => {
    if (!form.ico) return;

    lookupState.value = { loading: true, message: null, ok: false };
    existing.value = null;

    try {
        const data = await post('/lookup/ico', {
            ico: String(form.ico).replace(/\s/g, ''),
            country: (form.country || 'sk').toLowerCase(),
            // v edite je v `id` uuid – posielame ho, nech sa firma nehlási sama sebe
            exclude: props.organization?.id ?? null,
        });

        if (data.found) {
            const fill = (field, value) => { if (value) form[field] = value; };

            fill('legal_name', data.legal_name);
            fill('legal_form', data.legal_form);
            fill('dic', data.dic);
            fill('ic_dph', data.ic_dph);
            fill('street', data.street);
            fill('street_no', data.street_no);
            fill('city', data.city);
            fill('postal_code', data.postal_code);
            fill('region', data.region);
            fill('country', data.country);
            fill('register_court', data.register_court);
            fill('register_section', data.register_section);
            fill('register_insert', data.register_insert);

            if (!form.name) form.name = data.name ?? '';
            if (data.established_at) form.established_at = String(data.established_at).slice(0, 10);

            if (data.source === 'db') {
                existing.value = data.organization;
                lookupState.value = { loading: false, ok: false, message: null };
            } else {
                lookupState.value = {
                    loading: false,
                    ok: true,
                    message: t('organizations.form.lookup.filled', {
                        register: data.source === 'ares' ? 'ARES' : 'RPO',
                    }),
                };
            }
        } else {
            lookupState.value = {
                loading: false,
                ok: false,
                message: data.error ?? t('organizations.form.lookup.not_found'),
            };
        }
    } catch {
        lookupState.value = { loading: false, ok: false, message: t('organizations.form.lookup.registry_down') };
    }
};

const checkVat = async () => {
    if (!form.ic_dph) return;

    vatState.value = { loading: true, message: null, ok: false };

    try {
        const data = await post('/lookup/vat', { ic_dph: form.ic_dph });

        vatState.value = data.valid
            ? {
                loading: false,
                ok: true,
                message: `${t('organizations.form.lookup.vat_valid')}${data.name ? ` · ${data.name}` : ''}`,
            }
            : { loading: false, ok: false, message: data.error ?? t('organizations.form.lookup.vat_invalid') };
    } catch {
        vatState.value = { loading: false, ok: false, message: t('organizations.form.lookup.vies_down') };
    }
};

const submit = () => {
    isEdit ? form.put(`/organizations/${props.organization.id}`) : form.post('/organizations');
};
</script>

<template>
    <Head :title="isEdit ? t('organizations.form.edit') : t('organizations.form.create')" />

    <form class="mx-auto max-w-3xl space-y-6" @submit.prevent="submit">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">
                {{ isEdit ? t('organizations.form.edit') : t('organizations.form.create') }}
            </h1>
            <p class="mt-1.5 text-sm text-slate-500">{{ t('organizations.form.intro') }}</p>
        </div>

        <!-- Typ zákazníka -->
        <CardSection
            icon="user"
            :title="t('organizations.form.subject.title')"
            :description="t('organizations.form.subject.description')"
        >
            <div class="flex flex-wrap gap-3">
                <label
                    v-for="type in subject_types"
                    :key="type.value"
                    class="flex flex-1 cursor-pointer gap-3 rounded-xl border p-4 font-normal transition"
                    :class="form.subject_type === type.value
                        ? 'border-brand-500 bg-brand-50/60 ring-1 ring-brand-500/20'
                        : 'border-slate-200 hover:border-slate-300'"
                >
                    <input v-model="form.subject_type" type="radio" :value="type.value" class="mt-0.5" />
                    <span class="min-w-0">
                        <span class="block text-sm font-medium text-slate-900">{{ type.label }}</span>
                        <span class="mt-0.5 block text-xs text-slate-500">{{ type.description }}</span>
                    </span>
                </label>
            </div>
            <InputError :message="form.errors.subject_type" />
        </CardSection>

        <!-- Identifikácia -->
        <CardSection
            icon="building"
            :title="t('organizations.form.identification.title')"
            :description="isPerson
                ? t('organizations.form.identification.description_person')
                : t('organizations.form.identification.description')"
        >
            <div class="grid gap-4 sm:grid-cols-6">
                <div v-if="!isPerson" class="sm:col-span-2">
                    <label for="ico">{{ t('organizations.form.fields.ico') }}</label>
                    <div class="flex gap-2">
                        <input
                            id="ico"
                            v-model="form.ico"
                            type="text"
                            inputmode="numeric"
                            placeholder="12345678"
                            @keyup.enter.prevent="lookupIco"
                        />
                        <button
                            type="button"
                            class="btn-secondary shrink-0"
                            :disabled="lookupState.loading || !form.ico"
                            @click="lookupIco"
                        >
                            {{ lookupState.loading ? '…' : t('organizations.form.lookup.fetch') }}
                        </button>
                    </div>
                    <p v-if="lookupState.message" class="mt-1.5 text-sm" :class="lookupState.ok ? 'text-emerald-600' : 'text-amber-600'">
                        {{ lookupState.message }}
                    </p>
                    <InputError :message="form.errors.ico" />

                    <div
                        v-if="existing"
                        class="mt-2 rounded-xl bg-amber-50 px-3 py-2.5 text-sm text-amber-800 ring-1 ring-amber-200/70"
                    >
                        <p>
                            {{ t('organizations.form.existing.intro') }}
                            <strong>{{ existing.name }}</strong>
                            <span v-if="existing.status === 'deleted'"> {{ t('organizations.form.existing.deleted') }}</span>
                            <span v-else-if="existing.status === 'archived'"> {{ t('organizations.form.existing.archived') }}</span>
                            <span v-else-if="existing.status === 'suspended'"> {{ t('organizations.form.existing.suspended') }}</span>.
                        </p>
                        <p class="mt-1">
                            {{ t('organizations.form.existing.filled') }}
                            <a :href="existing.url" class="font-medium underline underline-offset-2">
                                {{ t('organizations.form.existing.open') }}
                            </a>
                            {{ t('organizations.form.existing.duplicate') }}
                        </p>
                    </div>
                </div>

                <div :class="isPerson ? 'sm:col-span-6' : 'sm:col-span-4'">
                    <label for="name">
                        {{ isPerson ? t('organizations.form.fields.name_person') : t('organizations.form.fields.name') }}
                    </label>
                    <input id="name" v-model="form.name" type="text" required />
                    <InputError :message="form.errors.name" />
                </div>

                <div v-if="!isPerson" class="sm:col-span-4">
                    <label for="legal_name">
                        {{ t('organizations.form.fields.legal_name') }}
                        <span class="font-normal text-slate-400">{{ t('organizations.form.fields.legal_name_hint') }}</span>
                    </label>
                    <input
                        id="legal_name"
                        v-model="form.legal_name"
                        type="text"
                        :placeholder="t('organizations.form.placeholders.legal_name')"
                    />
                    <InputError :message="form.errors.legal_name" />
                </div>

                <div v-if="!isPerson" class="sm:col-span-2">
                    <label for="legal_form">{{ t('organizations.form.fields.legal_form') }}</label>
                    <select id="legal_form" v-model="form.legal_form">
                        <option value="">—</option>
                        <option v-for="f in legal_forms" :key="f.value" :value="f.value">{{ f.label }}</option>
                    </select>
                    <InputError :message="form.errors.legal_form" />
                </div>

                <div v-if="!isPerson" class="sm:col-span-2">
                    <label for="dic">{{ t('organizations.form.fields.dic') }}</label>
                    <input id="dic" v-model="form.dic" type="text" placeholder="2020123456" />
                    <InputError :message="form.errors.dic" />
                </div>

                <div v-if="!isPerson" class="sm:col-span-4">
                    <label for="vat_mode">{{ t('organizations.form.fields.vat_mode') }}</label>
                    <select id="vat_mode" v-model="form.vat_mode">
                        <option v-for="m in vat_modes" :key="m.value" :value="m.value">{{ m.label }}</option>
                    </select>
                    <p class="mt-1.5 text-xs text-slate-500">{{ vatDescription }}</p>
                    <InputError :message="form.errors.vat_mode" />
                </div>

                <div v-if="needsVatNumber && !isPerson" class="sm:col-span-3">
                    <label for="ic_dph">{{ t('organizations.form.fields.ic_dph') }}</label>
                    <div class="flex gap-2">
                        <input id="ic_dph" v-model="form.ic_dph" type="text" placeholder="SK2020123456" />
                        <button
                            type="button"
                            class="btn-secondary shrink-0"
                            :disabled="vatState.loading || !form.ic_dph"
                            @click="checkVat"
                        >
                            {{ vatState.loading ? '…' : t('organizations.form.lookup.verify') }}
                        </button>
                    </div>
                    <p v-if="vatState.message" class="mt-1.5 text-sm" :class="vatState.ok ? 'text-emerald-600' : 'text-amber-600'">
                        {{ vatState.message }}
                    </p>
                    <InputError :message="form.errors.ic_dph" />
                </div>

                <div v-if="needsVatNumber && !isPerson" class="sm:col-span-3 flex items-end pb-2.5">
                    <label class="flex items-center gap-2.5 text-sm font-normal text-slate-600">
                        <input v-model="form.oss_registered" type="checkbox" />
                        {{ t('organizations.form.fields.oss') }}
                    </label>
                </div>
            </div>
        </CardSection>

        <!-- Zápis v registri -->
        <CardSection
            v-if="!isPerson"
            icon="shield"
            tone="slate"
            :title="t('organizations.form.register.title')"
            :description="t('organizations.form.register.description')"
        >
            <div class="grid gap-4 sm:grid-cols-6">
                <div class="sm:col-span-3">
                    <label for="register_court">{{ t('organizations.form.fields.register_court') }}</label>
                    <input
                        id="register_court"
                        v-model="form.register_court"
                        type="text"
                        :placeholder="t('organizations.form.placeholders.register_court')"
                    />
                    <InputError :message="form.errors.register_court" />
                </div>
                <div class="sm:col-span-1">
                    <label for="register_section">{{ t('organizations.form.fields.register_section') }}</label>
                    <input id="register_section" v-model="form.register_section" type="text" placeholder="Sro" />
                </div>
                <div class="sm:col-span-1">
                    <label for="register_insert">{{ t('organizations.form.fields.register_insert') }}</label>
                    <input id="register_insert" v-model="form.register_insert" type="text" placeholder="12345/B" />
                </div>
                <div class="sm:col-span-1">
                    <label for="established_at">{{ t('organizations.form.fields.established_at') }}</label>
                    <input id="established_at" v-model="form.established_at" type="date" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" />
                    <InputError :message="form.errors.established_at" />
                </div>
            </div>
        </CardSection>

        <!-- Sídlo -->
        <CardSection
            icon="home"
            tone="emerald"
            :title="isPerson ? t('organizations.form.address.title_person') : t('organizations.form.address.title')"
            :description="isPerson
                ? t('organizations.form.address.description_person')
                : t('organizations.form.address.description')"
        >
            <div class="grid gap-4 sm:grid-cols-6">
                <div class="sm:col-span-4">
                    <label for="street">{{ t('organizations.form.fields.street') }}</label>
                    <input
                        id="street"
                        v-model="form.street"
                        type="text"
                        :placeholder="t('organizations.form.placeholders.street')"
                    />
                    <InputError :message="form.errors.street" />
                </div>
                <div class="sm:col-span-2">
                    <label for="street_no">{{ t('organizations.form.fields.street_no') }}</label>
                    <input id="street_no" v-model="form.street_no" type="text" placeholder="12/A" />
                    <InputError :message="form.errors.street_no" />
                </div>
                <div class="sm:col-span-2">
                    <label for="postal_code">{{ t('organizations.form.fields.postal_code') }}</label>
                    <input id="postal_code" v-model="form.postal_code" type="text" placeholder="81101" />
                    <InputError :message="form.errors.postal_code" />
                </div>
                <div class="sm:col-span-2">
                    <label for="city">{{ t('organizations.form.fields.city') }}</label>
                    <input id="city" v-model="form.city" type="text" />
                    <InputError :message="form.errors.city" />
                </div>
                <div class="sm:col-span-1">
                    <label for="country">{{ t('organizations.form.fields.country') }}</label>
                    <input id="country" v-model="form.country" type="text" maxlength="2" placeholder="SK" />
                    <InputError :message="form.errors.country" />
                </div>
                <div class="sm:col-span-3">
                    <label for="region">{{ t('organizations.form.fields.region') }}</label>
                    <input
                        id="region"
                        v-model="form.region"
                        type="text"
                        :placeholder="t('organizations.form.placeholders.region')"
                    />
                </div>
            </div>

            <p v-if="isEdit" class="mt-4 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600 ring-1 ring-slate-200/70">
                {{ t('organizations.form.address.more') }}
            </p>
        </CardSection>

        <!-- Kontakt a banka -->
        <CardSection icon="user" :title="t('organizations.form.contact.title')">
            <div class="grid gap-4 sm:grid-cols-6">
                <div class="sm:col-span-3">
                    <label for="email">{{ t('organizations.form.fields.email') }}</label>
                    <input id="email" v-model="form.email" type="email" />
                    <InputError :message="form.errors.email" />
                </div>
                <div class="sm:col-span-3">
                    <label for="billing_email">{{ t('organizations.form.fields.billing_email') }}</label>
                    <input id="billing_email" v-model="form.billing_email" type="email" />
                    <InputError :message="form.errors.billing_email" />
                </div>
                <div class="sm:col-span-3">
                    <label for="phone">{{ t('organizations.form.fields.phone') }}</label>
                    <input id="phone" v-model="form.phone" type="text" placeholder="+421 900 000 000" />
                </div>
                <div class="sm:col-span-3">
                    <label for="website">{{ t('organizations.form.fields.website') }}</label>
                    <input id="website" v-model="form.website" type="url" placeholder="https://firma.sk" />
                    <InputError :message="form.errors.website" />
                </div>
                <div class="sm:col-span-2">
                    <label for="bank_name">{{ t('organizations.form.fields.bank_name') }}</label>
                    <input
                        id="bank_name"
                        v-model="form.bank_name"
                        type="text"
                        :placeholder="t('organizations.form.placeholders.bank_name')"
                    />
                </div>
                <div class="sm:col-span-3">
                    <label for="iban">{{ t('organizations.form.fields.iban') }}</label>
                    <input id="iban" v-model="form.iban" type="text" placeholder="SK31 1200 0000 1987 4263 7541" />
                    <InputError :message="form.errors.iban" />
                </div>
                <div class="sm:col-span-1">
                    <label for="swift">{{ t('organizations.form.fields.swift') }}</label>
                    <input id="swift" v-model="form.swift" type="text" placeholder="TATRSKBX" />
                </div>
            </div>
        </CardSection>

        <!-- Fakturačné preferencie -->
        <CardSection icon="card" tone="amber" :title="t('organizations.form.billing.title')">
            <div class="grid gap-4 sm:grid-cols-6">
                <div class="sm:col-span-2">
                    <label for="payment_terms_days">{{ t('organizations.form.fields.payment_terms_days') }}</label>
                    <input id="payment_terms_days" v-model.number="form.payment_terms_days" type="number" min="0" max="180" />
                    <InputError :message="form.errors.payment_terms_days" />
                </div>
                <div class="sm:col-span-2">
                    <label for="payment_method">{{ t('organizations.form.fields.payment_method') }}</label>
                    <select id="payment_method" v-model="form.payment_method">
                        <option value="transfer">{{ t('organizations.form.billing.payment_methods.transfer') }}</option>
                        <option value="card">{{ t('organizations.form.billing.payment_methods.card') }}</option>
                        <option value="cash">{{ t('organizations.form.billing.payment_methods.cash') }}</option>
                        <option value="cod">{{ t('organizations.form.billing.payment_methods.cod') }}</option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label for="currency">{{ t('organizations.form.fields.currency') }}</label>
                    <input id="currency" v-model="form.currency" type="text" maxlength="3" placeholder="EUR" />
                </div>
                <div class="sm:col-span-2">
                    <label for="invoice_delivery">{{ t('organizations.form.fields.invoice_delivery') }}</label>
                    <select id="invoice_delivery" v-model="form.invoice_delivery">
                        <option value="email">{{ t('organizations.form.billing.delivery.email') }}</option>
                        <option value="post">{{ t('organizations.form.billing.delivery.post') }}</option>
                        <option value="both">{{ t('organizations.form.billing.delivery.both') }}</option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label for="invoice_language">{{ t('organizations.form.fields.invoice_language') }}</label>
                    <select id="invoice_language" v-model="form.invoice_language">
                        <option value="sk">{{ t('organizations.form.billing.languages.sk') }}</option>
                        <option value="en">{{ t('organizations.form.billing.languages.en') }}</option>
                        <option value="de">{{ t('organizations.form.billing.languages.de') }}</option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label for="supplier_number">{{ t('organizations.form.fields.supplier_number') }}</label>
                    <input id="supplier_number" v-model="form.supplier_number" type="text" placeholder="DOD-2024-118" />
                </div>
            </div>
        </CardSection>

        <!-- Interné -->
        <CardSection
            icon="settings"
            tone="slate"
            :title="t('organizations.form.internal.title')"
            :description="t('organizations.form.internal.description')"
        >
            <div class="grid gap-4">
                <div class="sm:w-64">
                    <label for="status">{{ t('organizations.form.fields.status') }}</label>
                    <select id="status" v-model="form.status">
                        <option value="active">{{ t('organizations.form.internal.statuses.active') }}</option>
                        <option value="suspended">{{ t('organizations.form.internal.statuses.suspended') }}</option>
                        <option value="archived">{{ t('organizations.form.internal.statuses.archived') }}</option>
                    </select>
                    <InputError :message="form.errors.status" />
                </div>
                <div>
                    <label for="note">{{ t('organizations.form.fields.note') }}</label>
                    <textarea id="note" v-model="form.note" rows="3"></textarea>
                    <InputError :message="form.errors.note" />
                </div>
            </div>
        </CardSection>

        <div class="flex items-center justify-end gap-3 pb-4">
            <Link href="/organizations" class="text-sm text-slate-500 hover:text-slate-900">
                {{ t('common.form.cancel') }}
            </Link>
            <button type="submit" class="btn-primary" :disabled="form.processing">
                {{ form.processing ? t('common.form.saving') : t('common.form.save') }}
            </button>
        </div>
    </form>
</template>
