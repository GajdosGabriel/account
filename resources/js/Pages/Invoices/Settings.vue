<script setup>
import { computed, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import CardSection from '../../Components/CardSection.vue';
import InputError from '../../Components/InputError.vue';
import ToggleSwitch from '../../Components/ToggleSwitch.vue';
import Icon from '../../Components/Icon.vue';
import { t, tc } from '../../Composables/useLang';

const props = defineProps({
    settings: { type: Object, required: true },
    defaults: { type: Object, required: true },
    vat_rates: { type: Array, default: () => [] },
    qr_formats: { type: Array, default: () => [] },
    sending_modes: { type: Array, default: () => ['live', 'redirect', 'off'] },
    supplier_email: { type: String, default: null },
    pdf_available: { type: Boolean, default: true },
    overdue_count: { type: Number, default: 0 },
});

const clone = (value) => JSON.parse(JSON.stringify(value));

const form = useForm(clone(props.settings));

// Po uložení prídu zo servera nové hodnoty – tie sú odteraz „neupravené“.
watch(() => props.settings, (value) => {
    form.defaults(clone(value));
    form.reset();
});

const submit = () => form.put('/invoices/settings', { preserveScroll: true });

const reset = () => {
    if (confirm(t('invoices.settings.reset_confirm'))) {
        router.delete('/invoices/settings', { preserveScroll: true });
    }
};

// Odoslať sa dá len vystavený doklad – zapnutie odosielania zapne aj vystavovanie.
watch(() => form.billing_run.auto_send, (send) => {
    if (send) form.billing_run.auto_issue = true;
});

/* ---------- Nápoveda „pôvodne: …“ pri hodnotách odlišných od .env ---------- */

const read = (source, path) => path.split('.').reduce((carry, key) => carry?.[key], source);

const display = (value) => {
    if (value === true) return t('invoices.settings.on');
    if (value === false) return t('invoices.settings.off');
    if (value === null || value === '') return t('invoices.settings.none');
    return String(value);
};

const original = (path) => {
    const value = read(props.defaults, path);
    const current = read(form, path);

    // Číselné polia z inputu prichádzajú ako text – porovnávame hodnotu, nie typ.
    const same = value == current || ((value ?? '') === '' && (current ?? '') === '');

    return same ? null : t('invoices.settings.default_hint', { value: display(value) });
};

const levelError = computed(() => form.errors['reminders.levels']
    ?? Object.entries(form.errors).find(([key]) => key.startsWith('reminders.levels.'))?.[1]);

const tones = {
    friendly: 'bg-brand-50 text-brand-600',
    firm: 'bg-amber-50 text-amber-600',
    final: 'bg-rose-50 text-rose-600',
};

const toneIcons = { friendly: 'mail', firm: 'clock', final: 'warning' };
</script>

<template>
    <Head :title="t('invoices.settings.title')" />

    <div class="mb-3">
        <Link href="/invoices" class="text-sm text-slate-500 hover:text-slate-900">‹ {{ t('invoices.settings.back') }}</Link>
    </div>

    <PageHeader :title="t('invoices.settings.title')" :subtitle="t('invoices.settings.subtitle')">
        <template #action>
            <button type="button" class="btn-secondary" @click="reset">
                <Icon name="refresh" :size="16" />
                {{ t('invoices.settings.reset') }}
            </button>
        </template>
    </PageHeader>

    <form class="space-y-6 pb-24" @submit.prevent="submit">
        <!-- Upomienky -->
        <CardSection
            icon="mail"
            :tone="form.reminders.enabled ? 'brand' : 'slate'"
            :title="t('invoices.settings.reminders.title')"
            :description="t('invoices.settings.reminders.description')"
        >
            <div class="space-y-6">
                <ToggleSwitch
                    v-model="form.reminders.enabled"
                    :label="t('invoices.settings.reminders.enabled')"
                    :hint="t('invoices.settings.reminders.enabled_hint')"
                >
                    <p class="mt-1.5 text-xs" :class="overdue_count ? 'text-rose-600' : 'text-slate-400'">
                        {{ tc('invoices.settings.reminders.overdue_now', overdue_count) }}
                    </p>
                    <p v-if="original('reminders.enabled')" class="mt-1 text-xs text-amber-600">{{ original('reminders.enabled') }}</p>
                </ToggleSwitch>

                <div v-if="!form.reminders.enabled" class="flex items-start gap-2.5 rounded-lg bg-amber-50 px-3.5 py-3 text-sm text-amber-800">
                    <Icon name="warning" :size="17" class="mt-0.5 shrink-0" />
                    {{ t('invoices.settings.reminders.disabled_note') }}
                </div>

                <fieldset :disabled="!form.reminders.enabled" class="space-y-6 transition" :class="{ 'opacity-50': !form.reminders.enabled }">
                    <div>
                        <p class="text-sm font-medium text-slate-900">{{ t('invoices.settings.reminders.levels') }}</p>
                        <p class="mt-0.5 text-sm text-slate-500">{{ t('invoices.settings.reminders.levels_hint') }}</p>

                        <ol class="mt-3 divide-y divide-slate-100 overflow-hidden rounded-xl border border-slate-200">
                            <li
                                v-for="(level, i) in form.reminders.levels"
                                :key="level.tone"
                                class="flex flex-wrap items-center gap-x-4 gap-y-3 px-4 py-3"
                                :class="{ 'bg-slate-50/70': !level.enabled }"
                            >
                                <label class="mb-0 flex min-w-0 flex-1 cursor-pointer items-center gap-3 font-normal">
                                    <input v-model="level.enabled" type="checkbox" />
                                    <span class="chip h-8 w-8 shrink-0" :class="level.enabled ? tones[level.tone] : 'bg-slate-100 text-slate-400'">
                                        <Icon :name="toneIcons[level.tone]" :size="16" />
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block text-sm font-medium" :class="level.enabled ? 'text-slate-900' : 'text-slate-500'">
                                            {{ i + 1 }}. {{ t(`invoices.settings.reminders.tones.${level.tone}`) }}
                                        </span>
                                        <span class="block text-xs text-slate-500">{{ t(`invoices.settings.reminders.tone_hints.${level.tone}`) }}</span>
                                    </span>
                                </label>
                                <div class="flex items-center gap-2">
                                    <input
                                        v-model.number="level.days"
                                        type="number"
                                        min="1"
                                        max="365"
                                        class="w-20 text-right"
                                        :disabled="!level.enabled"
                                        :aria-label="`${t(`invoices.settings.reminders.tones.${level.tone}`)} – ${t('invoices.settings.reminders.days_after')}`"
                                    />
                                    <span class="text-sm whitespace-nowrap text-slate-500">{{ t('invoices.settings.reminders.days_after') }}</span>
                                </div>
                            </li>
                        </ol>
                        <InputError :message="levelError" />
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label for="min_amount">{{ t('invoices.settings.reminders.min_amount') }}</label>
                            <div class="flex items-center gap-2">
                                <input id="min_amount" v-model="form.reminders.min_amount" type="number" min="0" step="0.01" class="text-right" />
                                <span class="text-sm text-slate-500">€</span>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">{{ t('invoices.settings.reminders.min_amount_hint') }}</p>
                            <p v-if="original('reminders.min_amount')" class="mt-1 text-xs text-amber-600">{{ original('reminders.min_amount') }}</p>
                            <InputError :message="form.errors['reminders.min_amount']" />
                        </div>
                        <div>
                            <label for="final_grace_days">{{ t('invoices.settings.reminders.final_grace_days') }}</label>
                            <div class="flex items-center gap-2">
                                <input id="final_grace_days" v-model.number="form.reminders.final_grace_days" type="number" min="1" max="90" class="text-right" />
                                <span class="text-sm text-slate-500">{{ t('invoices.settings.reminders.days') }}</span>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">{{ t('invoices.settings.reminders.final_grace_days_hint') }}</p>
                            <p v-if="original('reminders.final_grace_days')" class="mt-1 text-xs text-amber-600">{{ original('reminders.final_grace_days') }}</p>
                            <InputError :message="form.errors['reminders.final_grace_days']" />
                        </div>
                    </div>

                    <div class="space-y-4 border-t border-slate-100 pt-5">
                        <ToggleSwitch
                            v-if="pdf_available"
                            v-model="form.reminders.attach_pdf"
                            :label="t('invoices.settings.reminders.attach_pdf')"
                        />
                        <ToggleSwitch
                            v-model="form.reminders.copy_to_supplier"
                            :label="t('invoices.settings.reminders.copy_to_supplier')"
                            :hint="supplier_email
                                ? t('invoices.settings.reminders.copy_to_supplier_hint', { email: supplier_email })
                                : t('invoices.settings.reminders.no_supplier_email')"
                        />
                    </div>
                </fieldset>
            </div>
        </CardSection>

        <!-- Odosielanie -->
        <CardSection icon="send" :title="t('invoices.settings.sending.title')" :description="t('invoices.settings.sending.description')">
            <div class="space-y-5">
                <div>
                    <p class="text-sm font-medium text-slate-900">{{ t('invoices.settings.sending.mode') }}</p>
                    <p class="mt-0.5 text-sm text-slate-500">{{ t('invoices.settings.sending.mode_hint') }}</p>

                    <div class="mt-3 divide-y divide-slate-100 overflow-hidden rounded-xl border border-slate-200">
                        <label
                            v-for="mode in sending_modes"
                            :key="mode"
                            class="mb-0 flex cursor-pointer items-start gap-3 px-4 py-3 font-normal"
                            :class="{ 'bg-slate-50/70': form.sending.mode === mode }"
                        >
                            <input v-model="form.sending.mode" type="radio" name="sending_mode" :value="mode" class="mt-0.5" />
                            <span class="min-w-0">
                                <span class="block text-sm font-medium text-slate-900">{{ t(`invoices.settings.sending.modes.${mode}`) }}</span>
                                <span class="block text-xs text-slate-500">{{ t(`invoices.settings.sending.mode_hints.${mode}`) }}</span>
                            </span>
                        </label>
                    </div>
                    <p v-if="original('sending.mode')" class="mt-1 text-xs text-amber-600">{{ original('sending.mode') }}</p>
                    <InputError :message="form.errors['sending.mode']" />

                    <div v-if="form.sending.mode === 'redirect'" class="mt-4">
                        <label for="redirect_to">{{ t('invoices.settings.sending.redirect_to') }}</label>
                        <input id="redirect_to" v-model="form.sending.redirect_to" type="email" class="sm:max-w-sm" placeholder="ja@mojafirma.sk" />
                        <p class="mt-1 text-xs text-slate-500">{{ t('invoices.settings.sending.redirect_to_hint') }}</p>
                        <InputError :message="form.errors['sending.redirect_to']" />
                    </div>

                    <div v-if="form.sending.mode === 'off'" class="mt-4 flex items-start gap-2.5 rounded-lg bg-amber-50 px-3.5 py-3 text-sm text-amber-800">
                        <Icon name="warning" :size="17" class="mt-0.5 shrink-0" />
                        {{ t('invoices.settings.sending.off_note') }}
                    </div>
                </div>

                <ToggleSwitch
                    class="border-t border-slate-100 pt-5"
                    v-model="form.sending.require_verified_email"
                    :label="t('invoices.settings.sending.require_verified')"
                    :hint="t('invoices.settings.sending.require_verified_hint')"
                >
                    <p v-if="original('sending.require_verified_email')" class="mt-1 text-xs text-amber-600">{{ original('sending.require_verified_email') }}</p>
                </ToggleSwitch>

                <div class="border-t border-slate-100 pt-5">
                    <label for="bcc">{{ t('invoices.settings.sending.bcc') }}</label>
                    <input id="bcc" v-model="form.sending.bcc" type="email" class="sm:max-w-sm" placeholder="uctovnik@firma.sk" />
                    <p class="mt-1 text-xs text-slate-500">{{ t('invoices.settings.sending.bcc_hint') }}</p>
                    <InputError :message="form.errors['sending.bcc']" />
                </div>
            </div>
        </CardSection>

        <!-- Predvolené hodnoty dokladu -->
        <CardSection icon="invoice" :title="t('invoices.settings.defaults.title')" :description="t('invoices.settings.defaults.description')">
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label for="due_days">{{ t('invoices.settings.defaults.due_days') }}</label>
                    <div class="flex items-center gap-2">
                        <input id="due_days" v-model.number="form.document.due_days" type="number" min="0" max="365" class="text-right" />
                        <span class="text-sm text-slate-500">{{ t('invoices.settings.reminders.days') }}</span>
                    </div>
                    <p v-if="original('document.due_days')" class="mt-1 text-xs text-amber-600">{{ original('document.due_days') }}</p>
                    <InputError :message="form.errors['document.due_days']" />
                </div>
                <div>
                    <label for="constant_symbol">{{ t('invoices.settings.defaults.constant_symbol') }}</label>
                    <input id="constant_symbol" v-model="form.document.constant_symbol" type="text" inputmode="numeric" maxlength="4" placeholder="0308" />
                    <InputError :message="form.errors['document.constant_symbol']" />
                </div>
                <div>
                    <label for="unit">{{ t('invoices.settings.defaults.unit') }}</label>
                    <input id="unit" v-model="form.document.unit" type="text" maxlength="20" />
                    <InputError :message="form.errors['document.unit']" />
                </div>
                <div>
                    <label for="vat_rate">{{ t('invoices.settings.defaults.vat_rate') }}</label>
                    <select id="vat_rate" v-model.number="form.document.vat_rate">
                        <option v-for="rate in vat_rates" :key="rate" :value="rate">{{ rate }} %</option>
                    </select>
                    <InputError :message="form.errors['document.vat_rate']" />
                </div>
            </div>
            <p class="mt-2 text-xs text-slate-500">
                {{ t('invoices.settings.defaults.due_days_hint') }} {{ t('invoices.settings.defaults.constant_symbol_hint') }}
            </p>

            <div class="mt-5">
                <label for="footer_note">{{ t('invoices.settings.defaults.footer_note') }}</label>
                <textarea id="footer_note" v-model="form.document.footer_note" rows="2" maxlength="1000"></textarea>
                <p class="mt-1 text-xs text-slate-500">{{ t('invoices.settings.defaults.footer_note_hint') }}</p>
                <InputError :message="form.errors['document.footer_note']" />
            </div>
        </CardSection>

        <div class="grid gap-6 lg:grid-cols-2">
            <!-- QR platba -->
            <CardSection icon="qr" :title="t('invoices.settings.qr.title')" :description="t('invoices.settings.qr.description')">
                <div class="space-y-5">
                    <ToggleSwitch v-model="form.qr.enabled" :label="t('invoices.settings.qr.enabled')" />
                    <div :class="{ 'opacity-50': !form.qr.enabled }">
                        <label for="qr_format">{{ t('invoices.settings.qr.format') }}</label>
                        <select id="qr_format" v-model="form.qr.format" :disabled="!form.qr.enabled">
                            <option v-for="format in qr_formats" :key="format" :value="format">
                                {{ t(`invoices.settings.qr.formats.${format}`) }}
                            </option>
                        </select>
                        <InputError :message="form.errors['qr.format']" />
                    </div>
                </div>
            </CardSection>

            <!-- Automatická fakturácia -->
            <CardSection icon="refresh" :title="t('invoices.settings.billing_run.title')" :description="t('invoices.settings.billing_run.description')">
                <div class="space-y-5">
                    <div>
                        <label for="issue_days_before">{{ t('invoices.settings.billing_run.issue_days_before') }}</label>
                        <div class="flex items-center gap-2">
                            <input id="issue_days_before" v-model.number="form.billing_run.issue_days_before" type="number" min="0" max="60" class="w-24 text-right" />
                            <span class="text-sm text-slate-500">{{ t('invoices.settings.reminders.days') }}</span>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">{{ t('invoices.settings.billing_run.issue_days_before_hint') }}</p>
                        <InputError :message="form.errors['billing_run.issue_days_before']" />
                    </div>
                    <ToggleSwitch
                        v-model="form.billing_run.auto_issue"
                        :disabled="form.billing_run.auto_send"
                        :label="t('invoices.settings.billing_run.auto_issue')"
                        :hint="t('invoices.settings.billing_run.auto_issue_hint')"
                    />
                    <ToggleSwitch
                        v-model="form.billing_run.auto_send"
                        :label="t('invoices.settings.billing_run.auto_send')"
                        :hint="t('invoices.settings.billing_run.auto_send_hint')"
                    >
                        <p v-if="original('billing_run.auto_send')" class="mt-1 text-xs text-amber-600">{{ original('billing_run.auto_send') }}</p>
                    </ToggleSwitch>
                    <ToggleSwitch v-model="form.billing_run.skip_zero" :label="t('invoices.settings.billing_run.skip_zero')" />
                </div>
            </CardSection>
        </div>

        <!-- Uloženie – drží sa spodku obrazovky, nech netreba rolovať -->
        <div class="sticky bottom-4 z-10">
            <div
                class="card flex flex-wrap items-center justify-end gap-3 px-5 py-3 shadow-lg transition"
                :class="form.isDirty ? 'border-brand-200' : ''"
            >
                <span v-if="form.isDirty" class="mr-auto flex items-center gap-2 text-sm text-slate-600">
                    <span class="h-2 w-2 rounded-full bg-amber-400" aria-hidden="true"></span>
                    {{ t('invoices.settings.unsaved') }}
                </span>
                <button type="submit" class="btn-primary" :disabled="form.processing || !form.isDirty">
                    <Icon name="check" :size="16" />
                    {{ t('invoices.settings.save') }}
                </button>
            </div>
        </div>
    </form>
</template>
