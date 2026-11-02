<script setup lang="ts">
import type { RosterShared } from '@/types/roster';
import { usePage } from '@inertiajs/vue3';
import { CircleCheck, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const page = usePage<RosterShared>();
const message = ref<string | null>(null);
let timer: ReturnType<typeof setTimeout> | undefined;

watch(
    () => page.props.flash?.success,
    (value) => {
        message.value = value ?? null;
        clearTimeout(timer);
        if (value) timer = setTimeout(() => (message.value = null), 5000);
    },
    { immediate: true },
);
</script>

<template>
    <div class="toastwrap" aria-live="polite">
        <div v-if="message" class="toast" role="status">
            <CircleCheck class="icon" />
            <span>{{ message }}</span>
            <button type="button" class="toastx" aria-label="Dismiss" @click="message = null"><X class="icon sm" /></button>
        </div>
    </div>
</template>
