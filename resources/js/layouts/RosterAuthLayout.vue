<script setup lang="ts">
import { Users } from 'lucide-vue-next';

defineProps<{ title: string; description?: string }>();
</script>

<template>
    <main class="authwrap">
        <div class="authcard">
            <div class="logo"><span class="mk"><Users class="icon sm" /></span>Roster</div>
            <h1>{{ title }}</h1>
            <p v-if="description" class="lead">{{ description }}</p>
            <slot />
        </div>
        <p class="authfoot">Synthetic demo data · Roster HRIS</p>
    </main>
</template>
