<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import CardSection from '../../Components/CardSection.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import PageHeader from '../../Components/PageHeader.vue';
import InputError from '../../Components/InputError.vue';
import Icon from '../../Components/Icon.vue';
import DropdownMenu from '../../Components/DropdownMenu.vue';
import RowActions from '../../Components/RowActions.vue';
import { invoiceMenu } from '../../Composables/useInvoiceActions';
import { t } from '../../Composables/useLang';

const props = defineProps({
    organization: { type: Object, required: true },
    addresses: { type: Array, default: () => [] },
    contacts: { type: Array, default: () => [] },
    address_types: { type: Array, default: () => [] },
    contact_types: { type: Array, default: () => [] },
    products: { type: Array, default: () => [] },
    overrides: { type: Array, default: () => [] },
    invoices: { type: Array, default: () => [] },
    billing_summary: { type: Object, default: () => ({ total: 0, outstanding_cents: 0, outstanding: '0,00 €' }) },
});

const verifying = ref(false);
const overrideOpen = ref(false);

const resending = ref(false);

const resendVerification = () => router.post(
    `/organizations/${props.organization.id}/billing-email/resend`,
    {},
    {
        preserveScroll: true,
        onStart: () => { resending.value = true; },
        onFinish: () => { resending.value = false; },
    },
);

const addressOpen = ref(false);
const contactOpen = ref(false);

const addressForm = useForm({
    type: 'mailing', label: '', recipient: '',
    street: '', street_no: '', city: '', postal_code: '', region: '', country: 'SK',
    phone: '', note: '', is_default: true,
});

const contactForm = useForm({
    type: 'general', name: '', position: '', email: '', phone: '', note: '', is_primary: false,
});

const overrideForm = useForm({
    product_key: props.products[0]?.key ?? '',
    feature: '',
    value: '',
    expires_at: '',
    note: '',
});

const base = `/organizations/${props.organization.id}`;

const toggleProduct = (key) => router.post(`${base}/products/${key}`, {}, { preserveScroll: true });
const subscribe = (planId) => router.post(`${base}/subscribe`, { plan_id: planId }, { preserveScroll: true });
const cancel = (key) => confirm('Naozaj zrušiť predplatné?') && router.post(`${base}/${key}/cancel`, {}, { preserveScroll: true });
const activate = (key) => router.post(`${base}/${key}/activate`, {}, { preserveScroll: true });

/* ---------- výnimky, adresy a kontakty ----------
 * Formulár slúži na pridanie aj na úpravu – rozhoduje `editing*`.
 * Mazanie a kôš rieši RowActions podľa policy, tu už nie sú.
 */

const editingOverride = ref(null);
const editingAddress = ref(null);
const editingContact = ref(null);

const editOverride = (override) => {
    editingOverride.value = override.id;
    overrideOpen.value = true;
    overrideForm.clearErrors();
    overrideForm.product_key = override.product_key;
    overrideForm.feature = override.feature;
    overrideForm.value = override.value ?? '';
    overrideForm.expires_at = override.expires_at ?? '';
    overrideForm.note = override.note ?? '';
};

const closeOverride = () => {
    editingOverride.value = null;
    overrideOpen.value = false;
    overrideForm.reset();
    overrideForm.clearErrors();
};

// Výnimka je jednoznačná dvojicou projekt + funkcia, preto ju server
// ukladá cez updateOrCreate a úprava je to isté volanie ako pridanie.
const saveOverride = () => overrideForm.post(`${base}/overrides`, {
    preserveScroll: true,
    onSuccess: () => closeOverride(),
});

const editAddress = (address) => {
    editingAddress.value = address.id;
    addressOpen.value = true;
    addressForm.clearErrors();

    Object.assign(addressForm, {
        type: address.type,
        label: address.label ?? '',
        recipient: address.recipient ?? '',
        street: address.street ?? '',
        street_no: address.street_no ?? '',
        city: address.city ?? '',
        postal_code: address.postal_code ?? '',
        region: address.region ?? '',
        country: address.country ?? 'SK',
        phone: address.phone ?? '',
        note: address.note ?? '',
        is_default: address.is_default,
    });
};

const closeAddress = () => {
    editingAddress.value = null;
    addressOpen.value = false;
    addressForm.reset();
    addressForm.clearErrors();
};

const saveAddress = () => {
    const options = { preserveScroll: true, onSuccess: () => closeAddress() };

    editingAddress.value
        ? addressForm.patch(`${base}/addresses/${editingAddress.value}`, options)
        : addressForm.post(`${base}/addresses`, options);
};

const editContact = (contact) => {
    editingContact.value = contact.id;
    contactOpen.value = true;
    contactForm.clearErrors();

    Object.assign(contactForm, {
        type: contact.type,
        name: contact.name,
        position: contact.position ?? '',
        email: contact.email ?? '',
        phone: contact.phone ?? '',
        note: contact.note ?? '',
        is_primary: contact.is_primary,
    });
};

const closeContact = () => {
    editingContact.value = null;
    contactOpen.value = false;
    contactForm.reset();
    contactForm.clearErrors();
};

const saveContact = () => {
    const options = { preserveScroll: true, onSuccess: () => closeContact() };

    editingContact.value
        ? contactForm.patch(`${base}/contacts/${editingContact.value}`, options)
        : contactForm.post(`${base}/contacts`, options);
};

const reverify = async () => {
    verifying.value = true;
    try {
        await fetch(`${base}/reverify`, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            credentials: 'same-origin',
        });
        router.reload();
    } finally {
        verifying.value = false;
    }
};

// funkcie vybraného projektu pre formulár výnimky
const featuresOf = (key) => props.products.find((p) => p.key === key)?.features ?? [];
</script>

<template>
    <Head :title="organization.name" />

    <PageHeader :title="organization.name" :subtitle="organization.legal_name || organization.address || '—'">
        <template #action>
            <button type="button" class="btn-secondary" :disabled="verifying" @click="reverify">
                <Icon name="refresh" :size="16" />
                {{ verifying ? 'Overujem…' : 'Overiť v registroch' }}
            </button>
            <Link :href="`${base}/edit`" class="btn-primary">{{ t('actions.edit') }}</Link>
            <RowActions
                :abilities="{ ...organization.can, view: false }"
                :trashed="!!organization.deleted_at"
                :base="base"
                :name="organization.name"
                :edit-href="`${base}/edit`"
                :label="t('actions.menu')"
            />
        </template>
    </PageHeader>

    <div class="space-y-6">
        <div class="grid gap-6 lg:grid-cols-3">
            <CardSection class="lg:col-span-2" icon="building" title="Firemné údaje">
                <div
                    v-if="organization.missing_billing.length"
                    class="mb-5 flex items-start gap-2.5 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-amber-600/15"
                >
                    <Icon name="warning" :size="18" class="mt-0.5 text-amber-600" />
                    <span>Na vystavenie faktúry chýba: <strong>{{ organization.missing_billing.join(', ') }}</strong></span>
                </div>

                <dl class="grid gap-x-6 gap-y-3.5 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-slate-500">Obchodné meno</dt>
                        <dd class="mt-0.5 font-medium text-slate-900">
                            {{ organization.legal_name || organization.name }}
                            <span v-if="organization.legal_form" class="text-slate-400">· {{ organization.legal_form }}</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">IČO</dt>
                        <dd class="mt-0.5 font-medium text-slate-900">
                            {{ organization.ico ?? '—' }}
                            <span v-if="organization.ico_verified_at" class="ml-1 text-xs font-normal text-emerald-600">overené</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">DIČ</dt>
                        <dd class="mt-0.5 font-medium text-slate-900">{{ organization.dic ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">IČ DPH</dt>
                        <dd class="mt-0.5 font-medium text-slate-900">
                            {{ organization.ic_dph ?? '—' }}
                            <span v-if="organization.vat_verified_at" class="ml-1 text-xs font-normal text-emerald-600">VIES ✓</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Vzťah k DPH</dt>
                        <dd class="mt-0.5 font-medium text-slate-900">
                            {{ organization.vat_mode ?? '—' }}
                            <span v-if="organization.oss_registered" class="ml-1 text-xs font-normal text-brand-600">OSS</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Vznik</dt>
                        <dd class="mt-0.5 font-medium text-slate-900">{{ organization.established_at ?? '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-slate-500">Zápis v registri</dt>
                        <dd class="mt-0.5 font-medium text-slate-900">{{ organization.registration ?? '—' }}</dd>
                    </div>

                    <div class="sm:col-span-2 border-t border-slate-100 pt-3.5">
                        <dt class="text-slate-500">Sídlo / miesto podnikania</dt>
                        <dd class="mt-0.5 font-medium text-slate-900">{{ organization.address || '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-slate-500">Pošta chodí na</dt>
                        <dd class="mt-0.5 font-medium text-slate-900">
                            <span v-for="(line, i) in organization.mailing_lines" :key="i" class="block">{{ line }}</span>
                        </dd>
                    </div>

                    <div class="border-t border-slate-100 pt-3.5">
                        <dt class="text-slate-500">E-mail na faktúry</dt>
                        <dd class="mt-0.5 font-medium text-slate-900">
                            {{ organization.billing_email_effective ?? '—' }}

                            <!-- Neoverená adresa je bežný stav, nie chyba – preto
                                 upozornenie, nie červená hláška. -->
                            <span
                                v-if="organization.billing_email_effective"
                                class="ml-1.5 rounded-full px-2 py-0.5 align-middle text-xs font-medium ring-1 ring-inset"
                                :class="organization.billing_email_verified
                                    ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20'
                                    : 'bg-amber-50 text-amber-800 ring-amber-600/20'"
                            >
                                {{ organization.billing_email_verified ? 'overený' : 'neoverený' }}
                            </span>
                        </dd>

                        <p v-if="organization.billing_email_verified" class="mt-1 text-xs text-slate-400">
                            Potvrdený {{ organization.billing_email_verified_at }}
                        </p>

                        <template v-else-if="organization.billing_email_effective">
                            <p class="mt-1 text-xs text-slate-500">
                                {{ organization.billing_email_verification_sent_at
                                    ? `Žiadosť odoslaná ${organization.billing_email_verification_sent_at}.`
                                    : 'Žiadosť o potvrdenie zatiaľ neodišla.' }}
                            </p>
                            <button
                                type="button"
                                class="mt-1.5 text-xs font-medium text-brand-700 hover:underline disabled:opacity-50"
                                :disabled="resending"
                                @click="resendVerification"
                            >
                                {{ resending ? 'Posielam…' : 'Poslať overovací e-mail' }}
                            </button>
                        </template>
                    </div>
                    <div class="border-t border-slate-100 pt-3.5">
                        <dt class="text-slate-500">Telefón</dt>
                        <dd class="mt-0.5 font-medium text-slate-900">{{ organization.phone ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Web</dt>
                        <dd class="mt-0.5 font-medium text-slate-900">
                            <a v-if="organization.website" :href="organization.website" target="_blank" rel="noopener" class="text-brand-700 hover:underline">
                                {{ organization.website }}
                            </a>
                            <span v-else>—</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Splatnosť</dt>
                        <dd class="mt-0.5 font-medium text-slate-900">{{ organization.payment_terms_days }} dní</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-slate-500">Bankové spojenie</dt>
                        <dd class="mt-0.5 font-medium text-slate-900">
                            {{ organization.iban ?? '—' }}
                            <span v-if="organization.bank_name" class="text-slate-400">· {{ organization.bank_name }}</span>
                            <span v-if="organization.swift" class="text-slate-400">· {{ organization.swift }}</span>
                        </dd>
                    </div>
                </dl>

                <p v-if="organization.note" class="mt-5 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600 ring-1 ring-slate-200/70">
                    {{ organization.note }}
                </p>
            </CardSection>

            <CardSection
                icon="invoice"
                tone="emerald"
                title="Faktúry"
                :description="billing_summary.outstanding_cents > 0
                    ? `Neuhradené: ${billing_summary.outstanding}`
                    : 'Všetko uhradené.'"
            >
                <template #action>
                    <div class="flex items-center gap-2">
                        <Link :href="`/invoices/create?organization=${organization.id}`" class="btn-secondary btn-sm">
                            <Icon name="plus" :size="15" />
                            Nový doklad
                        </Link>
                    </div>
                </template>

                <ul class="-my-1 divide-y divide-slate-100 text-sm">
                    <li v-for="invoice in invoices" :key="invoice.id" class="flex items-center gap-3 py-2.5">
                        <div class="min-w-0 flex-1">
                            <Link :href="`/invoices/${invoice.id}`" class="font-medium text-slate-900 hover:text-brand-700">
                                {{ invoice.number ?? 'Koncept' }}
                            </Link>
                            <p class="text-xs text-slate-500">
                                {{ invoice.issued_at ?? '—' }} · {{ invoice.type_label }}
                            </p>
                        </div>
                        <StatusBadge
                            :status="invoice.status"
                            :tone="invoice.is_overdue && invoice.status !== 'overdue' ? 'rose' : invoice.status_tone"
                            :label="invoice.status_label"
                        />
                        <span class="shrink-0 font-medium whitespace-nowrap text-slate-900">{{ invoice.total }}</span>
                        <DropdownMenu :abilities="invoice.can" :items="invoiceMenu(invoice)" size="sm" />
                    </li>
                    <li v-if="invoices.length === 0" class="py-8 text-center text-slate-500">
                        Zatiaľ žiadne faktúry.
                    </li>
                </ul>

                <template v-if="billing_summary.total > invoices.length" #footer>
                    <Link :href="`/invoices?organization=${organization.id}`" class="link text-sm">
                        Zobraziť všetkých {{ billing_summary.total }} dokladov →
                    </Link>
                </template>
            </CardSection>
        </div>

        <!-- Ďalšie adresy -->
        <div class="grid gap-6 lg:grid-cols-2">
            <CardSection
                icon="home"
                title="Ďalšie adresy"
                description="Pošta, doručenie, prevádzkarne. Sídlo sa upravuje v základných údajoch."
            >
                <template #action>
                    <button type="button" class="btn-secondary btn-sm" @click="addressOpen ? closeAddress() : (addressOpen = true)">
                        {{ addressOpen ? 'Zavrieť' : 'Pridať' }}
                    </button>
                </template>

                <ul class="divide-y divide-slate-100 text-sm">
                    <li
                        v-for="a in addresses"
                        :key="a.id"
                        class="flex items-start justify-between gap-3 py-3"
                        :class="a.deleted_at ? 'opacity-50' : ''"
                    >
                        <div class="min-w-0">
                            <p class="flex items-center gap-2">
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">
                                    {{ a.type_label }}
                                </span>
                                <span v-if="a.is_default" class="text-xs text-emerald-600">predvolená</span>
                                <span v-if="a.deleted_at" class="text-xs text-slate-400">{{ t('actions.trashed') }}</span>
                            </p>
                            <p class="mt-1 font-medium text-slate-900">{{ a.label || a.recipient || a.line }}</p>
                            <p class="text-xs text-slate-500">{{ a.line }}</p>
                            <p v-if="a.note" class="mt-0.5 text-xs text-slate-400">{{ a.note }}</p>
                        </div>
                        <RowActions
                            :abilities="a.can"
                            :trashed="!!a.deleted_at"
                            :base="`${base}/addresses/${a.id}`"
                            :name="a.label || a.line"
                            size="sm"
                            @edit="editAddress(a)"
                        />
                    </li>
                    <li v-if="addresses.length === 0" class="py-6 text-center text-slate-500">
                        Žiadne ďalšie adresy — pošta chodí na sídlo.
                    </li>
                </ul>

                <form v-if="addressOpen" class="mt-5 grid gap-4 border-t border-slate-100 pt-5 sm:grid-cols-6" @submit.prevent="saveAddress">
                    <div class="sm:col-span-3">
                        <label for="a_type">Typ</label>
                        <select id="a_type" v-model="addressForm.type">
                            <option v-for="t in address_types" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                    </div>
                    <div class="sm:col-span-3">
                        <label for="a_label">Označenie</label>
                        <input id="a_label" v-model="addressForm.label" type="text" placeholder="Sklad Bratislava" />
                    </div>
                    <div class="sm:col-span-6">
                        <label for="a_recipient">Príjemca / oddelenie</label>
                        <input id="a_recipient" v-model="addressForm.recipient" type="text" placeholder="Príjem tovaru" />
                    </div>
                    <div class="sm:col-span-4">
                        <label for="a_street">Ulica</label>
                        <input id="a_street" v-model="addressForm.street" type="text" required />
                        <InputError :message="addressForm.errors.street" />
                    </div>
                    <div class="sm:col-span-2">
                        <label for="a_street_no">Číslo</label>
                        <input id="a_street_no" v-model="addressForm.street_no" type="text" />
                    </div>
                    <div class="sm:col-span-2">
                        <label for="a_psc">PSČ</label>
                        <input id="a_psc" v-model="addressForm.postal_code" type="text" required />
                        <InputError :message="addressForm.errors.postal_code" />
                    </div>
                    <div class="sm:col-span-3">
                        <label for="a_city">Mesto</label>
                        <input id="a_city" v-model="addressForm.city" type="text" required />
                        <InputError :message="addressForm.errors.city" />
                    </div>
                    <div class="sm:col-span-1">
                        <label for="a_country">Krajina</label>
                        <input id="a_country" v-model="addressForm.country" type="text" maxlength="2" />
                    </div>
                    <div class="sm:col-span-3">
                        <label for="a_phone">Telefón</label>
                        <input id="a_phone" v-model="addressForm.phone" type="text" />
                    </div>
                    <div class="sm:col-span-3">
                        <label for="a_note">Poznámka pre kuriéra</label>
                        <input id="a_note" v-model="addressForm.note" type="text" placeholder="rampa č. 3, po 15:00" />
                    </div>
                    <div class="sm:col-span-6 flex items-center gap-4">
                        <button type="submit" class="btn-primary btn-sm" :disabled="addressForm.processing">
                            {{ editingAddress ? 'Uložiť zmeny' : 'Pridať adresu' }}
                        </button>
                        <button v-if="editingAddress" type="button" class="btn-secondary btn-sm" @click="closeAddress">Zrušiť</button>
                        <label class="flex items-center gap-2 text-sm font-normal text-slate-600">
                            <input v-model="addressForm.is_default" type="checkbox" />
                            predvolená pre tento typ
                        </label>
                    </div>
                </form>
            </CardSection>

            <!-- Kontaktné osoby -->
            <CardSection icon="user" tone="slate" title="Kontaktné osoby" description="Komu volať kvôli faktúre a komu kvôli technike.">
                <template #action>
                    <button type="button" class="btn-secondary btn-sm" @click="contactOpen ? closeContact() : (contactOpen = true)">
                        {{ contactOpen ? 'Zavrieť' : 'Pridať' }}
                    </button>
                </template>

                <ul class="divide-y divide-slate-100 text-sm">
                    <li
                        v-for="c in contacts"
                        :key="c.id"
                        class="flex items-start justify-between gap-3 py-3"
                        :class="c.deleted_at ? 'opacity-50' : ''"
                    >
                        <div class="min-w-0">
                            <p class="font-medium text-slate-900">
                                {{ c.name }}
                                <span v-if="c.is_primary" class="ml-1 text-xs font-normal text-brand-600">hlavný</span>
                                <span v-if="c.deleted_at" class="ml-1 text-xs font-normal text-slate-400">{{ t('actions.trashed') }}</span>
                            </p>
                            <p class="text-xs text-slate-500">
                                {{ c.type_label }}<span v-if="c.position"> · {{ c.position }}</span>
                            </p>
                            <p class="mt-0.5 text-xs text-slate-500">
                                <a v-if="c.email" :href="`mailto:${c.email}`" class="text-brand-700 hover:underline">{{ c.email }}</a>
                                <span v-if="c.email && c.phone"> · </span>
                                <span v-if="c.phone">{{ c.phone }}</span>
                            </p>
                        </div>
                        <RowActions
                            :abilities="c.can"
                            :trashed="!!c.deleted_at"
                            :base="`${base}/contacts/${c.id}`"
                            :name="c.name"
                            size="sm"
                            @edit="editContact(c)"
                        />
                    </li>
                    <li v-if="contacts.length === 0" class="py-6 text-center text-slate-500">Žiadne kontaktné osoby.</li>
                </ul>

                <form v-if="contactOpen" class="mt-5 grid gap-4 border-t border-slate-100 pt-5 sm:grid-cols-2" @submit.prevent="saveContact">
                    <div>
                        <label for="c_name">Meno</label>
                        <input id="c_name" v-model="contactForm.name" type="text" required />
                        <InputError :message="contactForm.errors.name" />
                    </div>
                    <div>
                        <label for="c_type">Typ</label>
                        <select id="c_type" v-model="contactForm.type">
                            <option v-for="t in contact_types" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label for="c_position">Funkcia</label>
                        <input id="c_position" v-model="contactForm.position" type="text" placeholder="konateľ" />
                    </div>
                    <div>
                        <label for="c_email">E-mail</label>
                        <input id="c_email" v-model="contactForm.email" type="email" />
                        <InputError :message="contactForm.errors.email" />
                    </div>
                    <div>
                        <label for="c_phone">Telefón</label>
                        <input id="c_phone" v-model="contactForm.phone" type="text" />
                    </div>
                    <div class="flex items-end pb-2.5">
                        <label class="flex items-center gap-2 text-sm font-normal text-slate-600">
                            <input v-model="contactForm.is_primary" type="checkbox" />
                            hlavný kontakt
                        </label>
                    </div>
                    <div class="sm:col-span-2 flex gap-2">
                        <button type="submit" class="btn-primary btn-sm" :disabled="contactForm.processing">
                            {{ editingContact ? 'Uložiť zmeny' : 'Pridať kontakt' }}
                        </button>
                        <button v-if="editingContact" type="button" class="btn-secondary btn-sm" @click="closeContact">Zrušiť</button>
                    </div>
                </form>
            </CardSection>
        </div>

        <!-- Projekty: naviazanie, plán, limity a spotreba -->
        <div class="space-y-4">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Projekty a limity</h2>

            <CardSection v-for="product in products" :key="product.key">
                <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span class="chip h-9 w-9" :class="product.linked ? 'bg-brand-50 text-brand-600' : 'bg-slate-100 text-slate-400'">
                            <Icon name="code" :size="18" />
                        </span>
                        <div>
                            <h3 class="font-semibold text-slate-900">{{ product.name }}</h3>
                            <p class="text-sm text-slate-500">
                                <span v-if="product.subscription">
                                    {{ product.subscription.plan }}
                                    <span v-if="product.subscription.current_period_end"> · obnova {{ product.subscription.current_period_end }}</span>
                                    <span v-if="product.subscription.grace_ends_at" class="text-amber-700"> · odklad do {{ product.subscription.grace_ends_at }}</span>
                                </span>
                                <span v-else-if="product.linked">naviazané, bez plánu</span>
                                <span v-else>nepoužíva tento projekt</span>
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <StatusBadge v-if="product.subscription" :status="product.subscription.status" :label="product.subscription.status_label" />
                        <button type="button" class="btn-secondary btn-sm" @click="toggleProduct(product.key)">
                            {{ product.linked ? 'Odviazať' : 'Naviazať' }}
                        </button>
                    </div>
                </div>

                <template v-if="product.linked">
                    <!-- výber plánu -->
                    <div class="mb-5 flex flex-wrap gap-2">
                        <button
                            v-for="plan in product.plans"
                            :key="plan.id"
                            type="button"
                            class="rounded-xl border px-3.5 py-2 text-sm font-medium transition"
                            :class="plan.current
                                ? 'border-brand-500 bg-brand-50 text-brand-700 ring-1 ring-brand-500/20'
                                : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50'"
                            :disabled="plan.current"
                            @click="subscribe(plan.id)"
                        >
                            {{ plan.name }}
                            <span class="ml-1.5 text-xs text-slate-400">{{ plan.price }}</span>
                        </button>

                        <button
                            v-if="product.subscription && product.subscription.status !== 'cancelled'"
                            type="button"
                            class="rounded-xl px-3.5 py-2 text-sm font-medium text-rose-600 transition hover:bg-rose-50"
                            @click="cancel(product.key)"
                        >
                            Zrušiť
                        </button>
                        <button
                            v-if="product.subscription && ['past_due', 'suspended', 'cancelled'].includes(product.subscription.status)"
                            type="button"
                            class="rounded-xl px-3.5 py-2 text-sm font-medium text-emerald-700 transition hover:bg-emerald-50"
                            @click="activate(product.key)"
                        >
                            Označiť ako zaplatené
                        </button>
                    </div>

                    <!-- limity a spotreba -->
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="feature in product.features"
                            :key="feature.key"
                            class="rounded-xl border px-4 py-3"
                            :class="feature.over ? 'border-rose-200 bg-rose-50/60' : 'border-slate-200 bg-slate-50/60'"
                        >
                            <div class="flex items-baseline justify-between gap-2">
                                <span class="truncate text-sm font-medium text-slate-700">{{ feature.name }}</span>
                                <span class="shrink-0 text-sm font-semibold" :class="feature.over ? 'text-rose-700' : 'text-slate-900'">
                                    {{ feature.formatted }}
                                </span>
                            </div>
                            <p v-if="feature.used !== null" class="mt-1 text-xs" :class="feature.over ? 'text-rose-600' : 'text-slate-500'">
                                využité: {{ feature.used }}{{ feature.unit ? ' ' + feature.unit : '' }}
                                <span v-if="feature.over"> — nad limit</span>
                            </p>
                            <p v-else-if="feature.type === 'limit'" class="mt-1 text-xs text-slate-400">
                                projekt zatiaľ nehlásil spotrebu
                            </p>
                        </div>

                        <p v-if="product.features.length === 0" class="text-sm text-slate-500">
                            Projekt nemá v katalógu žiadne funkcie.
                            <Link :href="`/products/${product.key}`" class="link">Doplniť</Link>
                        </p>
                    </div>
                </template>
            </CardSection>
        </div>

        <!-- Ručné výnimky -->
        <CardSection icon="key" tone="amber" title="Ručné výnimky" description="Prepíšu hodnotu z plánu — napríklad dočasne zvýšený limit.">
            <template #action>
                <button type="button" class="btn-secondary btn-sm" @click="overrideOpen ? closeOverride() : (overrideOpen = true)">
                    {{ overrideOpen ? 'Zavrieť' : 'Pridať výnimku' }}
                </button>
            </template>

            <ul class="divide-y divide-slate-100 text-sm">
                <li
                    v-for="o in overrides"
                    :key="o.id"
                    class="flex items-center justify-between gap-3 py-2.5"
                    :class="o.deleted_at ? 'opacity-50' : ''"
                >
                    <div class="min-w-0">
                        <p class="font-medium text-slate-900">
                            {{ o.product }} · <span class="font-mono text-xs">{{ o.feature }}</span> =
                            {{ o.value === null ? 'neobmedzene' : o.value }}
                            <span v-if="o.deleted_at" class="ml-1 text-xs font-normal text-slate-400">{{ t('actions.trashed') }}</span>
                        </p>
                        <p class="text-xs text-slate-500">
                            <span v-if="o.expires_at">platí do {{ o.expires_at }}</span>
                            <span v-else>bez expirácie</span>
                            <span v-if="o.note"> · {{ o.note }}</span>
                        </p>
                    </div>
                    <RowActions
                        :abilities="o.can"
                        :trashed="!!o.deleted_at"
                        :base="`${base}/overrides/${o.id}`"
                        :name="`${o.product} · ${o.feature}`"
                        size="sm"
                        @edit="editOverride(o)"
                    />
                </li>
                <li v-if="overrides.length === 0" class="py-6 text-center text-slate-500">Žiadne výnimky.</li>
            </ul>

            <form v-if="overrideOpen" class="mt-5 grid gap-4 border-t border-slate-100 pt-5 sm:grid-cols-2" @submit.prevent="saveOverride">
                <div>
                    <label for="o_product">Projekt</label>
                    <select id="o_product" v-model="overrideForm.product_key">
                        <option v-for="p in products" :key="p.key" :value="p.key">{{ p.name }}</option>
                    </select>
                </div>
                <div>
                    <label for="o_feature">Funkcia</label>
                    <select id="o_feature" v-model="overrideForm.feature">
                        <option value="">— vyber —</option>
                        <option v-for="f in featuresOf(overrideForm.product_key)" :key="f.key" :value="f.key">
                            {{ f.name }}
                        </option>
                    </select>
                    <InputError :message="overrideForm.errors.feature" />
                </div>
                <div>
                    <label for="o_value">Hodnota</label>
                    <input id="o_value" v-model="overrideForm.value" type="text" placeholder="číslo, true/false alebo prázdne = neobmedzene" />
                    <InputError :message="overrideForm.errors.value" />
                </div>
                <div>
                    <label for="o_expires">Platí do</label>
                    <input id="o_expires" v-model="overrideForm.expires_at" type="date" class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm" />
                    <InputError :message="overrideForm.errors.expires_at" />
                </div>
                <div class="sm:col-span-2">
                    <label for="o_note">Poznámka</label>
                    <input id="o_note" v-model="overrideForm.note" type="text" placeholder="dohoda s klientom, beta prístup…" />
                </div>
                <div class="sm:col-span-2 flex gap-2">
                    <button type="submit" class="btn-primary" :disabled="overrideForm.processing">Uložiť výnimku</button>
                    <button v-if="editingOverride" type="button" class="btn-secondary" @click="closeOverride">Zrušiť</button>
                </div>
            </form>
        </CardSection>
    </div>
</template>
