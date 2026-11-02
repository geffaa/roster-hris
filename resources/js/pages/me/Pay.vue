<script setup lang="ts">
import { plainMoney } from '@/composables/useRoster';
import { useT } from '@/i18n';
import EmployeeAppLayout from '@/layouts/EmployeeAppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ChevronRight, FileText, Receipt, Wallet } from 'lucide-vue-next';

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
const { t, d: fd, month } = useT();
const sym = (c: string) => (c === 'IDR' ? 'Rp' : c === 'GBP' ? '£' : c);
const dec = (c: string) => (c === 'IDR' ? 0 : 2);
</script>

<template>
    <Head :title="t('pay.title')" />
    <EmployeeAppLayout current="pay">
        <div class="hi"><h1>{{ t('pay.title') }}</h1><p>{{ t('pay.sub') }}</p></div>

        <Link :href="route('me.claims')" class="sliprow claimlink">
            <span class="sr-ic"><Receipt class="icon sm" /></span>
            <span class="sr-t"><b>{{ t('claims.open') }}</b><small>{{ t('claims.openSub') }}</small></span>
            <span />
            <ChevronRight class="icon sm muted" />
        </Link>
        <template v-if="slips.length">
            <Link :href="route('me.payslip', { payslip: slips[0].id })" class="payhero">
                <span class="ph-top">{{ t('pay.paid', { month: month(slips[0].period), date: fd(slips[0].pay_date) }) }}<span v-if="slips[0].new" class="newdot">{{ t('pay.new') }}</span></span>
                <b>{{ sym(slips[0].currency) }} {{ plainMoney(slips[0].net, dec(slips[0].currency)) }}</b>
                <span class="ph-sub">{{ t('pay.gross') }} {{ plainMoney(slips[0].gross, dec(slips[0].currency)) }}<ChevronRight class="icon sm" /></span>
            </Link>

            <section class="sx">
                <h2>{{ t('pay.earlier') }}</h2>
                <Link v-for="s in slips.slice(1)" :key="s.id" :href="route('me.payslip', { payslip: s.id })" class="sliprow">
                    <span class="sr-ic"><FileText class="icon sm" /></span>
                    <span class="sr-t"><b>{{ month(s.period) }}</b><small>{{ t('pay.paidOn', { date: fd(s.pay_date) }) }}</small></span>
                    <span class="num">{{ plainMoney(s.net, dec(s.currency)) }}</span>
                    <ChevronRight class="icon sm muted" />
                </Link>
                <p v-if="slips.length === 1" class="small muted">{{ t('pay.first') }}</p>
            </section>
        </template>
        <div v-else class="sx empty">
            <Wallet class="icon" />
            <p>{{ t('pay.none') }}</p>
        </div>
        <p class="synthetic">{{ t('common.demo') }}</p>
    </EmployeeAppLayout>
</template>
