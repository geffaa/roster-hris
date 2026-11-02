<script setup lang="ts">
import { plainMoney } from '@/composables/useRoster';
import { useT } from '@/i18n';
import EmployeeAppLayout from '@/layouts/EmployeeAppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ChevronLeft, Download, Info, Landmark } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Line {
    code: string;
    label: string;
    section: string;
    amount: number;
    basis: string | null;
}
const props = defineProps<{
    slip: {
        id: number;
        period: string;
        pay_date: string;
        name: string;
        tax_status: string;
        bank: { name: string; last4: string } | null;
        unpaid_dates: string[];
        currency: string;
        decimals: number;
        gross: number;
        tax: number;
        employee_contributions: number;
        employer_contributions: number;
        net: number;
        breakdown: Line[];
        tax_detail: Record<string, string | number>;
        version: string | null;
    };
}>();
const { t, d: fd, month, line } = useT();
const s = computed(() => props.slip);
const sym = computed(() => (s.value.currency === 'IDR' ? 'Rp' : s.value.currency === 'GBP' ? '£' : s.value.currency));
const m = (v: number) => plainMoney(v, s.value.decimals);
const minor = (v: number) => plainMoney(Math.abs(v) / 10 ** s.value.decimals, s.value.decimals);
const lines = (sections: string[]) => s.value.breakdown.filter((l) => sections.includes(l.section));
const isId = computed(() => s.value.currency === 'IDR');
const showEmployer = ref(false);
const unpaidNote = computed(() => {
    const d = s.value.unpaid_dates;
    if (!d.length) return null;
    const fmt = (x: string) => fd(x, { day: 'numeric', month: 'short' });
    const range = d.length > 1 ? `${fmt(d[0])} – ${fmt(d[d.length - 1])}` : fmt(d[0]);
    return d.length === 1 ? t('pay.unpaidOne', { range }) : t('pay.unpaid', { n: d.length, range });
});
</script>

<template>
    <Head :title="`${t('pay.payslip')} ${month(slip.period)}`" />
    <EmployeeAppLayout current="pay">
        <div class="slipbar">
            <Link :href="route('me.pay')" class="ibtn" :aria-label="t('pay.back')"><ChevronLeft class="icon" /></Link>
            <h1>{{ t('pay.payslip') }}</h1>
            <a :href="route('me.payslip.pdf', { payslip: slip.id })" class="ibtn" :aria-label="t('pay.pdf')" target="_blank" rel="noopener"><Download class="icon" /></a>
        </div>

        <div class="payhero static">
            <span class="ph-top">{{ t('pay.paid', { month: month(slip.period), date: fd(slip.pay_date) }) }}</span>
            <b>{{ sym }} {{ m(slip.net) }}</b>
            <div class="ph-stats">
                <div><span>{{ t('pay.gross') }}</span><b>{{ m(slip.gross) }}</b></div>
                <div><span>{{ t('pay.tax') }}</span><b>{{ slip.tax < 0 ? '−' : '' }}{{ m(Math.abs(slip.tax)) }}</b></div>
                <div><span>{{ isId ? t('pay.contrib') : t('pay.contribOther') }}</span><b>{{ m(slip.employee_contributions) }}</b></div>
            </div>
        </div>

        <div v-if="unpaidNote" class="note info small"><Info class="icon sm" /><span>{{ unpaidNote }}</span></div>
        <div v-if="slip.tax < 0" class="note success small"><Info class="icon sm" /><span>{{ t('pay.refund', { amount: `${sym} ${m(Math.abs(slip.tax))}` }) }}</span></div>

        <section class="sx">
            <h2>{{ t('pay.earnings') }}</h2>
            <dl class="lines">
                <div v-for="l in lines(['earning'])" :key="l.code"><dt>{{ line(l).label }}<small v-if="l.basis && l.code !== 'base'">{{ line(l).basis }}</small></dt><dd>{{ l.amount < 0 ? '−' : '' }}{{ minor(l.amount) }}</dd></div>
                <div class="total"><dt>{{ t('pay.gross') }}</dt><dd>{{ m(slip.gross) }}</dd></div>
            </dl>
            <h2>{{ t('pay.deductions') }}</h2>
            <dl class="lines">
                <div v-for="l in lines(['tax', 'employee_contribution', 'deduction'])" :key="l.code"><dt>{{ line(l).label }}<small v-if="l.basis">{{ line(l).basis }}</small></dt><dd>{{ l.amount > 0 ? '+' : '−' }}{{ minor(l.amount) }}</dd></div>
                <div class="total"><dt>{{ t('pay.net') }}</dt><dd>{{ m(slip.net) }}</dd></div>
            </dl>
            <button type="button" class="linkbtn small" :aria-expanded="showEmployer" @click="showEmployer = !showEmployer">
                {{ t('pay.employer', { amount: m(slip.employer_contributions) }) }}
            </button>
            <dl v-if="showEmployer" class="lines compact">
                <div v-for="l in lines(['employer_contribution'])" :key="l.code"><dt>{{ line(l).label }}<small>{{ line(l).basis }}</small></dt><dd>{{ minor(l.amount) }}</dd></div>
            </dl>
        </section>

        <section class="sx paidto">
            <Landmark class="icon sm" />
            <span v-if="slip.bank">{{ t('pay.paidInto', { bank: slip.bank.name, last4: slip.bank.last4 }) }}</span>
            <span v-else>{{ t('pay.noBank') }}</span>
        </section>
        <p class="synthetic">{{ t('pay.version', { v: slip.version ?? '' }) }}</p>
    </EmployeeAppLayout>
</template>
