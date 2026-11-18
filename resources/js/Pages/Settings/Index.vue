<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import CardSection from '../../Components/CardSection.vue';
import InputError from '../../Components/InputError.vue';
import PageHeader from '../../Components/PageHeader.vue';
import PasswordInput from '../../Components/PasswordInput.vue';
import { t } from '../../Composables/useLang';

const props = defineProps({
    user: { type: Object, required: true },
    operators: { type: Array, default: () => [] },
});

const profileForm = useForm({ name: props.user.name, email: props.user.email });

const passwordForm = useForm({
    current_password: '', password: '', password_confirmation: '',
});

const operatorForm = useForm({ name: '', email: '' });
const addingOperator = ref(false);

const updateProfile = () => profileForm.patch('/settings/profile', { preserveScroll: true });

const updatePassword = () => passwordForm.put('/settings/password', {
    preserveScroll: true,
    onSuccess: () => passwordForm.reset(),
    onError: () => passwordForm.reset('current_password'),
});

const addOperator = () => operatorForm.post('/settings/operators', {
    preserveScroll: true,
    onSuccess: () => { operatorForm.reset(); addingOperator.value = false; },
});

const removeOperator = (op) => {
    if (!confirm(t('ui.settings.remove_confirm', { email: op.email }))) return;
    router.delete(`/settings/operators/${op.id}`, { preserveScroll: true });
};
</script>

<template>
    <Head :title="t('ui.settings.title')" />

    <PageHeader :title="t('ui.settings.title')" :subtitle="t('ui.settings.subtitle')" />

    <div class="grid gap-6 lg:grid-cols-3">
        <CardSection class="lg:col-span-2" icon="user" :title="t('ui.settings.profile')">
            <form class="space-y-5" @submit.prevent="updateProfile">
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="name">{{ t('ui.settings.full_name') }}</label>
                        <input id="name" v-model="profileForm.name" type="text" required />
                        <InputError :message="profileForm.errors.name" />
                    </div>
                    <div>
                        <label for="email">{{ t('ui.auth.email') }}</label>
                        <input id="email" v-model="profileForm.email" type="email" required />
                        <InputError :message="profileForm.errors.email" />
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button type="submit" class="btn-primary" :disabled="profileForm.processing">{{ t('ui.settings.save') }}</button>
                    <transition enter-active-class="transition duration-150" enter-from-class="opacity-0">
                        <span v-if="profileForm.recentlySuccessful" class="text-sm text-emerald-600">{{ t('ui.settings.saved') }}</span>
                    </transition>
                </div>
            </form>
        </CardSection>

        <CardSection icon="shield" tone="emerald" :title="t('ui.settings.overview')">
            <dl class="space-y-3.5 text-sm">
                <div>
                    <dt class="text-slate-500">{{ t('ui.settings.last_login') }}</dt>
                    <dd class="mt-0.5 font-medium text-slate-900">{{ user.last_login_at ?? t('ui.settings.now') }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">{{ t('ui.settings.operators_total') }}</dt>
                    <dd class="mt-0.5 font-medium text-slate-900">{{ operators.length }}</dd>
                </div>
            </dl>
        </CardSection>

        <CardSection
            class="lg:col-span-2"
            icon="key"
            tone="amber"
            :title="t('ui.settings.password')"
            :description="t('ui.settings.password_hint')"
        >
            <form class="space-y-5" @submit.prevent="updatePassword">
                <div>
                    <label for="current_password">{{ t('ui.settings.current_password') }}</label>
                    <PasswordInput id="current_password" v-model="passwordForm.current_password" autocomplete="current-password" required />
                    <InputError :message="passwordForm.errors.current_password" />
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="new_password">{{ t('ui.auth.new_password') }}</label>
                        <PasswordInput id="new_password" v-model="passwordForm.password" autocomplete="new-password" required />
                        <InputError :message="passwordForm.errors.password" />
                    </div>
                    <div>
                        <label for="new_password_confirmation">{{ t('ui.auth.new_password_again') }}</label>
                        <PasswordInput id="new_password_confirmation" v-model="passwordForm.password_confirmation" autocomplete="new-password" required />
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button type="submit" class="btn-primary" :disabled="passwordForm.processing">{{ t('ui.auth.change_password') }}</button>
                    <transition enter-active-class="transition duration-150" enter-from-class="opacity-0">
                        <span v-if="passwordForm.recentlySuccessful" class="text-sm text-emerald-600">{{ t('ui.settings.password_changed') }}</span>
                    </transition>
                </div>
            </form>
        </CardSection>

        <CardSection icon="user" :title="t('ui.settings.operators')" :description="t('ui.settings.operators_hint')">
            <template #action>
                <button type="button" class="btn-secondary btn-sm" @click="addingOperator = !addingOperator">
                    {{ addingOperator ? t('common.secret.close') : t('ui.settings.add') }}
                </button>
            </template>

            <ul class="divide-y divide-slate-100 text-sm">
                <li v-for="op in operators" :key="op.id" class="flex items-start justify-between gap-3 py-2.5">
                    <div class="min-w-0">
                        <p class="font-medium text-slate-900">
                            {{ op.name }}
                            <span v-if="op.is_me" class="ml-1 text-xs font-normal text-brand-600">({{ t('ui.settings.you') }})</span>
                        </p>
                        <p class="truncate text-xs text-slate-500">{{ op.email }}</p>
                        <p class="text-xs text-slate-400">{{ op.last_login_at ? t('ui.settings.logged_in_at', { date: op.last_login_at }) : t('ui.settings.never_logged_in') }}</p>
                    </div>
                    <button
                        v-if="!op.is_me"
                        type="button"
                        class="shrink-0 text-xs text-rose-600 hover:text-rose-700"
                        @click="removeOperator(op)"
                    >{{ t('ui.settings.remove') }}</button>
                </li>
            </ul>

            <form v-if="addingOperator" class="mt-4 space-y-3 border-t border-slate-100 pt-4" @submit.prevent="addOperator">
                <div>
                    <label for="op_name">{{ t('ui.settings.name') }}</label>
                    <input id="op_name" v-model="operatorForm.name" type="text" required />
                    <InputError :message="operatorForm.errors.name" />
                </div>
                <div>
                    <label for="op_email">{{ t('ui.auth.email') }}</label>
                    <input id="op_email" v-model="operatorForm.email" type="email" required />
                    <InputError :message="operatorForm.errors.email" />
                </div>
                <p class="text-xs text-slate-500">{{ t('ui.settings.invite_hint') }}</p>
                <button type="submit" class="btn-primary btn-sm w-full" :disabled="operatorForm.processing">{{ t('ui.settings.create_operator') }}</button>
            </form>
        </CardSection>
    </div>
</template>
