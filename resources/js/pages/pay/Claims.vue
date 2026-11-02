<script setup lang="ts">
import Avatar from '@/components/roster/Avatar.vue';
import { compactMoney, formatDate, plainMoney } from '@/composables/useRoster';
import RosterLayout from '@/layouts/RosterLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Paperclip, Receipt } from 'lucide-vue-next';

interface Row {
    id: number;
    category: string;
    spent_on: string;
    amount: number;
    description: string;
    status: 'pending' | 'approved' | 'rejected' | 'cancelled' | 'paid';
    receipt: string | null;
    employee: string;
    employee_no: string;
}
const props = defineProps<{ claims: Row[]; counts: Record<string, { n: number; total: number }>; status: string | null; currency: string }>();
const dec = props.currency === 'IDR' ? 0 : 2;
const label = { pending: 'Waiting for manager', approved: 'Approved · next payroll', rejected: 'Rejected', cancelled: 'Withdrawn', paid: 'Paid' } as const;
const tone = { pending: 'warning', approved: 'info', rejected: 'danger', cancelled: '', paid: 'success' } as const;
const tabs = [
    { key: null, name: 'All' },
    { key: 'pending', name: 'Waiting' },
    { key: 'approved', name: 'To be paid' },
    { key: 'paid', name: 'Paid' },
    { key: 'rejected', name: 'Rejected' },
] as const;
</script>

<template>
    <Head title="Claims" />
    <RosterLayout current="Claims">
        <main class="page">
            <div class="phead">
                <div>
                    <h1>Claims</h1>
                    <p>Employees claim expenses in the app; managers approve them; approved claims are paid, untaxed, with the next payroll run.</p>
                </div>
            </div>
            <nav class="modtabs" aria-label="Pay">
                <Link :href="route('payroll.index')">Payroll runs</Link>
                <Link :href="route('rule-packs.show')">Rule packs</Link>
                <Link :href="route('claims.index')" aria-current="page">Claims</Link>
            </nav>
            <section class="card kpis5 kpis3">
                <div><span>Waiting for managers</span><b>{{ counts.pending?.n ?? 0 }}</b><small>{{ compactMoney(counts.pending?.total ?? 0, currency) }}</small></div>
                <div><span>Approved, paid with next run</span><b>{{ counts.approved?.n ?? 0 }}</b><small>{{ compactMoney(counts.approved?.total ?? 0, currency) }}</small></div>
                <div class="net"><span>Paid</span><b>{{ counts.paid?.n ?? 0 }}</b><small>{{ compactMoney(counts.paid?.total ?? 0, currency) }}</small></div>
            </section>
            <section class="card">
                <div class="filters">
                    <Link v-for="tb in tabs" :key="String(tb.key)" class="fchip" :href="route('claims.index', tb.key ? { status: tb.key } : {})" :aria-pressed="status === tb.key">{{ tb.name }}</Link>
                </div>
                <div v-if="!claims.length" class="empty"><Receipt class="icon" /><b>No claims here</b></div>
                <div v-else class="tablewrap">
                    <table class="t">
                        <thead><tr><th>Person</th><th>Category</th><th>Spent</th><th>For</th><th class="n">Amount</th><th>Status</th><th><span class="sr">Receipt</span></th></tr></thead>
                        <tbody>
                            <tr v-for="c in claims" :key="c.id">
                                <td><div class="who"><Avatar :name="c.employee" /><div><b>{{ c.employee }}</b><span class="small muted block">{{ c.employee_no }}</span></div></div></td>
                                <td>{{ c.category }}</td>
                                <td class="num">{{ formatDate(c.spent_on) }}</td>
                                <td class="small">{{ c.description }}</td>
                                <td class="n num">{{ plainMoney(c.amount, dec) }}</td>
                                <td><span class="badge" :class="tone[c.status]">{{ label[c.status] }}</span></td>
                                <td><a v-if="c.receipt" :href="c.receipt" target="_blank" rel="noopener" class="ibtn" aria-label="View receipt"><Paperclip class="icon sm" /></a></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
            <p class="synthetic">Synthetic demo data.</p>
        </main>
    </RosterLayout>
</template>
