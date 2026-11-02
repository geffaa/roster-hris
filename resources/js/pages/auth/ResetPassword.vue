<script setup lang="ts">
import RosterAuthLayout from '@/layouts/RosterAuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps<{ token: string; email: string }>();
const form = useForm({ token: props.token, email: props.email, password: '', password_confirmation: '' });
const submit = () => form.post(route('password.store'), { onFinish: () => form.reset('password', 'password_confirmation') });
</script>

<template>
    <Head title="Choose a new password" />
    <RosterAuthLayout title="Choose a new password" description="Use at least 8 characters. A passphrase is easier to remember.">
        <form class="authform" @submit.prevent="submit">
            <div class="field">
                <label for="email">Work email</label>
                <input id="email" v-model="form.email" class="input" type="email" required readonly autocomplete="email" />
                <span v-if="form.errors.email" class="err">{{ form.errors.email }}</span>
            </div>
            <div class="field">
                <label for="password">New password</label>
                <input id="password" v-model="form.password" class="input" type="password" required autofocus autocomplete="new-password" :class="{ invalid: form.errors.password }" />
                <span v-if="form.errors.password" class="err">{{ form.errors.password }}</span>
            </div>
            <div class="field">
                <label for="password_confirmation">Repeat new password</label>
                <input id="password_confirmation" v-model="form.password_confirmation" class="input" type="password" required autocomplete="new-password" />
            </div>
            <button type="submit" class="btn primary block" :disabled="form.processing">Save password</button>
        </form>
    </RosterAuthLayout>
</template>
