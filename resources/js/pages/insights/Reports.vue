<script setup lang="ts">
import Avatar from '@/components/roster/Avatar.vue';
import RSelect from '@/components/roster/RSelect.vue';
import { humanMinutes, monthLabel, plainMoney } from '@/composables/useRoster';
import RosterLayout from '@/layouts/RosterLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Download, Sheet } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface AttendanceRow {
    id: number;
    employee_no: string;
    name: string;
    department: string | null;
    expected: number;
    present: number;
    late: number;
    late_min: number;
    incomplete: number;
    leave: number;
    absent: number;
    rate: number | null;
}
interface Balance {
    entitled: number | null;
    carried: number;
    adjustment: number;
    used: number;
    pending: number;
    available: number | null;
    eligible: boolean;
}
interface LeaveData {
    types: { id: number; name: string; color: string; limited: boolean }[];
    rows: { id: number; employee_no: string; name: string; department: string | null; balances: Record<number, Balance> }[];
}
interface PayRow {
    department: string;
    headcount: number;
    gross: number;
    tax: number;
    employee_contributions: number;
    other_deductions: number;
    net: number;
    employer_contributions: number;
    employer_cost: number;
}
interface ContribRow {
    code: string;
    name: string;
    people: number;
    employee: number;
    employer: number;
    total: number;
}
const props = defineProps<{
    report: 'attendance' | 'leave' | 'payroll' | 'contributions';
    period: string;
    today: string;
    scope: 'company' | 'team';
    available: { time: boolean; pay: boolean };
    months: string[];
    currency: string;
    attendance: AttendanceRow[] | null;
    leave: LeaveData | null;
    payroll: { rows: PayRow[]; total: PayRow; reference: string; status: string } | null;
    contributions: { rows: ContribRow[]; reference: string } | null;
}>();
const isPay = computed(() => props.report === 'payroll' || props.report === 'contributions');
const dec = computed(() => (props.currency === 'IDR' ? 0 : 2));
const money = (v: number) => plainMoney(v, dec.value);
const monthOptions = computed(() => props.months.map((m) => ({ value: m, label: monthLabel(`${m}-01`) })));
const payMonth = ref<string | number | null>(props.period);
watch(payMonth, (m) => m && m !== props.period && router.get(route('reports'), { report: props.report, period: m }, { preserveScroll: true }));
const contribTotal = computed(() => (props.contributions?.rows ?? []).reduce((a, r) => ({ employee: a.employee + r.employee, employer: a.employer + r.employer, total: a.total + r.total }), { employee: 0, employer: 0, total: 0 }));

const fmt = (n: number) => (Number.isInteger(n) ? String(n) : n.toFixed(1));

const periodLabel = computed(() =>
    props.report === 'attendance' ? new Intl.DateTimeFormat('en-GB', { month: 'long', year: 'numeric' }).format(new Date(`${props.period}-01T00:00:00`)) : props.period,
);
const latest = computed(() => (props.report === 'attendance' ? props.today.slice(0, 7) : props.today.slice(0, 4)));
function move(n: number) {
    let period: string;
    if (props.report === 'attendance') {
        const [y, m] = props.period.split('-').map(Number);
        const d = new Date(y, m - 1 + n, 1);
        period = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
    } else {
        period = String(Number(props.period) + n);
    }
    router.get(route('reports'), { report: props.report, period }, { preserveScroll: true });
}

const totals = computed(() => {
    const rows = props.attendance ?? [];
    const rated = rows.filter((r) => r.rate !== null);
    return {
        rate: rated.length ? rated.reduce((s, r) => s + (r.rate ?? 0), 0) / rated.length : null,
        late: rows.reduce((s, r) => s + r.late, 0),
        lateMin: rows.reduce((s, r) => s + r.late_min, 0),
        incomplete: rows.reduce((s, r) => s + r.incomplete, 0),
        absent: rows.reduce((s, r) => s + r.absent, 0),
        leave: rows.reduce((s, r) => s + r.leave, 0),
    };
});
const exportUrl = (format: 'csv' | 'xlsx') => route('reports.export', { report: props.report, period: props.period, format });
const monthInProgress = computed(() => props.report === 'attendance' && props.period === props.today.slice(0, 7));
</script>

<template>
    <Head title="Reports" />
    <RosterLayout current="Reports">
        <main class="page">
            <div class="phead">
                <div>
                    <h1>Reports</h1>
                    <p>{{ scope === 'team' ? 'Your direct reports' : 'Everyone in the company' }}</p>
                </div>
                <div class="acts">
                    <div v-if="isPay" class="fsel monthsel"><RSelect v-model="payMonth" :options="monthOptions" label="Payroll month" placeholder="No locked payroll yet" /></div>
                    <div v-else class="datenav">
                        <button type="button" class="ibtn" :aria-label="report === 'attendance' ? 'Previous month' : 'Previous year'" @click="move(-1)"><ChevronLeft class="icon" /></button>
                        <span class="mlabel">{{ periodLabel }}</span>
                        <button type="button" class="ibtn" :aria-label="report === 'attendance' ? 'Next month' : 'Next year'" :disabled="period >= latest" @click="move(1)"><ChevronRight class="icon" /></button>
                    </div>
                    <a class="btn" :href="exportUrl('csv')"><Download class="icon sm" />CSV</a>
                    <a class="btn" :href="exportUrl('xlsx')"><Sheet class="icon sm" />Excel</a>
                </div>
            </div>

            <nav class="subtabs rtabs" aria-label="Report">
                <template v-if="available.time">
                    <Link :href="route('reports', { report: 'attendance' })" :aria-current="report === 'attendance' ? 'page' : undefined">Attendance summary</Link>
                    <Link :href="route('reports', { report: 'leave' })" :aria-current="report === 'leave' ? 'page' : undefined">Leave balances</Link>
                </template>
                <template v-if="available.pay">
                    <Link :href="route('reports', { report: 'payroll' })" :aria-current="report === 'payroll' ? 'page' : undefined">Payroll summary</Link>
                    <Link :href="route('reports', { report: 'contributions' })" :aria-current="report === 'contributions' ? 'page' : undefined">Contributions</Link>
                </template>
            </nav>

            <section v-if="attendance" class="card">
                <dl class="kstrip">
                    <div><dt>Attendance rate</dt><dd>{{ totals.rate === null ? '—' : `${fmt(Math.round(totals.rate * 10) / 10)}%` }}</dd></div>
                    <div><dt>Late arrivals</dt><dd>{{ totals.late }}<small v-if="totals.late">{{ humanMinutes(totals.lateMin) }} in total</small></dd></div>
                    <div><dt>Missing clock-outs</dt><dd :class="{ bad: totals.incomplete }">{{ totals.incomplete }}</dd></div>
                    <div><dt>Days with no record</dt><dd :class="{ bad: totals.absent }">{{ totals.absent }}<small>{{ fmt(totals.leave) }} leave days excluded</small></dd></div>
                </dl>
                <div v-if="!attendance.length" class="empty"><p>Nothing to report yet for {{ periodLabel }}. Days are counted once they are over.</p></div>
                <div v-else class="tablewrap">
                    <table class="t rep-t">
                        <thead>
                            <tr>
                                <th>Person</th>
                                <th class="n">Working days</th>
                                <th class="n">Present</th>
                                <th class="n">Late</th>
                                <th class="n">No clock-out</th>
                                <th class="n">Leave</th>
                                <th class="n">No record</th>
                                <th class="n">Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="r in attendance" :key="r.id">
                                <td><div class="who"><Avatar :name="r.name" /><div><Link :href="route('employees.show', { employee: r.id })" class="name">{{ r.name }}</Link><span>{{ r.employee_no }} · {{ r.department ?? '—' }}</span></div></div></td>
                                <td class="n num">{{ r.expected }}</td>
                                <td class="n num">{{ r.present }}</td>
                                <td class="n num"><template v-if="r.late">{{ r.late }}<span class="small muted nowrap"> · {{ humanMinutes(r.late_min) }}</span></template><span v-else class="muted">0</span></td>
                                <td class="n num" :class="{ bad: r.incomplete }">{{ r.incomplete }}</td>
                                <td class="n num">{{ fmt(r.leave) }}</td>
                                <td class="n num" :class="{ bad: r.absent }">{{ r.absent }}</td>
                                <td class="n"><span v-if="r.rate !== null" class="badge" :class="r.rate >= 95 ? 'success' : r.rate >= 85 ? 'warning' : 'danger'">{{ fmt(r.rate) }}%</span><span v-else class="muted">—</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section v-if="leave" class="card">
                <div class="legend2">
                    <span>Limited types show days still available out of the yearly entitlement plus carry-over, with pending requests already deducted. Open-ended types show days taken this year.</span>
                </div>
                <div class="tablewrap">
                    <table class="t rep-t">
                        <thead>
                            <tr>
                                <th>Person</th>
                                <th v-for="t in leave.types" :key="t.id" class="n lcol"><span class="tdot" :style="{ background: t.color }" />{{ t.name }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="r in leave.rows" :key="r.id">
                                <td><div class="who"><Avatar :name="r.name" /><div><Link :href="route('employees.show', { employee: r.id })" class="name">{{ r.name }}</Link><span>{{ r.employee_no }} · {{ r.department ?? '—' }}</span></div></div></td>
                                <td v-for="t in leave.types" :key="t.id" class="n num">
                                    <span v-if="!r.balances[t.id].eligible" class="muted">Not yet eligible</span>
                                    <template v-else-if="t.limited">
                                        <b :class="{ bad: (r.balances[t.id].available ?? 0) < 0 }">{{ fmt(r.balances[t.id].available ?? 0) }}</b>
                                        <span class="muted"> / {{ fmt((r.balances[t.id].entitled ?? 0) + r.balances[t.id].carried + r.balances[t.id].adjustment) }}</span>
                                        <span class="small muted sub">
                                            {{ fmt(r.balances[t.id].used) }} used<template v-if="r.balances[t.id].pending"> · {{ fmt(r.balances[t.id].pending) }} pending</template>
                                        </span>
                                    </template>
                                    <template v-else>
                                        <b>{{ fmt(r.balances[t.id].used) }}</b><span class="muted"> taken</span>
                                        <span v-if="r.balances[t.id].pending" class="small muted sub">{{ fmt(r.balances[t.id].pending) }} pending</span>
                                    </template>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section v-if="report === 'payroll'" class="card">
                <div v-if="!payroll" class="empty"><p>No locked payroll yet. Reports read locked runs only, so they always match the payslips.</p></div>
                <template v-else>
                    <dl class="kstrip">
                        <div><dt>Gross pay</dt><dd>{{ money(payroll.total.gross) }}</dd></div>
                        <div><dt>Net pay</dt><dd>{{ money(payroll.total.net) }}<small>{{ payroll.total.headcount }} people · {{ payroll.reference }}</small></dd></div>
                        <div><dt>Tax withheld</dt><dd>{{ money(payroll.total.tax) }}</dd></div>
                        <div><dt>Employer cost</dt><dd>{{ money(payroll.total.employer_cost) }}<small>gross + employer contributions</small></dd></div>
                    </dl>
                    <div class="tablewrap">
                        <table class="t rep-t">
                            <thead><tr><th>Department</th><th class="n">People</th><th class="n">Gross</th><th class="n">Tax</th><th class="n">Employee contrib.</th><th class="n">Other deductions</th><th class="n">Net</th><th class="n">Employer contrib.</th></tr></thead>
                            <tbody>
                                <tr v-for="r in payroll.rows" :key="r.department">
                                    <td><b>{{ r.department }}</b></td><td class="n num">{{ r.headcount }}</td><td class="n num">{{ money(r.gross) }}</td><td class="n num">{{ money(r.tax) }}</td>
                                    <td class="n num">{{ money(r.employee_contributions) }}</td><td class="n num">{{ money(r.other_deductions) }}</td><td class="n num"><b>{{ money(r.net) }}</b></td><td class="n num">{{ money(r.employer_contributions) }}</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td>Total</td><td class="n num">{{ payroll.total.headcount }}</td><td class="n num">{{ money(payroll.total.gross) }}</td><td class="n num">{{ money(payroll.total.tax) }}</td>
                                    <td class="n num">{{ money(payroll.total.employee_contributions) }}</td><td class="n num">{{ money(payroll.total.other_deductions) }}</td><td class="n num">{{ money(payroll.total.net) }}</td><td class="n num">{{ money(payroll.total.employer_contributions) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </template>
            </section>

            <section v-if="report === 'contributions'" class="card">
                <div v-if="!contributions" class="empty"><p>No locked payroll yet.</p></div>
                <template v-else>
                    <div class="legend2"><span>What to pay each programme for {{ monthLabel(`${period}-01`) }} ({{ contributions.reference }}). The Excel and CSV files list every person.</span></div>
                    <div class="tablewrap">
                        <table class="t rep-t">
                            <thead><tr><th>Programme</th><th class="n">People</th><th class="n">Employee part</th><th class="n">Employer part</th><th class="n">To pay</th></tr></thead>
                            <tbody>
                                <tr v-for="r in contributions.rows" :key="r.code">
                                    <td><b>{{ r.name }}</b></td><td class="n num">{{ r.people }}</td><td class="n num">{{ money(r.employee) }}</td><td class="n num">{{ money(r.employer) }}</td><td class="n num"><b>{{ money(r.total) }}</b></td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr><td>Total</td><td /><td class="n num">{{ money(contribTotal.employee) }}</td><td class="n num">{{ money(contribTotal.employer) }}</td><td class="n num">{{ money(contribTotal.total) }}</td></tr>
                            </tfoot>
                        </table>
                    </div>
                </template>
            </section>

            <p class="synthetic">
                Synthetic demo data.
                <template v-if="monthInProgress">This month is still running, so it is counted up to yesterday.</template>
                <template v-if="report === 'leave'">Marriage, maternity and other event-based leave is granted per event and is not listed here.</template>
            </p>
        </main>
    </RosterLayout>
</template>
