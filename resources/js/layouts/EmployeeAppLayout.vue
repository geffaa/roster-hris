<script setup lang="ts">
import NotificationBell from '@/components/roster/NotificationBell.vue';
import FlashToast from '@/components/roster/FlashToast.vue';
import { useRoster } from '@/composables/useRoster';
import { useT } from '@/i18n';
import { Link } from '@inertiajs/vue3';
import { CalendarDays, Clock3, House, LayoutGrid, UserRound, Users, Wallet } from 'lucide-vue-next';
import { computed } from 'vue';

defineProps<{ current: 'today' | 'time-off' | 'attendance' | 'pay' | 'me' }>();
const { user, company, can } = useRoster();
const { t } = useT();
const workspace = computed(() => can('employees.view_all') || can('employees.view_team'));
</script>

<template>
    <div class="eapp">
        <header class="ah">
            <Link class="logo" :href="route('me.today')"><span class="mk"><Users class="icon sm" /></span>Roster</Link>
            <div class="r">
                <span class="small muted co">{{ company?.name }}</span>
                <NotificationBell :labels="{ title: t('bell.title'), empty: t('bell.empty'), readAll: t('bell.readAll') }" />
                <Link v-if="workspace" class="ibtn" :href="route('dashboard')" :aria-label="t('nav.workspace')"><LayoutGrid class="icon" /></Link>
            </div>
        </header>
        <main class="eapp-main">
            <slot />
        </main>
        <nav class="tab" aria-label="Employee app">
            <Link :href="route('me.today')" :aria-current="current === 'today' ? 'page' : undefined"><House class="icon" />{{ t('nav.today') }}</Link>
            <Link :href="route('me.time-off')" :aria-current="current === 'time-off' ? 'page' : undefined"><CalendarDays class="icon" />{{ t('nav.timeOff') }}</Link>
            <Link :href="route('me.attendance')" :aria-current="current === 'attendance' ? 'page' : undefined"><Clock3 class="icon" />{{ t('nav.attendance') }}</Link>
            <Link :href="route('me.pay')" :aria-current="current === 'pay' ? 'page' : undefined"><Wallet class="icon" />{{ t('nav.pay') }}</Link>
            <Link v-if="user?.employee_id" :href="route('me.profile')" :aria-current="current === 'me' ? 'page' : undefined"><UserRound class="icon" />{{ t('nav.me') }}</Link>
        </nav>
        <FlashToast />
    </div>
</template>
