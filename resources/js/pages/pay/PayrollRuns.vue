<script setup lang="ts">
import { compactMoney, formatDate, monthLabel, plainMoney } from '@/composables/useRoster';
import RosterLayout from '@/layouts/RosterLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronRight, CirclePlay, Info, Lock, Send } from 'lucide-vue-next';
import { ref } from 'vue';

interface Run {
    id: number;
    reference: string;
    period: string;
    pay_date: string;
    status: 'draft' | 'calculating' | 'calculated' | 'approved' | 'locked' | 'published';
    headcount: number;
    totals: { gross: string; tax: string; net: string; employer_contributions: string } | null;
    approvals: number;
    include_thr: boolean;
}
const props = defineProps<{ runs: Run[]; currency: string; next: string; blocked: string | null; can: { run: boolean }; thr: boolean }>();

const decimals = props.currency === 'IDR' ? 0 : 2;
const includeThr = ref(false);
const busy = ref(false);
function start() {
    busy.value = true;
    router.post(route('payroll.store'), { period: props.next, include_thr: includeThr.value }, { onFinish: () => (busy.value = false) });
}

const status: Record<Run['status'], { label: string; cls: string }> = {
    draft: { label: 'Draft', cls: '' },
    calculating: { label: 'Calculating', cls: 'info' },
    calculated: { label: 'In review', cls: 'warning' },
    approved: { label: 'Approved', cls: 'info' },
    locked: { label: 'Locked', cls: 'brand' },
    published: { label: 'Payslips sent', cls: 'success' },
};
</script>

<template>
    <Head title="Payroll" />
    <RosterLayout current="Payroll">
        <main class="page">
            <div class="phead">
                <div>
                    <h1>Payroll</h1>
                    <p>Monthly runs: calculate, review every change, approve twice, lock, then send payslips.</p>
                </div>
            </div>
            <nav class="modtabs" aria-label="Pay">
                <Link :href="route('payroll.index')" aria-current="page">Payroll runs</Link>
                <Link :href="route('rule-packs.show')">Rule packs</Link>
                <Link :href="route('claims.index')">Claims</Link>
            </nav>

            <section v-if="can.run" class="card nextrun">
                <div class="nr-main">
                    <span class="nr-ic"><CirclePlay class="icon" /></span>
                    <div>
                        <h2>{{ monthLabel(next) }}</h2>
                        <p v-if="blocked" class="muted">Finish {{ blocked }} first. Year-to-date tax depends on earlier months being locked.</p>
                        <p v-else class="muted">Next period. Salaries, allowances, unpaid leave and approved overtime are read when you calculate.</p>
                    </div>
                </div>
                <div class="nr-acts">
                    <button v-if="thr" type="button" role="switch" class="swt" :aria-checked="includeThr" :disabled="!!blocked" @click="includeThr = !includeThr">
                        <span class="track" />Include THR this month
                    </button>
                    <button type="button" class="btn primary" :disabled="!!blocked || busy" @click="start">Start {{ monthLabel(next).split(' ')[0] }} run</button>
                </div>
            </section>

            <section class="card">
                <div class="tablewrap">
                    <table class="t runs">
                        <thead>
                            <tr>
                                <th>Period</th>
                                <th>Status</th>
                                <th class="n">People</th>
                                <th class="n">Gross</th>
                                <th class="n">Tax</th>
                                <th class="n">Net</th>
                                <th>Pay date</th>
                                <th><span class="sr">Open</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="r in runs" :key="r.id" class="rowlink" @click="router.visit(route('payroll.show', { run: r.id }))">
                                <td>
                                    <Link :href="route('payroll.show', { run: r.id })" class="name">{{ monthLabel(r.period) }}</Link>
                                    <span class="small muted block">{{ r.reference }}<template v-if="r.include_thr"> · with THR</template></span>
                                </td>
                                <td>
                                    <span class="badge" :class="status[r.status].cls">
                                        <Lock v-if="r.status === 'locked'" class="icon" /><Send v-else-if="r.status === 'published'" class="icon" />{{ status[r.status].label }}
                                    </span>
                                    <span v-if="r.status === 'calculated'" class="small muted"> · {{ r.approvals }}/2 approved</span>
                                </td>
                                <td class="n num">{{ r.headcount || '—' }}</td>
                                <td class="n num" :title="r.totals ? plainMoney(r.totals.gross, decimals) : undefined">{{ r.totals ? compactMoney(r.totals.gross, currency) : '—' }}</td>
                                <td class="n num">{{ r.totals ? compactMoney(r.totals.tax, currency) : '—' }}</td>
                                <td class="n num"><b>{{ r.totals ? compactMoney(r.totals.net, currency) : '—' }}</b></td>
                                <td class="num">{{ formatDate(r.pay_date) }}</td>
                                <td class="n"><ChevronRight class="icon sm muted" /></td>
                            </tr>
                            <tr v-if="!runs.length">
                                <td colspan="8"><div class="empty"><Info class="icon" /><p>No payroll runs yet. Start the first month above.</p></div></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
            <p class="synthetic">Synthetic demo data. Rates follow the cited regulations in the rule pack and must be verified before real use.</p>
        </main>
    </RosterLayout>
</template>
