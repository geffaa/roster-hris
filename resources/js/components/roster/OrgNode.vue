<script setup lang="ts">
import Avatar from '@/components/roster/Avatar.vue';
import { Link } from '@inertiajs/vue3';
import { ChevronDown, ChevronRight } from 'lucide-vue-next';
import { computed } from 'vue';

export interface OrgPerson {
    id: number;
    name: string;
    title: string;
    department: string | null;
    on_leave: boolean;
    size: number;
    children: OrgPerson[];
}

const props = defineProps<{ person: OrgPerson; collapsed: Set<number>; highlight: string | null; depth: number }>();
const emit = defineEmits<{ toggle: [id: number] }>();

const open = computed(() => !props.collapsed.has(props.person.id));
const dim = computed(() => props.highlight !== null && props.person.department !== props.highlight);
</script>

<template>
    <li class="onode" :class="{ dim }">
        <div class="ocard">
            <button
                v-if="person.children.length"
                type="button"
                class="otoggle"
                :aria-expanded="open"
                :aria-label="`${open ? 'Collapse' : 'Expand'} ${person.name}'s team`"
                @click="emit('toggle', person.id)"
            >
                <component :is="open ? ChevronDown : ChevronRight" class="icon sm" />
            </button>
            <span v-else class="otoggle spacer" aria-hidden="true"></span>
            <Avatar :name="person.name" size="sm" />
            <span class="ot">
                <Link :href="route('employees.show', { employee: person.id })" class="name">{{ person.name }}</Link>
                <span>{{ person.title }}<template v-if="person.department"> · {{ person.department }}</template></span>
            </span>
            <span v-if="person.on_leave" class="badge info">On leave</span>
            <span v-if="person.size" class="osize" :title="`${person.size} people in this team`">{{ person.size }}</span>
        </div>
        <ul v-if="person.children.length && open" class="otree" role="group">
            <OrgNode
                v-for="child in person.children"
                :key="child.id"
                :person="child"
                :collapsed="collapsed"
                :highlight="highlight"
                :depth="depth + 1"
                @toggle="emit('toggle', $event)"
            />
        </ul>
    </li>
</template>
