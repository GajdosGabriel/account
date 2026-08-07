<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputError from '../../Components/InputError.vue';
import CardSection from '../../Components/CardSection.vue';

const props = defineProps({
    organization: { type: Object, default: null },
    legal_forms: { type: Array, default: () => [] },
    vat_modes: { type: Array, default: () => [] },
});

const isEdit = Boolean(props.organization);
const o = props.organization ?? {};

const form = useForm({
    // identifikácia
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
                    message: `Údaje doplnené z registra ${data.source === 'ares' ? 'ARES' : 'RPO'}.`,
                };
            }
        } else {
            lookupState.value = { loading: false, ok: false, message: data.error ?? 'IČO sa nenašlo.' };
        }
    } catch {
        lookupState.value = { loading: false, ok: false, message: 'Register je nedostupný.' };
    }
};

const checkVat = async () => {
    if (!form.ic_dph) return;

    vatState.value = { loading: true, message: null, ok: false };

    try {
        const data = await post('/lookup/vat', { ic_dph: form.ic_dph });

        vatState.value = data.valid
            ? { loading: false, ok: true, message: `Platné vo VIES${data.name ? ` · ${data.name}` : ''}` }
            : { loading: false, ok: false, message: data.error ?? 'IČ DPH nie je platné.' };
    } catch {
        vatState.value = { loading: false, ok: false, message: 'VIES je nedostupný.' };
    }
};

const submit = () => {
    isEdit ? form.put(`/organizations/${props.organization.id}`) : form.post('/organizations');
};
</script>

<template>
    <Head :title="isEdit ? 'Úprava organizácie' : 'Nová organizácia'" />

    <form class="mx-auto max-w-3xl space-y-6" @submit.prevent="submit">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">
                {{ isEdit ? 'Úprava organizácie' : 'Nová organizácia' }}
            </h1>
            <p class="mt-1.5 text-sm text-slate-500">
                Tieto údaje používajú všetky pripojené projekty aj fakturácia.
            </p>
        </div>

        <!-- Identifikácia -->
        <CardSection icon="building" title="Identifikácia" description="IČO načítame z registra (SK: RPO, CZ: ARES), IČ DPH overíme vo VIES.">
            <div class="grid gap-4 sm:grid-cols-6">
                <div class="sm:col-span-2">
                    <label for="ico">IČO</label>
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
                            {{ lookupState.loading ? '…' : 'Načítať' }}
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
                            Firmu s týmto IČO už máme v databáze —
                            <strong>{{ existing.name }}</strong>
                            <span v-if="existing.status === 'deleted'"> (zmazaná)</span>
                            <span v-else-if="existing.status === 'archived'"> (archivovaná)</span>
                            <span v-else-if="existing.status === 'suspended'"> (pozastavená)</span>.
                        </p>
                        <p class="mt-1">
                            Údaje sme doplnili z nej.
                            <a :href="existing.url" class="font-medium underline underline-offset-2">Otvoriť existujúcu</a>
                            — nové uloženie by vytvorilo duplikát.
                        </p>
                    </div>
                </div>

                <div class="sm:col-span-4">
                    <label for="name">Zobrazovaný názov</label>
                    <input id="name" v-model="form.name" type="text" required />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="sm:col-span-4">
                    <label for="legal_name">Obchodné meno <span class="font-normal text-slate-400">— presne ako v registri</span></label>
                    <input id="legal_name" v-model="form.legal_name" type="text" placeholder="Firma, s. r. o." />
                    <InputError :message="form.errors.legal_name" />
                </div>

                <div class="sm:col-span-2">
                    <label for="legal_form">Právna forma</label>
                    <select id="legal_form" v-model="form.legal_form">
                        <option value="">—</option>
                        <option v-for="f in legal_forms" :key="f.value" :value="f.value">{{ f.label }}</option>
                    </select>
                    <InputError :message="form.errors.legal_form" />
                </div>

                <div class="sm:col-span-2">
                    <label for="dic">DIČ</label>
                    <input id="dic" v-model="form.dic" type="text" placeholder="2020123456" />
                    <InputError :message="form.errors.dic" />
                </div>

                <div class="sm:col-span-4">
                    <label for="vat_mode">Vzťah k DPH</label>
                    <select id="vat_mode" v-model="form.vat_mode">
                        <option v-for="m in vat_modes" :key="m.value" :value="m.value">{{ m.label }}</option>
                    </select>
                    <p class="mt-1.5 text-xs text-slate-500">{{ vatDescription }}</p>
                    <InputError :message="form.errors.vat_mode" />
                </div>

                <div v-if="needsVatNumber" class="sm:col-span-3">
                    <label for="ic_dph">IČ DPH</label>
                    <div class="flex gap-2">
                        <input id="ic_dph" v-model="form.ic_dph" type="text" placeholder="SK2020123456" />
                        <button
                            type="button"
                            class="btn-secondary shrink-0"
                            :disabled="vatState.loading || !form.ic_dph"
                            @click="checkVat"
                        >
                            {{ vatState.loading ? '…' : 'Overiť' }}
                        </button>
                    </div>
                    <p v-if="vatState.message" class="mt-1.5 text-sm" :class="vatState.ok ? 'text-emerald-600' : 'text-amber-600'">
                        {{ vatState.message }}
                    </p>
                    <InputError :message="form.errors.ic_dph" />
                </div>

                <div v-if="needsVatNumber" class="sm:col-span-3 flex items-end pb-2.5">
                    <label class="flex items-center gap-2.5 text-sm font-normal text-slate-600">
                        <input v-model="form.oss_registered" type="checkbox" />
                        Registrovaná v OSS (predaj do EÚ)
                    </label>
                </div>
            </div>
        </CardSection>

        <!-- Zápis v registri -->
        <CardSection icon="shield" tone="slate" title="Zápis v registri" description="Povinný údaj v päticke faktúry aj obchodnej korešpondencie.">
            <div class="grid gap-4 sm:grid-cols-6">
                <div class="sm:col-span-3">
                    <label for="register_court">Registrový súd / úrad</label>
                    <input id="register_court" v-model="form.register_court" type="text" placeholder="Okresný súd Bratislava I" />
                    <InputError :message="form.errors.register_court" />
                </div>
                <div class="sm:col-span-1">
                    <label for="register_section">Oddiel</label>
                    <input id="register_section" v-model="form.register_section" type="text" placeholder="Sro" />
                </div>
                <div class="sm:col-span-1">
                    <label for="register_insert">Vložka</label>
                    <input id="register_insert" v-model="form.register_insert" type="text" placeholder="12345/B" />
                </div>
                <div class="sm:col-span-1">
                    <label for="established_at">Vznik</label>
                    <input id="established_at" v-model="form.established_at" type="date" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" />
                    <InputError :message="form.errors.established_at" />
                </div>
            </div>
        </CardSection>

        <!-- Sídlo -->
        <CardSection
            icon="home"
            tone="emerald"
            title="Sídlo / miesto podnikania"
            description="Adresa ako na živnostenskom liste alebo vo výpise z obchodného registra."
        >
            <div class="grid gap-4 sm:grid-cols-6">
                <div class="sm:col-span-4">
                    <label for="street">Ulica</label>
                    <input id="street" v-model="form.street" type="text" placeholder="Hlavná" />
                    <InputError :message="form.errors.street" />
                </div>
                <div class="sm:col-span-2">
                    <label for="street_no">Číslo</label>
                    <input id="street_no" v-model="form.street_no" type="text" placeholder="12/A" />
                    <InputError :message="form.errors.street_no" />
                </div>
                <div class="sm:col-span-2">
                    <label for="postal_code">PSČ</label>
                    <input id="postal_code" v-model="form.postal_code" type="text" placeholder="81101" />
                    <InputError :message="form.errors.postal_code" />
                </div>
                <div class="sm:col-span-2">
                    <label for="city">Mesto</label>
                    <input id="city" v-model="form.city" type="text" />
                    <InputError :message="form.errors.city" />
                </div>
                <div class="sm:col-span-1">
                    <label for="country">Krajina</label>
                    <input id="country" v-model="form.country" type="text" maxlength="2" placeholder="SK" />
                    <InputError :message="form.errors.country" />
                </div>
                <div class="sm:col-span-3">
                    <label for="region">Kraj</label>
                    <input id="region" v-model="form.region" type="text" placeholder="Bratislavský kraj" />
                </div>
            </div>

            <p v-if="isEdit" class="mt-4 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600 ring-1 ring-slate-200/70">
                Adresu na zasielanie pošty, dodacie adresy a prevádzkarne pridáš na detaile organizácie.
            </p>
        </CardSection>

        <!-- Kontakt a banka -->
        <CardSection icon="user" title="Kontakt a bankové spojenie">
            <div class="grid gap-4 sm:grid-cols-6">
                <div class="sm:col-span-3">
                    <label for="email">Všeobecný e-mail</label>
                    <input id="email" v-model="form.email" type="email" />
                    <InputError :message="form.errors.email" />
                </div>
                <div class="sm:col-span-3">
                    <label for="billing_email">E-mail na faktúry</label>
                    <input id="billing_email" v-model="form.billing_email" type="email" />
                    <InputError :message="form.errors.billing_email" />
                </div>
                <div class="sm:col-span-3">
                    <label for="phone">Telefón</label>
                    <input id="phone" v-model="form.phone" type="text" placeholder="+421 900 000 000" />
                </div>
                <div class="sm:col-span-3">
                    <label for="website">Web</label>
                    <input id="website" v-model="form.website" type="url" placeholder="https://firma.sk" />
                    <InputError :message="form.errors.website" />
                </div>
                <div class="sm:col-span-2">
                    <label for="bank_name">Banka</label>
                    <input id="bank_name" v-model="form.bank_name" type="text" placeholder="Tatra banka" />
                </div>
                <div class="sm:col-span-3">
                    <label for="iban">IBAN</label>
                    <input id="iban" v-model="form.iban" type="text" placeholder="SK31 1200 0000 1987 4263 7541" />
                    <InputError :message="form.errors.iban" />
                </div>
                <div class="sm:col-span-1">
                    <label for="swift">SWIFT</label>
                    <input id="swift" v-model="form.swift" type="text" placeholder="TATRSKBX" />
                </div>
            </div>
        </CardSection>

        <!-- Fakturačné preferencie -->
        <CardSection icon="card" tone="amber" title="Fakturačné preferencie">
            <div class="grid gap-4 sm:grid-cols-6">
                <div class="sm:col-span-2">
                    <label for="payment_terms_days">Splatnosť (dni)</label>
                    <input id="payment_terms_days" v-model.number="form.payment_terms_days" type="number" min="0" max="180" />
                    <InputError :message="form.errors.payment_terms_days" />
                </div>
                <div class="sm:col-span-2">
                    <label for="payment_method">Spôsob platby</label>
                    <select id="payment_method" v-model="form.payment_method">
                        <option value="transfer">Prevodom</option>
                        <option value="card">Kartou</option>
                        <option value="cash">Hotovosť</option>
                        <option value="cod">Dobierka</option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label for="currency">Mena</label>
                    <input id="currency" v-model="form.currency" type="text" maxlength="3" placeholder="EUR" />
                </div>
                <div class="sm:col-span-2">
                    <label for="invoice_delivery">Doručovanie faktúr</label>
                    <select id="invoice_delivery" v-model="form.invoice_delivery">
                        <option value="email">E-mailom</option>
                        <option value="post">Poštou</option>
                        <option value="both">E-mailom aj poštou</option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label for="invoice_language">Jazyk faktúry</label>
                    <select id="invoice_language" v-model="form.invoice_language">
                        <option value="sk">Slovenčina</option>
                        <option value="en">Angličtina</option>
                        <option value="de">Nemčina</option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label for="supplier_number">Naše číslo u zákazníka</label>
                    <input id="supplier_number" v-model="form.supplier_number" type="text" placeholder="DOD-2024-118" />
                </div>
            </div>
        </CardSection>

        <!-- Interné -->
        <CardSection icon="settings" tone="slate" title="Interné" description="Vidí len prevádzkovateľ, do projektov sa neposiela.">
            <div class="grid gap-4">
                <div class="sm:w-64">
                    <label for="status">Stav organizácie</label>
                    <select id="status" v-model="form.status">
                        <option value="active">Aktívna</option>
                        <option value="suspended">Pozastavená — projekty ju zamknú</option>
                        <option value="archived">Archivovaná</option>
                    </select>
                    <InputError :message="form.errors.status" />
                </div>
                <div>
                    <label for="note">Poznámka</label>
                    <textarea id="note" v-model="form.note" rows="3"></textarea>
                    <InputError :message="form.errors.note" />
                </div>
            </div>
        </CardSection>

        <div class="flex items-center justify-end gap-3 pb-4">
            <Link href="/organizations" class="text-sm text-slate-500 hover:text-slate-900">Zrušiť</Link>
            <button type="submit" class="btn-primary" :disabled="form.processing">
                {{ form.processing ? 'Ukladám…' : 'Uložiť' }}
            </button>
        </div>
    </form>
</template>
