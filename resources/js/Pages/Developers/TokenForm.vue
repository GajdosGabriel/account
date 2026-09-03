<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import CardSection from '../../Components/CardSection.vue';
import InputError from '../../Components/InputError.vue';
import Icon from '../../Components/Icon.vue';
import RowActions from '../../Components/RowActions.vue';
import { t } from '../../Composables/useLang';
import { tokenMenu } from '../../Composables/useTokenActions';

const props = defineProps({
    token: { type: Object, required: true },
    available_abilities: { type: Array, default: () => [] },
});

const form = useForm({
    name: props.token.name,
    abilities: [...props.token.abilities],
});

const submit = () => form.patch(`/developers/tokens/${props.token.id}`);

// Priamo v upozornení – kým je token zrušený, je to jediná akcia,
// ktorú tu chceš spraviť, a hľadať ju v menu je zbytočný krok.
const unrevoke = () => {
    if (confirm(t('actions.token.confirm.unrevoke', { name: props.token.name }))) {
        router.post(`/developers/tokens/${props.token.id}/unrevoke`, {}, { preserveScroll: true });
    }
};

const toggleAll = () => {
    form.abilities = form.abilities.length === props.available_abilities.length
        ? []
        : props.available_abilities.map((ability) => ability.value);
};
</script>

<template>
    <Head :title="t('tokens.edit')" />

    <PageHeader :title="token.name" :subtitle="t('tokens.subtitle')">
        <template #action>
            <Link href="/developers" class="btn-secondary">{{ t('tokens.back') }}</Link>
            <RowActions
                :abilities="{ ...token.can, update: false }"
                :base="`/developers/tokens/${token.id}`"
                :name="token.name"
                :label="t('actions.menu')"
                :items="tokenMenu(token)"
            />
        </template>
    </PageHeader>

    <div
        v-if="token.revoked"
        class="mb-5 flex items-start gap-2.5 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-amber-600/15"
    >
        <Icon name="warning" :size="18" class="mt-0.5 text-amber-600" />
        <span class="min-w-0">{{ t('tokens.state.revoked') }}</span>
        <button
            v-if="token.can.unrevoke"
            type="button"
            class="btn-secondary btn-sm ml-auto shrink-0"
            @click="unrevoke"
        >
            <Icon name="refresh" :size="15" />
            {{ t('actions.token.unrevoke') }}
        </button>
    </div>

    <form class="space-y-6" @submit.prevent="submit">
        <CardSection icon="key" :title="t('tokens.edit')">
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="name">{{ t('tokens.name') }}</label>
                    <input id="name" v-model="form.name" type="text" required autofocus />
                    <p class="mt-1.5 text-xs text-slate-500">{{ t('tokens.name_hint') }}</p>
                    <InputError :message="form.errors.name" />
                </div>

                <dl class="grid grid-cols-2 gap-x-4 gap-y-3 self-start rounded-xl bg-slate-50 px-4 py-3 text-sm ring-1 ring-slate-200/70">
                    <div>
                        <dt class="text-xs text-slate-500">{{ t('tokens.prefix') }}</dt>
                        <dd class="mt-0.5 font-mono text-xs text-slate-900">{{ token.prefix }}…</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">{{ t('tokens.product') }}</dt>
                        <dd class="mt-0.5 font-medium text-slate-900">{{ token.product ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">{{ t('tokens.last_used') }}</dt>
                        <dd class="mt-0.5 text-slate-700">{{ token.last_used_at ?? t('tokens.never') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">{{ t('tokens.created') }}</dt>
                        <dd class="mt-0.5 text-slate-700">{{ token.created_at ?? '—' }}</dd>
                    </div>
                </dl>
            </div>
        </CardSection>

        <CardSection icon="shield" tone="emerald" :title="t('tokens.abilities')" :description="t('tokens.abilities_hint')">
            <template #action>
                <button type="button" class="btn-secondary btn-sm" @click="toggleAll">
                    {{ form.abilities.length === available_abilities.length ? t('tokens.deselect_all') : t('tokens.select_all') }}
                </button>
            </template>

            <div class="grid gap-3 sm:grid-cols-2">
                <label
                    v-for="ability in available_abilities"
                    :key="ability.value"
                    class="flex cursor-pointer items-start gap-3 rounded-xl border px-4 py-3 text-sm font-normal transition"
                    :class="form.abilities.includes(ability.value)
                        ? 'border-brand-500 bg-brand-50/60 ring-1 ring-brand-500/20'
                        : 'border-slate-200 hover:border-slate-300 hover:bg-slate-50'"
                >
                    <input
                        v-model="form.abilities"
                        type="checkbox"
                        :value="ability.value"
                        class="mt-0.5 rounded border-slate-300"
                    />
                    <span class="min-w-0">
                        <span class="block font-medium text-slate-900">{{ ability.label }}</span>
                        <span class="mt-0.5 block font-mono text-xs text-slate-400">{{ ability.value }}</span>
                        <span class="mt-1 block text-xs text-slate-500">{{ ability.description }}</span>
                    </span>
                </label>
            </div>

            <!-- `abilities.*` spadne len pri obídení formulára, ale mlčať by bolo horšie -->
            <InputError :message="form.errors.abilities ?? form.errors['abilities.0']" />

            <template #footer>
                <div class="flex items-center gap-3">
                    <button type="submit" class="btn-primary" :disabled="form.processing">
                        {{ form.processing ? t('tokens.saving') : t('tokens.save') }}
                    </button>
                    <Link href="/developers" class="text-sm text-slate-500 hover:text-slate-900">
                        {{ t('actions.cancel') }}
                    </Link>
                </div>
            </template>
        </CardSection>
    </form>
</template>
