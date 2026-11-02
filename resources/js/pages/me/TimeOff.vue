<script setup lang="ts">
import RDatePicker from '@/components/roster/RDatePicker.vue';
import RSelect from '@/components/roster/RSelect.vue';
import { formatDate, isoDate } from '@/composables/useRoster';
import EmployeeAppLayout from '@/layouts/EmployeeAppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { CalendarClock, Lock, Plus } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Balance {
    id: number;
    code: string;
    name: string;
    paid: boolean;
    color: string;
    limited: boolean;
    event_based: boolean;
    half_day_allowed: boolean;
    eligible_from: string | null;
    entitled: number | null;
    carried: number;
    used: number;
    pending: number;
    available: number | null;
}
interface Req {
    id: number;
    type: string;
    color: string;
    start: string;
    end: string;
    original_end: string | null;
    half_day: string;
    days: number;
    status: 'pending' | 'approved' | 'rejected' | 'cancelled';
    reason: string | null;
}

const props = defineProps<{ year: number; balances: Balance[]; requests: Req[]; holidays: { date: string; name: string }[] }>();

const today = isoDate();
const open = ref(false);
const form = useForm({ leave_type_id: (props.balances[0]?.id ?? null) as number | null, start_date: null as string | null, end_date: null as string | null, half_day: 'none', reason: '' });
const type = computed(() => props.balances.find((b) => b.id === form.leave_type_id));
const typeOptions = computed(() => props.balances.map((b) => ({ value: b.id, label: b.name, hint: b.limited ? `${b.available} days left` : b.paid ? 'Paid · no fixed limit' : 'Unpaid' })));
const single = computed(() => form.start_date && form.end_date && form.start_date === form.end_date);

watch(() => form.start_date, (v) => {
    if (v && (!form.end_date || form.end_date < v)) form.end_date = v;
});
watch(single, (v) => {
    if (!v) form.half_day = 'none';
});

/* Live count of working days (weekends and holidays excluded), from the server. */
const quote = ref<{ days: number; by_year: Record<string, number> } | null>(null);
watch(
    () => [form.start_date, form.end_date, form.half_day],
    async () => {
        quote.value = null;
        if (!form.start_date || !form.end_date || form.end_date < form.start_date) return;
        const url = route('me.time-off.quote', { start_date: form.start_date, end_date: form.end_date, half_day: form.half_day });
        const res = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (res.ok) quote.value = await res.json();
    },
);

const holidaysInRange = computed(() =>
    form.start_date && form.end_date ? props.holidays.filter((h) => h.date >= form.start_date! && h.date <= form.end_date!) : [],
);

function submit() {
    form.post(route('me.time-off.store'), {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
            form.reset();
            quote.value = null;
        },
    });
}

function cancel(r: Req) {
    const started = r.status === 'approved' && r.start < today;
    if (!window.confirm(started ? 'This leave has started. Days after today will go back to your balance.' : 'Cancel this request?')) return;
    router.delete(route('me.time-off.destroy', { leave: r.id }), { preserveScroll: true });
}

const fmt = (n: number | null) => (n === null ? '—' : Number.isInteger(n) ? String(n) : n.toFixed(1));
const statusCls = { pending: 'warning', approved: 'success', rejected: 'danger', cancelled: '' } as const;
const range = (r: Req) => (r.start === r.end ? formatDate(r.start) + (r.half_day !== 'none' ? ` · ${r.half_day.toUpperCase()}` : '') : `${formatDate(r.start).replace(/ \d{4}$/, '')} – ${formatDate(r.end)}`);
const cancellable = (r: Req) => r.status === 'pending' || (r.status === 'approved' && r.end >= today);
</script>

<template>
    <Head title="Time off" />
    <EmployeeAppLayout current="time-off">
        <div class="hi row-h">
            <div><h1>Time off</h1><p>{{ year }} balances</p></div>
            <button v-if="!open" type="button" class="btn primary" @click="open = true"><Plus class="icon sm" />Request</button>
        </div>

        <div class="bals">
            <div v-for="b in balances.filter((x) => x.limited && !x.event_based)" :key="b.id" class="bal">
                <span class="nm"><i :style="{ background: b.color }"></i>{{ b.name }}</span>
                <template v-if="b.eligible_from && b.eligible_from > today">
                    <b class="muted"><Lock class="icon sm" /></b>
                    <span class="small muted">Available from {{ formatDate(b.eligible_from) }}</span>
                </template>
                <template v-else>
                    <b class="num">{{ fmt(b.available) }}<small> / {{ fmt((b.entitled ?? 0) + b.carried) }}</small></b>
                    <span class="small muted">{{ fmt(b.used) }} used<template v-if="b.pending"> · {{ fmt(b.pending) }} pending</template></span>
                    <span class="tr"><i :style="{ width: `${Math.min(100, ((b.used + b.pending) / Math.max(1, (b.entitled ?? 0) + b.carried)) * 100)}%`, background: b.color }"></i></span>
                </template>
            </div>
        </div>
        <p class="small muted">
            Also available when needed:
            {{ balances.filter((x) => !x.limited || x.event_based).map((x) => (x.event_based && x.entitled ? `${x.name.toLowerCase()} (${fmt(x.entitled)} days)` : x.name.toLowerCase())).join(', ') }}.
        </p>

        <form v-if="open" class="sx reqform" @submit.prevent="submit">
            <h2>New request</h2>
            <div class="field">
                <label>Type</label>
                <RSelect :model-value="form.leave_type_id" :options="typeOptions" label="Leave type" @update:model-value="(v) => (form.leave_type_id = v === null ? null : Number(v))" />
            </div>
            <div class="two">
                <div class="field"><label>First day</label><RDatePicker v-model="form.start_date" label="First day" :min="type?.eligible_from && type.eligible_from > today ? type.eligible_from : today" /></div>
                <div class="field"><label>Last day</label><RDatePicker v-model="form.end_date" label="Last day" :min="form.start_date ?? today" /></div>
            </div>
            <span v-if="form.errors.start_date" class="err">{{ form.errors.start_date }}</span>
            <span v-if="form.errors.end_date" class="err">{{ form.errors.end_date }}</span>
            <div v-if="single && type?.half_day_allowed" class="field">
                <label>Length</label>
                <div class="seg" role="group" aria-label="Length">
                    <button type="button" :aria-pressed="form.half_day === 'none'" @click="form.half_day = 'none'">Full day</button>
                    <button type="button" :aria-pressed="form.half_day === 'am'" @click="form.half_day = 'am'">Morning</button>
                    <button type="button" :aria-pressed="form.half_day === 'pm'" @click="form.half_day = 'pm'">Afternoon</button>
                </div>
                <span v-if="form.errors.half_day" class="err">{{ form.errors.half_day }}</span>
            </div>
            <div v-if="quote" class="quote">
                <CalendarClock class="icon sm" />
                <span>
                    <b>{{ fmt(quote.days) }} working {{ quote.days === 1 ? 'day' : 'days' }}</b>
                    <template v-if="Object.keys(quote.by_year).length > 1"> ({{ Object.entries(quote.by_year).map(([y, d]) => `${fmt(d)} from ${y}`).join(', ') }})</template>
                    <template v-if="holidaysInRange.length"><br /><span class="small">Not counted: {{ holidaysInRange.map((h) => h.name).join(', ') }} and weekends</span></template>
                </span>
            </div>
            <div class="field">
                <label for="reason">Note for your manager <span class="muted">(optional)</span></label>
                <input id="reason" v-model="form.reason" class="input" maxlength="500" autocomplete="off" placeholder="e.g. Family wedding in Medan" />
            </div>
            <div class="formacts">
                <button type="button" class="btn ghost" @click="open = false">Cancel</button>
                <button type="submit" class="btn primary" :disabled="form.processing || !form.start_date">Send request</button>
            </div>
        </form>

        <section class="wk" aria-labelledby="hist">
            <h2 id="hist">Requests</h2>
            <div v-if="!requests.length" class="dy"><span class="muted">No requests yet.</span></div>
            <div v-for="r in requests" :key="r.id" class="lr">
                <i :style="{ background: r.color }"></i>
                <span class="t">
                    <b>{{ r.type }} · {{ fmt(r.days) }} {{ r.days === 1 ? 'day' : 'days' }}</b>
                    <span>{{ range(r) }}<template v-if="r.original_end"> · shortened from {{ formatDate(r.original_end) }}</template></span>
                </span>
                <span class="badge" :class="statusCls[r.status]">{{ r.status[0].toUpperCase() + r.status.slice(1) }}</span>
                <button v-if="cancellable(r)" type="button" class="linkbtn" @click="cancel(r)">{{ r.status === 'approved' && r.start < today ? 'End early' : 'Cancel' }}</button>
            </div>
        </section>
    </EmployeeAppLayout>
</template>
