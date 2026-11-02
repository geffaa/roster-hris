<script setup lang="ts">
import RosterAuthLayout from '@/layouts/RosterAuthLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const useRecovery = ref(false);
const form = useForm({ code: '', recovery_code: '' });
const submit = () =>
    form
        .transform((d) => (useRecovery.value ? { recovery_code: d.recovery_code } : { code: d.code }))
        .post(route('two-factor.verify'), { onFinish: () => form.reset() });
</script>

<template>
    <Head title="Two-factor check" />
    <RosterAuthLayout title="Confirm it's you" :description="useRecovery ? 'Enter one of your recovery codes. Each code works once.' : 'Enter the 6-digit code from your authenticator app.'">
        <form class="authform" @submit.prevent="submit">
            <div v-if="!useRecovery" class="field">
                <label for="code">Authentication code</label>
                <input id="code" v-model="form.code" class="input code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" autofocus required :class="{ invalid: form.errors.code }" />
                <span v-if="form.errors.code" class="err">{{ form.errors.code }}</span>
            </div>
            <div v-else class="field">
                <label for="rc">Recovery code</label>
                <input id="rc" v-model="form.recovery_code" class="input num" autocomplete="off" autofocus required placeholder="xxxxx-xxxxx" :class="{ invalid: form.errors.recovery_code }" />
                <span v-if="form.errors.recovery_code" class="err">{{ form.errors.recovery_code }}</span>
            </div>
            <button type="submit" class="btn primary block" :disabled="form.processing">Continue</button>
        </form>
        <div class="authlinks">
            <button type="button" class="linkbtn" @click="useRecovery = !useRecovery">{{ useRecovery ? 'Use the authenticator app' : 'Lost your phone? Use a recovery code' }}</button>
            <Link :href="route('logout')" method="post" as="button" class="linkbtn">Sign out</Link>
        </div>
    </RosterAuthLayout>
</template>
