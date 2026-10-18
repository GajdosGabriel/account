<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import CardSection from '../../Components/CardSection.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import InputError from '../../Components/InputError.vue';
import Icon from '../../Components/Icon.vue';
import { locale, t } from '../../Composables/useLang';

const props = defineProps({
    conversation: { type: Object, required: true },
    related: { type: Array, default: () => [] },
    targets: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
});

const replyForm = useForm({ text: '', subject: '' });
const mergeForm = useForm({ target: '' });

// Citácia je pri prijatých správach predvolene skrytá – stačí, čo klient napísal.
const expanded = ref({});
const toggle = (id) => {
    expanded.value[id] = !expanded.value[id];
};

const sendReply = () => replyForm.post(`/inbox/${props.conversation.id}/reply`, {
    preserveScroll: true,
    onSuccess: () => replyForm.reset(),
});

const merge = () => mergeForm.post(`/inbox/${props.conversation.id}/merge`);

const changeStatus = (event) => router.post(
    `/inbox/${props.conversation.id}/status`,
    { status: event.target.value },
    { preserveScroll: true },
);

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

const hasQuote = (message) => message.direction === 'in'
    && message.reply_text
    && message.text
    && message.reply_text.trim() !== message.text.trim();
</script>

<template>
    <Head :title="conversation.contact_email" />

    <div class="mb-6">
        <Link href="/inbox" class="text-sm text-slate-500 hover:text-slate-900">‹ {{ t('messaging.inbox.back') }}</Link>

        <div class="mt-3 flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <h1 class="truncate text-2xl font-semibold tracking-tight text-slate-900">
                    {{ conversation.ref_label || conversation.subject || conversation.contact_email }}
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
                <select :value="conversation.status" class="w-auto py-1.5" :title="t('messaging.inbox.change_status')" @change="changeStatus">
                    <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                </select>
            </div>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <!-- Nepriradená správa -->
            <div v-if="!conversation.assigned" class="card border-rose-200 p-5">
                <h2 class="font-semibold text-rose-700">{{ t('messaging.inbox.unassigned_title') }}</h2>
                <p class="mt-1 text-sm text-slate-600">{{ t('messaging.inbox.unassigned_hint') }}</p>
                <form class="mt-4 flex flex-wrap items-end gap-3" @submit.prevent="merge">
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
                :class="message.direction === 'out' ? 'ml-6 border-brand-100 bg-brand-50/30' : 'mr-6'"
            >
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
                        <span class="text-xs text-slate-400">{{ when(message.created_at) }}</span>
                        <StatusBadge :status="message.status" :label="message.status_label" :tone="message.status_tone" />
                    </div>
                </header>

                <p v-if="message.subject" class="mt-3 text-sm font-medium text-slate-700">{{ message.subject }}</p>
                <div class="mt-2 whitespace-pre-line break-words text-sm leading-relaxed text-slate-800">{{ body(message) }}</div>

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
            <CardSection :title="t('messaging.inbox.reply_title')" icon="send">
                <p v-if="!conversation.assigned" class="text-sm text-slate-500">{{ t('messaging.inbox.reply_unassigned') }}</p>
                <form v-else class="space-y-3" @submit.prevent="sendReply">
                    <div>
                        <label for="reply_subject">{{ t('messaging.inbox.reply_subject') }}</label>
                        <input id="reply_subject" v-model="replyForm.subject" type="text" :placeholder="/^re:/i.test(conversation.subject ?? '') ? conversation.subject : `Re: ${conversation.subject ?? ''}`" />
                        <InputError :message="replyForm.errors.subject" />
                    </div>
                    <div>
                        <textarea v-model="replyForm.text" rows="6" :placeholder="t('messaging.inbox.reply_placeholder')" required></textarea>
                        <InputError :message="replyForm.errors.text" />
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="btn-primary" :disabled="replyForm.processing || !replyForm.text.trim()">
                            <Icon name="send" :size="16" />
                            {{ replyForm.processing ? t('messaging.inbox.reply_sending') : t('messaging.inbox.reply_send') }}
                        </button>
                    </div>
                </form>
            </CardSection>
        </div>

        <aside class="space-y-4">
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

            <div v-if="conversation.mailbox" class="px-1 text-xs text-slate-400">
                {{ t('messaging.inbox.mailbox') }}: {{ conversation.mailbox }}
            </div>
        </aside>
    </div>
</template>
