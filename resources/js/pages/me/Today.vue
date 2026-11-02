<script setup lang="ts">
import { formatDate, humanMinutes } from '@/composables/useRoster';
import EmployeeAppLayout from '@/layouts/EmployeeAppLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { CalendarDays, CircleAlert, CloudOff, LocateFixed, LogIn, LogOut, MapPin, MapPinOff, PartyPopper, Plane, Timer, Wrench } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';

interface Day {
    date: string;
    label: string;
    working: boolean;
    holiday: string | null;
    in: string | null;
    out: string | null;
    status: string | null;
    late_min: number | null;
    future: boolean;
}

const props = defineProps<{
    now: string;
    timezone: string;
    employee: { first_name: string; job_title: string };
    shift: { label: string; start: string; end: string; grace: number; working_today: boolean } | null;
    holiday: string | null;
    location: { name: string; lat: number | null; lng: number | null; radius: number; remote: boolean } | null;
    record: { in: string; out: string | null; status: string; late_min: number } | null;
    week: Day[];
    incomplete: { id: number; date: string } | null;
    annual: { available: number | null; used: number; pending: number } | null;
}>();

const page = usePage<{ errors: Record<string, string> }>();

/* Server time, advanced locally; the server re-checks the time on every tap. */
const offset = new Date(props.now).getTime() - Date.now();
const tick = ref(Date.now() + offset);
let timer: ReturnType<typeof setInterval> | undefined;
const clock = computed(() => new Intl.DateTimeFormat('en-GB', { hour: '2-digit', minute: '2-digit', timeZone: props.timezone }).format(tick.value));
const dateLabel = computed(() => new Intl.DateTimeFormat('en-GB', { weekday: 'long', day: 'numeric', month: 'long', timeZone: props.timezone }).format(tick.value));

/* Location: shown to the employee for guidance; the server makes the real decision. */
type Geo = 'idle' | 'locating' | 'inside' | 'outside' | 'denied' | 'unavailable' | 'remote';
const geo = ref<Geo>(props.location?.remote || !props.location ? 'remote' : 'idle');
const coords = ref<{ lat: number; lng: number } | null>(null);
const distance = ref<number | null>(null);
let watchId: number | null = null;

function haversine(a: { lat: number; lng: number }, b: { lat: number; lng: number }) {
    const r = 6371000;
    const dLat = ((b.lat - a.lat) * Math.PI) / 180;
    const dLng = ((b.lng - a.lng) * Math.PI) / 180;
    const h = Math.sin(dLat / 2) ** 2 + Math.cos((a.lat * Math.PI) / 180) * Math.cos((b.lat * Math.PI) / 180) * Math.sin(dLng / 2) ** 2;
    return Math.round(2 * r * Math.asin(Math.min(1, Math.sqrt(h))));
}

function locate() {
    if (geo.value === 'remote') return;
    if (!('geolocation' in navigator)) {
        geo.value = 'unavailable';
        return;
    }
    geo.value = 'locating';
    watchId = navigator.geolocation.watchPosition(
        (pos) => {
            coords.value = { lat: pos.coords.latitude, lng: pos.coords.longitude };
            if (props.location?.lat != null && props.location?.lng != null) {
                distance.value = haversine(coords.value, { lat: props.location.lat, lng: props.location.lng });
                geo.value = distance.value <= props.location.radius ? 'inside' : 'outside';
            }
        },
        (err) => (geo.value = err.code === err.PERMISSION_DENIED ? 'denied' : 'unavailable'),
        { enableHighAccuracy: true, maximumAge: 15000, timeout: 20000 },
    );
}

const online = ref(navigator.onLine);
const setOnline = () => (online.value = navigator.onLine);

onMounted(() => {
    timer = setInterval(() => (tick.value = Date.now() + offset), 1000);
    window.addEventListener('online', setOnline);
    window.addEventListener('offline', setOnline);
    locate();
});
onUnmounted(() => {
    clearInterval(timer);
    window.removeEventListener('online', setOnline);
    window.removeEventListener('offline', setOnline);
    if (watchId !== null) navigator.geolocation.clearWatch(watchId);
});

const human = (m: number) => (m >= 1000 ? `${(m / 1000).toFixed(1)} km` : `${m} m`);

const mode = computed<'holiday' | 'off' | 'in' | 'out' | 'done'>(() => {
    if (props.record?.out) return 'done';
    if (props.record) return 'out';
    if (props.holiday) return 'holiday';
    if (props.shift && !props.shift.working_today) return 'off';
    return 'in';
});

const blocked = computed(() => mode.value === 'in' && ['outside', 'denied', 'unavailable', 'locating', 'idle'].includes(geo.value));
const busy = ref(false);
const failed = ref<string | null>(null);

function submit() {
    if (!online.value) {
        failed.value = 'You are offline. Nothing was saved; try again when you have signal.';
        return;
    }
    failed.value = null;
    busy.value = true;
    router.post(
        route('me.clock'),
        { action: mode.value === 'out' ? 'out' : 'in', lat: coords.value?.lat ?? null, lng: coords.value?.lng ?? null },
        { preserveScroll: true, onFinish: () => (busy.value = false) },
    );
}

const lateNote = computed(() => (props.record && props.record.late_min > 0 ? `${humanMinutes(props.record.late_min)} after the ${props.shift?.grace ?? 0}-minute grace period` : null));
const dayState = (d: Day) => {
    if (d.holiday) return { text: d.holiday, cls: 'muted' };
    if (!d.working) return { text: 'Day off', cls: 'muted' };
    if (d.status === 'incomplete') return { text: `${d.in} – no clock-out`, cls: 'warn' };
    if (d.in) return { text: `${d.in} – ${d.out ?? 'now'}`, cls: d.status === 'late' ? 'warn' : '' };
    return { text: d.future ? '—' : 'No record', cls: d.future ? 'muted' : 'bad' };
};
</script>

<template>
    <Head title="Today" />
    <EmployeeAppLayout current="today">
        <div class="hi">
            <h1>Hi, {{ employee.first_name }}</h1>
            <p>{{ dateLabel }}</p>
        </div>

        <Link v-if="incomplete" class="note warning fixit" :href="route('me.attendance')">
            <Wrench class="icon sm" /><span><b>You didn't clock out on {{ formatDate(incomplete.date) }}.</b> Tap to send a correction to your manager.</span>
        </Link>

        <section class="clock" aria-live="polite">
            <div class="time num">{{ clock }}<small v-if="shift">Shift {{ shift.start }}–{{ shift.end }} · grace {{ shift.grace }} min</small><small v-else>No shift pattern assigned</small></div>

            <template v-if="mode === 'holiday'">
                <div class="where muted-state"><PartyPopper class="icon" />Public holiday: {{ holiday }}</div>
                <p class="small muted">No clock-in needed. If you are working today, ask your manager to log overtime.</p>
            </template>
            <template v-else-if="mode === 'off'">
                <div class="where muted-state"><CalendarDays class="icon" />No shift today</div>
                <p class="small muted">Your pattern: {{ shift?.label }}.</p>
            </template>
            <template v-else>
                <div v-if="mode === 'in' || mode === 'out'" class="where" :class="geo">
                    <template v-if="geo === 'remote'"><MapPin class="icon" />{{ location ? `${location.name} · remote allowed` : 'Remote · no fixed location' }}</template>
                    <template v-else-if="geo === 'inside'"><MapPin class="icon" />At {{ location?.name }} · {{ human(distance ?? 0) }}</template>
                    <template v-else-if="geo === 'outside'"><MapPinOff class="icon" />{{ human(distance ?? 0) }} from {{ location?.name }}</template>
                    <template v-else-if="geo === 'denied'"><MapPinOff class="icon" />Location permission is off</template>
                    <template v-else-if="geo === 'unavailable'"><MapPinOff class="icon" />Can't read your location</template>
                    <template v-else><LocateFixed class="icon" />Finding your location…</template>
                </div>
                <p v-if="mode === 'in' && geo === 'outside'" class="small muted">Clock-in works within {{ location?.radius }} m of {{ location?.name }}.</p>
                <p v-if="mode === 'in' && geo === 'denied'" class="small muted">Allow location for this site in your browser settings, then reload.</p>

                <button v-if="mode !== 'done'" type="button" class="go" :class="{ out: mode === 'out' }" :disabled="blocked || busy" @click="submit">
                    <component :is="mode === 'out' ? LogOut : LogIn" class="icon" />{{ busy ? 'Saving…' : mode === 'out' ? 'Clock out' : 'Clock in' }}
                </button>
                <div v-else class="where done"><Timer class="icon" />Done for today</div>
            </template>

            <p v-if="!online" class="err row"><CloudOff class="icon sm" />Offline. Clocking needs a connection; nothing is stored on the phone.</p>
            <p v-if="failed" class="err row" role="alert"><CircleAlert class="icon sm" />{{ failed }}</p>
            <p v-if="page.props.errors.clock" class="err row" role="alert"><CircleAlert class="icon sm" />{{ page.props.errors.clock }}</p>

            <div v-if="record" class="row2">
                <span>In <b class="num">{{ record.in }}</b><template v-if="lateNote"><br /><span class="small warn">{{ lateNote }}</span></template></span>
                <span>Out <b class="num">{{ record.out ?? '—' }}</b></span>
            </div>
        </section>

        <div class="quick">
            <Link :href="route('me.time-off')"><span class="qi"><Plane class="icon" /></span>Request leave<small v-if="annual?.available != null">{{ annual.available }} days left</small></Link>
            <Link :href="route('me.attendance')"><span class="qi"><Wrench class="icon" /></span>Fix a day</Link>
            <Link :href="route('me.attendance')"><span class="qi"><Timer class="icon" /></span>Overtime</Link>
        </div>

        <section class="wk" aria-labelledby="wk">
            <h2 id="wk">This week</h2>
            <div v-for="d in week" :key="d.date" class="dy">
                <span class="d">{{ d.label }}</span>
                <span>{{ formatDate(d.date).replace(/ \d{4}$/, '') }}</span>
                <span class="num" :class="dayState(d).cls">{{ dayState(d).text }}</span>
            </div>
        </section>
    </EmployeeAppLayout>
</template>
