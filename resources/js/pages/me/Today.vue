<script setup lang="ts">
import { humanMinutes } from '@/composables/useRoster';
import { usePwa } from '@/composables/usePwa';
import { useT } from '@/i18n';
import EmployeeAppLayout from '@/layouts/EmployeeAppLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { CalendarDays, Download, X, CircleAlert, CloudOff, LocateFixed, LogIn, LogOut, MapPin, MapPinOff, PartyPopper, Plane, Timer, Wrench } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const { canInstall, install, standalone, ios } = usePwa();
const { t, dateLocale, d: fd } = useT();
const hideInstall = ref(typeof localStorage !== 'undefined' && (() => { try { return localStorage.getItem('roster.install.hide') === '1'; } catch { return false; } })());
function dismissInstall() {
    hideInstall.value = true;
    try { localStorage.setItem('roster.install.hide', '1'); } catch { /* private mode */ }
}

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
const clock = computed(() => new Intl.DateTimeFormat(dateLocale.value, { hour: '2-digit', minute: '2-digit', timeZone: props.timezone }).format(tick.value));
const dateLabel = computed(() => new Intl.DateTimeFormat(dateLocale.value, { weekday: 'long', day: 'numeric', month: 'long', timeZone: props.timezone }).format(tick.value));

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
        failed.value = t('today.offlineTap');
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

const lateNote = computed(() => (props.record && props.record.late_min > 0 ? t('today.lateNote', { late: humanMinutes(props.record.late_min), grace: props.shift?.grace ?? 0 }) : null));
const dayState = (d: Day) => {
    if (d.holiday) return { text: d.holiday, cls: 'muted' };
    if (!d.working) return { text: t('common.dayOff'), cls: 'muted' };
    if (d.status === 'incomplete') return { text: t('today.noClockOut', { in: d.in ?? '' }), cls: 'warn' };
    if (d.in) return { text: `${d.in} – ${d.out ?? t('common.now')}`, cls: d.status === 'late' ? 'warn' : '' };
    return { text: d.future ? '—' : t('common.noRecord'), cls: d.future ? 'muted' : 'bad' };
};
</script>

<template>
    <Head :title="t('today.title')" />
    <EmployeeAppLayout current="today">
        <div v-if="!standalone && !hideInstall && (canInstall || ios)" class="installbar">
            <img src="/icons/icon-192.png" alt="" />
            <span><b>{{ t('install.title') }}</b><small>{{ canInstall ? t('install.body') : t('install.ios') }}</small></span>
            <button v-if="canInstall" type="button" class="btn sm primary" @click="install"><Download class="icon sm" />{{ t('install.action') }}</button>
            <button type="button" class="ibtn" aria-label="Dismiss" @click="dismissInstall"><X class="icon sm" /></button>
        </div>
        <div class="hi">
            <h1>{{ t('today.hi', { name: employee.first_name }) }}</h1>
            <p>{{ dateLabel }}</p>
        </div>

        <Link v-if="incomplete" class="note warning fixit" :href="route('me.attendance')">
            <Wrench class="icon sm" /><span><b>{{ t('today.missedOut', { date: fd(incomplete.date) }) }}</b> {{ t('today.missedOutAction') }}</span>
        </Link>

        <section class="clock" aria-live="polite">
            <div class="time num">{{ clock }}<small v-if="shift">{{ t('today.shift', { start: shift.start, end: shift.end, grace: shift.grace }) }}</small><small v-else>{{ t('today.noShift') }}</small></div>

            <template v-if="mode === 'holiday'">
                <div class="where muted-state"><PartyPopper class="icon" />{{ t('today.holiday', { name: holiday ?? '' }) }}</div>
                <p class="small muted">{{ t('today.holidayHint') }}</p>
            </template>
            <template v-else-if="mode === 'off'">
                <div class="where muted-state"><CalendarDays class="icon" />{{ t('today.noShiftToday') }}</div>
                <p class="small muted">{{ t('today.pattern', { name: shift?.label ?? '' }) }}</p>
            </template>
            <template v-else>
                <div v-if="mode === 'in' || mode === 'out'" class="where" :class="geo">
                    <template v-if="geo === 'remote'"><MapPin class="icon" />{{ location ? t('today.remoteAt', { name: location.name }) : t('today.remote') }}</template>
                    <template v-else-if="geo === 'inside'"><MapPin class="icon" />{{ t('today.at', { name: location?.name ?? '', distance: human(distance ?? 0) }) }}</template>
                    <template v-else-if="geo === 'outside'"><MapPinOff class="icon" />{{ t('today.away', { name: location?.name ?? '', distance: human(distance ?? 0) }) }}</template>
                    <template v-else-if="geo === 'denied'"><MapPinOff class="icon" />{{ t('today.locDenied') }}</template>
                    <template v-else-if="geo === 'unavailable'"><MapPinOff class="icon" />{{ t('today.locUnavailable') }}</template>
                    <template v-else><LocateFixed class="icon" />{{ t('today.locating') }}</template>
                </div>
                <p v-if="mode === 'in' && geo === 'outside'" class="small muted">{{ t('today.radius', { radius: location?.radius ?? 0, name: location?.name ?? '' }) }}</p>
                <p v-if="mode === 'in' && geo === 'denied'" class="small muted">{{ t('today.allowLoc') }}</p>

                <button v-if="mode !== 'done'" type="button" class="go" :class="{ out: mode === 'out' }" :disabled="blocked || busy" @click="submit">
                    <component :is="mode === 'out' ? LogOut : LogIn" class="icon" />{{ busy ? t('today.saving') : mode === 'out' ? t('today.clockOut') : t('today.clockIn') }}
                </button>
                <div v-else class="where done"><Timer class="icon" />{{ t('today.done') }}</div>
            </template>

            <p v-if="!online" class="err row"><CloudOff class="icon sm" />{{ t('today.offline') }}</p>
            <p v-if="failed" class="err row" role="alert"><CircleAlert class="icon sm" />{{ failed }}</p>
            <p v-if="page.props.errors.clock" class="err row" role="alert"><CircleAlert class="icon sm" />{{ page.props.errors.clock }}</p>

            <div v-if="record" class="row2">
                <span>{{ t('today.in') }} <b class="num">{{ record.in }}</b><template v-if="lateNote"><br /><span class="small warn">{{ lateNote }}</span></template></span>
                <span>{{ t('today.out') }} <b class="num">{{ record.out ?? '—' }}</b></span>
            </div>
        </section>

        <div class="quick">
            <Link :href="route('me.time-off')"><span class="qi"><Plane class="icon" /></span>{{ t('today.requestLeave') }}<small v-if="annual?.available != null">{{ t('common.daysLeft', { n: annual.available }) }}</small></Link>
            <Link :href="route('me.attendance')"><span class="qi"><Wrench class="icon" /></span>{{ t('today.fixDay') }}</Link>
            <Link :href="route('me.attendance')"><span class="qi"><Timer class="icon" /></span>{{ t('today.overtime') }}</Link>
        </div>

        <section class="wk" aria-labelledby="wk">
            <h2 id="wk">{{ t('today.week') }}</h2>
            <div v-for="d in week" :key="d.date" class="dy">
                <span class="d">{{ fd(d.date, { weekday: 'short' }) }}</span>
                <span>{{ fd(d.date, { day: 'numeric', month: 'short' }) }}</span>
                <span class="num" :class="dayState(d).cls">{{ dayState(d).text }}</span>
            </div>
        </section>
    </EmployeeAppLayout>
</template>
