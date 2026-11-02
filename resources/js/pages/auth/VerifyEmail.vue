<script setup lang="ts">
import RosterAuthLayout from '@/layouts/RosterAuthLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps<{ status?: string }>();
const form = useForm({});
const submit = () => form.post(route('verification.send'));
</script>

<template>
    <Head title="Verify email" />
    <RosterAuthLayout title="Check your inbox" description="Click the link we sent to your work email to finish setting up your account.">
        <p v-if="status === 'verification-link-sent'" class="note success" role="status">A new verification link has been sent.</p>
        <form class="authform" @submit.prevent="submit">
            <button type="submit" class="btn primary block" :disabled="form.processing">Send the link again</button>
        </form>
        <div class="authlinks"><Link :href="route('logout')" method="post" as="button" class="linkbtn">Sign out</Link></div>
    </RosterAuthLayout>
</template>
