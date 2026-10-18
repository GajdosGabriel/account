<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import InputError from '../../Components/InputError.vue';
import PasswordInput from '../../Components/PasswordInput.vue';
import Icon from '../../Components/Icon.vue';
import { t } from '../../Composables/useLang';

const props = defineProps({
    mailboxes: { type: Array, default: () => [] },
    products: { type: Array, default: () => [] },
});

// Prvá schránka projektu je predvolená sama od seba, ďalšia by jej to
// potichu zobrala – preto ju predvolíme len tam, kde ešte žiadna nie je.
const hasMailbox = (productKey) => props.mailboxes.some((m) => m.product_key === productKey);

const blank = (productKey = props.products[0]?.key ?? '') => ({
    product_key: productKey,
    name: '',
    from_address: '',
    from_name: '',
    smtp_host: '',
    smtp_port: null,
    smtp_encryption: 'tls',
    smtp_username: '',
    smtp_password: '',
    imap_host: '',
    imap_port: null,
    imap_encryption: 'ssl',
    imap_username: '',
    imap_password: '',
    imap_folder: 'INBOX',
    is_default: !hasMailbox(productKey),
    is_active: true,
});

const form = useForm(blank());
const editing = ref(null);
const open = ref(false);
const formEl = ref(null);

// Formulár je nad zoznamom – pri úprave schránky dole by sa otvoril mimo obrazovky.
const reveal = async () => {
    open.value = true;
    await nextTick();
    formEl.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    formEl.value?.querySelector('input:not([type=hidden]), select')?.focus({ preventScroll: true });
};

const create = () => {
    editing.value = null;
    form.defaults(blank());
    form.reset();
    form.clearErrors();
    reveal();
};

const edit = (mailbox) => {
    editing.value = mailbox;
    form.clearErrors();
    Object.assign(form, {
        ...blank(mailbox.product_key),
        // null z DB by prepísal predvolené hodnoty (šifrovanie, priečinok)
        ...Object.fromEntries(Object.entries(mailbox).filter(([key, value]) => key in blank() && value !== null)),
        smtp_password: '',
        imap_password: '',
    });
    reveal();
};

watch(() => form.product_key, (key) => {
    if (!editing.value) form.is_default = !hasMailbox(key);
});

const close = () => {
    open.value = false;
    editing.value = null;
};

const submit = () => {
    const options = { preserveScroll: true, onSuccess: close };

    editing.value
        ? form.patch(`/inbox/mailboxes/${editing.value.id}`, options)
        : form.post('/inbox/mailboxes', options);
};

// Bežný port podľa šifrovania – placeholder napovie, čo vyplniť.
const imapPort = computed(() => ({ ssl: 993, tls: 993, starttls: 143, none: 143 })[form.imap_encryption] ?? 993);
const smtpPort = computed(() => (form.smtp_encryption === 'ssl' ? 465 : 587));

const groups = computed(() => {
    const map = new Map();
    for (const m of props.mailboxes) {
        const key = m.product_key ?? '';
        if (!map.has(key)) map.set(key, { key, name: m.product, items: [] });
        map.get(key).items.push(m);
    }

    return [...map.values()];
});

const testing = ref(null);

const test = (mailbox) => router.post(`/inbox/mailboxes/${mailbox.id}/test`, {}, {
    preserveScroll: true,
    onStart: () => { testing.value = mailbox.id; },
    onFinish: () => { testing.value = null; },
});

const destroy = (mailbox) => {
    if (confirm(t('messaging.mailboxes.confirm_delete', { name: mailbox.from_address }))) {
        router.delete(`/inbox/mailboxes/${mailbox.id}`, { preserveScroll: true });
    }
};
</script>

<template>
    <Head :title="t('messaging.mailboxes.title')" />

    <div class="mb-3">
        <Link href="/inbox" class="text-sm text-slate-500 hover:text-slate-900">‹ {{ t('messaging.inbox.back') }}</Link>
    </div>

    <PageHeader :title="t('messaging.mailboxes.title')" :subtitle="t('messaging.mailboxes.subtitle')">
        <template #action>
            <button v-if="!open" type="button" class="btn-primary" @click="create">
                <Icon name="plus" :size="16" />
                {{ t('messaging.mailboxes.add') }}
            </button>
        </template>
    </PageHeader>

    <!-- Formulár -->
    <form v-if="open" ref="formEl" class="card mb-6 scroll-mt-24 space-y-6 p-5" @submit.prevent="submit" @keydown.esc="close">
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <h2 class="font-semibold text-slate-900">
                    {{ editing ? t('messaging.mailboxes.edit') : t('messaging.mailboxes.add') }}
                </h2>
                <p v-if="editing" class="mt-0.5 truncate text-sm text-slate-500">{{ editing.product }} · {{ editing.from_address }}</p>
            </div>
            <button type="button" class="text-slate-400 hover:text-slate-700" :title="t('messaging.mailboxes.cancel')" :aria-label="t('messaging.mailboxes.cancel')" @click="close">
                <Icon name="close" :size="18" />
            </button>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div v-if="!editing">
                <label for="mb_product">{{ t('messaging.mailboxes.product') }}</label>
                <select id="mb_product" v-model="form.product_key" required>
                    <option v-for="p in products" :key="p.key" :value="p.key">{{ p.name }}</option>
                </select>
                <InputError :message="form.errors.product_key" />
            </div>
            <div>
                <label for="mb_name">{{ t('messaging.mailboxes.name') }}</label>
                <input id="mb_name" v-model="form.name" type="text" required />
                <InputError :message="form.errors.name" />
            </div>
            <div>
                <label for="mb_from">{{ t('messaging.mailboxes.from_address') }}</label>
                <input id="mb_from" v-model="form.from_address" type="email" placeholder="obchod@domena.sk" required />
                <InputError :message="form.errors.from_address" />
            </div>
            <div>
                <label for="mb_from_name">{{ t('messaging.mailboxes.from_name') }}</label>
                <input id="mb_from_name" v-model="form.from_name" type="text" />
                <InputError :message="form.errors.from_name" />
            </div>
        </div>

        <fieldset class="space-y-3">
            <legend class="font-medium text-slate-900">{{ t('messaging.mailboxes.imap') }}</legend>
            <p class="text-sm text-slate-500">{{ t('messaging.mailboxes.imap_hint') }}</p>
            <div class="grid gap-4 md:grid-cols-4">
                <div class="md:col-span-2">
                    <label for="mb_imap_host">{{ t('messaging.mailboxes.host') }}</label>
                    <input id="mb_imap_host" v-model="form.imap_host" type="text" placeholder="imap.gmail.com" />
                    <InputError :message="form.errors.imap_host" />
                </div>
                <div>
                    <label for="mb_imap_port">{{ t('messaging.mailboxes.port') }}</label>
                    <input id="mb_imap_port" v-model.number="form.imap_port" type="number" min="1" max="65535" :placeholder="imapPort" />
                    <InputError :message="form.errors.imap_port" />
                </div>
                <div>
                    <label for="mb_imap_enc">{{ t('messaging.mailboxes.encryption') }}</label>
                    <select id="mb_imap_enc" v-model="form.imap_encryption">
                        <option value="ssl">SSL</option>
                        <option value="tls">TLS</option>
                        <option value="starttls">STARTTLS</option>
                        <option value="none">—</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label for="mb_imap_user">{{ t('messaging.mailboxes.username') }}</label>
                    <input id="mb_imap_user" v-model="form.imap_username" type="text" autocomplete="off" />
                    <InputError :message="form.errors.imap_username" />
                </div>
                <div>
                    <label for="mb_imap_pass">{{ t('messaging.mailboxes.password') }}</label>
                    <PasswordInput
                        id="mb_imap_pass"
                        v-model="form.imap_password"
                        autocomplete="new-password"
                        :placeholder="editing?.has_imap_password ? t('messaging.mailboxes.password_keep') : ''"
                    />
                    <InputError :message="form.errors.imap_password" />
                </div>
                <div>
                    <label for="mb_imap_folder">{{ t('messaging.mailboxes.folder') }}</label>
                    <input id="mb_imap_folder" v-model="form.imap_folder" type="text" placeholder="INBOX" />
                    <InputError :message="form.errors.imap_folder" />
                </div>
            </div>
        </fieldset>

        <fieldset class="space-y-3">
            <legend class="font-medium text-slate-900">{{ t('messaging.mailboxes.smtp') }}</legend>
            <p class="text-sm text-slate-500">{{ t('messaging.mailboxes.smtp_hint') }}</p>
            <div class="grid gap-4 md:grid-cols-4">
                <div class="md:col-span-2">
                    <label for="mb_smtp_host">{{ t('messaging.mailboxes.host') }}</label>
                    <input id="mb_smtp_host" v-model="form.smtp_host" type="text" placeholder="smtp.gmail.com" />
                    <InputError :message="form.errors.smtp_host" />
                </div>
                <div>
                    <label for="mb_smtp_port">{{ t('messaging.mailboxes.port') }}</label>
                    <input id="mb_smtp_port" v-model.number="form.smtp_port" type="number" min="1" max="65535" :placeholder="smtpPort" />
                    <InputError :message="form.errors.smtp_port" />
                </div>
                <div>
                    <label for="mb_smtp_enc">{{ t('messaging.mailboxes.encryption') }}</label>
                    <select id="mb_smtp_enc" v-model="form.smtp_encryption">
                        <option value="tls">TLS</option>
                        <option value="ssl">SSL</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label for="mb_smtp_user">{{ t('messaging.mailboxes.username') }}</label>
                    <input id="mb_smtp_user" v-model="form.smtp_username" type="text" autocomplete="off" />
                    <InputError :message="form.errors.smtp_username" />
                </div>
                <div class="md:col-span-2">
                    <label for="mb_smtp_pass">{{ t('messaging.mailboxes.password') }}</label>
                    <PasswordInput
                        id="mb_smtp_pass"
                        v-model="form.smtp_password"
                        autocomplete="new-password"
                        :placeholder="editing?.has_smtp_password ? t('messaging.mailboxes.password_keep') : ''"
                    />
                    <InputError :message="form.errors.smtp_password" />
                </div>
            </div>
        </fieldset>

        <div class="flex flex-wrap gap-6 border-t border-slate-100 pt-5">
            <label class="mb-0 flex items-center gap-2 font-normal"><input v-model="form.is_default" type="checkbox" /> {{ t('messaging.mailboxes.is_default') }}</label>
            <label class="mb-0 flex items-center gap-2 font-normal"><input v-model="form.is_active" type="checkbox" /> {{ t('messaging.mailboxes.is_active') }}</label>
        </div>

        <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
            <button type="button" class="btn-secondary" @click="close">{{ t('messaging.mailboxes.cancel') }}</button>
            <button type="submit" class="btn-primary" :disabled="form.processing">{{ t('messaging.mailboxes.save') }}</button>
        </div>
    </form>

    <!-- Zoznam po projektoch -->
    <div v-if="mailboxes.length === 0" class="card flex flex-col items-center gap-3 px-4 py-12 text-center">
        <span class="chip h-11 w-11 bg-brand-50 text-brand-600"><Icon name="mail" :size="20" /></span>
        <p class="max-w-sm text-sm text-slate-500">{{ t('messaging.mailboxes.empty') }}</p>
        <button v-if="!open" type="button" class="btn-primary btn-sm" @click="create">
            <Icon name="plus" :size="14" />
            {{ t('messaging.mailboxes.add') }}
        </button>
    </div>

    <section v-for="group in groups" :key="group.key" class="mb-5">
        <h2 class="mb-2 px-1 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ group.name }}</h2>
        <div class="card overflow-hidden">
            <ul class="divide-y divide-slate-100">
                <li
                    v-for="m in group.items"
                    :key="m.id"
                    class="flex flex-wrap items-center gap-4 px-4 py-3.5"
                    :class="{ 'bg-brand-50/40': editing?.id === m.id }"
                >
                    <span class="chip h-9 w-9 shrink-0" :class="m.is_active ? 'bg-brand-50 text-brand-600' : 'bg-slate-100 text-slate-400'">
                        <Icon name="mail" :size="18" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="flex flex-wrap items-center gap-1.5 text-sm font-medium" :class="m.is_active ? 'text-slate-900' : 'text-slate-500'">
                            <span class="truncate">{{ m.from_address }}</span>
                            <span v-if="m.is_default" class="rounded bg-emerald-50 px-1.5 py-0.5 text-xs font-medium text-emerald-700">{{ t('messaging.mailboxes.default_badge') }}</span>
                            <span v-if="!m.is_active" class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-600">{{ t('messaging.mailboxes.inactive_badge') }}</span>
                        </p>
                        <p class="truncate text-xs text-slate-500">
                            {{ m.name }}
                            · {{ m.smtp_host ? `SMTP ${m.smtp_host}` : t('messaging.mailboxes.smtp_default') }}
                            ·
                            <template v-if="m.imap_host">
                                {{ m.imap_checked_at ? t('messaging.mailboxes.checked', { time: m.imap_checked_at }) : t('messaging.mailboxes.never_checked') }}
                            </template>
                            <template v-else>{{ t('messaging.mailboxes.no_imap') }}</template>
                        </p>
                        <p v-if="m.imap_error" class="mt-1 flex items-center gap-1 text-xs text-rose-600" :title="m.imap_error">
                            <Icon name="warning" :size="13" class="shrink-0" />
                            <span class="truncate">{{ m.imap_error }}</span>
                        </p>
                    </div>
                    <div class="flex shrink-0 gap-2">
                        <button type="button" class="btn-secondary btn-sm" :disabled="testing !== null" @click="test(m)">
                            <Icon name="refresh" :size="14" :class="{ 'animate-spin': testing === m.id }" />
                            {{ testing === m.id ? t('messaging.mailboxes.testing') : t('messaging.mailboxes.test') }}
                        </button>
                        <button type="button" class="btn-secondary btn-sm" :title="t('actions.edit')" :aria-label="`${t('actions.edit')} ${m.from_address}`" @click="edit(m)">
                            <Icon name="pencil" :size="14" />
                        </button>
                        <button type="button" class="btn-secondary btn-sm text-rose-600 hover:bg-rose-50" :title="t('messaging.mailboxes.delete')" :aria-label="`${t('messaging.mailboxes.delete')} ${m.from_address}`" @click="destroy(m)">
                            <Icon name="trash" :size="14" />
                        </button>
                    </div>
                </li>
            </ul>
        </div>
    </section>
</template>
