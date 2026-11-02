<script setup lang="ts">
import RosterAuthLayout from '@/layouts/RosterAuthLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Check } from 'lucide-vue-next';

defineProps<{ status?: string; canResetPassword: boolean }>();

const form = useForm({ email: '', password: '', remember: false as boolean });
const submit = () => form.post(route('login'), { onFinish: () => form.reset('password') });
</script>

<template>
    <Head title="Log in" />
    <RosterAuthLayout title="Sign in to Roster" description="Use the work email your HR team set up for you.">
        <p v-if="status" class="note success" role="status">{{ status }}</p>
        <form class="authform" @submit.prevent="submit">
            <div class="field">
                <label for="email">Work email</label>
                <input id="email" v-model="form.email" class="input" type="email" required autofocus autocomplete="email" :class="{ invalid: form.errors.email }" />
                <span v-if="form.errors.email" class="err">{{ form.errors.email }}</span>
            </div>
            <div class="field">
                <div class="lblrow">
                    <label for="password">Password</label>
                    <Link v-if="canResetPassword" :href="route('password.request')" class="small">Forgot password?</Link>
                </div>
                <input id="password" v-model="form.password" class="input" type="password" required autocomplete="current-password" :class="{ invalid: form.errors.password }" />
                <span v-if="form.errors.password" class="err">{{ form.errors.password }}</span>
            </div>
            <span class="cbx" role="checkbox" tabindex="0" :aria-checked="form.remember" @click="form.remember = !form.remember" @keydown.space.prevent="form.remember = !form.remember">
                <span class="box"><Check v-if="form.remember" class="icon" /></span>Keep me signed in on this device
            </span>
            <button type="submit" class="btn primary block" :disabled="form.processing">Sign in</button>
        </form>
        <details class="demo">
            <summary>Demo accounts</summary>
            <p>Password for all: <code>password</code></p>
            <ul>
                <li><code>hr@roster.test</code> HR Admin (asks you to set up 2FA)</li>
                <li><code>manager@roster.test</code> Manager</li>
                <li><code>employee@roster.test</code> Employee</li>
                <li><code>auditor@roster.test</code> Auditor</li>
                <li><code>super@roster.test</code> Super Admin, two companies</li>
            </ul>
        </details>
    </RosterAuthLayout>
</template>
