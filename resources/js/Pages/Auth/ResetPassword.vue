<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AuthLayout from '../../Layouts/AuthLayout.vue';
import InputError from '../../Components/InputError.vue';
import PasswordInput from '../../Components/PasswordInput.vue';
import { t } from '../../Composables/useLang';

const props = defineProps({
    token: { type: String, required: true },
    email: { type: String, default: '' },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => form.post('/reset-password', {
    onFinish: () => form.reset('password', 'password_confirmation'),
});
</script>

<template>
    <Head :title="t('ui.auth.new_password')" />

    <AuthLayout :title="t('ui.auth.new_password')" :subtitle="t('ui.auth.reset_subtitle')">
        <form class="space-y-5" @submit.prevent="submit">
            <div>
                <label for="email">{{ t('ui.auth.email') }}</label>
                <input id="email" v-model="form.email" type="email" autocomplete="username" required />
                <InputError :message="form.errors.email" />
            </div>

            <div>
                <label for="password">{{ t('ui.auth.new_password') }}</label>
                <PasswordInput id="password" v-model="form.password" autocomplete="new-password" required autofocus />
                <InputError :message="form.errors.password" />
            </div>

            <div>
                <label for="password_confirmation">{{ t('ui.auth.new_password_again') }}</label>
                <PasswordInput id="password_confirmation" v-model="form.password_confirmation" autocomplete="new-password" required />
            </div>

            <button type="submit" class="btn-primary w-full" :disabled="form.processing">
                {{ form.processing ? t('common.form.saving') : t('ui.auth.change_password') }}
            </button>
        </form>
    </AuthLayout>
</template>
