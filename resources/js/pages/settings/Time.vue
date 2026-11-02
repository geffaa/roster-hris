<script setup lang="ts">
import RSelect from '@/components/roster/RSelect.vue';
import RosterLayout from '@/layouts/RosterLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Check, Moon, Pencil, Plus, Star, UserCog } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Pattern {
    id: number;
    name: string;
    start_time: string;
    end_time: string;
    crosses_midnight: boolean;
    grace_min: number;
    working_days: number[];
    is_default: boolean;
    people: number;
}
interface LeaveType {
    id: number;
    code: string;
    name: string;
    paid: boolean;
    annual_days: number | null;
    accrual: 'upfront' | 'monthly';
    event_based: boolean;
    min_service_months: number;
    half_day_allowed: boolean;
    requires_second_approval: boolean;
    color: string;
    requests: number;
}
const props = defineProps<{ tab: 'patterns' | 'leave'; patterns: Pattern[]; leaveTypes: LeaveType[]; colors: string[] }>();
const tab = ref(props.tab);
const days = [
    [1, 'Mon'],
    [2, 'Tue'],
    [3, 'Wed'],
    [4, 'Thu'],
    [5, 'Fri'],
    [6, 'Sat'],
    [7, 'Sun'],
] as const;
const times = Array.from({ length: 96 }, (_, i) => {
    const t = `${String(Math.floor(i / 4)).padStart(2, '0')}:${String((i % 4) * 15).padStart(2, '0')}`;
    return { value: t, label: t };
});
const graceOptions = [0, 5, 10, 15, 20, 30].map((m) => ({ value: m, label: m ? `${m} minutes` : 'No grace period' }));

// Work pattern form
const editingPattern = ref<number | 'new' | null>(null);
const pf = useForm({ name: '', start_time: '08:30', end_time: '17:30', grace_min: 10, working_days: [1, 2, 3, 4, 5] as number[], is_default: false as boolean });
function editPattern(p?: Pattern) {
    editingPattern.value = p ? p.id : 'new';
    pf.clearErrors();
    Object.assign(pf, p ? { name: p.name, start_time: p.start_time, end_time: p.end_time, grace_min: p.grace_min, working_days: [...p.working_days], is_default: p.is_default } : { name: '', start_time: '08:30', end_time: '17:30', grace_min: 10, working_days: [1, 2, 3, 4, 5], is_default: false });
}
const toggleDay = (d: number) => (pf.working_days = pf.working_days.includes(d) ? pf.working_days.filter((x) => x !== d) : [...pf.working_days, d].sort());
const overnight = computed(() => pf.end_time <= pf.start_time);
const hours = computed(() => {
    const m = (t: string) => Number(t.slice(0, 2)) * 60 + Number(t.slice(3));
    const diff = (m(pf.end_time) - m(pf.start_time) + 1440) % 1440 || 1440;
    return `${Math.floor(diff / 60)} h${diff % 60 ? ` ${diff % 60} min` : ''}`;
});
function savePattern() {
    const opts = { preserveScroll: true, onSuccess: () => (editingPattern.value = null) };
    editingPattern.value === 'new' ? pf.post(route('settings.patterns.store'), opts) : pf.put(route('settings.patterns.update', { pattern: editingPattern.value as number }), opts);
}
const dayList = (ds: number[]) => (ds.join() === '1,2,3,4,5' ? 'Mon–Fri' : ds.map((d) => days[d - 1][1]).join(', '));

// Leave type form
const editingType = ref<number | 'new' | null>(null);
const blank = { name: '', paid: true, annual_days: 12 as number | null, accrual: 'upfront' as 'upfront' | 'monthly', event_based: false, min_service_months: 0, half_day_allowed: true, requires_second_approval: false, color: props.colors[0] };
const lf = useForm({ ...blank });
const unlimited = ref(false);
function editType(t?: LeaveType) {
    editingType.value = t ? t.id : 'new';
    lf.clearErrors();
    Object.assign(lf, t ? { name: t.name, paid: t.paid, annual_days: t.annual_days === null ? null : Number(t.annual_days), accrual: t.accrual, event_based: t.event_based, min_service_months: t.min_service_months, half_day_allowed: t.half_day_allowed, requires_second_approval: t.requires_second_approval, color: t.color } : { ...blank });
    unlimited.value = t ? t.annual_days === null : false;
}
function saveType() {
    lf.transform((d) => ({ ...d, annual_days: unlimited.value ? null : d.annual_days }));
    const opts = { preserveScroll: true, onSuccess: () => (editingType.value = null) };
    editingType.value === 'new' ? lf.post(route('settings.leave-types.store'), opts) : lf.put(route('settings.leave-types.update', { type: editingType.value as number }), opts);
}
const accrualOptions = [
    { value: 'upfront', label: 'All days at the start of the year' },
    { value: 'monthly', label: 'Earned month by month' },
];
const serviceOptions = [0, 3, 6, 12, 24].map((m) => ({ value: m, label: m ? `After ${m} months of service` : 'From the first day' }));
</script>

<template>
    <Head title="Time settings" />
    <RosterLayout current="Settings">
        <main class="page">
            <div class="phead">
                <div>
                    <h1>Time settings</h1>
                    <p>Work patterns drive clock-in, lateness and working-day counts; leave types drive balances and approvals.</p>
                </div>
                <div class="acts"><Link class="btn" :href="route('profile.edit')"><UserCog class="icon sm" />My account</Link></div>
            </div>
            <nav class="subtabs rtabs" aria-label="Settings">
                <a href="#" :aria-current="tab === 'patterns' ? 'page' : undefined" @click.prevent="tab = 'patterns'">Work patterns <span class="ct">{{ patterns.length }}</span></a>
                <a href="#" :aria-current="tab === 'leave' ? 'page' : undefined" @click.prevent="tab = 'leave'">Leave types <span class="ct">{{ leaveTypes.length }}</span></a>
            </nav>

            <div v-if="tab === 'patterns'" class="setwrap">
                <section class="card">
                    <div class="card-h"><h2>Work patterns</h2><div class="acts"><button type="button" class="btn sm" @click="editPattern()"><Plus class="icon sm" />New pattern</button></div></div>
                    <div class="tablewrap">
                        <table class="t">
                            <thead><tr><th>Pattern</th><th>Hours</th><th>Days</th><th class="n">Grace</th><th class="n">People</th><th><span class="sr">Edit</span></th></tr></thead>
                            <tbody>
                                <tr v-for="p in patterns" :key="p.id" :aria-selected="editingPattern === p.id">
                                    <td><b>{{ p.name }}</b><span v-if="p.is_default" class="badge brand sm-badge"><Star class="icon" />Default</span></td>
                                    <td class="num">{{ p.start_time }}–{{ p.end_time }}<Moon v-if="p.crosses_midnight" class="icon xs inline muted" aria-label="crosses midnight" /></td>
                                    <td>{{ dayList(p.working_days) }}</td>
                                    <td class="n num">{{ p.grace_min }} min</td>
                                    <td class="n num">{{ p.people }}</td>
                                    <td class="n"><button type="button" class="ibtn" :aria-label="`Edit ${p.name}`" @click="editPattern(p)"><Pencil class="icon sm" /></button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="small muted card-b setnote">People follow the default pattern unless their contract names another. A shift that ends before it starts runs overnight and belongs to the day it began.</p>
                </section>

                <aside v-if="editingPattern !== null" class="card setform">
                    <div class="card-h"><h2>{{ editingPattern === 'new' ? 'New work pattern' : 'Edit work pattern' }}</h2></div>
                    <form class="card-b stack" @submit.prevent="savePattern">
                        <div class="field"><label for="pname">Name</label><input id="pname" v-model="pf.name" class="input" placeholder="e.g. Warehouse early shift" /><span v-if="pf.errors.name" class="err">{{ pf.errors.name }}</span></div>
                        <div class="two">
                            <div class="field"><label>Starts</label><RSelect v-model="pf.start_time as any" :options="times" label="Shift starts" /></div>
                            <div class="field"><label>Ends</label><RSelect v-model="pf.end_time as any" :options="times" label="Shift ends" /></div>
                        </div>
                        <p class="small muted"><template v-if="overnight"><Moon class="icon xs inline" /> Overnight shift · </template>{{ hours }} including breaks</p>
                        <span v-if="pf.errors.end_time" class="err">{{ pf.errors.end_time }}</span>
                        <div class="field">
                            <label>Working days</label>
                            <div class="daychips" role="group" aria-label="Working days">
                                <button v-for="[d, l] in days" :key="d" type="button" class="fchip" :aria-pressed="pf.working_days.includes(d)" @click="toggleDay(d)">{{ l }}</button>
                            </div>
                            <span v-if="pf.errors.working_days" class="err">{{ pf.errors.working_days }}</span>
                        </div>
                        <div class="field"><label>Grace before counting late</label><RSelect v-model="pf.grace_min as any" :options="graceOptions" label="Grace period" /></div>
                        <button type="button" role="switch" class="swt" :aria-checked="pf.is_default" @click="pf.is_default = !pf.is_default"><span class="track" />Default for everyone without their own pattern</button>
                        <div class="formacts">
                            <button type="button" class="btn ghost" @click="editingPattern = null">Cancel</button>
                            <button type="submit" class="btn primary" :disabled="pf.processing"><Check class="icon sm" />Save pattern</button>
                        </div>
                    </form>
                </aside>
            </div>

            <div v-else class="setwrap">
                <section class="card">
                    <div class="card-h"><h2>Leave types</h2><div class="acts"><button type="button" class="btn sm" @click="editType()"><Plus class="icon sm" />New leave type</button></div></div>
                    <div class="tablewrap">
                        <table class="t">
                            <thead><tr><th>Type</th><th>Allowance</th><th>Eligible</th><th>Rules</th><th class="n">Requests</th><th><span class="sr">Edit</span></th></tr></thead>
                            <tbody>
                                <tr v-for="t in leaveTypes" :key="t.id" :aria-selected="editingType === t.id">
                                    <td><span class="tdot" :style="{ background: t.color }" /><b>{{ t.name }}</b><span v-if="!t.paid" class="small muted"> · unpaid</span></td>
                                    <td>{{ t.annual_days === null ? 'As needed' : `${Number(t.annual_days)} days${t.event_based ? ' per event' : t.accrual === 'monthly' ? ' a year, monthly' : ' a year'}` }}</td>
                                    <td>{{ t.min_service_months ? `After ${t.min_service_months} months` : 'From day one' }}</td>
                                    <td class="small">{{ [t.half_day_allowed ? 'half days' : null, t.requires_second_approval ? 'HR approves too' : null].filter(Boolean).join(' · ') || '—' }}</td>
                                    <td class="n num">{{ t.requests }}</td>
                                    <td class="n"><button type="button" class="ibtn" :aria-label="`Edit ${t.name}`" @click="editType(t)"><Pencil class="icon sm" /></button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <aside v-if="editingType !== null" class="card setform">
                    <div class="card-h"><h2>{{ editingType === 'new' ? 'New leave type' : 'Edit leave type' }}</h2></div>
                    <form class="card-b stack" @submit.prevent="saveType">
                        <div class="field"><label for="lname">Name</label><input id="lname" v-model="lf.name" class="input" placeholder="e.g. Study leave" /><span v-if="lf.errors.name" class="err">{{ lf.errors.name }}</span></div>
                        <button type="button" role="switch" class="swt" :aria-checked="unlimited" @click="unlimited = !unlimited"><span class="track" />No yearly limit (taken as needed)</button>
                        <template v-if="!unlimited">
                            <div class="field"><label for="ldays">Days {{ lf.event_based ? 'per event' : 'a year' }}</label><input id="ldays" v-model.number="lf.annual_days" class="input" inputmode="decimal" /><span v-if="lf.errors.annual_days" class="err">{{ lf.errors.annual_days }}</span></div>
                            <div v-if="!lf.event_based" class="field"><label>How days are given</label><RSelect v-model="lf.accrual as any" :options="accrualOptions" label="Accrual" /></div>
                        </template>
                        <div class="field"><label>Eligible</label><RSelect v-model="lf.min_service_months as any" :options="serviceOptions" label="Eligibility" /></div>
                        <button type="button" role="switch" class="swt" :aria-checked="lf.paid" @click="lf.paid = !lf.paid"><span class="track" />Paid leave</button>
                        <button type="button" role="switch" class="swt" :aria-checked="lf.event_based" @click="lf.event_based = !lf.event_based"><span class="track" />Granted per life event (marriage, birth)</button>
                        <button type="button" role="switch" class="swt" :aria-checked="lf.half_day_allowed" @click="lf.half_day_allowed = !lf.half_day_allowed"><span class="track" />Half days allowed</button>
                        <button type="button" role="switch" class="swt" :aria-checked="lf.requires_second_approval" @click="lf.requires_second_approval = !lf.requires_second_approval"><span class="track" />HR approves after the manager</button>
                        <div class="field">
                            <label>Colour on the calendar</label>
                            <div class="swatches" role="radiogroup" aria-label="Colour">
                                <button v-for="c in colors" :key="c" type="button" role="radio" :aria-checked="lf.color === c" :aria-label="c" :style="{ background: c }" @click="lf.color = c"><Check v-if="lf.color === c" class="icon xs" /></button>
                            </div>
                        </div>
                        <div class="formacts">
                            <button type="button" class="btn ghost" @click="editingType = null">Cancel</button>
                            <button type="submit" class="btn primary" :disabled="lf.processing"><Check class="icon sm" />Save leave type</button>
                        </div>
                    </form>
                </aside>
            </div>
            <p class="synthetic">Synthetic demo data.</p>
        </main>
    </RosterLayout>
</template>
