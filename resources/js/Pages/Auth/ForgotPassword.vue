<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '../../Layouts/AuthLayout.vue';
import InputError from '../../Components/InputError.vue';
import { t } from '../../Composables/useLang';

const form = useForm({ email: '' });

const submit = () => form.post('/forgot-password');
</script>

<template>
    <Head :title="t('ui.auth.forgot_title')" />

    <AuthLayout :title="t('ui.auth.forgot_heading')" :subtitle="t('ui.auth.forgot_subtitle')">
        <form class="space-y-5" @submit.prevent="submit">
            <div>
                <label for="email">{{ t('ui.auth.email') }}</label>
                <input id="email" v-model="form.email" type="email" autocomplete="username" :placeholder="t('ui.auth.email_placeholder')" required autofocus />
                <InputError :message="form.errors.email" />
            </div>

            <button type="submit" class="btn-primary w-full" :disabled="form.processing">
                {{ form.processing ? t('ui.auth.sending') : t('ui.auth.send_link') }}
            </button>
        </form>

        <p class="mt-8 text-center text-sm text-slate-500">
            <Link href="/login" class="link">{{ t('ui.auth.back_to_login') }}</Link>
        </p>
    </AuthLayout>
</template>
