<script setup lang="ts">
import RDatePicker from '@/components/roster/RDatePicker.vue';
import { formatDate, humanMinutes } from '@/composables/useRoster';
import EmployeeAppLayout from '@/layouts/EmployeeAppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { House, Timer, Wrench } from 'lucide-vue-next';
import { ref } from 'vue';

interface Rec {
    id: number;
    date: string;
    in: string;
    out: string | null;
    status: 'ok' | 'late' | 'incomplete' | 'corrected';
    late_min: number;
    remote: boolean;
    hours: number | null;
    correction: { status: string; reason: string } | null;
}
interface Ot {
    id: number;
    date: string;
    start: string;
    end: string;
    minutes: number;
    status: string;
    reason: string;
}

defineProps<{ timezone: string; records: Rec[]; overtime: Ot[] }>();

const fixing = ref<number | null>(null);
const fix = useForm({ in: '', out: '', reason: '' });
function startFix(r: Rec) {
    fixing.value = r.id;
    fix.clearErrors();
    fix.in = r.in;
    fix.out = r.out ?? '';
    fix.reason = '';
}
function sendFix(r: Rec) {
    fix.post(route('me.attendance.correct', { record: r.id }), { preserveScroll: true, onSuccess: () => (fixing.value = null) });
}

const otOpen = ref(false);
const ot = useForm({ work_date: null as string | null, start_time: '', end_time: '', reason: '' });
function sendOt() {
    ot.post(route('me.overtime'), {
        preserveScroll: true,
        onSuccess: () => {
            otOpen.value = false;
            ot.reset();
        },
    });
}

const label = { ok: ['On time', 'success'], late: ['Late', 'warning'], incomplete: ['No clock-out', 'danger'], corrected: ['Corrected', 'info'] } as const;
const hm = (m: number) => `${Math.floor(m / 60)}h ${String(m % 60).padStart(2, '0')}m`;
</script>

<template>
    <Head title="Attendance" />
    <EmployeeAppLayout current="attendance">
        <div class="hi row-h">
            <div><h1>Attendance</h1><p>Last 31 shifts</p></div>
            <button v-if="!otOpen" type="button" class="btn" @click="otOpen = true"><Timer class="icon sm" />Overtime</button>
        </div>

        <form v-if="otOpen" class="sx reqform" @submit.prevent="sendOt">
            <h2>Request overtime</h2>
            <div class="field"><label>Date</label><RDatePicker v-model="ot.work_date" label="Overtime date" /></div>
            <span v-if="ot.errors.work_date" class="err">{{ ot.errors.work_date }}</span>
            <div class="two">
                <div class="field"><label for="ots">From</label><input id="ots" v-model="ot.start_time" class="input num" inputmode="numeric" maxlength="5" placeholder="18:00" /></div>
                <div class="field"><label for="ote">To</label><input id="ote" v-model="ot.end_time" class="input num" inputmode="numeric" maxlength="5" placeholder="21:00" /></div>
            </div>
            <span v-if="ot.errors.start_time || ot.errors.end_time" class="err">{{ ot.errors.start_time || ot.errors.end_time }}</span>
            <div class="field"><label for="otr">What was the work?</label><input id="otr" v-model="ot.reason" class="input" maxlength="500" autocomplete="off" /></div>
            <span v-if="ot.errors.reason" class="err">{{ ot.errors.reason }}</span>
            <div class="formacts"><button type="button" class="btn ghost" @click="otOpen = false">Cancel</button><button type="submit" class="btn primary" :disabled="ot.processing">Send</button></div>
        </form>

        <section class="wk">
            <div v-for="r in records" :key="r.id" class="att">
                <div class="att-row">
                    <span class="t">
                        <b>{{ formatDate(r.date) }}</b>
                        <span class="num">{{ r.in }} – {{ r.out ?? '…' }}<template v-if="r.hours"> · {{ r.hours }} h</template><template v-if="r.remote"> · <House class="icon xs inline" />remote</template></span>
                        <span v-if="r.status === 'late'" class="small warn">{{ humanMinutes(r.late_min) }} late</span>
                        <span v-if="r.correction?.status === 'pending'" class="small muted">Correction waiting for approval</span>
                        <span v-else-if="r.correction?.status === 'rejected'" class="small bad">Correction rejected</span>
                    </span>
                    <span class="badge" :class="label[r.status][1]">{{ label[r.status][0] }}</span>
                    <button v-if="r.correction?.status !== 'pending' && fixing !== r.id" type="button" class="linkbtn" @click="startFix(r)"><Wrench class="icon xs" />Fix</button>
                </div>
                <form v-if="fixing === r.id" class="fixform" @submit.prevent="sendFix(r)">
                    <div class="two">
                        <div class="field"><label :for="`in${r.id}`">Actual in</label><input :id="`in${r.id}`" v-model="fix.in" class="input num" inputmode="numeric" maxlength="5" placeholder="08:30" /></div>
                        <div class="field"><label :for="`out${r.id}`">Actual out</label><input :id="`out${r.id}`" v-model="fix.out" class="input num" inputmode="numeric" maxlength="5" placeholder="17:30" /></div>
                    </div>
                    <span v-if="fix.errors.in || fix.errors.out" class="err">{{ fix.errors.in || fix.errors.out }}</span>
                    <div class="field"><label :for="`why${r.id}`">What happened?</label><input :id="`why${r.id}`" v-model="fix.reason" class="input" maxlength="500" autocomplete="off" placeholder="e.g. Phone battery died before clock-out" /></div>
                    <span v-if="fix.errors.reason" class="err">{{ fix.errors.reason }}</span>
                    <p class="small muted">Your manager approves it. The original times stay in the record history.</p>
                    <div class="formacts"><button type="button" class="btn ghost sm" @click="fixing = null">Cancel</button><button type="submit" class="btn primary sm" :disabled="fix.processing">Send correction</button></div>
                </form>
            </div>
        </section>

        <section v-if="overtime.length" class="wk">
            <h2>Overtime</h2>
            <div v-for="o in overtime" :key="o.id" class="att-row pad">
                <span class="t"><b>{{ formatDate(o.date) }} · {{ hm(o.minutes) }}</b><span class="num">{{ o.start }}–{{ o.end }} · {{ o.reason }}</span></span>
                <span class="badge" :class="{ pending: 'warning', approved: 'success', rejected: 'danger' }[o.status]">{{ o.status[0].toUpperCase() + o.status.slice(1) }}</span>
            </div>
        </section>
    </EmployeeAppLayout>
</template>
