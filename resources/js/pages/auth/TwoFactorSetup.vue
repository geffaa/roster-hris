<script setup lang="ts">
import RosterAuthLayout from '@/layouts/RosterAuthLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps<{ qrSvg: string; secret: string; role: string | null }>();
const form = useForm({ code: '' });
const submit = () => form.post(route('two-factor.confirm'), { onFinish: () => form.reset('code') });
</script>

<template>
    <Head title="Set up two-factor sign-in" />
    <RosterAuthLayout title="Set up two-factor sign-in" :description="`Your role (${role ?? 'admin'}) can see salaries and ID numbers, so a code from your phone is required.`">
        <ol class="steps">
            <li>Open an authenticator app (Google Authenticator, 1Password, Authy).</li>
            <li>Scan this code, or type the key below.</li>
        </ol>
        <div class="qr" v-html="qrSvg"></div>
        <p class="secret"><span class="muted small">Setup key</span><code>{{ secret }}</code></p>
        <form class="authform" @submit.prevent="submit">
            <div class="field">
                <label for="code">6-digit code from the app</label>
                <input id="code" v-model="form.code" class="input code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required :class="{ invalid: form.errors.code }" />
                <span v-if="form.errors.code" class="err">{{ form.errors.code }}</span>
            </div>
            <button type="submit" class="btn primary block" :disabled="form.processing">Turn on two-factor</button>
        </form>
        <Link :href="route('logout')" method="post" as="button" class="btn ghost block">Sign out</Link>
    </RosterAuthLayout>
</template>
