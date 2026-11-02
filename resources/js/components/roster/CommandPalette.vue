<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { CornerDownLeft, FileText, Search, UserRound } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

/** ⌘K / Ctrl+K: jump to a person or a page. Pages come from the sidebar the person already sees. */
const props = defineProps<{ pages: { name: string; href: string; group: string }[] }>();
const open = ref(false);
const q = ref('');
const people = ref<{ id: number; name: string; sub: string; url: string; resigned: boolean }[]>([]);
const active = ref(0);
const input = ref<HTMLInputElement | null>(null);
let timer: ReturnType<typeof setTimeout> | undefined;
let lastFocus: HTMLElement | null = null;

const pageHits = computed(() => {
    const s = q.value.trim().toLowerCase();
    return (s ? props.pages.filter((p) => `${p.group} ${p.name}`.toLowerCase().includes(s)) : props.pages).slice(0, 6);
});
const items = computed(() => [
    ...people.value.map((p) => ({ kind: 'person' as const, label: p.name, sub: p.sub + (p.resigned ? ' · left' : ''), url: p.url })),
    ...pageHits.value.map((p) => ({ kind: 'page' as const, label: p.name, sub: p.group, url: p.href })),
]);

watch(q, (v) => {
    clearTimeout(timer);
    active.value = 0;
    if (v.trim().length < 2) return (people.value = []);
    timer = setTimeout(async () => {
        const res = await fetch(`/search?q=${encodeURIComponent(v.trim())}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (res.ok && q.value === v) people.value = (await res.json()).people;
    }, 160);
});

async function show() {
    lastFocus = document.activeElement as HTMLElement | null;
    open.value = true;
    await nextTick();
    input.value?.focus();
}
function hide() {
    open.value = false;
    q.value = '';
    people.value = [];
    lastFocus?.focus();
}
function go(i = active.value) {
    const it = items.value[i];
    if (!it) return;
    hide();
    router.visit(it.url);
}
function key(e: KeyboardEvent) {
    if (e.key === 'ArrowDown') (active.value = Math.min(items.value.length - 1, active.value + 1)), e.preventDefault();
    else if (e.key === 'ArrowUp') (active.value = Math.max(0, active.value - 1)), e.preventDefault();
    else if (e.key === 'Enter') (go(), e.preventDefault());
    else if (e.key === 'Escape') hide();
}
function global(e: KeyboardEvent) {
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        open.value ? hide() : show();
    }
}
onMounted(() => window.addEventListener('keydown', global));
onBeforeUnmount(() => window.removeEventListener('keydown', global));
defineExpose({ show });
</script>

<template>
    <button class="cmdk" type="button" aria-label="Search people and pages" aria-keyshortcuts="Meta+K Control+K" @click="show">
        <Search class="icon sm" /><span>Search people and pages…</span><kbd>⌘K</kbd>
    </button>
    <Teleport to="body">
        <div v-if="open" class="pal-scrim" @click.self="hide">
            <div class="pal" role="dialog" aria-modal="true" aria-label="Search">
                <label class="pal-in">
                    <Search class="icon" />
                    <input
                        ref="input"
                        v-model="q"
                        type="text"
                        placeholder="Name, employee number, job title or page"
                        aria-label="Search"
                        role="combobox"
                        aria-expanded="true"
                        aria-controls="pal-list"
                        :aria-activedescendant="items.length ? `pal-${active}` : undefined"
                        autocomplete="off"
                        @keydown="key"
                    />
                    <kbd>esc</kbd>
                </label>
                <ul id="pal-list" role="listbox" class="pal-list">
                    <li v-for="(it, i) in items" :id="`pal-${i}`" :key="`${it.kind}${it.url}`" role="option" :aria-selected="i === active" @mouseenter="active = i" @click="go(i)">
                        <span class="pal-ic"><component :is="it.kind === 'person' ? UserRound : FileText" class="icon sm" /></span>
                        <span class="pal-t"><b>{{ it.label }}</b><small>{{ it.sub }}</small></span>
                        <CornerDownLeft v-if="i === active" class="icon xs muted" />
                    </li>
                    <li v-if="!items.length" class="pal-empty">{{ q.trim().length < 2 ? 'Type at least two letters to find people.' : 'No people or pages match.' }}</li>
                </ul>
            </div>
        </div>
    </Teleport>
</template>
