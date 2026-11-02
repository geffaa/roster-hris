<script setup lang="ts">
import RDatePicker from '@/components/roster/RDatePicker.vue';
import RSelect from '@/components/roster/RSelect.vue';
import { formatDateTime } from '@/composables/useRoster';
import RosterLayout from '@/layouts/RosterLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowRight, ChevronLeft, ChevronRight, ShieldCheck, X } from 'lucide-vue-next';
import { computed, reactive, watch } from 'vue';

interface Entry {
    id: number;
    at: string;
    user: string;
    event: string;
    area: string;
    record: string;
    link: string | null;
    changes: { field: string; from: unknown; to: unknown }[];
    tags: string | null;
    ip: string | null;
}
type Opt = { value: string | number; label: string };
const props = defineProps<{
    entries: Entry[];
    pager: { current: number; last: number; total: number; from: number | null; to: number | null };
    filters: { from: string | null; to: string | null; user: number | null; area: string | null; event: string | null };
    options: { areas: Opt[]; users: Opt[]; events: Opt[] };
}>();

const f = reactive({ ...props.filters, user: props.filters.user ? Number(props.filters.user) : null });
const all = (label: string, list: Opt[]) => [{ value: null, label }, ...list];
watch(
    () => ({ ...f }),
    (v) => router.get(route('audit-log'), Object.fromEntries(Object.entries(v).filter(([, x]) => x !== null && x !== '')), { preserveState: true, preserveScroll: true, replace: true }),
);
const active = computed(() => Object.values(f).some((x) => x !== null && x !== ''));
function clear() {
    Object.assign(f, { from: null, to: null, user: null, area: null, event: null });
}
function page(n: number) {
    router.get(route('audit-log'), { ...Object.fromEntries(Object.entries(f).filter(([, x]) => x !== null)), page: n }, { preserveScroll: false });
}
const tone = (e: string) => (e === 'created' ? 'success' : e === 'deleted' ? 'danger' : e === 'updated' ? 'info' : 'warning');
const show = (v: unknown) => (v === null || v === undefined || v === '' ? '—' : typeof v === 'boolean' ? (v ? 'yes' : 'no') : String(v));
</script>

<template>
    <Head title="Audit log" />
    <RosterLayout current="Audit log">
        <main class="page">
            <div class="phead">
                <div>
                    <h1>Audit log</h1>
                    <p>Every change to people, pay and rules: who made it, when, and the values before and after. Encrypted fields show only that they changed.</p>
                </div>
            </div>

            <section class="card">
                <div class="filters auditf">
                    <div class="fsel"><RDatePicker v-model="f.from" label="From" placeholder="From date" /></div>
                    <div class="fsel"><RDatePicker v-model="f.to" label="To" placeholder="To date" /></div>
                    <div class="fsel"><RSelect v-model="f.user as any" :options="all('Anyone', options.users)" label="Person" /></div>
                    <div class="fsel"><RSelect v-model="f.area as any" :options="all('All areas', options.areas)" label="Area" /></div>
                    <div class="fsel"><RSelect v-model="f.event as any" :options="all('All events', options.events)" label="Event" /></div>
                    <button v-if="active" type="button" class="btn ghost sm" @click="clear"><X class="icon sm" />Clear</button>
                </div>

                <div v-if="!entries.length" class="empty"><ShieldCheck class="icon" /><b>No changes match</b><span>Try a wider date range or another area.</span></div>
                <ol v-else class="audit">
                    <li v-for="e in entries" :key="e.id">
                        <div class="au-h">
                            <span class="badge" :class="tone(e.event)">{{ e.event.replace(/_/g, ' ') }}</span>
                            <span class="au-what">
                                <span class="muted">{{ e.area }}</span> ·
                                <Link v-if="e.link" :href="e.link">{{ e.record }}</Link><b v-else>{{ e.record }}</b>
                            </span>
                            <span class="au-who"><b>{{ e.user }}</b> · <time>{{ formatDateTime(e.at) }}</time></span>
                        </div>
                        <ul v-if="e.changes.length" class="au-ch">
                            <li v-for="c in e.changes" :key="c.field">
                                <span class="muted">{{ c.field }}</span>
                                <template v-if="e.event === 'created' || c.from === null"><span class="num"><b>{{ show(c.to) }}</b></span></template>
                                <template v-else><span class="num">{{ show(c.from) }}</span><ArrowRight class="icon xs muted" /><span class="num"><b>{{ show(c.to) }}</b></span></template>
                            </li>
                        </ul>
                        <p v-if="e.tags || e.ip" class="small muted au-meta"><template v-if="e.tags">tag: {{ e.tags }}</template><template v-if="e.tags && e.ip"> · </template><template v-if="e.ip">IP {{ e.ip }}</template></p>
                    </li>
                </ol>

                <div v-if="pager.total" class="pager">
                    <span class="muted small">{{ pager.from }}–{{ pager.to }} of {{ pager.total }}</span>
                    <nav v-if="pager.last > 1" aria-label="Pages" class="pgn">
                        <button type="button" class="ibtn" aria-label="Previous page" :disabled="pager.current <= 1" @click="page(pager.current - 1)"><ChevronLeft class="icon" /></button>
                        <span class="small">Page {{ pager.current }} of {{ pager.last }}</span>
                        <button type="button" class="ibtn" aria-label="Next page" :disabled="pager.current >= pager.last" @click="page(pager.current + 1)"><ChevronRight class="icon" /></button>
                    </nav>
                </div>
            </section>
            <p class="synthetic">Synthetic demo data. Audit entries are append-only and cannot be edited from Roster.</p>
        </main>
    </RosterLayout>
</template>
