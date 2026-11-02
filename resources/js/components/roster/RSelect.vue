<script setup lang="ts">
import { onClickOutside } from '@vueuse/core';
import { Check, ChevronDown } from 'lucide-vue-next';
import { computed, nextTick, ref, useId } from 'vue';

export interface Option {
    value: string | number | null;
    label: string;
    hint?: string;
}

const props = defineProps<{ modelValue: string | number | null; options: Option[]; label: string; placeholder?: string }>();
const emit = defineEmits<{ 'update:modelValue': [value: string | number | null] }>();

const open = ref(false);
const active = ref(0);
const root = ref<HTMLElement | null>(null);
const list = ref<HTMLElement | null>(null);
const id = useId();

const selected = computed(() => props.options.find((o) => o.value === props.modelValue) ?? null);

onClickOutside(root, () => (open.value = false));

async function toggle() {
    open.value = !open.value;
    if (open.value) {
        active.value = Math.max(0, props.options.findIndex((o) => o.value === props.modelValue));
        await nextTick();
        list.value?.focus();
    }
}

function choose(option: Option) {
    emit('update:modelValue', option.value);
    open.value = false;
}

function onKey(e: KeyboardEvent) {
    if (e.key === 'ArrowDown') active.value = Math.min(props.options.length - 1, active.value + 1);
    else if (e.key === 'ArrowUp') active.value = Math.max(0, active.value - 1);
    else if (e.key === 'Home') active.value = 0;
    else if (e.key === 'End') active.value = props.options.length - 1;
    else if (e.key === 'Enter' || e.key === ' ') choose(props.options[active.value]);
    else if (e.key === 'Escape' || e.key === 'Tab') open.value = false;
    else return;
    e.preventDefault();
}
</script>

<template>
    <div ref="root" class="popwrap">
        <button
            type="button"
            class="trigger"
            :aria-label="label"
            aria-haspopup="listbox"
            :aria-expanded="open"
            :aria-controls="`${id}-list`"
            @click="toggle"
        >
            <span class="val" :class="{ ph: !selected }">{{ selected?.label ?? placeholder ?? 'Select' }}</span>
            <ChevronDown class="icon sm" />
        </button>
        <div v-if="open" class="pop">
            <ul
                :id="`${id}-list`"
                ref="list"
                class="listbox"
                role="listbox"
                tabindex="-1"
                :aria-label="label"
                :aria-activedescendant="`${id}-opt-${active}`"
                @keydown="onKey"
            >
                <li
                    v-for="(option, i) in options"
                    :id="`${id}-opt-${i}`"
                    :key="String(option.value)"
                    role="option"
                    :aria-selected="option.value === modelValue"
                    :class="{ active: i === active }"
                    @mouseenter="active = i"
                    @click="choose(option)"
                >
                    <span>
                        {{ option.label }}
                        <small v-if="option.hint">{{ option.hint }}</small>
                    </span>
                    <Check v-if="option.value === modelValue" class="icon sm chk" />
                </li>
            </ul>
        </div>
    </div>
</template>
