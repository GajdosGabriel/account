<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import CardSection from '../../Components/CardSection.vue';
import InputError from '../../Components/InputError.vue';
import Icon from '../../Components/Icon.vue';
import Pagination from '../../Components/Pagination.vue';

const props = defineProps({
    products: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const creating = ref(false);
const form = useForm({ key: '', name: '', url: '', description: '' });

const search = ref(props.filters.search ?? '');
let timer = null;

watch(search, (value) => {
    clearTimeout(timer);
    timer = setTimeout(
        () => router.get('/products', value ? { search: value } : {}, { preserveState: true, replace: true }),
        350,
    );
});

const submit = () => form.post('/products', {
    onSuccess: () => { form.reset(); creating.value = false; },
});
</script>

<template>
    <Head title="Projekty" />

    <PageHeader title="Projekty" subtitle="Každý projekt má vlastný katalóg funkcií, cenník a limity.">
        <template #action>
            <button type="button" class="btn-primary" @click="creating = !creating">
                <Icon name="plus" :size="17" />
                Nový projekt
            </button>
        </template>
    </PageHeader>

    <CardSection v-if="creating" class="mb-6" icon="code" title="Nový projekt">
        <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="submit">
            <div>
                <label for="key">Kľúč</label>
                <input id="key" v-model="form.key" type="text" placeholder="projekt-1" required />
                <p class="mt-1.5 text-xs text-slate-500">Malé písmená, číslice a pomlčky. Neskôr sa nemení.</p>
                <InputError :message="form.errors.key" />
            </div>
            <div>
                <label for="name">Názov</label>
                <input id="name" v-model="form.name" type="text" placeholder="Projekt 1" required />
                <InputError :message="form.errors.name" />
            </div>
            <div class="sm:col-span-2">
                <label for="url">Adresa</label>
                <input id="url" v-model="form.url" type="url" placeholder="https://projekt1.sk" />
                <p class="mt-1.5 text-xs text-slate-500">Podľa nej sa kontrolujú návratové adresy a odkazy v e-mailoch.</p>
                <InputError :message="form.errors.url" />
            </div>
            <div class="sm:col-span-2 flex gap-2">
                <button type="submit" class="btn-primary" :disabled="form.processing">Vytvoriť</button>
                <button type="button" class="btn-secondary" @click="creating = false">Zrušiť</button>
            </div>
        </form>
    </CardSection>

    <div v-if="products.total > 6 || filters.search" class="mb-5">
        <input v-model="search" type="text" placeholder="Hľadať projekt podľa názvu alebo kľúča…" />
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <Link
            v-for="product in products.data"
            :key="product.key"
            :href="`/products/${product.key}`"
            class="card group p-5 transition hover:-translate-y-0.5 hover:shadow-md"
        >
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <h3 class="truncate font-semibold text-slate-900">{{ product.name }}</h3>
                    <p class="mt-0.5 font-mono text-xs text-slate-400">{{ product.key }}</p>
                </div>
                <span
                    class="shrink-0 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset"
                    :class="product.is_active
                        ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20'
                        : 'bg-slate-100 text-slate-500 ring-slate-500/20'"
                >
                    {{ product.is_active ? 'aktívny' : 'vypnutý' }}
                </span>
            </div>

            <dl class="mt-5 grid grid-cols-3 gap-2 text-center">
                <div class="rounded-xl bg-slate-50 py-2">
                    <dd class="text-lg font-semibold text-slate-900">{{ product.organizations_count }}</dd>
                    <dt class="text-xs text-slate-500">firiem</dt>
                </div>
                <div class="rounded-xl bg-slate-50 py-2">
                    <dd class="text-lg font-semibold text-slate-900">{{ product.plans_count }}</dd>
                    <dt class="text-xs text-slate-500">plánov</dt>
                </div>
                <div class="rounded-xl bg-slate-50 py-2">
                    <dd class="text-lg font-semibold text-slate-900">{{ product.features_count }}</dd>
                    <dt class="text-xs text-slate-500">funkcií</dt>
                </div>
            </dl>
        </Link>

        <div v-if="!products.data.length" class="card col-span-full p-12 text-center">
            <Icon name="card" :size="32" class="mx-auto mb-3 text-slate-300" />
            <p class="font-medium text-slate-600">Žiadne projekty</p>
            <p class="mt-1 text-sm text-slate-400">
                {{ filters.search ? 'Skús iný výraz.' : 'Vytvor prvý projekt.' }}
            </p>
        </div>
    </div>

    <div v-if="products.last_page > 1" class="card mt-5 overflow-hidden">
        <Pagination :meta="products" label="projektov" />
    </div>
</template>
