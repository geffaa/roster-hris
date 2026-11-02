<script setup lang="ts">
import { formatDate, monthLabel, plainMoney } from '@/composables/useRoster';
import EmployeeAppLayout from '@/layouts/EmployeeAppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ChevronRight, FileText, Wallet } from 'lucide-vue-next';

interface Slip {
    id: number;
    period: string;
    pay_date: string;
    net: number;
    gross: number;
    currency: string;
    new: boolean;
}
defineProps<{ slips: Slip[] }>();
const sym = (c: string) => (c === 'IDR' ? 'Rp' : c === 'GBP' ? '£' : c);
const dec = (c: string) => (c === 'IDR' ? 0 : 2);
</script>

<template>
    <Head title="Pay" />
    <EmployeeAppLayout current="pay">
        <div class="hi"><h1>Pay</h1><p>Your payslips</p></div>

        <template v-if="slips.length">
            <Link :href="route('me.payslip', { payslip: slips[0].id })" class="payhero">
                <span class="ph-top">{{ monthLabel(slips[0].period) }} · paid {{ formatDate(slips[0].pay_date) }}<span v-if="slips[0].new" class="newdot">New</span></span>
                <b>{{ sym(slips[0].currency) }} {{ plainMoney(slips[0].net, dec(slips[0].currency)) }}</b>
                <span class="ph-sub">Gross {{ plainMoney(slips[0].gross, dec(slips[0].currency)) }}<ChevronRight class="icon sm" /></span>
            </Link>

            <section class="sx">
                <h2>Earlier payslips</h2>
                <Link v-for="s in slips.slice(1)" :key="s.id" :href="route('me.payslip', { payslip: s.id })" class="sliprow">
                    <span class="sr-ic"><FileText class="icon sm" /></span>
                    <span class="sr-t"><b>{{ monthLabel(s.period) }}</b><small>Paid {{ formatDate(s.pay_date) }}</small></span>
                    <span class="num">{{ plainMoney(s.net, dec(s.currency)) }}</span>
                    <ChevronRight class="icon sm muted" />
                </Link>
                <p v-if="slips.length === 1" class="small muted">This is your first payslip in Roster.</p>
            </section>
        </template>
        <div v-else class="sx empty">
            <Wallet class="icon" />
            <p>No payslips yet. They appear here once payroll for your first month is approved and sent.</p>
        </div>
        <p class="synthetic">Synthetic demo data.</p>
    </EmployeeAppLayout>
</template>
