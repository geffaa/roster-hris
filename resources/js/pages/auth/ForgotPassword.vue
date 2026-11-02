<script setup lang="ts">
import RosterAuthLayout from '@/layouts/RosterAuthLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps<{ status?: string }>();
const form = useForm({ email: '' });
const submit = () => form.post(route('password.email'));
</script>

<template>
    <Head title="Forgot password" />
    <RosterAuthLayout title="Reset your password" description="We'll email a reset link to your work address.">
        <p v-if="status" class="note success" role="status">{{ status }}</p>
        <form class="authform" @submit.prevent="submit">
            <div class="field">
                <label for="email">Work email</label>
                <input id="email" v-model="form.email" class="input" type="email" required autofocus autocomplete="email" :class="{ invalid: form.errors.email }" />
                <span v-if="form.errors.email" class="err">{{ form.errors.email }}</span>
            </div>
            <button type="submit" class="btn primary block" :disabled="form.processing">Email reset link</button>
        </form>
        <div class="authlinks"><Link :href="route('login')" class="linkbtn">Back to sign in</Link></div>
    </RosterAuthLayout>
</template>
