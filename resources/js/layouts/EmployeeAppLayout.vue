<script setup lang="ts">
import FlashToast from '@/components/roster/FlashToast.vue';
import { useRoster } from '@/composables/useRoster';
import { Link } from '@inertiajs/vue3';
import { CalendarDays, Clock3, House, LayoutGrid, UserRound, Users, Wallet } from 'lucide-vue-next';
import { computed } from 'vue';

defineProps<{ current: 'today' | 'time-off' | 'attendance' | 'pay' | 'me' }>();
const { user, company, can } = useRoster();
const workspace = computed(() => can('employees.view_all') || can('employees.view_team'));
</script>

<template>
    <div class="eapp">
        <header class="ah">
            <Link class="logo" :href="route('me.today')"><span class="mk"><Users class="icon sm" /></span>Roster</Link>
            <div class="r">
                <span class="small muted">{{ company?.name }}</span>
                <Link v-if="workspace" class="ibtn" :href="route('dashboard')" aria-label="Open workspace"><LayoutGrid class="icon" /></Link>
            </div>
        </header>
        <main class="eapp-main">
            <slot />
        </main>
        <nav class="tab" aria-label="Employee app">
            <Link :href="route('me.today')" :aria-current="current === 'today' ? 'page' : undefined"><House class="icon" />Today</Link>
            <Link :href="route('me.time-off')" :aria-current="current === 'time-off' ? 'page' : undefined"><CalendarDays class="icon" />Time off</Link>
            <Link :href="route('me.attendance')" :aria-current="current === 'attendance' ? 'page' : undefined"><Clock3 class="icon" />Attendance</Link>
            <Link :href="route('me.pay')" :aria-current="current === 'pay' ? 'page' : undefined"><Wallet class="icon" />Pay</Link>
            <Link v-if="user?.employee_id" :href="route('employees.show', { employee: user.employee_id })" :aria-current="current === 'me' ? 'page' : undefined"><UserRound class="icon" />Me</Link>
        </nav>
        <FlashToast />
    </div>
</template>
