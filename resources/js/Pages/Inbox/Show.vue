<script setup>
import { computed, nextTick, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import CardSection from '../../Components/CardSection.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import InputError from '../../Components/InputError.vue';
import Icon from '../../Components/Icon.vue';
import EmailHtmlFrame from '../../Components/EmailHtmlFrame.vue';
import ReplyTemplatePicker from '../../Components/ReplyTemplatePicker.vue';
import { locale, t } from '../../Composables/useLang';
import { excerpt, renderTemplate, searchTemplates } from '../../Composables/useReplyTemplates';
import { returnUrl } from '../../Composables/useReturnUrl';

const props = defineProps({
    conversation: { type: Object, required: true },
    related: { type: Array, default: () => [] },
    targets: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    templates: { type: Array, default: () => [] },
    templateContext: { type: Object, default: () => ({}) },
});

const replyForm = useForm({ text: '', subject: '', template_ids: [] });
const mergeForm = useForm({ target: '' });

/* ---------- Šablóny odpovedí ---------- */

const replyEl = ref(null);
const caret = ref(0);
const suggestActive = ref(0);
// Pozícia „/“, pri ktorej operátor návrhy zavrel Esc-om – kým ju nezmaže, neotravujeme.
const dismissedAt = ref(null);

const trackCaret = () => {
    caret.value = replyEl.value?.selectionStart ?? replyForm.text.length;
};

// „/skratka“ tesne pred kurzorom, na začiatku alebo po medzere.
const slash = computed(() => {
    const before = replyForm.text.slice(0, caret.value);
    const match = before.match(/(^|\s)\/([\p{L}\p{N}_-]*)$/u);
    if (!match) return null;

    const start = before.length - match[2].length - 1;

    return start === dismissedAt.value ? null : { start, query: match[2] };
});

const replyFocused = ref(false);

const suggestions = computed(() => (replyFocused.value && slash.value && props.templates.length
    ? searchTemplates(props.templates, slash.value.query).slice(0, 6)
    : []));

const onReplyInput = () => {
    trackCaret();
    suggestActive.value = 0;
    if (dismissedAt.value !== null && replyForm.text[dismissedAt.value] !== '/') dismissedAt.value = null;
};

/**
 * Vloží šablónu: nahradí „/skratku“, označený text, alebo celý prázdny
 * koncept; inak vkladá na miesto kurzora. Predmet doplní, len ak je prázdny.
 */
const applyTemplate = async (tpl, range = null) => {
    const el = replyEl.value;
    const rendered = renderTemplate(tpl.body, props.templateContext);
    let start = range?.start ?? el?.selectionStart ?? replyForm.text.length;
    let end = range?.end ?? el?.selectionEnd ?? start;

    if (!replyForm.text.trim()) {
        start = 0;
        end = replyForm.text.length;
    }

    replyForm.text = replyForm.text.slice(0, start) + rendered.text + replyForm.text.slice(end);

    if (tpl.subject && !replyForm.subject.trim()) {
        replyForm.subject = renderTemplate(tpl.subject, props.templateContext).text;
    }
    if (!replyForm.template_ids.includes(tpl.id)) replyForm.template_ids.push(tpl.id);

    dismissedAt.value = null;
    await nextTick();
    const position = start + (rendered.cursor ?? rendered.text.length);
    el?.focus();
    el?.setSelectionRange(position, position);
    trackCaret();
};

const pickSuggestion = (tpl) => applyTemplate(tpl, { start: slash.value.start, end: caret.value });

const onReplyKeydown = (event) => {
    if (suggestions.value.length && !event.ctrlKey && !event.metaKey) {
        const count = suggestions.value.length;
        if (event.key === 'ArrowDown') { event.preventDefault(); suggestActive.value = (suggestActive.value + 1) % count; return; }
        if (event.key === 'ArrowUp') { event.preventDefault(); suggestActive.value = (suggestActive.value - 1 + count) % count; return; }
        if (event.key === 'Tab' || event.key === 'Enter') {
            event.preventDefault();
            pickSuggestion(suggestions.value[suggestActive.value]);
            return;
        }
        if (event.key === 'Escape') { event.preventDefault(); dismissedAt.value = slash.value.start; return; }
    }

    if (event.key === 'Enter') sendShortcut(event);
};

// Uloženie aktuálnej odpovede ako šablóny. Konkrétne mená sa nahradia
// premennými, aby šablóna sedela aj pre ďalšieho klienta – operátor to vidí a môže upraviť.
const saveOpen = ref(false);
const saveForm = useForm({ name: '', shortcut: '', product_key: '', subject: '', body: '', private: false });

const toVariables = (text) => {
    const ctx = props.templateContext;
    const escape = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    let out = text ?? '';

    for (const key of ['name', 'email', 'agent', 'first_name', 'agent_first_name']) {
        const value = (ctx[key] ?? '').trim();
        if (value.length < 2) continue;
        out = out.replace(new RegExp(`(?<![\\p{L}\\p{N}])${escape(value)}(?![\\p{L}\\p{N}])`, 'gu'), `{${key}}`);
    }

    return out;
};

const openSave = () => {
    saveForm.clearErrors();
    Object.assign(saveForm, {
        name: '',
        shortcut: '',
        product_key: props.conversation.product_key ?? '',
        subject: replyForm.subject.trim(),
        body: toVariables(replyForm.text),
        private: false,
    });
    saveOpen.value = true;
};

const saveTemplate = () => saveForm.post('/inbox/templates', {
    preserveScroll: true,
    onSuccess: () => {
        saveOpen.value = false;
    },
});

// Citácia je pri prijatých správach predvolene skrytá – stačí, čo klient napísal.
const expanded = ref({});
const toggle = (id) => {
    expanded.value[id] = !expanded.value[id];
};

// Odoslané e-maily ukazujeme tak, ako ich videl klient; text je na prepnutie.
const asText = ref({});
const toggleText = (id) => {
    asText.value[id] = !asText.value[id];
};
const showHtml = (message) => message.html && !asText.value[message.id];

const sendReply = () => replyForm.post(`/inbox/${props.conversation.id}/reply`, {
    preserveScroll: true,
    onSuccess: () => {
        replyForm.reset();
        replyForm.template_ids = [];
    },
});

const merge = () => mergeForm.post(`/inbox/${props.conversation.id}/merge`);

const isSpam = computed(() => props.conversation.status === 'spam');

const markSpam = () => {
    if (!confirm(t('messaging.inbox.spam_confirm', { email: props.conversation.contact_email }))) return;
    router.post(`/inbox/${props.conversation.id}/spam`);
};

const notSpam = () => router.post(`/inbox/${props.conversation.id}/not-spam`, {}, { preserveScroll: true });

const destroy = () => {
    if (!confirm(t('messaging.inbox.delete_confirm'))) return;
    router.delete(`/inbox/${props.conversation.id}`);
};

// Keď už nie sme na ťahu (vybavené, čaká sa na klienta), nie je dôvod vo vlákne
// zostávať – vrátime sa tam, odkiaľ operátor prišiel. Pri „Otvorené“ si ho práve
// berie do práce, tak zostane.
const changeStatus = (event) => {
    const status = event.target.value;
    const leave = status !== 'open';

    router.post(
        `/inbox/${props.conversation.id}/status`,
        { status, return_to: leave ? returnUrl('/inbox') : null },
        { preserveScroll: !leave },
    );
};

const when = (iso) => (iso
    ? new Intl.DateTimeFormat(locale(), { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso))
    : '');

const size = (bytes) => (bytes > 1024 * 1024
    ? `${(bytes / 1024 / 1024).toFixed(1)} MB`
    : `${Math.max(1, Math.round(bytes / 1024))} kB`);

const body = (message) => {
    if (message.direction === 'in' && !expanded.value[message.id]) {
        return message.reply_text || message.text;
    }

    return message.text;
};

const title = computed(() => props.conversation.ref_label || props.conversation.subject || props.conversation.contact_email);

// Predmet správy ukážeme len vtedy, keď hovorí niečo iné ako nadpis vlákna.
const normalize = (text) => (text ?? '').replace(/^\s*((re|fw|fwd|aw|wg|odp|tr)\s*:\s*)+/i, '').trim().toLowerCase();
const showSubject = (message) => message.subject && !normalize(title.value).includes(normalize(message.subject));

const replySubject = computed(() => {
    const subject = props.conversation.subject ?? '';

    return /^re:/i.test(subject) ? subject : `Re: ${subject}`;
});

// Ctrl/⌘ + Enter odošle odpoveď bez siahania po myši.
const sendShortcut = (event) => {
    if ((event.ctrlKey || event.metaKey) && !replyForm.processing && replyForm.text.trim()) {
        sendReply();
    }
};

const hasQuote = (message) => message.direction === 'in'
    && message.reply_text
    && message.text
    && message.reply_text.trim() !== message.text.trim();
</script>

<template>
    <Head :title="title" />

    <div class="mb-6">
        <Link href="/inbox" class="text-sm text-slate-500 hover:text-slate-900">‹ {{ t('messaging.inbox.back') }}</Link>

        <div class="mt-3 flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <h1 class="truncate text-2xl font-semibold tracking-tight text-slate-900">
                    {{ title }}
                </h1>
                <p class="mt-1.5 text-sm text-slate-500">
                    <span class="font-medium text-slate-700">{{ conversation.contact_name || conversation.contact_email }}</span>
                    <span v-if="conversation.contact_name"> &lt;{{ conversation.contact_email }}&gt;</span>
                    · {{ conversation.product }}
                    <template v-if="conversation.organization">
                        · <Link :href="`/organizations/${conversation.organization_id}`" class="link">{{ conversation.organization }}</Link>
                    </template>
                </p>
            </div>

            <div class="flex shrink-0 items-center gap-2">
                <a v-if="conversation.ref_url" :href="conversation.ref_url" target="_blank" rel="noopener" class="btn-secondary btn-sm">
                    <Icon name="external" :size="15" />
                    {{ t('messaging.inbox.open_in_project') }}
                </a>
                <button v-if="!conversation.assigned && !isSpam" type="button" class="btn-secondary btn-sm" @click="markSpam">
                    <Icon name="ban" :size="15" />
                    {{ t('messaging.inbox.spam_button') }}
                </button>
                <button v-if="!conversation.assigned && !isSpam" type="button" class="btn-secondary btn-sm text-rose-700" @click="destroy">
                    <Icon name="trash" :size="15" />
                    {{ t('messaging.inbox.delete') }}
                </button>
                <select v-if="!isSpam" :value="conversation.status" class="w-auto py-1.5" :title="t('messaging.inbox.change_status')" :aria-label="t('messaging.inbox.change_status')" @change="changeStatus">
                    <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                </select>
            </div>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <!-- Spam – odosielateľ je zablokovaný -->
            <div v-if="isSpam" class="card border-rose-200 p-5">
                <h2 class="font-semibold text-rose-800">{{ t('messaging.inbox.spam_banner_title') }}</h2>
                <p class="mt-1 text-sm text-slate-600">{{ t('messaging.inbox.spam_banner_hint') }}</p>
                <div class="mt-4 flex flex-wrap gap-3">
                    <button type="button" class="btn-primary" @click="notSpam">{{ t('messaging.inbox.not_spam') }}</button>
                    <button type="button" class="btn-secondary text-rose-700" @click="destroy">
                        <Icon name="trash" :size="16" />
                        {{ t('messaging.inbox.delete') }}
                    </button>
                </div>
            </div>

            <!-- Všeobecná správa – priradenie k veci v projekte je voliteľné -->
            <div v-else-if="!conversation.assigned" class="card border-amber-200 p-5">
                <h2 class="font-semibold text-amber-800">{{ t('messaging.inbox.unassigned_title') }}</h2>
                <p class="mt-1 text-sm text-slate-600">{{ t('messaging.inbox.unassigned_hint') }}</p>
                <p v-if="!targets.length" class="mt-3 text-sm text-slate-500">{{ t('messaging.inbox.merge_none') }}</p>
                <form v-else class="mt-4 flex flex-wrap items-end gap-3" @submit.prevent="merge">
                    <div class="min-w-64 flex-1">
                        <label for="merge_target">{{ t('messaging.inbox.merge_label') }}</label>
                        <select id="merge_target" v-model="mergeForm.target" required>
                            <option value="" disabled>{{ t('messaging.inbox.merge_choose') }}</option>
                            <option v-for="target in targets" :key="target.id" :value="target.id">{{ target.label }}</option>
                        </select>
                        <InputError :message="mergeForm.errors.target" />
                    </div>
                    <button type="submit" class="btn-primary" :disabled="mergeForm.processing || !mergeForm.target">
                        {{ t('messaging.inbox.merge_button') }}
                    </button>
                </form>
            </div>

            <!-- Vlákno -->
            <article
                v-for="message in conversation.messages"
                :key="message.id"
                class="card p-5"
                :class="message.mailing
                    ? 'ml-6 border-dashed border-slate-200 bg-slate-50/70 opacity-80'
                    : (message.direction === 'out' ? 'ml-6 border-brand-100 bg-brand-50/30' : 'mr-6')"
            >
                <p v-if="message.mailing" class="mb-3 flex items-center gap-1.5 text-xs font-medium text-slate-500">
                    <Icon name="send" :size="14" />
                    {{ message.campaign ? t('messaging.inbox.mailing_badge', { campaign: message.campaign }) : t('messaging.inbox.source.mailing') }}
                </p>
                <header class="flex flex-wrap items-start justify-between gap-2 text-sm">
                    <div class="min-w-0">
                        <p class="truncate font-medium text-slate-900">{{ message.from }}</p>
                        <p class="truncate text-xs text-slate-500">
                            {{ t('messaging.inbox.to') }}: {{ message.to }}
                            · {{ t(`messaging.inbox.source.${message.source}`) }}
                            <template v-if="message.sent_by"> · {{ t('messaging.inbox.sent_by', { name: message.sent_by }) }}</template>
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <time :datetime="message.created_at" class="text-xs text-slate-500">{{ when(message.created_at) }}</time>
                        <StatusBadge :status="message.status" :label="message.status_label" :tone="message.status_tone" />
                    </div>
                </header>

                <p v-if="showSubject(message)" class="mt-3 text-sm font-medium text-slate-700">{{ message.subject }}</p>
                <EmailHtmlFrame v-if="showHtml(message)" :html="message.html" collapse-quotes class="mt-3" />
                <div v-else class="mt-2 whitespace-pre-line break-words text-sm leading-relaxed text-slate-800">{{ body(message) }}</div>

                <button v-if="message.html" type="button" class="mt-2 text-xs text-slate-500 hover:text-slate-900" @click="toggleText(message.id)">
                    {{ asText[message.id] ? t('messaging.inbox.show_as_html') : t('messaging.inbox.show_as_text') }}
                </button>

                <button v-if="hasQuote(message)" type="button" class="mt-2 text-xs text-slate-500 hover:text-slate-900" @click="toggle(message.id)">
                    {{ expanded[message.id] ? t('messaging.inbox.hide_full') : t('messaging.inbox.show_full') }}
                </button>

                <p v-if="message.error" class="mt-3 rounded-lg bg-rose-50 px-3 py-2 text-xs text-rose-700">{{ message.error }}</p>

                <div v-if="message.attachments.length" class="mt-3 flex flex-wrap gap-2">
                    <a
                        v-for="file in message.attachments"
                        :key="file.url"
                        :href="file.url"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-700 hover:border-slate-300"
                    >
                        <Icon name="paperclip" :size="14" />
                        {{ file.filename }}
                        <span class="text-slate-400">{{ size(file.size) }}</span>
                    </a>
                </div>
            </article>

            <!-- Odpoveď -->
            <CardSection v-if="!isSpam" :title="t('messaging.inbox.reply_title')" icon="send">
                <form class="space-y-3" @submit.prevent="sendReply">
                    <div>
                        <label for="reply_subject">{{ t('messaging.inbox.reply_subject') }}</label>
                        <input id="reply_subject" v-model="replyForm.subject" type="text" :placeholder="replySubject" />
                        <InputError :message="replyForm.errors.subject" />
                    </div>
                    <div>
                        <div class="mb-1.5 flex items-end justify-between gap-3">
                            <label for="reply_text" class="mb-0">{{ t('messaging.inbox.reply_text') }}</label>
                            <ReplyTemplatePicker :templates="templates" :context="templateContext" @pick="applyTemplate" @save="openSave" />
                        </div>
                        <div class="relative">
                            <textarea
                                id="reply_text"
                                ref="replyEl"
                                v-model="replyForm.text"
                                rows="6"
                                :placeholder="templates.length ? t('messaging.templates.reply_placeholder') : t('messaging.inbox.reply_placeholder')"
                                required
                                role="combobox"
                                aria-autocomplete="list"
                                :aria-expanded="suggestions.length > 0"
                                aria-controls="reply_suggestions"
                                @input="onReplyInput"
                                @click="trackCaret"
                                @keyup="trackCaret"
                                @keydown="onReplyKeydown"
                                @focus="replyFocused = true"
                                @blur="replyFocused = false"
                            ></textarea>

                            <!-- Návrhy pri písaní „/skratka“ -->
                            <ul
                                v-if="suggestions.length"
                                id="reply_suggestions"
                                role="listbox"
                                class="absolute inset-x-0 top-full z-20 mt-1 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-lg"
                            >
                                <li
                                    v-for="(tpl, index) in suggestions"
                                    :key="tpl.id"
                                    role="option"
                                    :aria-selected="index === suggestActive"
                                    class="flex cursor-pointer items-baseline gap-2 px-3 py-1.5 text-sm"
                                    :class="index === suggestActive ? 'bg-brand-50' : 'hover:bg-slate-50'"
                                    @mouseenter="suggestActive = index"
                                    @mousedown.prevent="pickSuggestion(tpl)"
                                >
                                    <code v-if="tpl.shortcut" class="shrink-0 text-xs text-slate-500">/{{ tpl.shortcut }}</code>
                                    <span class="shrink-0 font-medium text-slate-900">{{ tpl.name }}</span>
                                    <span class="truncate text-xs text-slate-400">{{ excerpt(tpl.body, templateContext, 80) }}</span>
                                </li>
                                <li class="border-t border-slate-100 px-3 pt-1.5 pb-1 text-[11px] text-slate-400">{{ t('messaging.templates.suggest_hint') }}</li>
                            </ul>
                        </div>
                        <InputError :message="replyForm.errors.text" />
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <span class="hidden text-xs text-slate-500 sm:inline">{{ t('messaging.inbox.reply_shortcut') }}</span>
                        <button type="submit" class="btn-primary" :disabled="replyForm.processing || !replyForm.text.trim()">
                            <Icon name="send" :size="16" />
                            {{ replyForm.processing ? t('messaging.inbox.reply_sending') : t('messaging.inbox.reply_send') }}
                        </button>
                    </div>
                </form>

                <!-- Uložiť odpoveď ako šablónu – vlastný formulár, Enter tu nesmie odoslať odpoveď -->
                <form v-if="saveOpen" class="mt-4 space-y-3 rounded-xl border border-brand-100 bg-brand-50/40 p-4" @submit.prevent="saveTemplate" @keydown.esc.stop="saveOpen = false">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-medium text-slate-900">{{ t('messaging.templates.save_title') }}</p>
                            <p class="mt-0.5 text-xs text-slate-500">{{ t('messaging.templates.save_hint') }}</p>
                        </div>
                        <button type="button" class="text-slate-400 hover:text-slate-700" :aria-label="t('messaging.templates.cancel')" @click="saveOpen = false">
                            <Icon name="close" :size="16" />
                        </button>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="sm:col-span-2">
                            <label for="save_name">{{ t('messaging.templates.name') }}</label>
                            <input id="save_name" v-model="saveForm.name" type="text" maxlength="120" :placeholder="t('messaging.templates.name_placeholder')" />
                            <InputError :message="saveForm.errors.name" />
                        </div>
                        <div>
                            <label for="save_shortcut">{{ t('messaging.templates.shortcut') }}</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">/</span>
                                <input id="save_shortcut" v-model="saveForm.shortcut" type="text" maxlength="40" class="pl-6" autocomplete="off" />
                            </div>
                            <InputError :message="saveForm.errors.shortcut" />
                        </div>
                    </div>
                    <div>
                        <label for="save_body">{{ t('messaging.templates.body') }}</label>
                        <textarea id="save_body" v-model="saveForm.body" rows="5"></textarea>
                        <InputError :message="saveForm.errors.body" />
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex flex-wrap items-center gap-4 text-sm">
                            <select v-model="saveForm.product_key" class="w-auto py-1.5" :aria-label="t('messaging.templates.product')">
                                <option :value="conversation.product_key">{{ t('messaging.templates.only_product', { product: conversation.product }) }}</option>
                                <option value="">{{ t('messaging.templates.all_products') }}</option>
                            </select>
                            <label class="mb-0 flex items-center gap-2 font-normal"><input v-model="saveForm.private" type="checkbox" /> {{ t('messaging.templates.private') }}</label>
                        </div>
                        <button type="submit" class="btn-primary btn-sm" :disabled="saveForm.processing || !saveForm.name.trim() || !saveForm.body.trim()">
                            {{ t('messaging.templates.save') }}
                        </button>
                    </div>
                </form>
            </CardSection>
        </div>

        <aside class="space-y-4">
            <CardSection :title="t('messaging.inbox.details_title')">
                <dl class="-my-1 space-y-2.5 text-sm">
                    <div>
                        <dt class="text-xs text-slate-500">{{ t('messaging.inbox.contact') }}</dt>
                        <dd class="break-words text-slate-800">
                            <span v-if="conversation.contact_name" class="font-medium">{{ conversation.contact_name }}<br /></span>
                            <a :href="`mailto:${conversation.contact_email}`" class="link font-normal">{{ conversation.contact_email }}</a>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">{{ t('messaging.inbox.product') }}</dt>
                        <dd class="text-slate-800">{{ conversation.product }}</dd>
                    </div>
                    <div v-if="conversation.organization">
                        <dt class="text-xs text-slate-500">{{ t('messaging.inbox.organization') }}</dt>
                        <dd><Link :href="`/organizations/${conversation.organization_id}`" class="link font-normal">{{ conversation.organization }}</Link></dd>
                    </div>
                    <div v-if="conversation.mailbox">
                        <dt class="text-xs text-slate-500">{{ t('messaging.inbox.mailbox') }}</dt>
                        <dd class="break-words text-slate-800">{{ conversation.mailbox }}</dd>
                    </div>
                </dl>
            </CardSection>

            <CardSection :title="t('messaging.inbox.related_title')">
                <ul v-if="related.length" class="-my-2 divide-y divide-slate-100">
                    <li v-for="c in related" :key="c.id" class="py-2">
                        <Link :href="`/inbox/${c.id}`" class="block text-sm hover:text-brand-700">
                            <span class="font-medium text-slate-800">{{ c.ref_label || c.subject || c.contact_email }}</span>
                            <span class="mt-0.5 flex items-center gap-1.5 text-xs text-slate-500">
                                <span class="rounded bg-slate-100 px-1.5 py-0.5">{{ c.product }}</span>
                                {{ c.contact_email }}
                            </span>
                        </Link>
                    </li>
                </ul>
                <p v-else class="text-sm text-slate-500">{{ t('messaging.inbox.related_empty') }}</p>
            </CardSection>

        </aside>
    </div>
</template>
