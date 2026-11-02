<script setup lang="ts">
import { avatarClass, initials } from '@/composables/useRoster';
import { computed } from 'vue';

const props = defineProps<{ name: string; size?: 'sm' | 'lg' }>();
const ini = computed(() => initials(props.name));
</script>

<template>
    <span class="av" :class="[size, avatarClass(ini)]" aria-hidden="true">{{ ini }}</span>
</template>
