<script setup lang="ts">
import Avatar from '@/components/roster/Avatar.vue';
import { formatDate, humanMinutes, monthLabel, plainMoney, useRoster } from '@/composables/useRoster';
import RosterLayout from '@/layouts/RosterLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowRight, Check, ChevronRight, ClockAlert, FileClock, Inbox, PartyPopper, Plane, Timer, Wallet, Wrench, Landmark } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Req {
    step_id: number;
    type: string;
    title: string;
    summary: string;
    requester: { id: number; name: string; title: string };
}
interface Row {
    id: number;
    name: string;
    title: string;
    state: string;
    in: string | null;
    late_min: number | null;
    shift: string | null;
}
const props = defineProps<{
    now: string;
    scope: 'company' | 'team';
    headcount: number;
    requests: { count: number; by_type: Record<string, number>; items: Req[] };
    attendance: { holiday: string | null; counts: Record<string, number>; watch: Row[] };
    payroll: { id: number; reference: string; period: string; pay_date: string; status: string; approvals: number; net: string | null; headcount: number; open_reviews: number; currency: string } | null;
    contracts: { employee_id: number; name: string; date: string }[];
    upcoming: { date: string; title: string; detail: string; kind: string; link: string | null }[];
    departments: { name: string; count: number }[];
    team: { id: number; full_name: string; job_title: string }[];
}>();

const { user, company } = useRoster();
const hour = new Date(props.now).getHours();
const greeting = hour < 11 ? 'Good morning' : hour < 15 ? 'Good afternoon' : 'Good evening';
const today = new Intl.DateTimeFormat('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(props.now));
const typeLabel: Record<string, string> = { leave: 'leave', overtime: 'overtime', attendance_correction: 'corrections', bank_change: 'bank changes' };
const typeIcon: Record<string, unknown> = { leave: Plane, overtime: Timer, attendance_correction: Wrench, bank_change: Landmark };
const byType = computed(() => Object.entries(props.requests.by_type).map(([k, n]) => `${n} ${typeLabel[k] ?? k}`).join(' · '));

const bars = [
    { key: 'present', label: 'On time', cls: 'ok' },
    { key: 'late', label: 'Late', cls: 'late' },
    { key: 'not_in', label: 'Not clocked in', cls: 'miss' },
    { key: 'incomplete', label: 'No clock-out', cls: 'miss' },
    { key: 'leave', label: 'On leave', cls: 'leave' },
    { key: 'later', label: 'Shift later today', cls: 'off' },
    { key: 'off', label: 'Day off', cls: 'off' },
];
const shown = computed(() => bars.filter((b) => props.attendance.counts[b.key]));
const total = computed(() => shown.value.reduce((s, b) => s + (props.attendance.counts[b.key] ?? 0), 0));

const payStep = computed(() => {
    const p = props.payroll;
    if (!p) return null;
    const order = ['draft', 'calculating', 'calculated', 'approved', 'locked'];
    return order.indexOf(p.status);
});
const payTitle = computed(() => {
    const p = props.payroll;
    if (!p) return '';
    return {
        draft: `${monthLabel(p.period).split(' ')[0]} payroll not calculated yet`,
        calculating: 'Payroll is calculating',
        calculated: `${monthLabel(p.period).split(' ')[0]} payroll needs approval`,
        approved: 'Payroll approved, ready to lock',
        locked: 'Payroll locked, payslips not sent',
    }[p.status] ?? '';
});

const busy = ref<number | null>(null);
function approve(r: Req) {
    busy.value = r.step_id;
    router.post(route('approvals.decide', { step: r.step_id }), { decision: 'approve' }, { preserveScroll: true, onFinish: () => (busy.value = null) });
}
const day = (iso: string) => new Date(`${iso}T00:00:00`);
const maxDept = computed(() => Math.max(1, ...props.departments.map((d) => d.count)));
</script>

<template>
    <Head title="Home" />
    <RosterLayout current="Home">
        <main class="page">
            <div class="phead">
                <div>
                    <h1>{{ greeting }}, {{ user?.name.split(' ')[0] }}</h1>
                    <p>{{ today }} · {{ headcount }} {{ scope === 'team' ? 'people in your team' : `people at ${company?.name}` }}</p>
                </div>
                <div class="acts">
                    <Link v-if="requests.count" class="btn" :href="route('inbox')"><Inbox class="icon sm" />Inbox · {{ requests.count }}</Link>
                    <Link v-if="payroll" class="btn primary" :href="route('payroll.show', { run: payroll.id })"><Wallet class="icon sm" />Review {{ monthLabel(payroll.period).split(' ')[0] }} payroll</Link>
                </div>
            </div>

            <div class="attn3">
                <Link v-if="payroll" class="acard" :href="route('payroll.show', { run: payroll.id })">
                    <span class="ac-ic warn"><ClockAlert class="icon" /></span>
                    <span><b>{{ payTitle }}</b><small>{{ payroll.status === 'calculated' ? `${payroll.approvals}/2 approved${payroll.open_reviews ? ` · ${payroll.open_reviews} to review` : ''} · ` : '' }}pay date {{ formatDate(payroll.pay_date) }}</small></span>
                    <ChevronRight class="icon sm muted" />
                </Link>
                <Link class="acard" :href="route('inbox')">
                    <span class="ac-ic brand"><Inbox class="icon" /></span>
                    <span><b>{{ requests.count ? `${requests.count} ${requests.count === 1 ? 'request' : 'requests'} waiting` : 'No requests waiting' }}</b><small>{{ byType || 'You are all caught up' }}</small></span>
                    <ChevronRight class="icon sm muted" />
                </Link>
                <component :is="contracts.length ? Link : 'div'" class="acard" :href="contracts.length ? route('employees.show', { employee: contracts[0].employee_id }) : undefined">
                    <span class="ac-ic info"><FileClock class="icon" /></span>
                    <span>
                        <b>{{ contracts.length ? `${contracts.length} ${contracts.length === 1 ? 'contract ends' : 'contracts end'} within 60 days` : 'No contracts ending soon' }}</b>
                        <small v-if="contracts.length">{{ contracts[0].name }} · {{ formatDate(contracts[0].date) }}</small><small v-else>Next 60 days are clear</small>
                    </span>
                    <ChevronRight v-if="contracts.length" class="icon sm muted" />
                </component>
            </div>

            <div class="grid-main">
                <div class="stack">
                    <section class="card">
                        <div class="card-h"><h2>Requests</h2><div class="acts"><Link class="btn ghost sm" :href="route('inbox')">View all</Link></div></div>
                        <div v-if="!requests.items.length" class="empty"><Check class="icon" /><b>All caught up</b><span>New leave, overtime and correction requests appear here.</span></div>
                        <ul v-else class="dreq">
                            <li v-for="r in requests.items" :key="r.step_id">
                                <Avatar :name="r.requester.name" />
                                <div class="dr-t">
                                    <b>{{ r.requester.name }}</b>
                                    <span class="dr-k"><component :is="typeIcon[r.type]" class="icon xs" />{{ r.title }}</span>
                                    <small>{{ r.summary }}</small>
                                </div>
                                <div class="dr-a">
                                    <Link class="btn sm" :href="route('inbox')">Details</Link>
                                    <button type="button" class="btn sm primary" :disabled="busy === r.step_id" @click="approve(r)"><Check class="icon sm" />Approve</button>
                                </div>
                            </li>
                        </ul>
                    </section>

                    <section class="card">
                        <div class="card-h"><h2>Attendance today</h2><div class="acts"><Link class="btn ghost sm" :href="route('attendance')">Open attendance</Link></div></div>
                        <div class="card-b">
                            <div v-if="attendance.holiday" class="note info small"><PartyPopper class="icon sm" /><span><b>{{ attendance.holiday }}.</b> Nobody is expected to clock in.</span></div>
                            <template v-else-if="total">
                                <div class="abar" role="img" :aria-label="shown.map((b) => `${attendance.counts[b.key]} ${b.label}`).join(', ')">
                                    <span v-for="b in shown" :key="b.key" :class="b.cls" :style="{ flexGrow: attendance.counts[b.key] }" />
                                </div>
                                <ul class="alegend">
                                    <li v-for="b in shown" :key="b.key"><span class="sw" :class="b.cls" />{{ b.label }} <b>{{ attendance.counts[b.key] }}</b></li>
                                </ul>
                            </template>
                        </div>
                        <ul v-if="attendance.watch.length" class="dwatch">
                            <li v-for="r in attendance.watch" :key="r.id">
                                <Avatar :name="r.name" />
                                <div><b>{{ r.name }}</b><small>{{ r.title }}<template v-if="r.shift"> · shift {{ r.shift.split('–')[0] }}</template><template v-if="r.in"> · in at {{ r.in }}</template></small></div>
                                <span class="badge" :class="r.state === 'late' ? 'warning' : 'danger'">{{ r.state === 'late' ? `${humanMinutes(r.late_min ?? 0)} late` : r.state === 'not_in' ? 'Not clocked in' : 'No clock-out' }}</span>
                            </li>
                        </ul>
                    </section>
                </div>

                <aside class="stack">
                    <section v-if="payroll" class="card">
                        <div class="card-h"><h2>{{ monthLabel(payroll.period).split(' ')[0] }} payroll</h2></div>
                        <div class="card-b">
                            <template v-if="payroll.net">
                                <span class="small muted">Net pay</span>
                                <p class="bignum">{{ payroll.currency === 'IDR' ? 'Rp ' : '' }}{{ plainMoney(payroll.net, payroll.currency === 'IDR' ? 0 : 2) }}</p>
                                <p class="small muted">{{ payroll.headcount }} people · {{ payroll.reference }}</p>
                            </template>
                            <ol class="flow pflow">
                                <li :class="{ done: (payStep ?? 0) >= 2 }"><Check v-if="(payStep ?? 0) >= 2" class="icon xs" /><span v-else class="circ" />Calculated</li>
                                <li :class="{ done: (payStep ?? 0) >= 3, current: payStep === 2 }"><Check v-if="(payStep ?? 0) >= 3" class="icon xs" /><span v-else class="circ" />Two approvals ({{ payroll.approvals }} of 2)</li>
                                <li :class="{ done: (payStep ?? 0) >= 4, current: payStep === 3 }"><Check v-if="(payStep ?? 0) >= 4" class="icon xs" /><span v-else class="circ" />Locked</li>
                                <li :class="{ current: payStep === 4 }"><span class="circ" />Payslips sent · pay date {{ formatDate(payroll.pay_date) }}</li>
                            </ol>
                            <Link class="btn wide" :href="route('payroll.show', { run: payroll.id })">Review payroll<ArrowRight class="icon sm" /></Link>
                        </div>
                    </section>

                    <section class="card">
                        <div class="card-h"><h2>Coming up</h2></div>
                        <p v-if="!upcoming.length" class="card-b small muted">Nothing in the next six weeks.</p>
                        <ul v-else class="dup">
                            <li v-for="(u, i) in upcoming" :key="i">
                                <span class="cal"><b>{{ day(u.date).getDate() }}</b><small>{{ day(u.date).toLocaleString('en-GB', { month: 'short' }) }}</small></span>
                                <div><component :is="u.link ? Link : 'b'" :href="u.link ?? undefined" class="ut">{{ u.title }}</component><small>{{ u.detail }}</small></div>
                                <span v-if="u.kind === 'holiday'" class="badge info">Holiday</span>
                                <span v-else-if="u.kind === 'pending'" class="badge warning">Needs approval</span>
                            </li>
                        </ul>
                    </section>

                    <section v-if="departments.length" class="card">
                        <div class="card-h"><h2>Headcount</h2><span class="muted">{{ headcount }}</span></div>
                        <div class="card-b deptb">
                            <ul class="dept">
                                <li v-for="d in departments" :key="d.name"><span>{{ d.name }}</span><span class="db"><span :style="{ width: `${(d.count / maxDept) * 100}%` }" /></span><b class="num">{{ d.count }}</b></li>
                            </ul>
                        </div>
                    </section>
                    <section v-else-if="team.length" class="card">
                        <div class="card-h"><h2>Your team</h2><span class="muted">{{ team.length }}</span></div>
                        <ul class="dwatch">
                            <li v-for="m in team" :key="m.id"><Avatar :name="m.full_name" /><div><Link :href="route('employees.show', { employee: m.id })"><b>{{ m.full_name }}</b></Link><small>{{ m.job_title }}</small></div></li>
                        </ul>
                    </section>
                </aside>
            </div>
            <p class="synthetic">Synthetic demo data.</p>
        </main>
    </RosterLayout>
</template>
