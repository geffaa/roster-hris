<script setup lang="ts">
import Avatar from '@/components/roster/Avatar.vue';
import RSelect from '@/components/roster/RSelect.vue';
import { formatDate } from '@/composables/useRoster';
import RosterLayout from '@/layouts/RosterLayout.vue';
import type { Paginated } from '@/types/roster';
import { Head, Link, router } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import { ChevronRight, Network, Search, Upload, UsersRound } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Row {
    id: number;
    employee_no: string;
    full_name: string;
    job_title: string;
    department: string | null;
    location: string | null;
    manager: string | null;
    status: 'active' | 'on_leave' | 'resigned';
    hire_date: string;
}

const props = defineProps<{
    employees: Paginated<Row>;
    filters: { q?: string; department?: number; status?: string };
    departments: { id: number; name: string }[];
    counts: { active: number; on_leave: number };
    scope: 'company' | 'team';
    canImport: boolean;
}>();

const q = ref(props.filters.q ?? '');
const department = ref<number | null>(props.filters.department ? Number(props.filters.department) : null);
const status = ref<string | null>(props.filters.status ?? null);

const departmentOptions = computed(() => [{ value: null, label: 'All departments' }, ...props.departments.map((d) => ({ value: d.id, label: d.name }))]);
const statusTabs = [
    { value: null, label: 'Current' },
    { value: 'on_leave', label: 'On leave' },
    { value: 'resigned', label: 'Left' },
];

function apply() {
    router.get(
        route('employees.index'),
        { q: q.value || undefined, department: department.value ?? undefined, status: status.value ?? undefined },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

watchDebounced(q, apply, { debounce: 300 });
watch([department, status], apply);

const statusBadge: Record<Row['status'], { label: string; cls: string }> = {
    active: { label: 'Active', cls: 'success' },
    on_leave: { label: 'On leave', cls: 'info' },
    resigned: { label: 'Left', cls: '' },
};
</script>

<template>
    <Head title="Directory" />
    <RosterLayout current="Directory">
        <main class="page">
            <div class="phead">
                <div>
                    <h1>{{ scope === 'team' ? 'My team' : 'Directory' }}</h1>
                    <p v-if="scope === 'company'">{{ counts.active }} active · {{ counts.on_leave }} on leave</p>
                    <p v-else>People who report to you directly</p>
                </div>
                <div class="acts">
                    <Link class="btn" :href="route('org-chart')"><Network class="icon sm" />Org chart</Link>
                    <Link v-if="canImport" class="btn primary" :href="route('employees.import.create')"><Upload class="icon sm" />Import</Link>
                </div>
            </div>

            <section class="card">
                <div class="filters">
                    <label class="searchbox">
                        <Search class="icon sm" />
                        <span class="sr">Search people</span>
                        <input v-model="q" class="bare" type="search" placeholder="Search name, ID or job title" autocomplete="off" />
                    </label>
                    <div class="fsel"><RSelect v-model="department" :options="departmentOptions" label="Department" /></div>
                    <div class="seg" role="group" aria-label="Employment status">
                        <button v-for="t in statusTabs" :key="String(t.value)" type="button" :aria-pressed="status === t.value" @click="status = t.value">{{ t.label }}</button>
                    </div>
                </div>

                <div v-if="employees.data.length" class="tablewrap">
                    <table class="t dir">
                        <thead>
                            <tr>
                                <th>Person</th>
                                <th>Department</th>
                                <th>Location</th>
                                <th>Manager</th>
                                <th>Started</th>
                                <th>Status</th>
                                <th><span class="sr">Open</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="e in employees.data" :key="e.id" class="rowlink" @click="router.visit(route('employees.show', { employee: e.id }))">
                                <td>
                                    <div class="who">
                                        <Avatar :name="e.full_name" />
                                        <div>
                                            <Link :href="route('employees.show', { employee: e.id })" class="name" @click.stop>{{ e.full_name }}</Link>
                                            <span>{{ e.job_title }} · {{ e.employee_no }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ e.department ?? '—' }}</td>
                                <td>{{ e.location ?? '—' }}</td>
                                <td>{{ e.manager ?? '—' }}</td>
                                <td class="num">{{ formatDate(e.hire_date) }}</td>
                                <td><span class="badge" :class="statusBadge[e.status].cls">{{ statusBadge[e.status].label }}</span></td>
                                <td class="n"><ChevronRight class="icon sm muted" /></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="empty">
                    <UsersRound class="icon" />
                    <b>No one matches these filters</b>
                    <span>Try another name or clear the department filter.</span>
                </div>

                <div v-if="employees.last_page > 1 || employees.total" class="pager">
                    <span class="muted small">{{ employees.from ?? 0 }}–{{ employees.to ?? 0 }} of {{ employees.total }}</span>
                    <nav v-if="employees.last_page > 1" aria-label="Pages">
                        <template v-for="l in employees.links" :key="l.label">
                            <Link v-if="l.url" :href="l.url" class="pg" :aria-current="l.active ? 'page' : undefined" preserve-scroll v-html="l.label" />
                            <span v-else class="pg off" v-html="l.label" />
                        </template>
                    </nav>
                </div>
            </section>
            <p class="synthetic">Synthetic demo data.</p>
        </main>
    </RosterLayout>
</template>
