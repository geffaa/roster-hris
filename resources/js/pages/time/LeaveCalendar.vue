<script setup lang="ts">
import RosterLayout from '@/layouts/RosterLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { computed } from 'vue';

interface Span {
    from: number;
    to: number;
    type: string;
    color: string;
    pending: boolean;
    half: boolean;
}
const props = defineProps<{
    month: string;
    days: { day: number; weekday: number }[];
    holidays: Record<string, string>;
    people: { id: number; name: string; title: string; spans: Span[] }[];
    types: { id: number; name: string; color: string }[];
}>();

const label = computed(() => new Intl.DateTimeFormat('en-GB', { month: 'long', year: 'numeric' }).format(new Date(`${props.month}-01T00:00:00`)));
function move(n: number) {
    const d = new Date(`${props.month}-01T00:00:00`);
    d.setMonth(d.getMonth() + n);
    router.get(route('leave-calendar'), { month: `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}` }, { preserveScroll: true });
}
const holidayOn = (day: number) => props.holidays[`${props.month}-${String(day).padStart(2, '0')}`] ?? null;
const offCount = (day: number) => props.people.filter((p) => p.spans.some((s) => !s.pending && s.from <= day && s.to >= day)).length;
const cols = computed(() => `220px repeat(${props.days.length}, minmax(28px, 1fr))`);
</script>

<template>
    <Head title="Leave calendar" />
    <RosterLayout current="Leave calendar">
        <main class="page">
            <div class="phead">
                <div><h1>Leave calendar</h1><p>Approved and pending leave, with public holidays</p></div>
                <div class="acts">
                    <div class="datenav">
                        <button type="button" class="ibtn" aria-label="Previous month" @click="move(-1)"><ChevronLeft class="icon" /></button>
                        <b class="mlabel">{{ label }}</b>
                        <button type="button" class="ibtn" aria-label="Next month" @click="move(1)"><ChevronRight class="icon" /></button>
                    </div>
                </div>
            </div>
            <section class="card">
                <div class="legend2">
                    <span v-for="t in types" :key="t.id"><i :style="{ background: t.color }"></i>{{ t.name }}</span>
                    <span><i class="pend"></i>Pending</span>
                    <span><i class="hol"></i>Holiday</span>
                </div>
                <div class="lcal-wrap">
                    <div class="lcal" role="grid" :aria-label="`Leave in ${label}`">
                        <div class="lc-row head" :style="{ gridTemplateColumns: cols }" role="row">
                            <div class="lc-h name">Person</div>
                            <div v-for="d in days" :key="d.day" class="lc-h" :class="{ we: d.weekday >= 6, hol: holidayOn(d.day) }" :title="holidayOn(d.day) ?? undefined">
                                <span>{{ ['', 'M', 'T', 'W', 'T', 'F', 'S', 'S'][d.weekday] }}</span><b>{{ d.day }}</b>
                            </div>
                        </div>
                        <div v-for="p in people" :key="p.id" class="lc-row" :style="{ gridTemplateColumns: cols }" role="row">
                            <div class="lc-name"><Link :href="route('employees.show', { employee: p.id })">{{ p.name }}</Link><span>{{ p.title }}</span></div>
                            <div v-for="d in days" :key="d.day" class="lc-c" :class="{ we: d.weekday >= 6, hol: holidayOn(d.day) }" :style="{ gridColumn: d.day + 1 }"></div>
                            <div
                                v-for="(s, i) in p.spans"
                                :key="i"
                                class="lc-bar"
                                :class="{ pending: s.pending, half: s.half }"
                                :style="{ gridColumn: `${s.from + 1} / ${s.to + 2}`, '--c': s.color }"
                                :title="`${s.type}${s.pending ? ' (pending)' : ''}`"
                            >
                                <span>{{ s.type }}</span>
                            </div>
                        </div>
                        <div class="lc-row foot" :style="{ gridTemplateColumns: cols }" role="row">
                            <div class="lc-name">People off</div>
                            <div v-for="d in days" :key="d.day" class="lc-c num" :class="{ we: d.weekday >= 6 }">{{ offCount(d.day) || '' }}</div>
                        </div>
                    </div>
                </div>
            </section>
            <p class="synthetic">Synthetic demo data. Holiday dates are illustrative; check the official calendar for your country.</p>
        </main>
    </RosterLayout>
</template>
