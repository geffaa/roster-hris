<script setup lang="ts">
import RSelect from '@/components/roster/RSelect.vue';
import { formatDate, formatDateTime, isoDate, monthLabel, plainMoney } from '@/composables/useRoster';
import RosterLayout from '@/layouts/RosterLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Check, ChevronRight, CircleAlert, Copy, Lock, Send, Trash2, X } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';

type Band = [number | null, number];
interface Contribution {
    code: string;
    name: string;
    employer: number;
    employee: number;
    cap: number | null;
    exempt_below: number;
    taxable_employer: boolean;
    deductible_employee: boolean;
}
interface Config {
    method: string;
    currency: string;
    decimals: number;
    ptkp?: { base: number; married: number; per_dependant: number; max_dependants: number };
    ter_categories?: Record<string, string[]>;
    ter?: Record<string, Band[]>;
    art17?: Band[];
    position_cost?: { rate: number; monthly_cap: number };
    allowance_annual?: number;
    bands_annual?: Band[];
    contributions: Contribution[];
    overtime?: { hourly_divisor: number; workday: Band[]; restday: Band[] };
    thr?: { min_service_months: number; full_after_months: number } | null;
}
type Source = { note: string; verified: string; verify?: boolean };
interface VersionCard {
    id: number;
    version: string;
    status: string;
    state: 'active' | 'superseded' | 'scheduled' | 'draft' | 'pending' | 'rejected';
    effective_from: string;
    until: string | null;
    runs: number;
}
const props = defineProps<{
    pack: { id: number; code: string; name: string; method: string; country: string | null };
    versions: VersionCard[];
    version: {
        id: number;
        version: string;
        status: string;
        effective_from: string;
        config: Config;
        sources: Record<string, Source>;
        change_note: string | null;
        rejected_reason: string | null;
        based_on: string | null;
        base_config: Config | null;
        author: string | null;
        author_id: number | null;
        approver: string | null;
        submitted_at: string | null;
        approved_at: string | null;
        created_at: string | null;
        editable: boolean;
    };
    diff: { key: string; label: string; from: unknown; to: unknown }[];
    unsourced: string[];
    active: number | null;
    can: { manage: boolean; approve: boolean };
    today: string;
}>();

const page = usePage();
const errors = computed(() => (page.props.errors ?? {}) as Record<string, string>);
const isId = computed(() => props.pack.method === 'ter_monthly_with_december_true_up');
const editing = computed(() => props.version.editable && props.can.manage);
const dec = computed(() => props.version.config.decimals);
const money = (v: number | null | undefined) => (v === null || v === undefined ? '—' : plainMoney(v, dec.value));

// Local editable copy; saved explicitly.
const form = reactive({
    effective_from: props.version.effective_from,
    config: JSON.parse(JSON.stringify(props.version.config)) as Config,
    sources: {} as Record<string, Source>,
    change_note: props.version.change_note ?? '',
});
watch(
    () => props.version,
    (v) => {
        form.effective_from = v.effective_from;
        form.config = JSON.parse(JSON.stringify(v.config));
        form.sources = {};
        form.change_note = v.change_note ?? '';
    },
);
const dirty = computed(() => JSON.stringify(form.config) !== JSON.stringify(props.version.config) || form.effective_from !== props.version.effective_from || form.change_note !== (props.version.change_note ?? '') || Object.keys(form.sources).length > 0);

const base = computed(() => props.version.base_config);
const baseC = (code: string) => base.value?.contributions.find((c) => c.code === code);
const changedC = (c: Contribution, f: keyof Contribution) => base.value !== null && baseC(c.code)?.[f] != c[f];
const source = (key: string) => form.sources[key] ?? props.version.sources[key];
function setSource(key: string, note: string) {
    form.sources[key] = { note, verified: isoDate() };
}
const needsSource = (key: string) => props.unsourced.includes(key);

function num(v: string): number | null {
    const t = v.replace(/[,\s]/g, '');
    if (t === '') return null;
    const n = Number(t);
    return Number.isFinite(n) ? n : NaN;
}

const tabs = computed(() =>
    isId.value
        ? [
              { id: 'tax', label: 'Income tax' },
              { id: 'ptkp', label: 'PTKP' },
              { id: 'bpjs', label: 'BPJS' },
              { id: 'overtime', label: 'Overtime' },
              { id: 'thr', label: 'THR' },
          ]
        : [
              { id: 'tax', label: 'Income tax' },
              { id: 'bpjs', label: 'Contributions' },
              { id: 'overtime', label: 'Overtime' },
          ],
);
const firstTab = () => (props.diff.length ? (props.diff[0].key.startsWith('contributions') ? 'bpjs' : props.diff[0].key === 'ptkp' ? 'ptkp' : 'tax') : 'tax');
const tab = ref(firstTab());
watch(() => props.version.id, () => (tab.value = firstTab()));
const changesIn = (id: string) =>
    props.diff.filter((d) => (id === 'bpjs' ? d.key.startsWith('contributions') : id === 'tax' ? ['art17', 'position_cost', 'bands_annual', 'allowance_annual'].includes(d.key) : d.key === id)).length;
const terCat = ref('A');

const ptkpFor = (status: string) => {
    const p = form.config.ptkp!;
    const [m, d] = status.split('/');
    return p.base + (m === 'K' ? p.married : 0) + Math.min(Number(d), p.max_dependants) * p.per_dependant;
};
const statuses = ['TK/0', 'TK/1', 'TK/2', 'TK/3', 'K/0', 'K/1', 'K/2', 'K/3'];
const catOf = (s: string) => Object.entries(form.config.ter_categories ?? {}).find(([, list]) => list.includes(s))?.[0] ?? '—';

const monthOptions = computed(() => {
    const out = [];
    const d = new Date(`${props.today.slice(0, 7)}-01T00:00:00`);
    for (let i = 1; i <= 15; i++) {
        const m = new Date(d.getFullYear(), d.getMonth() + i, 1);
        const iso = isoDate(m);
        out.push({ value: iso, label: new Intl.DateTimeFormat('en-GB', { weekday: 'short', day: 'numeric', month: 'long', year: 'numeric' }).format(m) });
    }
    if (!out.some((o) => o.value === form.effective_from)) out.unshift({ value: form.effective_from, label: formatDate(form.effective_from) });
    return out;
});

const busy = ref(false);
const done = { preserveScroll: true, onFinish: () => (busy.value = false) };
function save(then?: () => void) {
    busy.value = true;
    router.put(route('rule-packs.update', { version: props.version.id }), { ...form }, { ...done, onSuccess: () => then?.() });
}
function submit() {
    const send = () => {
        busy.value = true;
        router.post(route('rule-packs.submit', { version: props.version.id }), {}, done);
    };
    dirty.value ? save(send) : send();
}
const act = (name: string, data: Record<string, string> = {}) => {
    busy.value = true;
    router.post(route(name, { version: props.version.id }), data, done);
};
const rejecting = ref(false);
const reason = ref('');

const stateMeta: Record<VersionCard['state'], { label: string; cls: string }> = {
    active: { label: 'Active', cls: 'success' },
    scheduled: { label: 'Scheduled', cls: 'info' },
    superseded: { label: 'Superseded', cls: '' },
    draft: { label: 'Draft', cls: 'warning' },
    pending: { label: 'Waiting for approval', cls: 'warning' },
    rejected: { label: 'Sent back', cls: 'danger' },
};
const keyLabel = (key: string) =>
    key.startsWith('contributions.')
        ? (props.version.config.contributions.find((c) => `contributions.${c.code}` === key)?.name ?? key)
        : (({ ptkp: 'PTKP', position_cost: 'biaya jabatan', art17: 'the Article 17 bands', overtime: 'overtime', thr: 'THR', bands_annual: 'the tax bands', allowance_annual: 'the allowance' }) as Record<string, string>)[key] ?? key;
const fmtVal = (v: unknown) => (v === null || v === undefined ? 'none' : typeof v === 'number' ? plainMoney(v, v % 1 ? 2 : 0) : Array.isArray(v) ? `${v.length} bands` : String(v));
</script>

<template>
    <Head :title="`${pack.name} rule pack`" />
    <RosterLayout current="Rule packs">
        <main class="page">
            <nav class="crumbs" aria-label="Breadcrumb">
                <Link :href="route('payroll.index')">Payroll</Link><ChevronRight class="icon xs" /><span>Rule packs</span><ChevronRight class="icon xs" /><span>{{ pack.name }}</span>
            </nav>
            <div class="phead">
                <div>
                    <h1>{{ pack.name }} rule pack</h1>
                    <p v-if="isId">Tax, BPJS, THR and overtime rules for companies in Indonesia. Every change is a new dated version; past payroll keeps the version it used.</p>
                    <p v-else>Illustrative progressive tax and contributions for companies without a country pack. Replace the numbers before relying on them.</p>
                </div>
                <div class="acts">
                    <button v-if="can.manage && version.status === 'approved'" type="button" class="btn" :disabled="busy" @click="act('rule-packs.draft')">
                        <Copy class="icon sm" />Start a new version from {{ version.version }}
                    </button>
                </div>
            </div>
            <nav class="modtabs" aria-label="Pay">
                <Link :href="route('payroll.index')">Payroll runs</Link>
                <Link :href="route('rule-packs.show')" aria-current="page">Rule packs</Link>
                <a aria-disabled="true">Claims <span class="soon">Soon</span></a>
            </nav>

            <div class="vcards">
                <Link v-for="v in versions" :key="v.id" :href="route('rule-packs.show', { version: v.id })" class="vcard" :class="[v.state, { on: v.id === version.id }]" :aria-current="v.id === version.id ? 'page' : undefined">
                    <div class="vc-h"><b>{{ v.version }}</b><span class="badge" :class="stateMeta[v.state].cls">{{ stateMeta[v.state].label }}</span></div>
                    <p class="small muted">
                        <template v-if="v.until">{{ formatDate(v.effective_from).replace(/ \d{4}$/, '') }} – {{ formatDate(v.until) }}</template>
                        <template v-else-if="v.state === 'active'">From {{ formatDate(v.effective_from) }}</template>
                        <template v-else>Starts {{ formatDate(v.effective_from) }}</template>
                        <template v-if="v.runs"> · {{ v.runs }} {{ v.runs === 1 ? 'run' : 'runs' }}</template>
                    </p>
                </Link>
            </div>

            <div class="rpwrap">
                <section class="card">
                    <nav class="subtabs rptabs" aria-label="Sections">
                        <a v-for="t in tabs" :key="t.id" href="#" :aria-current="tab === t.id ? 'true' : undefined" @click.prevent="tab = t.id">
                            {{ t.label }}<span v-if="changesIn(t.id)" class="ct warn">{{ changesIn(t.id) }} {{ changesIn(t.id) === 1 ? 'change' : 'changes' }}</span>
                        </a>
                    </nav>
                    <div class="card-b rp-b">
                        <div class="note lockn">
                            <Lock class="icon sm" />
                            <span v-if="isId"><b>The method stays fixed in code:</b> monthly TER January–November, yearly recalculation with Article 17 in December or the month someone leaves. Only the numbers below change.</span>
                            <span v-else><b>The method stays fixed in code:</b> each month's pay is annualised, taxed on the bands above the allowance, and divided by 12.</span>
                        </div>

                        <!-- Income tax -->
                        <template v-if="tab === 'tax'">
                            <h3 class="rp-h">{{ isId ? 'Article 17 rates (December recalculation)' : 'Tax bands (annual, above the allowance)' }}</h3>
                            <table class="t rpt">
                                <thead><tr><th>Taxable income up to</th><th class="n">Rate %</th></tr></thead>
                                <tbody>
                                    <tr v-for="(b, i) in (isId ? form.config.art17! : form.config.bands_annual!)" :key="i">
                                        <td>
                                            <input v-if="editing && b[0] !== null" class="input num" :value="b[0]" :aria-label="`Band ${i + 1} upper bound`" inputmode="numeric" @change="b[0] = num(($event.target as HTMLInputElement).value)" />
                                            <span v-else class="num">{{ b[0] === null ? 'and above' : money(b[0]) }}</span>
                                        </td>
                                        <td class="n">
                                            <input v-if="editing" class="input num rate" :value="b[1]" :aria-label="`Band ${i + 1} rate`" inputmode="decimal" @change="b[1] = num(($event.target as HTMLInputElement).value) ?? 0" />
                                            <span v-else class="num">{{ b[1] }}%</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <p class="small muted src">{{ source(isId ? 'art17' : 'bands_annual')?.note }} · checked {{ formatDate(source(isId ? 'art17' : 'bands_annual')?.verified) }}</p>

                            <template v-if="isId">
                                <h3 class="rp-h">Biaya jabatan</h3>
                                <div class="two">
                                    <label class="field"><span>Rate %</span><input class="input num" :disabled="!editing" :value="form.config.position_cost!.rate" @change="form.config.position_cost!.rate = num(($event.target as HTMLInputElement).value) ?? 0" /></label>
                                    <label class="field"><span>Monthly cap (Rp)</span><input class="input num" :disabled="!editing" :value="money(form.config.position_cost!.monthly_cap)" @change="form.config.position_cost!.monthly_cap = num(($event.target as HTMLInputElement).value) ?? 0" /></label>
                                </div>
                                <p class="small muted src">{{ source('position_cost')?.note }} · checked {{ formatDate(source('position_cost')?.verified) }}</p>

                                <h3 class="rp-h">TER monthly rates <span class="small muted">· read-only, PP 58/2023</span></h3>
                                <div class="seg" role="group" aria-label="TER category">
                                    <button v-for="c in ['A', 'B', 'C']" :key="c" type="button" :aria-pressed="terCat === c" @click="terCat = c">Category {{ c }} · {{ form.config.ter_categories![c].join(', ') }}</button>
                                </div>
                                <div class="terwrap">
                                    <table class="t rpt ter">
                                        <thead><tr><th>Monthly gross from</th><th>up to</th><th class="n">TER</th></tr></thead>
                                        <tbody>
                                            <tr v-for="(r, i) in form.config.ter![terCat]" :key="i">
                                                <td class="num">{{ i === 0 ? '0' : money((form.config.ter![terCat][i - 1][0] as number) + 1) }}</td>
                                                <td class="num">{{ r[0] === null ? 'and above' : money(r[0]) }}</td>
                                                <td class="n num">{{ r[1] }}%</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <p class="small muted src">{{ source('ter')?.note }} · checked {{ formatDate(source('ter')?.verified) }}</p>
                            </template>
                            <template v-else>
                                <h3 class="rp-h">Tax-free allowance</h3>
                                <label class="field narrow"><span>Per year</span><input class="input num" :disabled="!editing" :value="money(form.config.allowance_annual)" @change="form.config.allowance_annual = num(($event.target as HTMLInputElement).value) ?? 0" /></label>
                            </template>
                        </template>

                        <!-- PTKP -->
                        <template v-if="tab === 'ptkp' && form.config.ptkp">
                            <h3 class="rp-h">Non-taxable income per year</h3>
                            <div class="four">
                                <label v-for="(label, k) in { base: 'Taxpayer', married: 'Married', per_dependant: 'Per dependant', max_dependants: 'Max dependants' }" :key="k" class="field">
                                    <span>{{ label }}</span>
                                    <input class="input num" :disabled="!editing" :value="k === 'max_dependants' ? form.config.ptkp[k] : money(form.config.ptkp[k])" @change="form.config.ptkp[k] = num(($event.target as HTMLInputElement).value) ?? 0" />
                                </label>
                            </div>
                            <table class="t rpt">
                                <thead><tr><th>Status</th><th class="n">PTKP per year</th><th>TER category</th></tr></thead>
                                <tbody><tr v-for="s in statuses" :key="s"><td>{{ s }}</td><td class="n num">{{ money(ptkpFor(s)) }}</td><td>{{ catOf(s) }}</td></tr></tbody>
                            </table>
                            <p class="small muted src">{{ source('ptkp')?.note }} · checked {{ formatDate(source('ptkp')?.verified) }}</p>
                        </template>

                        <!-- Contributions -->
                        <template v-if="tab === 'bpjs'">
                            <div class="tablewrap">
                                <table class="t rpt contrib">
                                    <thead><tr><th>Programme</th><th class="n">Employer %</th><th class="n">Employee %</th><th class="n">Wage cap</th><th>Source</th></tr></thead>
                                    <tbody>
                                        <tr v-for="(c, i) in form.config.contributions" :key="c.code">
                                            <td><b>{{ c.name }}</b><span class="small muted block">{{ c.taxable_employer ? 'Employer part taxable' : c.deductible_employee ? 'Reduces yearly tax' : c.exempt_below ? `Above ${money(c.exempt_below)}/month` : ' ' }}</span></td>
                                            <td class="n">
                                                <input v-if="editing" class="input num rate" :class="{ changed: changedC(c, 'employer'), invalid: errors[`config.contributions.${i}.employer`] }" :value="c.employer" :aria-label="`${c.name} employer rate`" inputmode="decimal" @change="c.employer = num(($event.target as HTMLInputElement).value) ?? 0" />
                                                <span v-else class="num" :class="{ chg: changedC(c, 'employer') }">{{ c.employer ? c.employer.toFixed(2) : '—' }}</span>
                                            </td>
                                            <td class="n">
                                                <input v-if="editing" class="input num rate" :class="{ changed: changedC(c, 'employee'), invalid: errors[`config.contributions.${i}.employee`] }" :value="c.employee" :aria-label="`${c.name} employee rate`" inputmode="decimal" @change="c.employee = num(($event.target as HTMLInputElement).value) ?? 0" />
                                                <span v-else class="num" :class="{ chg: changedC(c, 'employee') }">{{ c.employee ? c.employee.toFixed(2) : '—' }}</span>
                                            </td>
                                            <td class="n">
                                                <input v-if="editing" class="input num cap" :class="{ changed: changedC(c, 'cap'), invalid: errors[`config.contributions.${i}.cap`] }" :value="c.cap === null ? '' : money(c.cap)" placeholder="No cap" :aria-label="`${c.name} wage cap`" inputmode="numeric" @change="c.cap = num(($event.target as HTMLInputElement).value)" />
                                                <span v-else class="num" :class="{ chg: changedC(c, 'cap') }">{{ c.cap === null ? 'No cap' : money(c.cap) }}</span>
                                            </td>
                                            <td class="srccell" :class="{ bad: needsSource(`contributions.${c.code}`) && !form.sources[`contributions.${c.code}`] }">
                                                <template v-if="editing && (changedC(c, 'employer') || changedC(c, 'employee') || changedC(c, 'cap'))">
                                                    <textarea class="input srcin" rows="2" :value="form.sources[`contributions.${c.code}`]?.note ?? (needsSource(`contributions.${c.code}`) ? '' : source(`contributions.${c.code}`)?.note)" placeholder="Regulation or notice behind this change" :aria-label="`Source for ${c.name}`" @change="setSource(`contributions.${c.code}`, ($event.target as HTMLTextAreaElement).value)" />
                                                </template>
                                                <template v-else>
                                                    <span :class="{ warn: source(`contributions.${c.code}`)?.verify }">{{ source(`contributions.${c.code}`)?.note }}</span>
                                                    <span class="muted"> · checked {{ formatDate(source(`contributions.${c.code}`)?.verified) }}</span>
                                                </template>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </template>

                        <!-- Overtime -->
                        <template v-if="tab === 'overtime' && form.config.overtime">
                            <label class="field narrow"><span>Hourly rate = monthly wage ÷</span><input class="input num" :disabled="!editing" :value="form.config.overtime.hourly_divisor" @change="form.config.overtime.hourly_divisor = num(($event.target as HTMLInputElement).value) ?? 173" /></label>
                            <div class="two">
                                <div>
                                    <h3 class="rp-h">On a working day</h3>
                                    <ul class="tiers"><li v-for="(t, i) in form.config.overtime.workday" :key="i"><span>{{ t[0] === null ? (i ? 'Every hour after' : 'Every hour') : i === 0 ? `First ${t[0] === 1 ? 'hour' : t[0] + ' hours'}` : `Up to hour ${t[0]}` }}</span><b>{{ t[1] }}×</b></li></ul>
                                </div>
                                <div>
                                    <h3 class="rp-h">On a rest day or public holiday</h3>
                                    <ul class="tiers"><li v-for="(t, i) in form.config.overtime.restday" :key="i"><span>{{ t[0] === null ? (i ? 'Every hour after' : 'Every hour') : i === 0 ? `Hours 1–${t[0]}` : `Hour ${t[0]}` }}</span><b>{{ t[1] }}×</b></li></ul>
                                </div>
                            </div>
                            <p class="small muted src">{{ source('overtime')?.note }} · checked {{ formatDate(source('overtime')?.verified) }}</p>
                        </template>

                        <!-- THR -->
                        <template v-if="tab === 'thr' && form.config.thr">
                            <div class="two">
                                <label class="field"><span>Paid from (months of service)</span><input class="input num" :disabled="!editing" :value="form.config.thr.min_service_months" @change="form.config.thr.min_service_months = num(($event.target as HTMLInputElement).value) ?? 1" /></label>
                                <label class="field"><span>One full month of wage after (months)</span><input class="input num" :disabled="!editing" :value="form.config.thr.full_after_months" @change="form.config.thr.full_after_months = num(($event.target as HTMLInputElement).value) ?? 12" /></label>
                            </div>
                            <p class="small muted">Under {{ form.config.thr.full_after_months }} months, THR is months of service ÷ {{ form.config.thr.full_after_months }} × monthly wage (base + fixed allowances). Payroll adds it when a run is started with "Include THR".</p>
                            <p class="small muted src">{{ source('thr')?.note }} · checked {{ formatDate(source('thr')?.verified) }}</p>
                        </template>
                    </div>
                </section>

                <aside class="card vpanel">
                    <div class="card-h">
                        <h2>{{ version.status === 'approved' ? version.version : `${version.status === 'pending' ? 'Pending' : 'Draft'} ${version.version}` }}</h2>
                        <span class="badge" :class="stateMeta[versions.find((v) => v.id === version.id)?.state ?? 'draft'].cls">{{ stateMeta[versions.find((v) => v.id === version.id)?.state ?? 'draft'].label }}</span>
                    </div>
                    <div class="card-b">
                        <div v-if="editing" class="field">
                            <span>Takes effect from</span>
                            <RSelect v-model="form.effective_from as any" :options="monthOptions" label="Takes effect from" />
                            <small v-if="errors.effective_from" class="err">{{ errors.effective_from }}</small>
                            <small v-else class="muted">Payroll for {{ monthLabel(form.effective_from) }} onwards uses this version; earlier months keep theirs.</small>
                        </div>
                        <dl v-else class="kv tight">
                            <div><dt>Takes effect</dt><dd>{{ formatDate(version.effective_from) }}</dd></div>
                            <div v-if="version.based_on"><dt>Based on</dt><dd>{{ version.based_on }}</dd></div>
                        </dl>

                        <div v-if="diff.length" class="diff">
                            <div v-for="(d, i) in diff" :key="i" class="drow">
                                <b>{{ d.label }}</b>
                                <span class="num">{{ fmtVal(d.from) }} → {{ fmtVal(d.to) }}</span>
                            </div>
                        </div>
                        <p v-else-if="version.based_on" class="small muted">No changes from {{ version.based_on }} yet.</p>

                        <label v-if="editing" class="field">
                            <span>Why the numbers change</span>
                            <textarea v-model="form.change_note" class="input" rows="3" placeholder="e.g. JP wage cap for 2027 announced by BPJS Ketenagakerjaan" />
                            <small v-if="errors.change_note" class="err">{{ errors.change_note }}</small>
                        </label>
                        <p v-else-if="version.change_note" class="small">{{ version.change_note }}</p>

                        <ol class="flow">
                            <li class="done"><Check class="icon xs" />Drafted by {{ version.author ?? 'someone' }}<template v-if="version.created_at">, {{ formatDate(version.created_at) }}</template></li>
                            <li :class="{ done: !!version.approver, current: version.status === 'pending' }">
                                <Check v-if="version.approver" class="icon xs" /><span v-else class="circ" />
                                <template v-if="version.approver">Approved by {{ version.approver }}, {{ formatDateTime(version.approved_at!) }}</template>
                                <template v-else>Second approver: anyone with approval rights except {{ version.author ?? 'the author' }}</template>
                            </li>
                            <li :class="{ done: version.status === 'approved' && version.effective_from <= today }">
                                <Check v-if="version.status === 'approved' && version.effective_from <= today" class="icon xs" /><span v-else class="circ" />{{ version.status === 'approved' && version.effective_from <= today ? 'In force since' : 'In force from' }} {{ formatDate(version.effective_from) }}
                            </li>
                        </ol>

                        <div v-if="version.rejected_reason" class="note danger small"><X class="icon sm" /><span><b>Sent back:</b> {{ version.rejected_reason }}</span></div>
                        <div v-if="editing && (unsourced.length || Object.keys(errors).length)" class="note danger small">
                            <CircleAlert class="icon sm" />
                            <span>
                                <b>Before sending:</b>
                                <template v-if="unsourced.length"> add the source behind {{ unsourced.map(keyLabel).join(', ') }}.</template>
                                <template v-for="(e, k) in errors" :key="k"> {{ e }}</template>
                            </span>
                        </div>

                        <div v-if="editing" class="vacts">
                            <button type="button" class="btn primary" :disabled="busy" @click="submit"><Send class="icon sm" />Send for approval</button>
                            <button type="button" class="btn" :disabled="busy || !dirty" @click="save()">Save draft</button>
                            <button type="button" class="btn ghost danger" :disabled="busy" @click="act('rule-packs.discard')"><Trash2 class="icon sm" />Discard</button>
                        </div>
                        <div v-else-if="version.status === 'pending'" class="vacts">
                            <template v-if="can.approve && !rejecting">
                                <button type="button" class="btn primary" :disabled="busy" @click="act('rule-packs.approve')"><Check class="icon sm" />Approve</button>
                                <button type="button" class="btn" :disabled="busy" @click="rejecting = true">Send back</button>
                            </template>
                            <form v-else-if="rejecting" class="stack" @submit.prevent="act('rule-packs.reject', { reason })">
                                <label class="field"><span>What needs to change</span><textarea v-model="reason" class="input" rows="2" required /></label>
                                <div class="vacts"><button type="submit" class="btn primary" :disabled="busy || !reason">Send back</button><button type="button" class="btn ghost" @click="rejecting = false">Cancel</button></div>
                            </form>
                            <p v-else class="small muted">Waiting for someone other than {{ version.author }} to approve.</p>
                        </div>
                    </div>
                </aside>
            </div>
            <p class="synthetic">Rates come from the regulations cited next to each number. They are maintained for a portfolio demo; verify them before running real payroll.</p>
        </main>
    </RosterLayout>
</template>
