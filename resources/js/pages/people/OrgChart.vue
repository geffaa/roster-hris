<script setup lang="ts">
import OrgNode, { type OrgPerson } from '@/components/roster/OrgNode.vue';
import RSelect from '@/components/roster/RSelect.vue';
import RosterLayout from '@/layouts/RosterLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { List } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    roots: OrgPerson[];
    departments: { id: number; name: string }[];
    total: number | null;
    scope: 'company' | 'team';
}>();

/* Start with the top two levels open so large companies stay readable. */
const collapsed = ref(new Set<number>());
const walk = (nodes: OrgPerson[], fn: (p: OrgPerson, depth: number) => void, depth = 0) =>
    nodes.forEach((n) => {
        fn(n, depth);
        walk(n.children, fn, depth + 1);
    });
walk(props.roots, (p, depth) => {
    if (depth >= 1 && p.children.length) collapsed.value.add(p.id);
});

function toggle(id: number) {
    const next = new Set(collapsed.value);
    next.has(id) ? next.delete(id) : next.add(id);
    collapsed.value = next;
}
const expandAll = () => (collapsed.value = new Set());
const collapseAll = () => {
    const next = new Set<number>();
    walk(props.roots, (p) => p.children.length && next.add(p.id));
    collapsed.value = next;
};

const department = ref<string | null>(null);
const options = computed(() => [{ value: null, label: 'Highlight a department' }, ...props.departments.map((d) => ({ value: d.name, label: d.name }))]);
</script>

<template>
    <Head title="Org chart" />
    <RosterLayout current="Org chart">
        <main class="page">
            <div class="phead">
                <div>
                    <h1>{{ scope === 'team' ? 'My team structure' : 'Org chart' }}</h1>
                    <p v-if="total !== null">{{ total }} people · reporting lines from each profile's manager</p>
                    <p v-else>You and everyone who reports to you</p>
                </div>
                <div class="acts">
                    <Link class="btn" :href="route('employees.index')"><List class="icon sm" />Directory</Link>
                </div>
            </div>
            <section class="card">
                <div class="filters">
                    <div class="fsel"><RSelect v-model="department" :options="options" label="Highlight department" /></div>
                    <div class="seg" role="group" aria-label="Tree">
                        <button type="button" aria-pressed="false" @click="expandAll">Expand all</button>
                        <button type="button" aria-pressed="false" @click="collapseAll">Collapse all</button>
                    </div>
                </div>
                <div class="card-b orgwrap">
                    <ul class="otree root" role="tree" aria-label="Reporting lines">
                        <OrgNode v-for="r in roots" :key="r.id" :person="r" :collapsed="collapsed" :highlight="department" :depth="0" @toggle="toggle" />
                    </ul>
                </div>
            </section>
            <p class="synthetic">Synthetic demo data.</p>
        </main>
    </RosterLayout>
</template>
