<script setup lang="ts">
import Avatar from '@/components/roster/Avatar.vue';
import RDatePicker from '@/components/roster/RDatePicker.vue';
import { addDays, formatDate, humanMinutes } from '@/composables/useRoster';
import RosterLayout from '@/layouts/RosterLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { CalendarDays, ChevronLeft, ChevronRight, House, PartyPopper } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

type State = 'present' | 'late' | 'incomplete' | 'corrected' | 'leave' | 'holiday' | 'off' | 'absent' | 'not_in';
interface Row {
    id: number;
    name: string;
    title: string;
    department: string | null;
    state: State;
    in: string | null;
    out: string | null;
    late_min: number | null;
    remote: boolean | null;
    leave: string | null;
    shift: string | null;
}
const props = defineProps<{ date: string; today: string; holiday: string | null; rows: Row[]; summary: Partial<Record<State, number>>; scope: 'company' | 'team' }>();

const picked = ref<string | null>(props.date);
watch(picked, (v) => v && v !== props.date && go(v));
function go(date: string) {
    router.get(route('attendance'), { date }, { preserveScroll: true });
}
function shift(days: number) {
    go(addDays(props.date, days));
}

const meta: Record<State, { label: string; cls: string }> = {
    present: { label: 'On time', cls: 'success' },
    late: { label: 'Late', cls: 'warning' },
    incomplete: { label: 'No clock-out', cls: 'danger' },
    corrected: { label: 'Corrected', cls: 'info' },
    leave: { label: 'On leave', cls: 'info' },
    holiday: { label: 'Holiday', cls: '' },
    off: { label: 'Day off', cls: '' },
    absent: { label: 'No record', cls: 'danger' },
    not_in: { label: 'Not in yet', cls: '' },
};
const chips: State[] = ['present', 'late', 'incomplete', 'leave', 'absent', 'not_in'];
const filter = ref<State | null>(null);
const shown = computed(() => (filter.value ? props.rows.filter((r) => r.state === filter.value) : props.rows));
</script>

<template>
    <Head title="Attendance" />
    <RosterLayout current="Attendance">
        <main class="page">
            <div class="phead">
                <div>
                    <h1>{{ scope === 'team' ? 'Team attendance' : 'Attendance' }}</h1>
                    <p>{{ formatDate(date) }}<template v-if="date === today"> · today</template></p>
                </div>
                <div class="acts">
                    <div class="datenav">
                        <button type="button" class="ibtn" aria-label="Previous day" @click="shift(-1)"><ChevronLeft class="icon" /></button>
                        <div class="fsel"><RDatePicker v-model="picked" label="Pick a day" /></div>
                        <button type="button" class="ibtn" aria-label="Next day" :disabled="date >= today" @click="shift(1)"><ChevronRight class="icon" /></button>
                    </div>
                    <Link class="btn" :href="route('leave-calendar')"><CalendarDays class="icon sm" />Leave calendar</Link>
                </div>
            </div>

            <div v-if="holiday" class="note info" style="margin-bottom: var(--s5)"><PartyPopper class="icon sm" /><span><b>{{ holiday }}.</b> Public holiday; nobody is expected to clock in.</span></div>

            <section class="card">
                <div class="filters">
                    <button type="button" class="fchip" :aria-pressed="filter === null" @click="filter = null">Everyone <b>{{ rows.length }}</b></button>
                    <template v-for="c in chips" :key="c">
                        <button v-if="summary[c]" type="button" class="fchip" :class="meta[c].cls" :aria-pressed="filter === c" @click="filter = filter === c ? null : c">
                            {{ meta[c].label }} <b>{{ summary[c] }}</b>
                        </button>
                    </template>
                </div>
                <div class="tablewrap">
                    <table class="t dir">
                        <thead><tr><th>Person</th><th>Department</th><th>Shift</th><th>In</th><th>Out</th><th>Status</th></tr></thead>
                        <tbody>
                            <tr v-for="r in shown" :key="r.id">
                                <td><div class="who"><Avatar :name="r.name" /><div><Link :href="route('employees.show', { employee: r.id })" class="name">{{ r.name }}</Link><span>{{ r.title }}</span></div></div></td>
                                <td>{{ r.department ?? '—' }}</td>
                                <td class="num muted">{{ r.shift ?? '—' }}</td>
                                <td class="num">{{ r.in ?? '—' }} <House v-if="r.remote" class="icon xs inline muted" aria-label="remote" /></td>
                                <td class="num">{{ r.out ?? '—' }}</td>
                                <td>
                                    <span class="badge" :class="meta[r.state].cls">{{ meta[r.state].label }}<template v-if="r.state === 'late' && r.late_min"> · {{ humanMinutes(r.late_min) }}</template><template v-if="r.state === 'leave'"> · {{ r.leave }}</template></span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
            <p class="synthetic">Synthetic demo data. Times use the company time zone and the server clock.</p>
        </main>
    </RosterLayout>
</template>
