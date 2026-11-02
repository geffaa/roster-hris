<script setup lang="ts">
import { useRoster } from '@/composables/useRoster';
import RosterLayout from '@/layouts/RosterLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, CalendarClock, FileClock, Plane, UserPlus, Users } from 'lucide-vue-next';

defineProps<{
    stats: { headcount: number; on_leave: number; joined_this_quarter: number; contracts_ending: number } | null;
    team: number | null;
    me: { id: number; job_title: string } | null;
}>();

const { user, company } = useRoster();
const hour = new Date().getHours();
const greeting = hour < 11 ? 'Good morning' : hour < 15 ? 'Good afternoon' : 'Good evening';
</script>

<template>
    <Head title="Home" />
    <RosterLayout current="Home">
        <main class="page">
            <div class="phead">
                <div>
                    <h1>{{ greeting }}, {{ user?.name.split(' ')[0] }}</h1>
                    <p>{{ company?.name }} · {{ user?.role }}</p>
                </div>
            </div>

            <div v-if="stats" class="tiles">
                <Link class="tile" :href="route('employees.index')">
                    <span class="ti brand"><Users class="icon" /></span>
                    <span><b class="fig">{{ stats.headcount }}</b><span>People employed</span></span>
                </Link>
                <Link class="tile" :href="route('employees.index', { status: 'on_leave' })">
                    <span class="ti info"><Plane class="icon" /></span>
                    <span><b class="fig">{{ stats.on_leave }}</b><span>On leave now</span></span>
                </Link>
                <div class="tile">
                    <span class="ti success"><UserPlus class="icon" /></span>
                    <span><b class="fig">{{ stats.joined_this_quarter }}</b><span>Joined this quarter</span></span>
                </div>
                <div class="tile">
                    <span class="ti warning"><FileClock class="icon" /></span>
                    <span><b class="fig">{{ stats.contracts_ending }}</b><span>Contracts ending in 60 days</span></span>
                </div>
            </div>

            <div class="grid-main">
                <section class="card">
                    <div class="card-h"><h2>What is ready in this build</h2></div>
                    <div class="card-b">
                        <ul class="ready">
                            <li><b>Directory and profiles</b><span>Search, filter by department, effective-dated salary history.</span></li>
                            <li><b>Company isolation</b><span>Every record belongs to one company; switching companies changes everything you can see.</span></li>
                            <li><b>Masked personal IDs</b><span>NIK, NPWP and BPJS numbers stay hidden until someone allowed reveals them, and each reveal is logged.</span></li>
                            <li><b>Two-factor sign-in</b><span>Required for HR, payroll and super admins.</span></li>
                        </ul>
                    </div>
                </section>
                <section class="card">
                    <div class="card-h"><h2>Next milestones</h2></div>
                    <div class="card-b">
                        <ul class="ready">
                            <li><b><CalendarClock class="icon xs" /> M2 · Attendance &amp; leave</b><span>Geofenced clock-in, corrections, leave balances.</span></li>
                            <li><b><CalendarClock class="icon xs" /> M3 · Payroll</b><span>Rule packs, PPh 21 TER, BPJS, two-person approval.</span></li>
                        </ul>
                        <Link v-if="team !== null && me" class="btn sm" :href="route('employees.index')">My team · {{ team }}<ArrowRight class="icon sm" /></Link>
                        <Link v-else-if="me && !stats" class="btn sm" :href="route('employees.show', { employee: me.id })">Open my profile<ArrowRight class="icon sm" /></Link>
                    </div>
                </section>
            </div>
            <p class="synthetic">Synthetic demo data.</p>
        </main>
    </RosterLayout>
</template>
