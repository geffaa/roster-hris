<script setup lang="ts">
import type { RosterShared } from '@/types/roster';
import { router, usePage } from '@inertiajs/vue3';
import { onClickOutside } from '@vueuse/core';
import { Bell, CalendarCheck, CircleCheck, CircleX, Inbox, Landmark, Wallet } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = withDefaults(defineProps<{ labels?: { title: string; empty: string; readAll: string } }>(), {
    labels: () => ({ title: 'Notifications', empty: 'Nothing new. Approvals and payslips will show up here.', readAll: 'Mark all read' }),
});
const page = usePage<RosterShared>();
const n = computed(() => page.props.notifications ?? { unread: 0, latest: [] });
const open = ref(false);
const root = ref<HTMLElement | null>(null);
onClickOutside(root, () => (open.value = false));

const icon = (kind: string) =>
    ({ approval_needed: Inbox, request_approved: CircleCheck, request_rejected: CircleX, payslip: Wallet, bank: Landmark })[kind] ?? CalendarCheck;
const ago = (iso: string) => {
    const min = Math.round((Date.now() - new Date(iso).getTime()) / 60000);
    if (min < 1) return 'now';
    if (min < 60) return `${min} min`;
    if (min < 60 * 24) return `${Math.round(min / 60)} h`;
    return new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'short' }).format(new Date(iso));
};
function readAll() {
    router.post(route('notifications.read-all'), {}, { preserveScroll: true, preserveState: true });
}
</script>

<template>
    <div ref="root" class="popwrap bell">
        <button type="button" class="ibtn" :aria-label="`${props.labels.title}${n.unread ? `, ${n.unread} unread` : ''}`" :aria-expanded="open" @click="open = !open">
            <Bell class="icon" /><span v-if="n.unread" class="dot">{{ n.unread > 9 ? '9+' : n.unread }}</span>
        </button>
        <div v-if="open" class="pop bellpop" role="dialog" :aria-label="props.labels.title">
            <div class="bp-h">
                <b>{{ props.labels.title }}</b>
                <button v-if="n.unread" type="button" class="linkbtn small" @click="readAll">{{ props.labels.readAll }}</button>
            </div>
            <p v-if="!n.latest.length" class="small muted bp-empty">{{ props.labels.empty }}</p>
            <a v-for="x in n.latest" :key="x.id" :href="route('notifications.open', { id: x.id })" class="bp-it" :class="{ unread: !x.read }">
                <span class="bp-ic" :class="x.kind"><component :is="icon(x.kind)" class="icon sm" /></span>
                <span class="bp-t"><b>{{ x.title }}</b><small>{{ x.body }}</small></span>
                <time class="small muted">{{ ago(x.at) }}</time>
            </a>
        </div>
    </div>
</template>
