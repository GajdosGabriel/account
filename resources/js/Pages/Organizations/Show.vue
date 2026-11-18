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
import { csrfHeaders } from '../../Composables/useCsrf';

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
const cancel = (key) => confirm(t('ui.org.cancel_confirm')) && router.post(`${base}/${key}/cancel`, {}, { preserveScroll: true });
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
                ...csrfHeaders(),
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
                {{ verifying ? t('ui.org.verifying') : t('ui.org.verify') }}
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
            <CardSection class="lg:col-span-2" icon="building" :title="t('ui.org.company')">
                <div
                    v-if="organization.missing_billing.length"
                    class="mb-5 flex items-start gap-2.5 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-amber-600/15"
                >
                    <Icon name="warning" :size="18" class="mt-0.5 text-amber-600" />
                    <span>{{ t('ui.org.missing_billing') }} <strong>{{ organization.missing_billing.join(', ') }}</strong></span>
                </div>

                <dl class="grid gap-x-6 gap-y-3.5 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-slate-500">{{ t('ui.org.legal_name') }}</dt>
                        <dd class="mt-0.5 font-medium text-slate-900">
                            {{ organization.legal_name || organization.name }}
                            <span v-if="organization.legal_form" class="text-slate-400">· {{ organization.legal_form }}</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">{{ t('ui.org.ico') }}</dt>
                        <dd class="mt-0.5 font-medium text-slate-900">
                            {{ organization.ico ?? '—' }}
                            <span v-if="organization.ico_verified_at" class="ml-1 text-xs font-normal text-emerald-600">{{ t('ui.org.verified') }}</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">{{ t('ui.org.dic') }}</dt>
                        <dd class="mt-0.5 font-medium text-slate-900">{{ organization.dic ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">{{ t('ui.org.ic_dph') }}</dt>
                        <dd class="mt-0.5 font-medium text-slate-900">
                            {{ organization.ic_dph ?? '—' }}
                            <span v-if="organization.vat_verified_at" class="ml-1 text-xs font-normal text-emerald-600">VIES ✓</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">{{ t('ui.org.vat_mode') }}</dt>
                        <dd class="mt-0.5 font-medium text-slate-900">
                            {{ organization.vat_mode ?? '—' }}
                            <span v-if="organization.oss_registered" class="ml-1 text-xs font-normal text-brand-600">OSS</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">{{ t('ui.org.established') }}</dt>
                        <dd class="mt-0.5 font-medium text-slate-900">{{ organization.established_at ?? '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-slate-500">{{ t('ui.org.registration') }}</dt>
                        <dd class="mt-0.5 font-medium text-slate-900">{{ organization.registration ?? '—' }}</dd>
                    </div>

                    <div class="sm:col-span-2 border-t border-slate-100 pt-3.5">
                        <dt class="text-slate-500">{{ t('ui.org.seat') }}</dt>
                        <dd class="mt-0.5 font-medium text-slate-900">{{ organization.address || '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-slate-500">{{ t('ui.org.mailing') }}</dt>
                        <dd class="mt-0.5 font-medium text-slate-900">
                            <span v-for="(line, i) in organization.mailing_lines" :key="i" class="block">{{ line }}</span>
                        </dd>
                    </div>

                    <div class="border-t border-slate-100 pt-3.5">
                        <dt class="text-slate-500">{{ t('ui.org.billing_email') }}</dt>
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
                                {{ organization.billing_email_verified ? t('ui.org.email_verified') : t('ui.org.email_unverified') }}
                            </span>
                        </dd>

                        <p v-if="organization.billing_email_verified" class="mt-1 text-xs text-slate-400">
                            {{ t('ui.org.confirmed_at', { date: organization.billing_email_verified_at }) }}
                        </p>

                        <template v-else-if="organization.billing_email_effective">
                            <p class="mt-1 text-xs text-slate-500">
                                {{ organization.billing_email_verification_sent_at
                                    ? t('ui.org.request_sent', { date: organization.billing_email_verification_sent_at })
                                    : t('ui.org.request_not_sent') }}
                            </p>
                            <button
                                type="button"
                                class="mt-1.5 text-xs font-medium text-brand-700 hover:underline disabled:opacity-50"
                                :disabled="resending"
                                @click="resendVerification"
                            >
                                {{ resending ? t('ui.auth.sending') : t('ui.org.send_verification') }}
                            </button>
                        </template>
                    </div>
                    <div class="border-t border-slate-100 pt-3.5">
                        <dt class="text-slate-500">{{ t('ui.org.phone') }}</dt>
                        <dd class="mt-0.5 font-medium text-slate-900">{{ organization.phone ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">{{ t('ui.org.web') }}</dt>
                        <dd class="mt-0.5 font-medium text-slate-900">
                            <a v-if="organization.website" :href="organization.website" target="_blank" rel="noopener" class="text-brand-700 hover:underline">
                                {{ organization.website }}
                            </a>
                            <span v-else>—</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">{{ t('invoices.table.due') }}</dt>
                        <dd class="mt-0.5 font-medium text-slate-900">{{ t('actions.invoice.days', { count: organization.payment_terms_days }) }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-slate-500">{{ t('ui.org.bank') }}</dt>
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
                :title="t('invoices.title')"
                :description="billing_summary.outstanding_cents > 0
                    ? t('ui.org.unpaid', { amount: billing_summary.outstanding })
                    : t('ui.org.all_paid')"
            >
                <template #action>
                    <div class="flex items-center gap-2">
                        <Link :href="`/invoices/create?organization=${organization.id}`" class="btn-secondary btn-sm">
                            <Icon name="plus" :size="15" />
                            {{ t('invoices.create') }}
                        </Link>
                    </div>
                </template>

                <ul class="-my-1 divide-y divide-slate-100 text-sm">
                    <li v-for="invoice in invoices" :key="invoice.id" class="flex items-center gap-3 py-2.5">
                        <div class="min-w-0 flex-1">
                            <Link :href="`/invoices/${invoice.id}`" class="font-medium text-slate-900 hover:text-brand-700">
                                {{ invoice.number ?? t('ui.org.draft') }}
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
                        {{ t('ui.org.no_invoices') }}
                    </li>
                </ul>

                <template v-if="billing_summary.total > invoices.length" #footer>
                    <Link :href="`/invoices?organization=${organization.id}`" class="link text-sm">
                        {{ t('ui.org.all_invoices', { count: billing_summary.total }) }} →
                    </Link>
                </template>
            </CardSection>
        </div>

        <!-- Ďalšie adresy -->
        <div class="grid gap-6 lg:grid-cols-2">
            <CardSection
                icon="home"
                :title="t('ui.org.addresses')"
                :description="t('ui.org.addresses_hint')"
            >
                <template #action>
                    <button type="button" class="btn-secondary btn-sm" @click="addressOpen ? closeAddress() : (addressOpen = true)">
                        {{ addressOpen ? t('common.secret.close') : t('ui.settings.add') }}
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
                                <span v-if="a.is_default" class="text-xs text-emerald-600">{{ t('ui.org.default') }}</span>
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
                        {{ t('ui.org.no_addresses') }}
                    </li>
                </ul>

                <form v-if="addressOpen" class="mt-5 grid gap-4 border-t border-slate-100 pt-5 sm:grid-cols-6" @submit.prevent="saveAddress">
                    <div class="sm:col-span-3">
                        <label for="a_type">{{ t('ui.org.type') }}</label>
                        <select id="a_type" v-model="addressForm.type">
                            <option v-for="type in address_types" :key="type.value" :value="type.value">{{ type.label }}</option>
                        </select>
                    </div>
                    <div class="sm:col-span-3">
                        <label for="a_label">{{ t('ui.org.label') }}</label>
                        <input id="a_label" v-model="addressForm.label" type="text" :placeholder="t('ui.org.label_placeholder')" />
                    </div>
                    <div class="sm:col-span-6">
                        <label for="a_recipient">{{ t('ui.org.recipient') }}</label>
                        <input id="a_recipient" v-model="addressForm.recipient" type="text" :placeholder="t('ui.org.recipient_placeholder')" />
                    </div>
                    <div class="sm:col-span-4">
                        <label for="a_street">{{ t('ui.org.street') }}</label>
                        <input id="a_street" v-model="addressForm.street" type="text" required />
                        <InputError :message="addressForm.errors.street" />
                    </div>
                    <div class="sm:col-span-2">
                        <label for="a_street_no">{{ t('ui.org.street_no') }}</label>
                        <input id="a_street_no" v-model="addressForm.street_no" type="text" />
                    </div>
                    <div class="sm:col-span-2">
                        <label for="a_psc">{{ t('ui.org.postal_code') }}</label>
                        <input id="a_psc" v-model="addressForm.postal_code" type="text" required />
                        <InputError :message="addressForm.errors.postal_code" />
                    </div>
                    <div class="sm:col-span-3">
                        <label for="a_city">{{ t('ui.org.city') }}</label>
                        <input id="a_city" v-model="addressForm.city" type="text" required />
                        <InputError :message="addressForm.errors.city" />
                    </div>
                    <div class="sm:col-span-1">
                        <label for="a_country">{{ t('ui.org.country') }}</label>
                        <input id="a_country" v-model="addressForm.country" type="text" maxlength="2" />
                    </div>
                    <div class="sm:col-span-3">
                        <label for="a_phone">{{ t('ui.org.phone') }}</label>
                        <input id="a_phone" v-model="addressForm.phone" type="text" />
                    </div>
                    <div class="sm:col-span-3">
                        <label for="a_note">{{ t('ui.org.courier_note') }}</label>
                        <input id="a_note" v-model="addressForm.note" type="text" :placeholder="t('ui.org.courier_placeholder')" />
                    </div>
                    <div class="sm:col-span-6 flex items-center gap-4">
                        <button type="submit" class="btn-primary btn-sm" :disabled="addressForm.processing">
                            {{ editingAddress ? t('ui.settings.save') : t('ui.org.add_address') }}
                        </button>
                        <button v-if="editingAddress" type="button" class="btn-secondary btn-sm" @click="closeAddress">{{ t('common.form.cancel') }}</button>
                        <label class="flex items-center gap-2 text-sm font-normal text-slate-600">
                            <input v-model="addressForm.is_default" type="checkbox" />
                            {{ t('ui.org.default_for_type') }}
                        </label>
                    </div>
                </form>
            </CardSection>

            <!-- Kontaktné osoby -->
            <CardSection icon="user" tone="slate" :title="t('ui.org.contacts')" :description="t('ui.org.contacts_hint')">
                <template #action>
                    <button type="button" class="btn-secondary btn-sm" @click="contactOpen ? closeContact() : (contactOpen = true)">
                        {{ contactOpen ? t('common.secret.close') : t('ui.settings.add') }}
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
                                <span v-if="c.is_primary" class="ml-1 text-xs font-normal text-brand-600">{{ t('ui.org.primary') }}</span>
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
                    <li v-if="contacts.length === 0" class="py-6 text-center text-slate-500">{{ t('ui.org.no_contacts') }}</li>
                </ul>

                <form v-if="contactOpen" class="mt-5 grid gap-4 border-t border-slate-100 pt-5 sm:grid-cols-2" @submit.prevent="saveContact">
                    <div>
                        <label for="c_name">{{ t('ui.settings.name') }}</label>
                        <input id="c_name" v-model="contactForm.name" type="text" required />
                        <InputError :message="contactForm.errors.name" />
                    </div>
                    <div>
                        <label for="c_type">{{ t('ui.org.type') }}</label>
                        <select id="c_type" v-model="contactForm.type">
                            <option v-for="type in contact_types" :key="type.value" :value="type.value">{{ type.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label for="c_position">{{ t('ui.org.position') }}</label>
                        <input id="c_position" v-model="contactForm.position" type="text" :placeholder="t('ui.org.position_placeholder')" />
                    </div>
                    <div>
                        <label for="c_email">{{ t('ui.auth.email') }}</label>
                        <input id="c_email" v-model="contactForm.email" type="email" />
                        <InputError :message="contactForm.errors.email" />
                    </div>
                    <div>
                        <label for="c_phone">{{ t('ui.org.phone') }}</label>
                        <input id="c_phone" v-model="contactForm.phone" type="text" />
                    </div>
                    <div class="flex items-end pb-2.5">
                        <label class="flex items-center gap-2 text-sm font-normal text-slate-600">
                            <input v-model="contactForm.is_primary" type="checkbox" />
                            {{ t('ui.org.primary_contact') }}
                        </label>
                    </div>
                    <div class="sm:col-span-2 flex gap-2">
                        <button type="submit" class="btn-primary btn-sm" :disabled="contactForm.processing">
                            {{ editingContact ? t('ui.settings.save') : t('ui.org.add_contact') }}
                        </button>
                        <button v-if="editingContact" type="button" class="btn-secondary btn-sm" @click="closeContact">{{ t('common.form.cancel') }}</button>
                    </div>
                </form>
            </CardSection>
        </div>

        <!-- Projekty: naviazanie, plán, limity a spotreba -->
        <div class="space-y-4">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ t('ui.org.products') }}</h2>

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
                                    <span v-if="product.subscription.current_period_end"> · {{ t('ui.org.renewal', { date: product.subscription.current_period_end }) }}</span>
                                    <span v-if="product.subscription.grace_ends_at" class="text-amber-700"> · {{ t('ui.org.grace', { date: product.subscription.grace_ends_at }) }}</span>
                                </span>
                                <span v-else-if="product.linked">{{ t('ui.org.linked_no_plan') }}</span>
                                <span v-else>{{ t('ui.org.not_linked') }}</span>
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <StatusBadge v-if="product.subscription" :status="product.subscription.status" :label="product.subscription.status_label" />
                        <button type="button" class="btn-secondary btn-sm" @click="toggleProduct(product.key)">
                            {{ product.linked ? t('ui.org.unlink') : t('ui.org.link') }}
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
                            {{ t('ui.org.cancel_subscription') }}
                        </button>
                        <button
                            v-if="product.subscription && ['past_due', 'suspended', 'cancelled'].includes(product.subscription.status)"
                            type="button"
                            class="rounded-xl px-3.5 py-2 text-sm font-medium text-emerald-700 transition hover:bg-emerald-50"
                            @click="activate(product.key)"
                        >
                            {{ t('ui.org.mark_paid') }}
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
                                {{ t('ui.org.used') }} {{ feature.used }}{{ feature.unit ? ' ' + feature.unit : '' }}
                                <span v-if="feature.over"> — {{ t('ui.org.over_limit') }}</span>
                            </p>
                            <p v-else-if="feature.type === 'limit'" class="mt-1 text-xs text-slate-400">
                                {{ t('ui.org.no_usage') }}
                            </p>
                        </div>

                        <p v-if="product.features.length === 0" class="text-sm text-slate-500">
                            {{ t('ui.org.no_features') }}
                            <Link :href="`/products/${product.key}`" class="link">{{ t('ui.org.fill_in') }}</Link>
                        </p>
                    </div>
                </template>
            </CardSection>
        </div>

        <!-- Ručné výnimky -->
        <CardSection icon="key" tone="amber" :title="t('ui.org.overrides')" :description="t('ui.org.overrides_hint')">
            <template #action>
                <button type="button" class="btn-secondary btn-sm" @click="overrideOpen ? closeOverride() : (overrideOpen = true)">
                    {{ overrideOpen ? t('common.secret.close') : t('ui.org.add_override') }}
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
                            {{ o.value === null ? t('ui.product.unlimited') : o.value }}
                            <span v-if="o.deleted_at" class="ml-1 text-xs font-normal text-slate-400">{{ t('actions.trashed') }}</span>
                        </p>
                        <p class="text-xs text-slate-500">
                            <span v-if="o.expires_at">{{ t('ui.org.valid_until', { date: o.expires_at }) }}</span>
                            <span v-else>{{ t('ui.org.no_expiry') }}</span>
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
                <li v-if="overrides.length === 0" class="py-6 text-center text-slate-500">{{ t('ui.org.no_overrides') }}</li>
            </ul>

            <form v-if="overrideOpen" class="mt-5 grid gap-4 border-t border-slate-100 pt-5 sm:grid-cols-2" @submit.prevent="saveOverride">
                <div>
                    <label for="o_product">{{ t('ui.org.product') }}</label>
                    <select id="o_product" v-model="overrideForm.product_key">
                        <option v-for="p in products" :key="p.key" :value="p.key">{{ p.name }}</option>
                    </select>
                </div>
                <div>
                    <label for="o_feature">{{ t('ui.org.feature') }}</label>
                    <select id="o_feature" v-model="overrideForm.feature">
                        <option value="">— {{ t('ui.org.choose') }} —</option>
                        <option v-for="f in featuresOf(overrideForm.product_key)" :key="f.key" :value="f.key">
                            {{ f.name }}
                        </option>
                    </select>
                    <InputError :message="overrideForm.errors.feature" />
                </div>
                <div>
                    <label for="o_value">{{ t('ui.org.value') }}</label>
                    <input id="o_value" v-model="overrideForm.value" type="text" :placeholder="t('ui.org.value_placeholder')" />
                    <InputError :message="overrideForm.errors.value" />
                </div>
                <div>
                    <label for="o_expires">{{ t('ui.org.expires') }}</label>
                    <input id="o_expires" v-model="overrideForm.expires_at" type="date" />
                    <InputError :message="overrideForm.errors.expires_at" />
                </div>
                <div class="sm:col-span-2">
                    <label for="o_note">{{ t('ui.invoice.pay_note') }}</label>
                    <input id="o_note" v-model="overrideForm.note" type="text" :placeholder="t('ui.org.note_placeholder')" />
                </div>
                <div class="sm:col-span-2 flex gap-2">
                    <button type="submit" class="btn-primary" :disabled="overrideForm.processing">{{ t('ui.org.save_override') }}</button>
                    <button v-if="editingOverride" type="button" class="btn-secondary" @click="closeOverride">{{ t('common.form.cancel') }}</button>
                </div>
            </form>
        </CardSection>
    </div>
</template>
