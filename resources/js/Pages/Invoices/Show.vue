<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import CardSection from '../../Components/CardSection.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import DropdownMenu from '../../Components/DropdownMenu.vue';
import InputError from '../../Components/InputError.vue';
import Icon from '../../Components/Icon.vue';
import { invoiceMenu, money, shortDate } from '../../Composables/useInvoiceActions';

const props = defineProps({
    invoice: { type: Object, required: true },
    credit_notes: { type: Array, default: () => [] },
    preview_url: { type: String, default: null },
    pdf_available: { type: Boolean, default: true },
});

const base = computed(() => `/invoices/${props.invoice.id}`);
const editable = computed(() => props.invoice.can?.update === true);

/* ---------- dialógy ---------- */
const dialog = ref(null);
const close = () => (dialog.value = null);

const sendForm = useForm({ email: '', message: '' });
const payForm = useForm({ amount: '', paid_at: '', note: '' });
const creditForm = useForm({ items: [], reason: '' });

const openSend = () => {
    sendForm.reset();
    dialog.value = 'send';
};

const openPay = () => {
    payForm.reset();
    payForm.amount = (props.invoice.outstanding_cents / 100).toFixed(2);
    payForm.paid_at = new Date().toISOString().slice(0, 10);
    dialog.value = 'pay';
};

const openCredit = () => {
    creditForm.reset();
    creditForm.items = props.invoice.items.map((i) => i.id);
    dialog.value = 'credit';
};

const submitSend = () => sendForm.post(`${base.value}/send`, { preserveScroll: true, onSuccess: close });
const submitPay = () => payForm.post(`${base.value}/pay`, { preserveScroll: true, onSuccess: close });
const submitCredit = () => creditForm.post(`${base.value}/credit`, { onSuccess: close });

/* ---------- menu ---------- */
const menu = computed(() =>
    invoiceMenu(props.invoice, {
        on: { send: openSend, pay: openPay, credit: openCredit },
    }).filter((item) => item.separator || item.can !== 'view'),
);

/* ---------- hlavička dokladu ---------- */
const headerForm = useForm({
    issued_at: props.invoice.issued_at ?? '',
    delivered_at: props.invoice.delivered_at ?? '',
    due_at: props.invoice.due_at ?? '',
    variable_symbol: props.invoice.variable_symbol ?? '',
    constant_symbol: props.invoice.constant_symbol ?? '',
    specific_symbol: props.invoice.specific_symbol ?? '',
    note: props.invoice.note ?? '',
    internal_note: props.invoice.internal_note ?? '',
});

const saveHeader = () => headerForm.patch(base.value, { preserveScroll: true });

/* ---------- položky ---------- */
const itemOpen = ref(false);
const editingId = ref(null);

const emptyItem = () => ({
    description: '',
    detail: '',
    quantity: 1,
    unit: 'mesiac',
    unit_price: '',
    discount_percent: 0,
    vat_rate: props.invoice.vat_rate ?? 23,
    period_start: '',
    period_end: '',
});

const itemForm = useForm(emptyItem());

const startAdd = () => {
    editingId.value = null;
    itemForm.defaults(emptyItem());
    itemForm.reset();
    itemOpen.value = true;
};

const startEdit = (item) => {
    editingId.value = item.id;
    itemForm.defaults({
        description: item.description,
        detail: item.detail ?? '',
        quantity: item.quantity,
        unit: item.unit,
        unit_price: item.unit_price,
        discount_percent: item.discount_percent,
        vat_rate: item.vat_rate,
        period_start: item.period_start ?? '',
        period_end: item.period_end ?? '',
    });
    itemForm.reset();
    itemOpen.value = true;
};

const saveItem = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            itemOpen.value = false;
            editingId.value = null;
        },
    };

    editingId.value
        ? itemForm.patch(`${base.value}/items/${editingId.value}`, options)
        : itemForm.post(`${base.value}/items`, options);
};

const removeItem = (item) => {
    if (!confirm(`Odstrániť položku „${item.description}“?`)) return;
    router.delete(`${base.value}/items/${item.id}`, { preserveScroll: true });
};

const itemMenu = (item) => [
    { label: 'Upraviť položku', icon: 'pencil', can: 'update', onSelect: () => startEdit(item) },
    {
        label: 'Duplikovať položku',
        icon: 'copy',
        can: 'update',
        onSelect: () =>
            router.post(
                `${base.value}/items`,
                {
                    description: item.description,
                    detail: item.detail,
                    quantity: item.quantity,
                    unit: item.unit,
                    unit_price: item.unit_price,
                    discount_percent: item.discount_percent,
                    vat_rate: item.vat_rate,
                },
                { preserveScroll: true },
            ),
    },
    { separator: true },
    { label: 'Odstrániť položku', icon: 'trash', can: 'update', danger: true, onSelect: () => removeItem(item) },
];

const num = (value, decimals = 2) =>
    new Intl.NumberFormat('sk-SK', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }).format(value ?? 0);

const trimmed = (value) => num(value, 3).replace(/[,.]?0+$/, '');
</script>

<template>
    <Head :title="`${invoice.type_label} ${invoice.number ?? '(koncept)'}`" />

    <PageHeader
        :title="`${invoice.type_label} ${invoice.number ?? '(koncept)'}`"
        :subtitle="invoice.organization.name"
    >
        <template #action>
            <a v-if="preview_url" :href="preview_url" target="_blank" class="btn-secondary">
                <Icon name="eye" :size="16" />
                Náhľad
            </a>
            <a v-if="pdf_available" :href="`${base}/pdf`" target="_blank" class="btn-secondary">
                <Icon name="download" :size="16" />
                PDF
            </a>
            <button v-if="invoice.can.issue" type="button" class="btn-primary" @click="router.post(`${base}/issue`)">
                <Icon name="check" :size="16" />
                Vystaviť
            </button>
            <button v-else-if="invoice.can.send" type="button" class="btn-primary" @click="openSend">
                <Icon name="send" :size="16" />
                Poslať e-mailom
            </button>
            <DropdownMenu :abilities="invoice.can" :items="menu" show-disabled />
        </template>
    </PageHeader>

    <!-- Stavový pás -->
    <div class="card mb-6 flex flex-wrap items-center gap-x-8 gap-y-4 px-5 py-4">
        <div>
            <p class="text-xs text-slate-500">Stav</p>
            <StatusBadge
                class="mt-1"
                :status="invoice.status"
                :tone="invoice.is_overdue && invoice.status !== 'overdue' ? 'rose' : invoice.status_tone"
                :label="invoice.status_label"
            />
        </div>
        <div>
            <p class="text-xs text-slate-500">Celkom</p>
            <p class="mt-0.5 text-lg font-semibold tracking-tight text-slate-900">{{ invoice.total }}</p>
        </div>
        <div v-if="invoice.outstanding_cents > 0 && !invoice.can.issue">
            <p class="text-xs text-slate-500">Zostáva uhradiť</p>
            <p class="mt-0.5 text-lg font-semibold tracking-tight" :class="invoice.is_overdue ? 'text-rose-600' : 'text-brand-700'">
                {{ invoice.outstanding }}
            </p>
        </div>
        <div v-if="invoice.due_at">
            <p class="text-xs text-slate-500">Splatnosť</p>
            <p class="mt-0.5 font-medium" :class="invoice.is_overdue ? 'text-rose-600' : 'text-slate-900'">
                {{ shortDate(invoice.due_at) }}
                <span v-if="invoice.is_overdue" class="text-xs font-normal">· {{ invoice.days_overdue }} dní po</span>
            </p>
        </div>
        <div v-if="invoice.sent_at">
            <p class="text-xs text-slate-500">Odoslané</p>
            <p class="mt-0.5 text-sm font-medium text-slate-900">
                {{ invoice.sent_to }}
                <span class="text-slate-400">· {{ invoice.sent_count }}×</span>
            </p>
        </div>
        <div v-if="invoice.parent" class="ml-auto">
            <p class="text-xs text-slate-500">Väzba</p>
            <Link :href="`/invoices/${invoice.parent.id}`" class="link text-sm">
                {{ invoice.parent.type_label }} {{ invoice.parent.number }}
            </Link>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <!-- ============ POLOŽKY ============ -->
        <div class="space-y-6 lg:col-span-2">
            <CardSection
                icon="invoice"
                title="Položky dokladu"
                :description="editable ? 'Koncept sa dá ľubovoľne meniť.' : 'Vystavený doklad je zamknutý – opravuje sa dobropisom.'"
            >
                <template v-if="editable" #action>
                    <button type="button" class="btn-secondary btn-sm" @click="startAdd">
                        <Icon name="plus" :size="15" />
                        Pridať položku
                    </button>
                </template>

                <div class="-mx-5 -mt-5 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50/70 text-left text-xs uppercase tracking-wide text-slate-500">
                                <th class="px-5 py-2.5 font-semibold">Popis</th>
                                <th class="px-3 py-2.5 text-right font-semibold">Množstvo</th>
                                <th class="px-3 py-2.5 text-right font-semibold">Cena / MJ</th>
                                <th class="px-3 py-2.5 text-right font-semibold">DPH</th>
                                <th class="px-3 py-2.5 text-right font-semibold">Spolu</th>
                                <th class="w-10 px-2 py-2.5"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <tr v-for="item in invoice.items" :key="item.id">
                                <td class="px-5 py-3">
                                    <p class="font-medium text-slate-900">{{ item.description }}</p>
                                    <p v-if="item.detail" class="text-xs text-slate-500">{{ item.detail }}</p>
                                    <p v-if="item.period" class="text-xs text-slate-400">Obdobie: {{ item.period }}</p>
                                    <p v-if="item.discount_percent > 0" class="text-xs text-emerald-600">
                                        Zľava {{ trimmed(item.discount_percent) }} %
                                    </p>
                                </td>
                                <td class="px-3 py-3 text-right whitespace-nowrap text-slate-600">
                                    {{ trimmed(item.quantity) }} {{ item.unit }}
                                </td>
                                <td class="px-3 py-3 text-right whitespace-nowrap text-slate-600">{{ num(item.unit_price) }}</td>
                                <td class="px-3 py-3 text-right whitespace-nowrap text-slate-500">{{ trimmed(item.vat_rate) }} %</td>
                                <td class="px-3 py-3 text-right whitespace-nowrap font-semibold text-slate-900">
                                    {{ num(item.subtotal_cents / 100) }}
                                </td>
                                <td class="px-2 py-3 text-right">
                                    <DropdownMenu v-if="editable" :abilities="invoice.can" :items="itemMenu(item)" size="sm" />
                                </td>
                            </tr>

                            <tr v-if="!invoice.items.length">
                                <td colspan="6" class="px-5 py-10 text-center text-sm text-slate-400">
                                    Doklad zatiaľ nemá položky.
                                    <button v-if="editable" type="button" class="link ml-1" @click="startAdd">Pridať prvú</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <template #footer>
                    <div class="flex justify-end">
                        <table class="text-sm">
                            <tbody>
                                <tr>
                                    <td class="py-1 pr-8 text-slate-500">Základ dane</td>
                                    <td class="py-1 text-right font-medium whitespace-nowrap text-slate-900">
                                        {{ money(invoice.subtotal_cents, invoice.currency) }}
                                    </td>
                                </tr>
                                <tr v-for="row in invoice.vat_summary.filter((r) => r.rate > 0)" :key="row.rate">
                                    <td class="py-1 pr-8 text-slate-500">DPH {{ trimmed(row.rate) }} %</td>
                                    <td class="py-1 text-right font-medium whitespace-nowrap text-slate-900">
                                        {{ money(row.vat_cents, invoice.currency) }}
                                    </td>
                                </tr>
                                <tr class="border-t-2 border-brand-600">
                                    <td class="pt-2 pr-8 font-semibold text-brand-700">Celkom</td>
                                    <td class="pt-2 text-right text-lg font-bold whitespace-nowrap text-brand-700">
                                        {{ invoice.total }}
                                    </td>
                                </tr>
                                <tr v-if="invoice.paid_cents > 0">
                                    <td class="pt-1 pr-8 text-xs text-slate-500">Už uhradené</td>
                                    <td class="pt-1 text-right text-xs whitespace-nowrap text-emerald-600">
                                        −{{ money(invoice.paid_cents, invoice.currency) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </template>
            </CardSection>

            <!-- Formulár položky -->
            <CardSection v-if="itemOpen" icon="pencil" :title="editingId ? 'Upraviť položku' : 'Nová položka'" tone="slate">
                <div class="grid gap-4 sm:grid-cols-6">
                    <div class="sm:col-span-6">
                        <label>Popis</label>
                        <input v-model="itemForm.description" type="text" placeholder="Predplatné Projekt 1 – Standard" />
                        <InputError :message="itemForm.errors.description" />
                    </div>
                    <div class="sm:col-span-6">
                        <label>Doplňujúci text <span class="font-normal text-slate-400">(nepovinné)</span></label>
                        <input v-model="itemForm.detail" type="text" placeholder="Mesačné predplatné" />
                    </div>
                    <div class="sm:col-span-2">
                        <label>Množstvo</label>
                        <input v-model="itemForm.quantity" type="number" step="0.001" />
                        <InputError :message="itemForm.errors.quantity" />
                    </div>
                    <div class="sm:col-span-2">
                        <label>Merná jednotka</label>
                        <input v-model="itemForm.unit" type="text" placeholder="mesiac" />
                    </div>
                    <div class="sm:col-span-2">
                        <label>Cena za MJ bez DPH</label>
                        <input v-model="itemForm.unit_price" type="number" step="0.0001" placeholder="29.00" />
                        <InputError :message="itemForm.errors.unit_price" />
                    </div>
                    <div class="sm:col-span-2">
                        <label>Zľava (%)</label>
                        <input v-model="itemForm.discount_percent" type="number" step="0.01" min="0" max="100" />
                    </div>
                    <div class="sm:col-span-2">
                        <label>Sadzba DPH (%)</label>
                        <input v-model="itemForm.vat_rate" type="number" step="0.01" min="0" max="100" />
                        <InputError :message="itemForm.errors.vat_rate" />
                    </div>
                    <div class="sm:col-span-2"></div>
                    <div class="sm:col-span-3">
                        <label>Obdobie od <span class="font-normal text-slate-400">(nepovinné)</span></label>
                        <input v-model="itemForm.period_start" type="date" />
                    </div>
                    <div class="sm:col-span-3">
                        <label>Obdobie do</label>
                        <input v-model="itemForm.period_end" type="date" />
                        <InputError :message="itemForm.errors.period_end" />
                    </div>
                </div>

                <template #footer>
                    <div class="flex justify-end gap-2">
                        <button type="button" class="btn-secondary" @click="itemOpen = false">Zrušiť</button>
                        <button type="button" class="btn-primary" :disabled="itemForm.processing" @click="saveItem">
                            {{ editingId ? 'Uložiť zmeny' : 'Pridať položku' }}
                        </button>
                    </div>
                </template>
            </CardSection>

            <!-- História -->
            <CardSection icon="clock" title="História dokladu" description="Kto čo urobil a kedy.">
                <ol class="space-y-3">
                    <li v-for="event in invoice.events" :key="event.id" class="flex gap-3">
                        <span class="chip mt-0.5 h-7 w-7 shrink-0 bg-slate-100 text-slate-500">
                            <Icon :name="event.icon" :size="14" />
                        </span>
                        <div class="min-w-0 flex-1 border-b border-slate-50 pb-3 last:border-0">
                            <p class="text-sm font-medium text-slate-900">{{ event.label }}</p>
                            <p v-if="event.description" class="text-sm text-slate-500">{{ event.description }}</p>
                            <p class="mt-0.5 text-xs text-slate-400">
                                {{ new Date(event.at).toLocaleString('sk-SK') }}
                                <span v-if="event.user"> · {{ event.user }}</span>
                            </p>
                        </div>
                    </li>
                    <li v-if="!invoice.events.length" class="text-sm text-slate-400">Zatiaľ žiadne záznamy.</li>
                </ol>
            </CardSection>
        </div>

        <!-- ============ BOČNÝ PANEL ============ -->
        <div class="space-y-6">
            <CardSection icon="card" title="Doklad" :description="editable ? null : 'Zamknuté – doklad je vystavený.'">
                <div class="space-y-4">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label>Vystavené</label>
                            <input v-model="headerForm.issued_at" type="date" :disabled="!editable" />
                        </div>
                        <div>
                            <label>Dodané</label>
                            <input v-model="headerForm.delivered_at" type="date" :disabled="!editable" />
                        </div>
                        <div>
                            <label>Splatnosť</label>
                            <input v-model="headerForm.due_at" type="date" :disabled="!editable" />
                            <InputError :message="headerForm.errors.due_at" />
                        </div>
                        <div>
                            <label>Variabilný symbol</label>
                            <input v-model="headerForm.variable_symbol" type="text" :disabled="!editable" />
                            <InputError :message="headerForm.errors.variable_symbol" />
                        </div>
                        <div>
                            <label>Konštantný symbol</label>
                            <input v-model="headerForm.constant_symbol" type="text" :disabled="!editable" />
                        </div>
                        <div>
                            <label>Špecifický symbol</label>
                            <input v-model="headerForm.specific_symbol" type="text" :disabled="!editable" />
                        </div>
                    </div>

                    <div>
                        <label>Poznámka na faktúre</label>
                        <textarea v-model="headerForm.note" rows="2" :disabled="!editable"></textarea>
                    </div>

                    <div>
                        <label>Interná poznámka <span class="font-normal text-slate-400">(nevidí zákazník)</span></label>
                        <textarea v-model="headerForm.internal_note" rows="2" :disabled="!editable"></textarea>
                    </div>
                </div>

                <template v-if="editable" #footer>
                    <button type="button" class="btn-primary w-full" :disabled="headerForm.processing" @click="saveHeader">
                        Uložiť
                    </button>
                </template>
            </CardSection>

            <div
                v-if="invoice.vat_note"
                class="flex items-start gap-2.5 rounded-2xl bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-amber-600/15"
            >
                <Icon name="warning" :size="18" class="mt-0.5 shrink-0 text-amber-600" />
                <span>{{ invoice.vat_note }}</span>
            </div>

            <CardSection v-if="credit_notes.length" icon="invoice" title="Súvisiace doklady" tone="slate">
                <ul class="space-y-2">
                    <li v-for="doc in credit_notes" :key="doc.id" class="flex items-center justify-between text-sm">
                        <Link :href="`/invoices/${doc.id}`" class="link">{{ doc.type_label }} {{ doc.number }}</Link>
                        <span class="font-medium text-slate-700">{{ doc.total }}</span>
                    </li>
                </ul>
            </CardSection>

            <div v-if="!pdf_available" class="rounded-2xl bg-slate-100 px-4 py-3 text-sm text-slate-600">
                PDF nie je k dispozícii – chýba knižnica dompdf.
                <code class="mt-1 block text-xs">composer require barryvdh/laravel-dompdf</code>
            </div>
        </div>
    </div>

    <!-- ============ DIALÓGY ============ -->
    <teleport to="body">
        <div v-if="dialog" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" @click="close"></div>

            <div class="card relative w-full max-w-md p-6">
                <!-- Odoslanie -->
                <template v-if="dialog === 'send'">
                    <h2 class="text-lg font-semibold text-slate-900">Poslať doklad e-mailom</h2>
                    <p class="mt-1 mb-4 text-sm text-slate-500">
                        PDF sa priloží automaticky. Prázdna adresa = fakturačný e-mail firmy.
                    </p>
                    <div class="space-y-3">
                        <div>
                            <label>E-mail príjemcu</label>
                            <input v-model="sendForm.email" type="email" placeholder="faktury@firma.sk" />
                            <InputError :message="sendForm.errors.email" />
                        </div>
                        <div>
                            <label>Sprievodný text <span class="font-normal text-slate-400">(nepovinné)</span></label>
                            <textarea v-model="sendForm.message" rows="3" placeholder="Dobrý deň, v prílohe posielame…"></textarea>
                        </div>
                    </div>
                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" class="btn-secondary" @click="close">Zrušiť</button>
                        <button type="button" class="btn-primary" :disabled="sendForm.processing" @click="submitSend">
                            Odoslať
                        </button>
                    </div>
                </template>

                <!-- Úhrada -->
                <template v-else-if="dialog === 'pay'">
                    <h2 class="text-lg font-semibold text-slate-900">Zaznamenať úhradu</h2>
                    <p class="mt-1 mb-4 text-sm text-slate-500">
                        Zostáva uhradiť {{ invoice.outstanding }}. Menšia suma sa zapíše ako čiastočná platba.
                    </p>
                    <div class="space-y-3">
                        <div>
                            <label>Suma ({{ invoice.currency }})</label>
                            <input v-model="payForm.amount" type="number" step="0.01" />
                            <InputError :message="payForm.errors.amount" />
                        </div>
                        <div>
                            <label>Dátum úhrady</label>
                            <input v-model="payForm.paid_at" type="date" />
                        </div>
                        <div>
                            <label>Poznámka</label>
                            <input v-model="payForm.note" type="text" placeholder="Výpis č. 8/2026" />
                        </div>
                    </div>
                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" class="btn-secondary" @click="close">Zrušiť</button>
                        <button type="button" class="btn-primary" :disabled="payForm.processing" @click="submitPay">
                            Zaznamenať
                        </button>
                    </div>
                </template>

                <!-- Dobropis -->
                <template v-else-if="dialog === 'credit'">
                    <h2 class="text-lg font-semibold text-slate-900">Vystaviť dobropis</h2>
                    <p class="mt-1 mb-4 text-sm text-slate-500">
                        Vyber položky, ktoré sa majú dobropisovať. Dobropis vznikne rovno vystavený.
                    </p>
                    <div class="max-h-56 space-y-2 overflow-y-auto">
                        <label
                            v-for="item in invoice.items"
                            :key="item.id"
                            class="flex items-start gap-2.5 rounded-xl border border-slate-200 px-3 py-2 text-sm font-normal"
                        >
                            <input v-model="creditForm.items" type="checkbox" :value="item.id" class="mt-0.5" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-slate-900">{{ item.description }}</span>
                                <span class="text-xs text-slate-500">{{ money(item.subtotal_cents, invoice.currency) }}</span>
                            </span>
                        </label>
                    </div>
                    <div class="mt-3">
                        <label>Dôvod</label>
                        <input v-model="creditForm.reason" type="text" placeholder="Reklamácia, zrušená objednávka…" />
                    </div>
                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" class="btn-secondary" @click="close">Zrušiť</button>
                        <button
                            type="button"
                            class="btn-danger"
                            :disabled="creditForm.processing || !creditForm.items.length"
                            @click="submitCredit"
                        >
                            Vystaviť dobropis
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </teleport>
</template>
