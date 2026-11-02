<script setup lang="ts">
import Avatar from '@/components/roster/Avatar.vue';
import CommandPalette from '@/components/roster/CommandPalette.vue';
import NotificationBell from '@/components/roster/NotificationBell.vue';
import FlashToast from '@/components/roster/FlashToast.vue';
import { useRoster } from '@/composables/useRoster';
import { Link, router } from '@inertiajs/vue3';
import { onClickOutside } from '@vueuse/core';
import {
    Briefcase,
    Building2,
    ChartColumn,
    Check,
    ChevronDown,
    ChevronRight,
    ChevronsUpDown,
    CircleHelp,
    Clock,
    House,
    Inbox,
    LogOut,
    Menu,
    Search,
    Settings,
    Users,
    Wallet,
    X,
} from 'lucide-vue-next';
import type { Component } from 'vue';
import { computed, ref } from 'vue';

const props = defineProps<{ current?: string }>();
const { page, user, company, can } = useRoster();

interface Sub {
    name: string;
    href?: string;
    soon?: string;
}
interface Group {
    name: string;
    icon: Component;
    subs: Sub[];
    soon?: boolean;
}

const seesDirectory = computed(() => can('employees.view_all') || can('employees.view_team'));

const groups = computed<Group[]>(() => [
    {
        name: 'People',
        icon: Users,
        subs: [
            seesDirectory.value
                ? { name: 'Directory', href: route('employees.index') }
                : { name: 'My profile', href: user.value?.employee_id ? route('employees.show', { employee: user.value.employee_id }) : undefined },
            ...(seesDirectory.value ? [{ name: 'Org chart', href: route('org-chart') }] : []),
            { name: 'Onboarding', soon: 'P2' },
        ],
    },
    {
        name: 'Time',
        icon: Clock,
        subs: [
            ...(seesDirectory.value
                ? [
                      { name: 'Attendance', href: route('attendance') },
                      { name: 'Leave calendar', href: route('leave-calendar') },
                  ]
                : []),
            ...(user.value?.employee_id ? [{ name: 'My time', href: route('me.today') }] : []),
        ],
    },
    {
        name: 'Pay',
        icon: Wallet,
        subs: [
            ...(can('payroll.view') ? [{ name: 'Payroll', href: route('payroll.index') }] : []),
            ...(can('payroll.view') || can('rule_packs.manage') ? [{ name: 'Rule packs', href: route('rule-packs.show') }] : []),
            { name: 'Claims', soon: 'P2' },
        ],
    },
    { name: 'Talent', icon: Briefcase, soon: true, subs: [{ name: 'Recruitment', soon: 'P2' }, { name: 'Performance', soon: 'P2' }] },
    {
        name: 'Workplace',
        icon: Building2,
        subs: [...(can('employees.manage') ? [{ name: 'HR policies', href: route('settings.policies') }] : []), { name: 'Announcements', soon: 'P2' }],
    },
    { name: 'Insights', icon: ChartColumn, subs: [...(seesDirectory.value ? [{ name: 'Reports', href: route('reports') }] : []), ...(can('audit.view') ? [{ name: 'Audit log', href: route('audit-log') }] : [])] },
]);

const paletteGroups = computed(() => [
    { name: 'Home', href: route('dashboard'), group: 'Roster' },
    { name: 'Inbox', href: route('inbox'), group: 'Roster' },
    ...groups.value.flatMap((g) => g.subs.filter((s) => 'href' in s && s.href).map((s) => ({ name: s.name, href: (s as { href: string }).href, group: g.name }))),
]);
const currentGroup = computed(() => groups.value.find((g) => g.subs.some((s) => s.name === props.current))?.name ?? null);
const expanded = ref<string | null>(currentGroup.value);
const mobileOpen = ref(false);

function toggleGroup(group: Group) {
    if (group.soon) return;
    expanded.value = expanded.value === group.name ? null : group.name;
}

const switcherOpen = ref(false);
const switcher = ref<HTMLElement | null>(null);
onClickOutside(switcher, () => (switcherOpen.value = false));

function switchCompany(id: number) {
    switcherOpen.value = false;
    if (id !== company.value?.id) router.post(route('companies.switch', { company: id }));
}

const companies = computed(() => page.props.companies ?? []);
const inboxCount = computed(() => (page.props.inboxCount as number | undefined) ?? 0);
const flagOf = (name: string) =>
    name
        .split(/\s+/)
        .map((w) => w[0])
        .join('')
        .slice(0, 2)
        .toUpperCase();
</script>

<template>
    <div class="app" :class="{ 'side-open': mobileOpen }">
        <aside class="side" aria-label="Workspace">
            <div class="side-top">
                <Link class="logo" :href="route('dashboard')"><span class="mk"><Users class="icon sm" /></span><span class="txt">Roster</span></Link>
                <button class="ibtn side-close" type="button" aria-label="Close menu" @click="mobileOpen = false"><X class="icon" /></button>
            </div>

            <div v-if="company" ref="switcher" class="popwrap">
                <button
                    class="coswitch"
                    type="button"
                    :aria-label="`Company: ${company.name}. Switch company`"
                    aria-haspopup="listbox"
                    :aria-expanded="switcherOpen"
                    :disabled="companies.length < 2"
                    @click="switcherOpen = !switcherOpen"
                >
                    <span class="flag">{{ flagOf(company.name) }}</span>
                    <span><b>{{ company.name }}</b><span>{{ company.country_code === 'ID' ? 'Indonesia' : company.country_code }} · {{ company.base_currency }}</span></span>
                    <ChevronsUpDown v-if="companies.length > 1" class="icon sm" />
                </button>
                <div v-if="switcherOpen" class="pop">
                    <ul class="listbox" role="listbox" aria-label="Switch company">
                        <li v-for="c in companies" :key="c.id" role="option" :aria-selected="c.id === company.id" @click="switchCompany(c.id)">
                            <span>{{ c.name }}<small>{{ c.country_code }}</small></span>
                            <Check v-if="c.id === company.id" class="icon sm chk" />
                        </li>
                    </ul>
                </div>
            </div>

            <nav class="snav" aria-label="Main">
                <Link class="it" :href="route('dashboard')" :aria-current="current === 'Home' ? 'page' : undefined"><House class="icon sm" /><span class="lbl">Home</span></Link>
                <Link class="it" :href="route('inbox')" :aria-current="current === 'Inbox' ? 'page' : undefined">
                    <Inbox class="icon sm" /><span class="lbl">Inbox</span><span v-if="inboxCount" class="ct">{{ inboxCount }}</span>
                </Link>
                <div class="divider"></div>
                <template v-for="group in groups" :key="group.name">
                    <a
                        class="grp"
                        :class="{ open: expanded === group.name, dim: group.soon }"
                        role="button"
                        tabindex="0"
                        :aria-expanded="group.soon ? undefined : expanded === group.name"
                        @click="toggleGroup(group)"
                        @keydown.enter.prevent="toggleGroup(group)"
                    >
                        <component :is="group.icon" class="icon sm" />
                        <span class="lbl">{{ group.name }}</span>
                        <span v-if="group.soon" class="soon">Soon</span>
                        <component :is="expanded === group.name ? ChevronDown : ChevronRight" v-else class="icon xs chev" />
                    </a>
                    <div v-if="expanded === group.name" class="subs">
                        <template v-for="sub in group.subs" :key="sub.name">
                            <Link v-if="sub.href" :href="sub.href" :aria-current="current === sub.name ? 'page' : undefined">{{ sub.name }}</Link>
                            <a v-else aria-disabled="true" class="off">{{ sub.name }}<span class="soon">{{ sub.soon }}</span></a>
                        </template>
                    </div>
                </template>
            </nav>

            <div class="foot">
                <Link class="sidelink" :href="can('employees.manage') ? route('settings.time') : route('profile.edit')"><Settings class="icon sm" /><span class="lbl">Settings</span></Link>
                <Link class="sidelink" :href="route('logout')" method="post" as="button"><LogOut class="icon sm" /><span class="lbl">Log out</span></Link>
                <div v-if="user" class="me">
                    <Avatar :name="user.name" size="sm" />
                    <div><b>{{ user.name }}</b><span>{{ user.role ?? 'Member' }}</span></div>
                </div>
            </div>
        </aside>
        <div class="scrim" @click="mobileOpen = false"></div>

        <div class="mainc">
            <header class="topbar">
                <button class="ibtn menu-btn" type="button" aria-label="Open menu" @click="mobileOpen = true"><Menu class="icon" /></button>
                <CommandPalette :pages="paletteGroups" />
                <div class="tb-r">
                    <NotificationBell />
                    <button class="ibtn" type="button" aria-label="Help"><CircleHelp class="icon" /></button>
                </div>
            </header>
            <slot />
        </div>
        <FlashToast />
    </div>
</template>
