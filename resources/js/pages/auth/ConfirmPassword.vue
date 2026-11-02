<script setup lang="ts">
import RosterAuthLayout from '@/layouts/RosterAuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({ password: '' });
const submit = () => form.post(route('password.confirm'), { onFinish: () => form.reset() });
</script>

<template>
    <Head title="Confirm password" />
    <RosterAuthLayout title="Confirm your password" description="This area handles sensitive data. Enter your password to continue.">
        <form class="authform" @submit.prevent="submit">
            <div class="field">
                <label for="password">Password</label>
                <input id="password" v-model="form.password" class="input" type="password" required autofocus autocomplete="current-password" :class="{ invalid: form.errors.password }" />
                <span v-if="form.errors.password" class="err">{{ form.errors.password }}</span>
            </div>
            <button type="submit" class="btn primary block" :disabled="form.processing">Confirm</button>
        </form>
    </RosterAuthLayout>
</template>
