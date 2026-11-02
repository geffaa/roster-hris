<script setup lang="ts">
import RosterLayout from '@/layouts/RosterLayout.vue';
import type { BreadcrumbItemType } from '@/types';

withDefaults(defineProps<{ breadcrumbs?: BreadcrumbItemType[] }>(), { breadcrumbs: () => [] });
</script>

<template>
    <!-- Settings pages from the starter kit render inside the Roster workspace shell. -->
    <RosterLayout current="Settings">
        <main class="page">
            <slot />
        </main>
    </RosterLayout>
</template>
