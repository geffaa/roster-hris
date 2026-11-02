<script setup lang="ts">
import { formatDate, monthLabel, plainMoney } from '@/composables/useRoster';
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
    const fmt = (x: string) => formatDate(x).replace(/ \d{4}$/, '');
    return `${d.length} ${d.length === 1 ? 'day' : 'days'} of unpaid leave (${d.length > 1 ? `${fmt(d[0])} – ${fmt(d[d.length - 1])}` : fmt(d[0])}) reduced your base pay this month.`;
});
</script>

<template>
    <Head :title="`Payslip ${monthLabel(slip.period)}`" />
    <EmployeeAppLayout current="pay">
        <div class="slipbar">
            <Link :href="route('me.pay')" class="ibtn" aria-label="Back to pay"><ChevronLeft class="icon" /></Link>
            <h1>Payslip</h1>
            <a :href="route('me.payslip.pdf', { payslip: slip.id })" class="ibtn" aria-label="Download PDF" target="_blank" rel="noopener"><Download class="icon" /></a>
        </div>

        <div class="payhero static">
            <span class="ph-top">{{ monthLabel(slip.period) }} · paid {{ formatDate(slip.pay_date) }}</span>
            <b>{{ sym }} {{ m(slip.net) }}</b>
            <div class="ph-stats">
                <div><span>Gross</span><b>{{ m(slip.gross) }}</b></div>
                <div><span>Tax</span><b>{{ slip.tax < 0 ? '−' : '' }}{{ m(Math.abs(slip.tax)) }}</b></div>
                <div><span>{{ isId ? 'BPJS' : 'Contributions' }}</span><b>{{ m(slip.employee_contributions) }}</b></div>
            </div>
        </div>

        <div v-if="unpaidNote" class="note info small"><Info class="icon sm" /><span>{{ unpaidNote }}</span></div>
        <div v-if="slip.tax < 0" class="note success small"><Info class="icon sm" /><span>Tax withheld earlier this year was more than the yearly tax, so {{ sym }} {{ m(Math.abs(slip.tax)) }} comes back to you.</span></div>

        <section class="sx">
            <h2>Earnings</h2>
            <dl class="lines">
                <div v-for="l in lines(['earning'])" :key="l.code"><dt>{{ l.label }}<small v-if="l.basis && l.code !== 'base'">{{ l.basis }}</small></dt><dd>{{ l.amount < 0 ? '−' : '' }}{{ minor(l.amount) }}</dd></div>
                <div class="total"><dt>Gross</dt><dd>{{ m(slip.gross) }}</dd></div>
            </dl>
            <h2>Deductions</h2>
            <dl class="lines">
                <div v-for="l in lines(['tax', 'employee_contribution', 'deduction'])" :key="l.code"><dt>{{ l.label }}<small v-if="l.basis">{{ l.basis }}</small></dt><dd>{{ l.amount > 0 ? '+' : '−' }}{{ minor(l.amount) }}</dd></div>
                <div class="total"><dt>Net pay</dt><dd>{{ m(slip.net) }}</dd></div>
            </dl>
            <button type="button" class="linkbtn small" :aria-expanded="showEmployer" @click="showEmployer = !showEmployer">
                Your employer also pays {{ m(slip.employer_contributions) }} on top
            </button>
            <dl v-if="showEmployer" class="lines compact">
                <div v-for="l in lines(['employer_contribution'])" :key="l.code"><dt>{{ l.label }}<small>{{ l.basis }}</small></dt><dd>{{ minor(l.amount) }}</dd></div>
            </dl>
        </section>

        <section class="sx paidto">
            <Landmark class="icon sm" />
            <span v-if="slip.bank">Paid into {{ slip.bank.name }} ···· {{ slip.bank.last4 }}</span>
            <span v-else>No bank account on file. Ask HR to add yours.</span>
        </section>
        <p class="synthetic">Calculated with rule pack {{ slip.version }}. Synthetic demo data.</p>
    </EmployeeAppLayout>
</template>
