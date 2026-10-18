<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import InputError from '../../Components/InputError.vue';
import Icon from '../../Components/Icon.vue';
import { t } from '../../Composables/useLang';

const props = defineProps({
    mailboxes: { type: Array, default: () => [] },
    products: { type: Array, default: () => [] },
});

const blank = () => ({
    product_key: props.products[0]?.key ?? '',
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
    is_default: true,
    is_active: true,
});

const form = useForm(blank());
const editing = ref(null);
const open = ref(false);

const create = () => {
    editing.value = null;
    form.defaults(blank());
    form.reset();
    form.clearErrors();
    open.value = true;
};

const edit = (mailbox) => {
    editing.value = mailbox;
    form.clearErrors();
    Object.assign(form, {
        ...blank(),
        ...Object.fromEntries(Object.entries(mailbox).filter(([key]) => key in blank())),
        smtp_password: '',
        imap_password: '',
    });
    open.value = true;
};

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
            <button type="button" class="btn-primary" @click="create">
                <Icon name="plus" :size="16" />
                {{ t('messaging.mailboxes.add') }}
            </button>
        </template>
    </PageHeader>

    <!-- Formulár -->
    <form v-if="open" class="card mb-6 space-y-6 p-5" @submit.prevent="submit">
        <h2 class="font-semibold text-slate-900">
            {{ editing ? t('messaging.mailboxes.edit') : t('messaging.mailboxes.add') }}
        </h2>

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
                <input id="mb_name" v-model="form.name" type="text" placeholder="Obchod" required />
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
                    <input id="mb_imap_port" v-model.number="form.imap_port" type="number" placeholder="993" />
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
                </div>
                <div>
                    <label for="mb_imap_pass">{{ t('messaging.mailboxes.password') }}</label>
                    <input
                        id="mb_imap_pass"
                        v-model="form.imap_password"
                        type="password"
                        autocomplete="new-password"
                        :placeholder="editing?.has_imap_password ? t('messaging.mailboxes.password_keep') : ''"
                    />
                </div>
                <div>
                    <label for="mb_imap_folder">{{ t('messaging.mailboxes.folder') }}</label>
                    <input id="mb_imap_folder" v-model="form.imap_folder" type="text" />
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
                    <input id="mb_smtp_port" v-model.number="form.smtp_port" type="number" placeholder="587" />
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
                </div>
                <div class="md:col-span-2">
                    <label for="mb_smtp_pass">{{ t('messaging.mailboxes.password') }}</label>
                    <input
                        id="mb_smtp_pass"
                        v-model="form.smtp_password"
                        type="password"
                        autocomplete="new-password"
                        :placeholder="editing?.has_smtp_password ? t('messaging.mailboxes.password_keep') : ''"
                    />
                </div>
            </div>
        </fieldset>

        <div class="flex flex-wrap gap-6">
            <label class="mb-0 flex items-center gap-2"><input v-model="form.is_default" type="checkbox" /> {{ t('messaging.mailboxes.is_default') }}</label>
            <label class="mb-0 flex items-center gap-2"><input v-model="form.is_active" type="checkbox" /> {{ t('messaging.mailboxes.is_active') }}</label>
        </div>

        <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
            <button type="button" class="btn-secondary" @click="close">{{ t('messaging.mailboxes.cancel') }}</button>
            <button type="submit" class="btn-primary" :disabled="form.processing">{{ t('messaging.mailboxes.save') }}</button>
        </div>
    </form>

    <!-- Zoznam -->
    <div class="card overflow-hidden">
        <ul class="divide-y divide-slate-100">
            <li v-for="m in mailboxes" :key="m.id" class="flex flex-wrap items-center gap-4 px-4 py-3.5" :class="m.is_active ? '' : 'opacity-60'">
                <span class="chip h-9 w-9 shrink-0 bg-brand-50 text-brand-600"><Icon name="mail" :size="18" /></span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-slate-900">
                        {{ m.from_address }}
                        <span v-if="m.is_default" class="ml-1 rounded bg-emerald-50 px-1.5 py-0.5 text-xs font-medium text-emerald-700">{{ t('messaging.mailboxes.default_badge') }}</span>
                        <span v-if="!m.is_active" class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-600">{{ t('messaging.mailboxes.inactive_badge') }}</span>
                    </p>
                    <p class="truncate text-xs text-slate-500">
                        {{ m.product }} · {{ m.name }}
                        ·
                        <template v-if="m.imap_host">
                            {{ m.imap_checked_at ? t('messaging.mailboxes.checked', { time: m.imap_checked_at }) : t('messaging.mailboxes.never_checked') }}
                        </template>
                        <template v-else>{{ t('messaging.mailboxes.no_imap') }}</template>
                    </p>
                    <p v-if="m.imap_error" class="mt-1 truncate text-xs text-rose-600" :title="m.imap_error">{{ m.imap_error }}</p>
                </div>
                <div class="flex shrink-0 gap-2">
                    <button type="button" class="btn-secondary btn-sm" :disabled="testing === m.id" @click="test(m)">
                        <Icon name="refresh" :size="14" /> {{ t('messaging.mailboxes.test') }}
                    </button>
                    <button type="button" class="btn-secondary btn-sm" @click="edit(m)">
                        <Icon name="pencil" :size="14" />
                    </button>
                    <button type="button" class="btn-secondary btn-sm text-rose-600" @click="destroy(m)">
                        <Icon name="trash" :size="14" />
                    </button>
                </div>
            </li>
            <li v-if="mailboxes.length === 0" class="px-4 py-10 text-center text-sm text-slate-500">
                {{ t('messaging.mailboxes.empty') }}
            </li>
        </ul>
    </div>
</template>
