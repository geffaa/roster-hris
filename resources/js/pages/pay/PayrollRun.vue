<script setup lang="ts">
import Avatar from '@/components/roster/Avatar.vue';
import { compactMoney, formatDate, formatDateTime, monthLabel, plainMoney, xsrfToken } from '@/composables/useRoster';
import RosterLayout from '@/layouts/RosterLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Calculator, CalendarCheck, Info, Sparkles, Check, ChevronRight, CircleAlert, Download, Landmark, Lock, RefreshCw, Search, Send, TriangleAlert } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

interface Line {
    code: string;
    label: string;
    section: 'earning' | 'deduction' | 'tax' | 'employee_contribution' | 'employer_contribution';
    amount: number;
    basis: string | null;
}
interface Flag {
    type: string;
    level: 'error' | 'warning' | 'check' | 'info';
    message: string;
}
interface Item {
    id: number;
    employee_id: number;
    context: {
        name: string;
        employee_no: string;
        job_title: string;
        department: string | null;
        tax_status: string;
        joined: string | null;
        leaving: string | null;
        unpaid_days: number;
        overtime_hours: number;
        one_offs: string[];
        employed_days: number;
        period_days: number;
        bank: { name: string; last4: string } | null;
    };
    gross: number;
    tax: number;
    employee_contributions: number;
    employer_contributions: number;
    other_deductions: number;
    net: number;
    previous_net: number | null;
    breakdown: Line[];
    tax_detail: Record<string, number | string>;
    flags: Flag[];
    reviewed: boolean;
}
type Totals = { headcount: number; gross: string; tax: string; employee_contributions: string; employer_contributions: string; net: string };
const props = defineProps<{
    run: {
        id: number;
        reference: string;
        period: string;
        pay_date: string;
        status: 'draft' | 'calculating' | 'calculated' | 'approved' | 'locked' | 'published';
        progress: number;
        headcount: number;
        include_thr: boolean;
        totals: Totals | null;
        version: string | null;
        calculated_at: string | null;
        calculated_by: string | null;
        approvals: { by: string | null; at: string | null; id: number | null }[];
        locked_by: string | null;
        locked_at: string | null;
        published_at: string | null;
    };
    previous: { month: string; totals: Totals } | null;
    currency: string;
    decimals: number;
    items: Item[];
    can: { run: boolean; approve: boolean; already_approved: boolean };
}>();

const isId = computed(() => props.currency === 'IDR');
const taxName = computed(() => (isId.value ? 'PPh 21' : 'Income tax'));
const contribName = computed(() => (isId.value ? 'BPJS' : 'contributions'));
const money = (v: number | string) => plainMoney(v, props.decimals);
const formatMoneyFull = (v: number) => `${isId.value ? 'Rp ' : props.currency === 'GBP' ? '£' : props.currency + ' '}${plainMoney(v, props.decimals)}`;
const minor = (m: number) => plainMoney(Math.abs(m) / 10 ** props.decimals, props.decimals);
const pct = (a: number, b: number) => ((a - b) / b) * 100;
const signed = (n: number) => `${n > 0 ? '+' : n < 0 ? '−' : ''}${Math.abs(n).toFixed(1)}%`;

// Poll while the queue calculates.
let timer: ReturnType<typeof setInterval> | undefined;
watch(
    () => props.run.status,
    (s) => {
        clearInterval(timer);
        if (s === 'calculating') timer = setInterval(() => router.reload({ only: ['run', 'items'] }), 1000);
    },
    { immediate: true },
);
onBeforeUnmount(() => clearInterval(timer));

// Steps
const steps = computed(() => {
    const order = ['draft', 'calculated', 'approved', 'locked', 'published'];
    const at = order.indexOf(props.run.status === 'calculating' ? 'draft' : props.run.status);
    return [
        { label: 'Draft', done: at >= 1 },
        { label: 'Calculated', done: at >= 1, current: props.run.status === 'calculating' || props.run.status === 'draft' },
        { label: `Review & approve${at === 1 ? ` · ${props.run.approvals.filter((a) => a.by).length}/2` : ''}`, done: at >= 2, current: at === 1 },
        { label: 'Locked', done: at >= 3, current: at === 2 },
        { label: 'Payslips sent', done: at >= 4, current: at === 3 },
    ];
});

// Status per person
function state(i: Item): { label: string; cls: string } {
    if (i.flags.some((f) => f.level === 'error')) return { label: 'Negative net', cls: 'danger' };
    if (i.flags.some((f) => f.level === 'check')) return i.reviewed ? { label: 'Reviewed', cls: 'success' } : { label: 'Check', cls: 'warning' };
    if (i.flags.some((f) => f.type === 'no_bank')) return { label: 'No account', cls: 'danger' };
    if (i.context.leaving) return { label: 'Leaving', cls: 'info' };
    if (i.context.joined || i.previous_net === null) return { label: 'New', cls: 'info' };
    return changed(i) ? { label: 'Changed', cls: '' } : { label: 'Same', cls: '' };
}
function subline(i: Item): string {
    const bits = [i.context.job_title];
    if (i.context.one_offs.length) bits.push(i.context.one_offs.map((o) => o.split(' · ').pop()).join(', '));
    if (i.context.joined) bits.push(`joined ${formatDate(i.context.joined).replace(/ \d{4}$/, '')}`);
    if (i.context.leaving) bits.push(`leaves ${formatDate(i.context.leaving).replace(/ \d{4}$/, '')}`);
    if (i.context.unpaid_days) bits.push(`${i.context.unpaid_days} days unpaid`);
    if (i.context.overtime_hours) bits.push(`${i.context.overtime_hours} h overtime`);
    return bits.join(' · ');
}
function changed(i: Item) {
    return i.previous_net === null || Math.abs(i.net - i.previous_net) >= 1;
}
const issue = (i: Item) => i.flags.some((f) => ['error', 'warning', 'check'].includes(f.level));

const filter = ref<'all' | 'changed' | 'issues'>(props.items.some(changed) ? 'changed' : 'all');
const q = ref('');
const shown = computed(() =>
    props.items
        .filter((i) => (filter.value === 'changed' ? changed(i) : filter.value === 'issues' ? issue(i) : true))
        .filter((i) => !q.value || `${i.context.name} ${i.context.employee_no}`.toLowerCase().includes(q.value.toLowerCase())),
);
const reviewable = computed(() => props.items.filter((i) => i.flags.some((f) => f.level === 'check')));
const alerts = computed(() =>
    props.items.flatMap((i) => i.flags.filter((f) => f.level !== 'info').map((f) => ({ item: i, flag: f }))).sort((a, b) => ['error', 'check', 'warning'].indexOf(a.flag.level) - ['error', 'check', 'warning'].indexOf(b.flag.level)),
);
const openReviews = computed(() => reviewable.value.filter((i) => !i.reviewed).length);
const blocking = computed(() => props.items.some((i) => i.flags.some((f) => f.level === 'error')));

const selectedId = ref<number | null>(null);
const selected = computed(() => props.items.find((i) => i.id === selectedId.value) ?? shown.value[0] ?? props.items[0] ?? null);
watch(shown, (list) => {
    if (selectedId.value && !list.some((i) => i.id === selectedId.value)) selectedId.value = null;
});
function pick(i: Item) {
    selectedId.value = i.id;
    if (window.matchMedia('(max-width: 1100px)').matches) document.getElementById('detail')?.scrollIntoView({ behavior: 'smooth' });
}

const section = (i: Item, s: Line['section'][]) => i.breakdown.filter((l) => s.includes(l.section));
const showEmployer = ref(false);

const busy = ref(false);
const post = (name: string, params: Record<string, number> = {}) => {
    busy.value = true;
    router.post(route(name, { run: props.run.id, ...params }), {}, { preserveScroll: true, onFinish: () => (busy.value = false) });
};
const approvedCount = computed(() => props.run.approvals.filter((a) => a.by).length);
const approveHint = computed(() => {
    if (props.can.already_approved) return 'You approved. A second person must approve.';
    if (blocking.value) return 'Fix negative net pay first.';
    if (openReviews.value) return `Review ${openReviews.value} flagged ${openReviews.value === 1 ? 'person' : 'people'} first.`;
    return '';
});
// Briefing: a short read of what changed and what to check, from the run figures.
const briefing = ref<{ points: string[]; mode: string; note: string | null } | null>(null);
const briefingBusy = ref(false);
async function brief() {
    briefingBusy.value = true;
    try {
        const res = await fetch(route('payroll.briefing', { run: props.run.id }), { method: 'POST', headers: { Accept: 'application/json', 'X-XSRF-TOKEN': xsrfToken() }, credentials: 'same-origin' });
        if (res.ok) briefing.value = await res.json();
    } finally {
        briefingBusy.value = false;
    }
}
const netDelta = computed(() => (props.previous && props.run.totals ? pct(Number(props.run.totals.net), Number(props.previous.totals.net)) : null));
</script>

<template>
    <Head :title="`${monthLabel(run.period)} payroll`" />
    <RosterLayout current="Payroll">
        <main class="page">
            <nav class="crumbs" aria-label="Breadcrumb"><Link :href="route('payroll.index')">Payroll</Link><ChevronRight class="icon xs" /><span>{{ monthLabel(run.period) }}</span></nav>
            <div class="phead">
                <div>
                    <h1>{{ monthLabel(run.period) }} payroll</h1>
                    <p>
                        {{ run.reference }} · {{ run.headcount }} people<template v-if="run.version"> · rule pack <Link :href="route('rule-packs.show')">{{ run.version }}</Link></template>
                        <template v-if="run.calculated_at"> · calculated {{ formatDateTime(run.calculated_at) }}<template v-if="run.calculated_by"> by {{ run.calculated_by }}</template></template>
                        <template v-if="run.include_thr"> · includes THR</template>
                    </p>
                </div>
                <div class="acts">
                    <a v-if="run.totals" class="btn" :href="route('payroll.summary', { run: run.id })"><Download class="icon sm" />Summary CSV</a>
                    <a v-if="can.run && (run.status === 'locked' || run.status === 'published')" class="btn" :href="route('payroll.bank', { run: run.id })"><Landmark class="icon sm" />Bank file</a>
                    <button v-if="can.run && ['calculated', 'approved', 'draft'].includes(run.status)" type="button" class="btn" :disabled="busy" @click="post('payroll.calculate')">
                        <RefreshCw class="icon sm" />{{ run.status === 'draft' ? 'Calculate' : 'Recalculate' }}
                    </button>
                    <button v-if="run.status === 'calculated'" type="button" class="btn primary" :disabled="busy || !can.approve || !!approveHint" :title="approveHint || undefined" @click="post('payroll.approve')">
                        <Check class="icon sm" />Approve ({{ approvedCount + 1 }} of 2)
                    </button>
                    <button v-if="run.status === 'approved' && can.run" type="button" class="btn primary" :disabled="busy" @click="post('payroll.lock')"><Lock class="icon sm" />Lock run</button>
                    <button v-if="run.status === 'locked' && can.run" type="button" class="btn primary" :disabled="busy" @click="post('payroll.publish')"><Send class="icon sm" />Send payslips</button>
                </div>
            </div>
            <nav class="modtabs" aria-label="Pay">
                <Link :href="route('payroll.index')" aria-current="page">Payroll runs</Link>
                <Link :href="route('rule-packs.show')">Rule packs</Link>
                <a aria-disabled="true">Claims <span class="soon">Soon</span></a>
            </nav>

            <section class="card stepper" aria-label="Progress">
                <ol>
                    <li v-for="(s, n) in steps" :key="n" :class="{ done: s.done && !s.current, current: s.current }">
                        <span class="dot"><Check v-if="s.done && !s.current" class="icon xs" /><template v-else>{{ n + 1 }}</template></span>{{ s.label }}
                    </li>
                </ol>
                <span class="chip"><CalendarCheck class="icon xs" />Pay date {{ formatDate(run.pay_date) }}</span>
            </section>
            <p v-if="run.status === 'calculated' && approveHint" class="small apprhint"><TriangleAlert class="icon xs" />{{ approveHint }}</p>
            <div v-if="approvedCount || run.locked_by" class="approvals small">
                <span v-for="(a, n) in run.approvals.filter((x) => x.by)" :key="n"><Check class="icon xs" />Approved by {{ a.by }} · {{ formatDateTime(a.at!) }}</span>
                <span v-if="run.status === 'calculated' && approvedCount === 1" class="muted">Waiting for a second approver (anyone with approval rights except {{ run.approvals[0].by }})</span>
                <span v-if="run.locked_by"><Lock class="icon xs" />Locked by {{ run.locked_by }} · {{ formatDateTime(run.locked_at!) }}</span>
                <span v-if="run.published_at"><Send class="icon xs" />Payslips sent {{ formatDateTime(run.published_at) }}</span>
            </div>

            <section v-if="run.status === 'draft'" class="card empty-run">
                <span class="nr-ic"><Calculator class="icon" /></span>
                <h2>Not calculated yet</h2>
                <p class="muted">Calculating reads salaries, allowances, one-off pay, approved unpaid leave and overtime for {{ monthLabel(run.period) }}, and the rule pack in force on {{ formatDate(run.period.slice(0, 8) + '28') }}.</p>
                <button v-if="can.run" type="button" class="btn primary" :disabled="busy" @click="post('payroll.calculate')">Calculate {{ run.headcount ? run.headcount + ' people' : 'now' }}</button>
            </section>

            <section v-else-if="run.status === 'calculating'" class="card empty-run" aria-live="polite">
                <span class="nr-ic"><RefreshCw class="icon spin" /></span>
                <h2>Calculating {{ run.progress }} of {{ run.headcount || '…' }}</h2>
                <div class="bar"><span :style="{ width: `${run.headcount ? (run.progress / run.headcount) * 100 : 5}%` }" /></div>
            </section>

            <template v-else-if="run.totals">
                <section class="card kpis5">
                    <div><span>Gross pay</span><b>{{ compactMoney(run.totals.gross, currency) }}</b><small>{{ money(run.totals.gross) }}</small></div>
                    <div><span>{{ taxName }} withheld</span><b>{{ compactMoney(run.totals.tax, currency) }}</b><small>{{ money(run.totals.tax) }}</small></div>
                    <div><span>Employee {{ contribName }}</span><b>{{ compactMoney(run.totals.employee_contributions, currency) }}</b><small>{{ money(run.totals.employee_contributions) }}</small></div>
                    <div><span>Employer {{ contribName }}</span><b>{{ compactMoney(run.totals.employer_contributions, currency) }}</b><small>{{ money(run.totals.employer_contributions) }}</small></div>
                    <div class="net"><span>Net pay</span><b>{{ compactMoney(run.totals.net, currency) }}</b><small v-if="netDelta !== null">{{ signed(netDelta) }} vs {{ previous!.month }}</small><small v-else>{{ money(run.totals.net) }}</small></div>
                </section>

                <section class="card brief">
                    <div class="brief-h">
                        <span class="nr-ic"><Sparkles class="icon" /></span>
                        <div><b>Briefing before you approve</b><small>What changed against last month and who needs a check, written from this run's figures.</small></div>
                        <button v-if="!briefing" type="button" class="btn sm" :disabled="briefingBusy" @click="brief">{{ briefingBusy ? 'Reading the run…' : 'Write briefing' }}</button>
                    </div>
                    <ul v-if="briefing" class="brief-list"><li v-for="(p, i) in briefing.points" :key="i">{{ p }}</li></ul>
                    <p v-if="briefing?.note" class="small muted brief-note">{{ briefing.note }}</p>
                </section>

                <div v-if="alerts.length" class="alerts">
                    <div v-for="(a, n) in alerts" :key="n" class="note" :class="a.flag.level === 'error' ? 'danger' : a.flag.level === 'check' && a.item.reviewed ? 'success' : 'warning'">
                        <component :is="a.flag.level === 'error' ? CircleAlert : a.item.reviewed && a.flag.level === 'check' ? Check : TriangleAlert" class="icon sm" />
                        <span><b>{{ a.item.context.name }}:</b> {{ a.flag.message }}<template v-if="a.flag.level === 'check' && a.item.reviewed"> Reviewed.</template></span>
                        <button type="button" class="btn sm" @click="pick(a.item)">{{ a.flag.level === 'check' && !a.item.reviewed ? 'Review' : 'Open' }}</button>
                    </div>
                </div>

                <div class="runwrap">
                    <section class="card">
                        <div class="filters">
                            <div class="seg" role="group" aria-label="Show">
                                <button type="button" :aria-pressed="filter === 'all'" @click="filter = 'all'">All {{ items.length }}</button>
                                <button type="button" :aria-pressed="filter === 'changed'" @click="filter = 'changed'">Changed {{ items.filter(changed).length }}</button>
                                <button type="button" :aria-pressed="filter === 'issues'" @click="filter = 'issues'">Issues {{ items.filter(issue).length }}</button>
                            </div>
                            <label class="searchbox"><Search class="icon sm" /><input v-model="q" class="bare" type="search" placeholder="Search name or ID" aria-label="Search name or ID" /></label>
                            <span v-if="reviewable.length" class="small muted rev"><b>{{ reviewable.length - openReviews }} of {{ reviewable.length }}</b> reviewed</span>
                        </div>
                        <div class="tablewrap">
                            <table class="t payt">
                                <thead>
                                    <tr><th>Employee</th><th class="n">Gross</th><th class="n">{{ taxName }}</th><th class="n">Net · vs {{ previous?.month.slice(0, 3) ?? 'prev' }}</th><th>Status</th></tr>
                                </thead>
                                <tbody>
                                    <tr v-for="i in shown" :key="i.id" :aria-selected="selected?.id === i.id" tabindex="0" @click="pick(i)" @keydown.enter="pick(i)">
                                        <td><div class="who"><Avatar :name="i.context.name" /><div><b>{{ i.context.name }}</b><span>{{ subline(i) }}</span></div></div></td>
                                        <td class="n num">{{ money(i.gross) }}</td>
                                        <td class="n num" :class="{ refund: i.tax < 0 }">{{ i.tax < 0 ? '−' : '' }}{{ money(Math.abs(i.tax)) }}</td>
                                        <td class="n num">
                                            <b>{{ money(i.net) }}</b>
                                            <span class="delta" :class="{ up: i.previous_net !== null && i.net > i.previous_net + 1, down: i.previous_net !== null && i.net < i.previous_net - 1 }">{{ i.previous_net === null ? 'new' : Math.abs(i.net - i.previous_net) < 1 ? 'same' : signed(pct(i.net, i.previous_net)) }}</span>
                                        </td>
                                        <td><span class="badge" :class="state(i).cls">{{ state(i).label }}</span></td>
                                    </tr>
                                    <tr v-if="!shown.length"><td colspan="5"><div class="empty"><p>Nobody matches this view.</p></div></td></tr>
                                </tbody>
                                <tfoot v-if="shown.length">
                                    <tr>
                                        <td>{{ shown.length }} of {{ items.length }} shown</td>
                                        <td class="n num">{{ money(shown.reduce((s, i) => s + i.gross, 0)) }}</td>
                                        <td class="n num">{{ money(shown.reduce((s, i) => s + i.tax, 0)) }}</td>
                                        <td class="n num">{{ money(shown.reduce((s, i) => s + i.net, 0)) }}</td>
                                        <td />
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </section>

                    <aside v-if="selected" id="detail" class="card slip" :aria-label="`Breakdown for ${selected.context.name}`">
                        <div class="slip-h">
                            <Avatar :name="selected.context.name" />
                            <div>
                                <Link :href="route('employees.show', { employee: selected.employee_id })" class="name">{{ selected.context.name }}</Link>
                                <span>
                                    {{ selected.context.employee_no }}<template v-if="selected.context.tax_status"> · {{ selected.context.tax_status }}</template>
                                    <template v-if="selected.tax_detail.category && isId"> · TER {{ selected.tax_detail.category }}</template>
                                </span>
                            </div>
                        </div>
                        <div class="slip-b">
                            <h3>Earnings</h3>
                            <dl class="lines">
                                <div v-for="l in section(selected, ['earning'])" :key="l.code"><dt>{{ l.label }}<small v-if="l.basis">{{ l.basis }}</small></dt><dd :class="{ neg: l.amount < 0 }">{{ l.amount < 0 ? '−' : '' }}{{ minor(l.amount) }}</dd></div>
                                <div class="total"><dt>Gross</dt><dd>{{ money(selected.gross) }}</dd></div>
                            </dl>
                            <h3>Deductions</h3>
                            <dl class="lines">
                                <div v-for="l in section(selected, ['tax', 'employee_contribution', 'deduction'])" :key="l.code">
                                    <dt>{{ l.label }}<small v-if="l.basis">{{ l.basis }}</small></dt>
                                    <dd :class="{ pos: l.amount > 0 }">{{ l.amount > 0 ? '+' : '−' }}{{ minor(l.amount) }}</dd>
                                </div>
                            </dl>
                            <div v-if="selected.tax_detail.method === 'true_up'" class="trueup">
                                <h3>Year-end recalculation</h3>
                                <dl class="lines compact">
                                    <div><dt>Gross for {{ selected.tax_detail.months }} months</dt><dd>{{ minor(Number(selected.tax_detail.annual_bruto)) }}</dd></div>
                                    <div><dt>Biaya jabatan</dt><dd>−{{ minor(Number(selected.tax_detail.position_cost)) }}</dd></div>
                                    <div><dt>JHT + JP paid by employee</dt><dd>−{{ minor(Number(selected.tax_detail.deductible)) }}</dd></div>
                                    <div><dt>PTKP {{ selected.tax_detail.status }}</dt><dd>−{{ minor(Number(selected.tax_detail.ptkp)) }}</dd></div>
                                    <div><dt>Taxable income (rounded down)</dt><dd>{{ minor(Number(selected.tax_detail.pkp)) }}</dd></div>
                                    <div><dt>Tax for the year (Article 17)</dt><dd>{{ minor(Number(selected.tax_detail.annual_tax)) }}</dd></div>
                                    <div><dt>Withheld earlier this year</dt><dd>−{{ minor(Number(selected.tax_detail.withheld)) }}</dd></div>
                                </dl>
                            </div>
                            <div class="netline"><span>Net pay</span><b>{{ formatMoneyFull(selected.net) }}</b></div>
                            <button type="button" class="linkbtn small" :aria-expanded="showEmployer" @click="showEmployer = !showEmployer">
                                Employer also pays {{ money(selected.employer_contributions) }} in {{ contribName }} (not deducted)
                            </button>
                            <dl v-if="showEmployer" class="lines compact">
                                <div v-for="l in section(selected, ['employer_contribution'])" :key="l.code"><dt>{{ l.label }}<small>{{ l.basis }}</small></dt><dd>{{ minor(l.amount) }}</dd></div>
                            </dl>
                            <div v-for="f in selected.flags" :key="f.type" class="note small" :class="f.level === 'error' ? 'danger' : f.level === 'info' ? 'info' : 'warning'">
                                <component :is="f.level === 'error' ? CircleAlert : f.level === 'info' ? Info : TriangleAlert" class="icon sm" /><span>{{ f.message }}</span>
                            </div>
                            <button
                                v-if="can.run && run.status === 'calculated' && selected.flags.some((f) => f.level === 'check')"
                                type="button"
                                class="btn primary wide"
                                :disabled="selected.reviewed || busy"
                                @click="post('payroll.review', { item: selected.id })"
                            >
                                <Check class="icon sm" />{{ selected.reviewed ? 'Reviewed' : 'Mark reviewed' }}
                            </button>
                        </div>
                    </aside>
                </div>
            </template>
            <p class="synthetic">Synthetic demo data. {{ isId ? 'Rates follow the regulations cited in the rule pack; verify them before real use.' : 'The Generic pack uses illustrative rates, not a real country table.' }}</p>
        </main>
    </RosterLayout>
</template>
