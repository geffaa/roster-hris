<script setup lang="ts">
import RDatePicker from '@/components/roster/RDatePicker.vue';
import { humanMinutes } from '@/composables/useRoster';
import { useT } from '@/i18n';
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

const { t, d: fd } = useT();
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

const tone = { ok: 'success', late: 'warning', incomplete: 'danger', corrected: 'info' } as const;
const hm = (m: number) => `${Math.floor(m / 60)}h ${String(m % 60).padStart(2, '0')}m`;
</script>

<template>
    <Head :title="t('att.title')" />
    <EmployeeAppLayout current="attendance">
        <div class="hi row-h">
            <div><h1>{{ t('att.title') }}</h1><p>{{ t('att.last') }}</p></div>
            <button v-if="!otOpen" type="button" class="btn" @click="otOpen = true"><Timer class="icon sm" />{{ t('att.overtime') }}</button>
        </div>

        <form v-if="otOpen" class="sx reqform" @submit.prevent="sendOt">
            <h2>{{ t('att.requestOt') }}</h2>
            <div class="field"><label>{{ t('att.date') }}</label><RDatePicker v-model="ot.work_date" :label="t('att.date')" /></div>
            <span v-if="ot.errors.work_date" class="err">{{ ot.errors.work_date }}</span>
            <div class="two">
                <div class="field"><label for="ots">{{ t('att.from') }}</label><input id="ots" v-model="ot.start_time" class="input num" inputmode="numeric" maxlength="5" placeholder="18:00" /></div>
                <div class="field"><label for="ote">{{ t('att.to') }}</label><input id="ote" v-model="ot.end_time" class="input num" inputmode="numeric" maxlength="5" placeholder="21:00" /></div>
            </div>
            <span v-if="ot.errors.start_time || ot.errors.end_time" class="err">{{ ot.errors.start_time || ot.errors.end_time }}</span>
            <div class="field"><label for="otr">{{ t('att.work') }}</label><input id="otr" v-model="ot.reason" class="input" maxlength="500" autocomplete="off" /></div>
            <span v-if="ot.errors.reason" class="err">{{ ot.errors.reason }}</span>
            <div class="formacts"><button type="button" class="btn ghost" @click="otOpen = false">{{ t('att.cancel') }}</button><button type="submit" class="btn primary" :disabled="ot.processing">{{ t('att.send') }}</button></div>
        </form>

        <section class="wk">
            <div v-for="r in records" :key="r.id" class="att">
                <div class="att-row">
                    <span class="t">
                        <b>{{ fd(r.date, { weekday: 'short', day: 'numeric', month: 'short' }) }}</b>
                        <span class="num">{{ r.in }} – {{ r.out ?? '…' }}<template v-if="r.hours"> · {{ r.hours }} h</template><template v-if="r.remote"> · <House class="icon xs inline" />{{ t('att.remote') }}</template></span>
                        <span v-if="r.status === 'late'" class="small warn">{{ t('att.late', { t: humanMinutes(r.late_min) }) }}</span>
                        <span v-if="r.correction?.status === 'pending'" class="small muted">{{ t('att.corrWaiting') }}</span>
                        <span v-else-if="r.correction?.status === 'rejected'" class="small bad">{{ t('att.corrRejected') }}</span>
                    </span>
                    <span class="badge" :class="tone[r.status]">{{ t(`att.state.${r.status}`) }}</span>
                    <button v-if="r.correction?.status !== 'pending' && fixing !== r.id" type="button" class="linkbtn" @click="startFix(r)"><Wrench class="icon xs" />{{ t('att.fix') }}</button>
                </div>
                <form v-if="fixing === r.id" class="fixform" @submit.prevent="sendFix(r)">
                    <div class="two">
                        <div class="field"><label :for="`in${r.id}`">{{ t('att.actualIn') }}</label><input :id="`in${r.id}`" v-model="fix.in" class="input num" inputmode="numeric" maxlength="5" placeholder="08:30" /></div>
                        <div class="field"><label :for="`out${r.id}`">{{ t('att.actualOut') }}</label><input :id="`out${r.id}`" v-model="fix.out" class="input num" inputmode="numeric" maxlength="5" placeholder="17:30" /></div>
                    </div>
                    <span v-if="fix.errors.in || fix.errors.out" class="err">{{ fix.errors.in || fix.errors.out }}</span>
                    <div class="field"><label :for="`why${r.id}`">{{ t('att.what') }}</label><input :id="`why${r.id}`" v-model="fix.reason" class="input" maxlength="500" autocomplete="off" :placeholder="t('att.whatPlaceholder')" /></div>
                    <span v-if="fix.errors.reason" class="err">{{ fix.errors.reason }}</span>
                    <p class="small muted">{{ t('att.approves') }}</p>
                    <div class="formacts"><button type="button" class="btn ghost sm" @click="fixing = null">{{ t('att.cancel') }}</button><button type="submit" class="btn primary sm" :disabled="fix.processing">{{ t('att.sendFix') }}</button></div>
                </form>
            </div>
        </section>

        <section v-if="overtime.length" class="wk">
            <h2>{{ t('att.overtime') }}</h2>
            <div v-for="o in overtime" :key="o.id" class="att-row pad">
                <span class="t"><b>{{ fd(o.date) }} · {{ hm(o.minutes) }}</b><span class="num">{{ o.start }}–{{ o.end }} · {{ o.reason }}</span></span>
                <span class="badge" :class="{ pending: 'warning', approved: 'success', rejected: 'danger' }[o.status]">{{ t(`timeOff.status.${o.status}`) }}</span>
            </div>
        </section>
    </EmployeeAppLayout>
</template>
