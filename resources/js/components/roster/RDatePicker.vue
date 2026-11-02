<script setup lang="ts">
import { onClickOutside } from '@vueuse/core';
import { CalendarDays, ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{ modelValue: string | null; label: string; min?: string; placeholder?: string }>();
const emit = defineEmits<{ 'update:modelValue': [value: string] }>();

const pad = (n: number) => String(n).padStart(2, '0');
const iso = (d: Date) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
const todayIso = iso(new Date());

const open = ref(false);
const root = ref<HTMLElement | null>(null);
const start = props.modelValue ? new Date(`${props.modelValue}T00:00:00`) : new Date();
const view = ref(new Date(start.getFullYear(), start.getMonth(), 1));

onClickOutside(root, () => (open.value = false));

/* Each time it opens, show the month of the chosen date (or of the earliest allowed date). */
function toggle() {
    open.value = !open.value;
    if (!open.value) return;
    const anchor = props.modelValue ?? (props.min && props.min > todayIso ? props.min : null);
    if (anchor) {
        const d = new Date(`${anchor}T00:00:00`);
        view.value = new Date(d.getFullYear(), d.getMonth(), 1);
    }
}

const monthLabel = computed(() => new Intl.DateTimeFormat('en-GB', { month: 'long', year: 'numeric' }).format(view.value));
const display = computed(() =>
    props.modelValue
        ? new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(`${props.modelValue}T00:00:00`))
        : null,
);

/** Six Monday-first weeks covering the visible month. */
const days = computed(() => {
    const first = new Date(view.value);
    const offset = (first.getDay() + 6) % 7;
    const cursor = new Date(first.getFullYear(), first.getMonth(), 1 - offset);
    return Array.from({ length: 42 }, () => {
        const d = new Date(cursor);
        cursor.setDate(cursor.getDate() + 1);
        const value = iso(d);
        return {
            value,
            day: d.getDate(),
            out: d.getMonth() !== view.value.getMonth(),
            weekend: d.getDay() === 0 || d.getDay() === 6,
            disabled: props.min ? value < props.min : false,
        };
    });
});

function shift(months: number) {
    view.value = new Date(view.value.getFullYear(), view.value.getMonth() + months, 1);
}

function pick(value: string) {
    emit('update:modelValue', value);
    open.value = false;
}
</script>

<template>
    <div ref="root" class="popwrap">
        <button type="button" class="trigger" :aria-label="label" aria-haspopup="dialog" :aria-expanded="open" @click="toggle">
            <CalendarDays class="icon sm" />
            <span class="val" :class="{ ph: !display }">{{ display ?? (props.placeholder ?? 'Pick a date') }}</span>
        </button>
        <div v-if="open" class="pop cal" role="dialog" :aria-label="label" @keydown.esc="open = false">
            <div class="cal-h">
                <b>{{ monthLabel }}</b>
                <span class="nav">
                    <button type="button" aria-label="Previous month" @click="shift(-1)"><ChevronLeft class="icon sm" /></button>
                    <button type="button" aria-label="Next month" @click="shift(1)"><ChevronRight class="icon sm" /></button>
                </span>
            </div>
            <div class="cal-g">
                <span v-for="d in ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su']" :key="d" class="dow">{{ d }}</span>
                <button
                    v-for="d in days"
                    :key="d.value"
                    type="button"
                    :class="{ out: d.out, we: d.weekend && !d.out, today: d.value === todayIso }"
                    :aria-selected="d.value === modelValue"
                    :disabled="d.disabled"
                    :aria-label="d.value"
                    @click="pick(d.value)"
                >
                    {{ d.day }}
                </button>
            </div>
            <div class="cal-f">
                <span>Weekends shaded</span>
                <button type="button" class="btn ghost sm" @click="pick(todayIso)">Today</button>
            </div>
        </div>
    </div>
</template>
