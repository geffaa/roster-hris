<script setup lang="ts">
import RosterAuthLayout from '@/layouts/RosterAuthLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Copy } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{ codes: string[] }>();
const copied = ref(false);
async function copy() {
    await navigator.clipboard.writeText(props.codes.join('\n'));
    copied.value = true;
}
</script>

<template>
    <Head title="Recovery codes" />
    <RosterAuthLayout title="Save your recovery codes" description="If you lose your phone, each of these codes lets you sign in once. They will not be shown again.">
        <ul class="codes">
            <li v-for="c in codes" :key="c"><code>{{ c }}</code></li>
        </ul>
        <button type="button" class="btn block" @click="copy"><Copy class="icon sm" />{{ copied ? 'Copied' : 'Copy all' }}</button>
        <Link :href="route('dashboard')" class="btn primary block">I have saved them, continue</Link>
    </RosterAuthLayout>
</template>
