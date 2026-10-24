<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import InputError from '../../Components/InputError.vue';
import EmailHtmlFrame from '../../Components/EmailHtmlFrame.vue';
import HtmlEditor from '../../Components/HtmlEditor.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import Icon from '../../Components/Icon.vue';
import { t } from '../../Composables/useLang';
import { campaignTone, delayLabel, postJson } from './mailing';

const props = defineProps({
    campaign: { type: Object, required: true },
    products: { type: Array, default: () => [] },
    layouts: { type: Array, default: () => [] },
    templates: { type: Array, default: () => [] },
    settings: { type: Object, default: () => ({}) },
});

const isNew = computed(() => !props.campaign.id);
const isDraft = computed(() => props.campaign.status === 'draft');

const form = useForm({
    ...props.campaign,
    product_key: props.campaign.product_key ?? props.products[0]?.key ?? null,
});

const mode = ref(form.trigger_event ? 'automation' : 'broadcast');
const product = computed(() => props.products.find((p) => p.key === form.product_key));
const triggers = computed(() => product.value?.triggers ?? []);
const trigger = computed(() => triggers.value.find((tr) => tr.key === form.trigger_event));
const productLayouts = computed(() => props.layouts.filter((l) => l.product_key === form.product_key));
const productTemplates = computed(() => props.templates.filter((tpl) => tpl.product_key === form.product_key));
const isReply = computed(() => mode.value === 'automation' && form.reply_in_thread);

watch(mode, (value) => {
    if (value === 'broadcast') {
        form.trigger_event = null;
        form.reply_in_thread = false;
    } else if (!form.trigger_event) {
        form.trigger_event = triggers.value[0]?.key ?? null;
    }
});

// Iný projekt = iné schránky, layouty a udalosti.
watch(() => form.product_key, () => {
    form.mailbox_id = null;
    form.layout_id = null;
    if (mode.value === 'automation') form.trigger_event = triggers.value[0]?.key ?? null;
});

/* ---------- oneskorenie: ukladá sa v hodinách ---------- */

const delayUnit = ref(form.delay_hours % 24 === 0 ? 'd' : 'h');
const unitHours = () => (delayUnit.value === 'd' ? 24 : 1);
const delayAmount = computed({
    get: () => form.delay_hours / unitHours(),
    set: (v) => { form.delay_hours = Math.max(0, Math.round(Number(v) || 0)) * unitHours(); },
});
const setDelayUnit = (unit) => {
    const amount = Math.ceil(delayAmount.value);
    delayUnit.value = unit;
    delayAmount.value = amount;
};
const presets = [0, 1, 24, 48, 72, 168, 336];

/* ---------- náhľad ---------- */

const html = ref('');
const previewError = ref('');
let timer = null;

const payload = () => ({ ...form.data(), trigger_event: mode.value === 'automation' ? form.trigger_event : null });

const refresh = async () => {
    try {
        html.value = (await postJson('/mailing/preview', payload())).html;
        previewError.value = '';
    } catch (e) {
        previewError.value = e.message;
    }
};

watch(() => JSON.stringify(payload()), () => {
    clearTimeout(timer);
    timer = setTimeout(refresh, 500);
});
onMounted(refresh);

/* ---------- akcie ---------- */

const notice = ref('');
const testing = ref(false);

const sendTest = async () => {
    testing.value = true;
    try {
        notice.value = (await postJson('/mailing/test', payload())).message;
    } catch (e) {
        notice.value = e.message;
    } finally {
        testing.value = false;
    }
};

const save = () => {
    form.transform(() => payload());
    isNew.value
        ? form.post('/mailing', { preserveScroll: true })
        : form.patch(`/mailing/${props.campaign.id}`, { preserveScroll: true });
};

const scheduledAt = ref('');
const queue = () => {
    if (form.isDirty) {
        notice.value = t('mailing.campaigns.save_first');
        return;
    }
    const message = mode.value === 'automation' ? t('mailing.campaigns.confirm_activate') : t('mailing.campaigns.confirm_queue');
    if (confirm(message)) {
        router.post(`/mailing/${props.campaign.id}/queue`, { scheduled_at: scheduledAt.value || null }, { preserveScroll: true });
    }
};

const remove = () => {
    if (confirm(t('mailing.campaigns.confirm_delete', { name: props.campaign.name }))) {
        router.delete(`/mailing/${props.campaign.id}`);
    }
};

/* ---------- adresáti: druh a segment podľa štítkov ---------- */

const tags = computed(() => product.value?.tags ?? []);
const audienceCount = ref(null);
let countTimer = null;

const toggleTag = (part, key) => {
    const other = part === 'any' ? 'none' : 'any';
    const list = form.audience[part];
    form.audience[part] = list.includes(key) ? list.filter((k) => k !== key) : [...list, key];
    // Ten istý štítok nemôže byť naraz „s“ aj „bez“.
    form.audience[other] = form.audience[other].filter((k) => k !== key);
};

const refreshCount = async () => {
    if (!form.product_key) return;
    try {
        audienceCount.value = (await postJson('/mailing/audience-count', {
            product_key: form.product_key, kind: form.kind, audience: form.audience,
        })).count;
    } catch {
        audienceCount.value = null;
    }
};

watch(() => JSON.stringify([form.product_key, form.kind, form.audience]), () => {
    clearTimeout(countTimer);
    countTimer = setTimeout(refreshCount, 300);
});
onMounted(refreshCount);

watch(() => form.product_key, () => { form.audience = { any: [], none: [] }; });

const variables = computed(() => [...new Set([...(trigger.value?.variables ?? []), ...(product.value?.contact_variables ?? [])])]);
</script>

<template>
    <Head :title="isNew ? t('mailing.campaigns.new') : campaign.name" />

    <div class="mb-3">
        <Link href="/mailing" class="text-sm text-slate-500 hover:text-slate-900">‹ {{ t('mailing.back') }}</Link>
    </div>

    <PageHeader :title="isNew ? t('mailing.campaigns.new') : campaign.name" :subtitle="t('mailing.campaigns.editor_hint')">
        <template #action>
            <StatusBadge v-if="!isNew" :status="campaign.status" :label="t(`mailing.status.${campaign.status}`)" :tone="campaignTone(campaign.status)" />
            <Link v-if="!isNew" :href="`/mailing/${campaign.id}`" class="btn-secondary">{{ t('mailing.campaigns.detail') }}</Link>
        </template>
    </PageHeader>

    <div v-if="!isDraft" class="mb-5 rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
        {{ t('mailing.campaigns.readonly') }}
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <form class="card space-y-5 p-5" @submit.prevent="save">
            <fieldset :disabled="!isDraft" class="space-y-5">
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label for="m_product">{{ t('mailing.fields.product') }}</label>
                        <select id="m_product" v-model="form.product_key" :disabled="!isNew" required>
                            <option v-for="p in products" :key="p.key" :value="p.key">{{ p.name }}</option>
                        </select>
                        <InputError :message="form.errors.product_key" />
                    </div>
                    <div>
                        <label for="m_name">{{ t('mailing.fields.name') }}</label>
                        <input id="m_name" v-model="form.name" type="text" required />
                        <InputError :message="form.errors.name" />
                    </div>
                </div>

                <div v-if="isNew && productTemplates.length" class="text-sm text-slate-600">
                    {{ t('mailing.campaigns.from_template') }}
                    <Link v-for="tpl in productTemplates" :key="tpl.id" :href="`/mailing/create?template=${tpl.id}`" class="chip ml-1">{{ tpl.name }}</Link>
                </div>

                <fieldset class="space-y-3">
                    <legend class="font-medium text-slate-900">{{ t('mailing.fields.mode') }}</legend>
                    <div class="flex flex-wrap gap-4 text-sm">
                        <label class="flex items-center gap-2"><input v-model="mode" type="radio" value="broadcast" /> {{ t('mailing.modes.broadcast') }}</label>
                        <label class="flex items-center gap-2"><input v-model="mode" type="radio" value="automation" :disabled="!triggers.length" /> {{ t('mailing.modes.automation') }}</label>
                    </div>
                    <p v-if="!triggers.length" class="text-xs text-slate-500">{{ t('mailing.campaigns.no_triggers') }}</p>

                    <div v-if="mode === 'automation'" class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label for="m_trigger">{{ t('mailing.fields.trigger') }}</label>
                            <select id="m_trigger" v-model="form.trigger_event">
                                <option v-for="tr in triggers" :key="tr.key" :value="tr.key">{{ tr.label }}</option>
                            </select>
                            <InputError :message="form.errors.trigger_event" />
                        </div>
                        <div>
                            <label for="m_delay">{{ t('mailing.fields.delay') }}</label>
                            <div class="flex gap-2">
                                <input id="m_delay" v-model.number="delayAmount" type="number" min="0" class="w-24" />
                                <select :value="delayUnit" :aria-label="t('mailing.fields.delay')" @change="setDelayUnit($event.target.value)">
                                    <option value="h">{{ t('mailing.delay.unit_hours') }}</option>
                                    <option value="d">{{ t('mailing.delay.unit_days') }}</option>
                                </select>
                            </div>
                            <div class="mt-2 flex flex-wrap gap-1">
                                <button v-for="h in presets" :key="h" type="button" class="chip" @click="delayUnit = h % 24 === 0 ? 'd' : 'h'; form.delay_hours = h">{{ delayLabel(h) }}</button>
                            </div>
                            <InputError :message="form.errors.delay_hours" />
                        </div>
                        <label class="flex items-start gap-2 text-sm md:col-span-2">
                            <input v-model="form.reply_in_thread" type="checkbox" class="mt-1" />
                            <span>{{ t('mailing.fields.reply_in_thread') }}<br><span class="text-xs text-slate-500">{{ t('mailing.fields.reply_hint') }}</span></span>
                        </label>
                        <label v-if="isReply && trigger?.quotes" class="flex items-center gap-2 text-sm md:col-span-2">
                            <input v-model="form.quote_event" type="checkbox" /> {{ t('mailing.fields.quote_event') }}
                        </label>
                    </div>
                </fieldset>

                <fieldset v-if="!isReply" class="space-y-3">
                    <legend class="font-medium text-slate-900">{{ t('mailing.fields.audience') }}</legend>
                    <div class="flex flex-wrap gap-4 text-sm">
                        <label class="flex items-center gap-2"><input v-model="form.kind" type="radio" value="offer" /> {{ t('mailing.kinds.offer') }}</label>
                        <label class="flex items-center gap-2"><input v-model="form.kind" type="radio" value="service" /> {{ t('mailing.kinds.service') }}</label>
                    </div>
                    <p class="text-xs" :class="form.kind === 'service' ? 'text-amber-700' : 'text-slate-500'">{{ t(`mailing.kinds.${form.kind}_hint`) }}</p>

                    <template v-if="tags.length">
                        <div v-for="part in ['any', 'none']" :key="part">
                            <p class="mb-1 text-sm text-slate-600">{{ t(`mailing.fields.audience_${part}`) }}</p>
                            <div class="flex flex-wrap gap-1">
                                <button
                                    v-for="tag in tags"
                                    :key="tag.key"
                                    type="button"
                                    class="chip"
                                    :class="form.audience[part].includes(tag.key) ? (part === 'any' ? 'bg-brand-600 text-white' : 'bg-rose-600 text-white') : ''"
                                    :aria-pressed="form.audience[part].includes(tag.key)"
                                    :title="tag.description || tag.key"
                                    @click="toggleTag(part, tag.key)"
                                >{{ tag.label }}</button>
                            </div>
                        </div>
                    </template>
                    <p v-else class="text-xs text-slate-500">{{ t('mailing.campaigns.no_tags') }}</p>

                    <p class="text-sm text-slate-700">
                        {{ mode === 'automation' ? t('mailing.campaigns.audience_now_automation') : t('mailing.campaigns.audience_now') }}
                        <strong>{{ audienceCount ?? '…' }}</strong>
                    </p>
                    <InputError :message="form.errors.kind || form.errors.audience" />
                </fieldset>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label for="m_mailbox">{{ t('mailing.fields.mailbox') }}</label>
                        <select id="m_mailbox" v-model="form.mailbox_id">
                            <option :value="null">{{ t('mailing.fields.mailbox_default') }}</option>
                            <option v-for="m in product?.mailboxes ?? []" :key="m.id" :value="m.id">{{ m.label }}</option>
                        </select>
                    </div>
                    <div v-if="!isReply">
                        <label for="m_layout">{{ t('mailing.fields.layout') }}</label>
                        <select id="m_layout" v-model="form.layout_id">
                            <option :value="null">{{ t('mailing.fields.layout_default') }}</option>
                            <option v-for="l in productLayouts" :key="l.id" :value="l.id">{{ l.name }}</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label for="m_subject">{{ t('mailing.fields.subject') }}</label>
                    <input id="m_subject" v-model="form.subject" type="text" :required="!isReply" :placeholder="isReply ? t('mailing.fields.subject_reply') : ''" />
                    <InputError :message="form.errors.subject" />
                </div>
                <div v-if="!isReply">
                    <label for="m_heading">{{ t('mailing.fields.heading') }}</label>
                    <input id="m_heading" v-model="form.heading" type="text" />
                    <InputError :message="form.errors.heading" />
                </div>
                <div>
                    <label for="m_body">{{ t('mailing.fields.body') }}</label>
                    <HtmlEditor id="m_body" v-model="form.body" :placeholder="isReply ? t('mailing.editor.placeholder_reply') : t('mailing.editor.placeholder')" min-height="14rem" />
                    <p class="mt-1 text-xs text-slate-500">{{ t('mailing.fields.body_hint') }}</p>
                    <p v-if="variables.length" class="mt-1 text-xs text-slate-500">
                        {{ t('mailing.fields.variables') }}
                        <code v-for="v in variables" :key="v" class="ml-1 rounded bg-slate-100 px-1">{{ '{' + v + '}' }}</code>
                    </p>
                    <InputError :message="form.errors.body" />
                </div>

                <div v-if="!isReply" class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label for="m_btn_label">{{ t('mailing.fields.button_label') }}</label>
                        <input id="m_btn_label" v-model="form.button_label" type="text" />
                    </div>
                    <div>
                        <label for="m_btn_url">{{ t('mailing.fields.button_url') }}</label>
                        <input id="m_btn_url" v-model="form.button_url" type="url" placeholder="https://" />
                        <InputError :message="form.errors.button_url" />
                    </div>
                    <div>
                        <label for="m_coupon">{{ t('mailing.fields.coupon_code') }}</label>
                        <input id="m_coupon" v-model="form.coupon_code" type="text" />
                    </div>
                    <div>
                        <label for="m_coupon_to">{{ t('mailing.fields.coupon_valid_to') }}</label>
                        <input id="m_coupon_to" v-model="form.coupon_valid_to" type="date" />
                    </div>
                    <label class="flex items-center gap-2 text-sm"><input v-model="form.track_clicks" type="checkbox" /> {{ t('mailing.fields.track_clicks') }}</label>
                    <label class="flex items-center gap-2 text-sm"><input v-model="form.track_opens" type="checkbox" /> {{ t('mailing.fields.track_opens') }}</label>
                </div>
            </fieldset>

            <div class="flex flex-wrap items-center gap-2 border-t border-slate-100 pt-4">
                <button v-if="isDraft" type="submit" class="btn-primary" :disabled="form.processing">{{ t('mailing.campaigns.save') }}</button>
                <button type="button" class="btn-secondary" :disabled="testing" @click="sendTest">
                    <Icon name="send" :size="16" />
                    {{ t('mailing.campaigns.test') }}
                </button>
                <button v-if="!isNew && !isDraft" type="button" class="btn-secondary" @click="router.post(`/mailing/${campaign.id}/duplicate`)">{{ t('mailing.campaigns.duplicate') }}</button>
                <button v-if="!isNew && isDraft" type="button" class="btn-danger ml-auto" @click="remove">{{ t('mailing.campaigns.delete') }}</button>
            </div>
            <p v-if="notice" class="text-sm text-slate-600">{{ notice }}</p>

            <div v-if="!isNew && isDraft" class="space-y-3 rounded-lg bg-slate-50 p-4">
                <p class="font-medium text-slate-900">{{ mode === 'automation' ? t('mailing.campaigns.activate_title') : t('mailing.campaigns.queue_title') }}</p>
                <div v-if="mode === 'broadcast'">
                    <label for="m_schedule">{{ t('mailing.fields.scheduled_at') }}</label>
                    <input id="m_schedule" v-model="scheduledAt" type="datetime-local" />
                    <p class="mt-1 text-xs text-slate-500">{{ t('mailing.fields.scheduled_hint', { timezone: settings.timezone }) }}</p>
                </div>
                <p v-else class="text-sm text-slate-600">{{ t('mailing.campaigns.activate_hint') }}</p>
                <button type="button" class="btn-primary" @click="queue">{{ mode === 'automation' ? t('mailing.campaigns.activate') : t('mailing.campaigns.queue') }}</button>
            </div>
        </form>

        <div class="space-y-2">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-slate-900">{{ t('mailing.campaigns.preview') }}</h2>
                <button type="button" class="text-sm text-slate-500 hover:text-slate-900" @click="refresh"><Icon name="refresh" :size="16" /></button>
            </div>
            <p v-if="previewError" class="text-sm text-rose-600">{{ previewError }}</p>
            <EmailHtmlFrame v-if="html" :html="html" />
        </div>
    </div>
</template>
