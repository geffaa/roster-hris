<script setup lang="ts">
import RSelect from '@/components/roster/RSelect.vue';
import { useT } from '@/i18n';
import EmployeeAppLayout from '@/layouts/EmployeeAppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Clock3, Landmark, ShieldCheck } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    me: {
        name: string;
        employee_no: string;
        job_title: string;
        department: string | null;
        location: string | null;
        manager: string | null;
        work_email: string | null;
        hire_date: string;
        phone: string | null;
        bank: { name: string; last4: string } | null;
    };
    pending: { id: number; bank_name: string; last4: string; since: string } | null;
    banks: string[];
}>();
const { t, locale, dateLocale } = useT();
const date = (iso: string) => new Intl.DateTimeFormat(dateLocale.value, { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(`${iso}T00:00:00`));

const contact = useForm({ phone: props.me.phone ?? '' });
const bank = useForm({ bank_name: props.banks[0] ?? '', account_number: '', holder_name: props.me.name });
const changing = ref(false);
const bankOptions = computed(() => props.banks.map((b) => ({ value: b, label: b })));
function sendBank() {
    bank.post(route('me.profile.bank'), { preserveScroll: true, onSuccess: () => ((changing.value = false), bank.reset('account_number')) });
}
function setLocale(l: 'en' | 'id') {
    if (l !== locale.value) router.put(route('me.locale'), { locale: l }, { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('me.title')" />
    <EmployeeAppLayout current="me">
        <div class="hi"><h1>{{ me.name }}</h1><p>{{ me.job_title }}</p></div>

        <section class="sx">
            <h2>{{ t('me.details') }}</h2>
            <dl class="lines">
                <div><dt>{{ t('me.employeeNo') }}</dt><dd>{{ me.employee_no }}</dd></div>
                <div v-if="me.department"><dt>{{ t('me.department') }}</dt><dd>{{ me.department }}</dd></div>
                <div v-if="me.location"><dt>{{ t('me.location') }}</dt><dd>{{ me.location }}</dd></div>
                <div v-if="me.manager"><dt>{{ t('me.manager') }}</dt><dd>{{ me.manager }}</dd></div>
                <div v-if="me.work_email"><dt>{{ t('me.workEmail') }}</dt><dd class="wrap">{{ me.work_email }}</dd></div>
                <div><dt>{{ t('me.since') }}</dt><dd>{{ date(me.hire_date) }}</dd></div>
            </dl>
        </section>

        <form class="sx" @submit.prevent="contact.put(route('me.profile.contact'), { preserveScroll: true })">
            <h2>{{ t('me.contact') }}</h2>
            <div class="field"><label for="phone">{{ t('me.phone') }}</label><input id="phone" v-model="contact.phone" class="input" inputmode="tel" autocomplete="tel" /></div>
            <span v-if="contact.errors.phone" class="err">{{ contact.errors.phone }}</span>
            <div class="formacts"><button type="submit" class="btn" :disabled="contact.processing || !contact.isDirty">{{ t('me.save') }}</button></div>
        </form>

        <section class="sx">
            <h2>{{ t('me.bank') }}</h2>
            <div v-if="me.bank" class="bankcard"><Landmark class="icon sm" /><span><small>{{ t('me.current') }}</small><b>{{ me.bank.name }} ···· {{ me.bank.last4 }}</b></span></div>
            <p v-else class="note warning small">{{ t('me.noBank') }}</p>

            <div v-if="pending" class="note info small">
                <Clock3 class="icon sm" />
                <span><b>{{ t('me.pendingTitle') }}.</b> {{ t('me.pending', { bank: pending.bank_name, last4: pending.last4, date: date(pending.since) }) }}</span>
            </div>
            <button v-if="pending" type="button" class="linkbtn small" @click="router.delete(route('me.profile.bank.cancel', { change: pending.id }), { preserveScroll: true })">{{ t('me.withdraw') }}</button>

            <button v-if="!pending && !changing" type="button" class="btn" @click="changing = true">{{ t('me.change') }}</button>
            <form v-if="changing" class="stack bankform" @submit.prevent="sendBank">
                <div class="field">
                    <label>{{ t('me.bankName') }}</label>
                    <RSelect v-if="bankOptions.length" v-model="bank.bank_name as any" :options="bankOptions" :label="t('me.bankName')" />
                    <input v-else v-model="bank.bank_name" class="input" />
                </div>
                <div class="field"><label for="acct">{{ t('me.accountNumber') }}</label><input id="acct" v-model="bank.account_number" class="input" inputmode="numeric" autocomplete="off" /></div>
                <span v-if="bank.errors.account_number" class="err">{{ bank.errors.account_number }}</span>
                <div class="field"><label for="holder">{{ t('me.holder') }}</label><input id="holder" v-model="bank.holder_name" class="input" /><span class="hint">{{ t('me.holderHint') }}</span></div>
                <p class="small muted why"><ShieldCheck class="icon xs" />{{ t('me.why') }}</p>
                <div class="formacts">
                    <button type="button" class="btn ghost" @click="changing = false">{{ t('me.cancel') }}</button>
                    <button type="submit" class="btn primary" :disabled="bank.processing || !bank.account_number">{{ t('me.send') }}</button>
                </div>
            </form>
        </section>

        <section class="sx">
            <h2>{{ t('me.language') }}</h2>
            <div class="seg" role="group" :aria-label="t('me.language')">
                <button type="button" :aria-pressed="locale === 'en'" @click="setLocale('en')">English</button>
                <button type="button" :aria-pressed="locale === 'id'" @click="setLocale('id')">Bahasa Indonesia</button>
            </div>
            <p class="small muted">{{ t('me.langHint') }}</p>
        </section>
        <p class="synthetic">{{ t('me.demo') }}</p>
    </EmployeeAppLayout>
</template>
