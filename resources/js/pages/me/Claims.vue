<script setup lang="ts">
import RDatePicker from '@/components/roster/RDatePicker.vue';
import RSelect from '@/components/roster/RSelect.vue';
import { isoDate, plainMoney } from '@/composables/useRoster';
import { useT } from '@/i18n';
import EmployeeAppLayout from '@/layouts/EmployeeAppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Camera, ChevronLeft, Paperclip, Plus, Receipt } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Cat {
    id: number;
    name: string;
    limit: number | null;
    receipt: boolean;
}
interface Row {
    id: number;
    category: string;
    spent_on: string;
    amount: number;
    description: string;
    status: 'pending' | 'approved' | 'rejected' | 'cancelled' | 'paid';
    receipt: string | null;
}
const props = defineProps<{ categories: Cat[]; claims: Row[]; currency: string }>();
const { t, d: fd } = useT();
const dec = props.currency === 'IDR' ? 0 : 2;
const money = (v: number) => `${props.currency === 'IDR' ? 'Rp' : props.currency} ${plainMoney(v, dec)}`;
const open = ref(false);
const form = useForm({ claim_category_id: (props.categories[0]?.id ?? null) as number | null, spent_on: isoDate() as string | null, amount: '', description: '', receipt: null as File | null });
const cat = computed(() => props.categories.find((c) => c.id === form.claim_category_id));
const catOptions = computed(() => props.categories.map((c) => ({ value: c.id, label: c.name, hint: c.limit ? t('claims.limit', { amount: money(c.limit) }) : t('claims.noLimit') })));
const file = ref<HTMLInputElement | null>(null);
function send() {
    form.post(route('me.claims.store'), { forceFormData: true, preserveScroll: true, onSuccess: () => ((open.value = false), form.reset()) });
}
const tone = { pending: 'warning', approved: 'info', rejected: 'danger', cancelled: '', paid: 'success' } as const;
const confirming = ref<number | null>(null);
function withdraw(id: number) {
    if (confirming.value !== id) return (confirming.value = id);
    router.delete(route('me.claims.cancel', { claim: id }), { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('claims.title')" />
    <EmployeeAppLayout current="pay">
        <div class="slipbar">
            <a :href="route('me.pay')" class="ibtn" :aria-label="t('pay.back')"><ChevronLeft class="icon" /></a>
            <h1>{{ t('claims.title') }}</h1>
            <button v-if="!open" type="button" class="btn primary sm" @click="open = true"><Plus class="icon sm" />{{ t('claims.new') }}</button>
        </div>
        <p class="small muted">{{ t('claims.sub') }}</p>

        <form v-if="open" class="sx reqform" @submit.prevent="send">
            <h2>{{ t('claims.new') }}</h2>
            <div class="field">
                <label>{{ t('claims.category') }}</label>
                <RSelect :model-value="form.claim_category_id" :options="catOptions" :label="t('claims.category')" @update:model-value="(v) => (form.claim_category_id = v === null ? null : Number(v))" />
            </div>
            <div class="two">
                <div class="field"><label>{{ t('claims.date') }}</label><RDatePicker v-model="form.spent_on" :label="t('claims.date')" /></div>
                <div class="field"><label for="amt">{{ t('claims.amount') }}</label><input id="amt" v-model="form.amount" class="input num" inputmode="decimal" autocomplete="off" /></div>
            </div>
            <span v-if="form.errors.spent_on" class="err">{{ form.errors.spent_on }}</span>
            <span v-if="form.errors.amount" class="err">{{ form.errors.amount }}</span>
            <div class="field"><label for="what">{{ t('claims.what') }}</label><input id="what" v-model="form.description" class="input" maxlength="300" :placeholder="t('claims.whatPlaceholder')" /></div>
            <span v-if="form.errors.description" class="err">{{ form.errors.description }}</span>
            <div class="field">
                <label>{{ cat?.receipt ? t('claims.receipt') : t('claims.receiptOptional') }}</label>
                <input ref="file" type="file" accept="image/*,application/pdf" capture="environment" class="sr" @change="(e) => (form.receipt = (e.target as HTMLInputElement).files?.[0] ?? null)" />
                <button type="button" class="drop" @click="file?.click()">
                    <component :is="form.receipt ? Paperclip : Camera" class="icon" /><b>{{ form.receipt ? form.receipt.name : t('claims.addReceipt') }}</b>
                </button>
            </div>
            <span v-if="form.errors.receipt" class="err">{{ form.errors.receipt }}</span>
            <div class="formacts">
                <button type="button" class="btn ghost" @click="open = false">{{ t('claims.cancel') }}</button>
                <button type="submit" class="btn primary" :disabled="form.processing || !form.amount || !form.description">{{ t('claims.send') }}</button>
            </div>
        </form>

        <section class="wk">
            <h2>{{ t('claims.history') }}</h2>
            <div v-if="!claims.length" class="dy"><span class="muted">{{ t('claims.none') }}</span></div>
            <div v-for="c in claims" :key="c.id" class="lr clr">
                <i class="cl-ic"><Receipt class="icon xs" /></i>
                <span class="t">
                    <b>{{ c.category }} · {{ money(c.amount) }}</b>
                    <span>{{ fd(c.spent_on) }} · {{ c.description }}</span>
                </span>
                <span class="badge" :class="tone[c.status]">{{ t(`claims.status.${c.status}`) }}</span>
                <a v-if="c.receipt" :href="c.receipt" class="linkbtn" target="_blank" rel="noopener">{{ t('claims.viewReceipt') }}</a>
                <button v-if="c.status === 'pending'" type="button" class="linkbtn" @click="withdraw(c.id)">{{ confirming === c.id ? t('timeOff.confirmCancel') : t('claims.withdraw') }}</button>
            </div>
        </section>
        <p class="synthetic">{{ t('common.demo') }}</p>
    </EmployeeAppLayout>
</template>
