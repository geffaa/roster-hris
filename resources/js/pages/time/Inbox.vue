<script setup lang="ts">
import Avatar from '@/components/roster/Avatar.vue';
import { formatDate, formatDateTime } from '@/composables/useRoster';
import RosterLayout from '@/layouts/RosterLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Check, CircleAlert, Clock3, Inbox, Landmark, Plane, Repeat2, Timer, Wrench, X } from 'lucide-vue-next';
import { ref } from 'vue';

interface Item {
    step_id: number;
    type: 'leave' | 'attendance_correction' | 'overtime';
    step: number;
    steps: number;
    hr_queue: boolean;
    delegated_from: string | null;
    requester: { id: number; name: string; title: string };
    submitted_at: string;
    title: string;
    summary: string;
    reason: string | null;
    color?: string;
    facts: string[];
}

defineProps<{ items: Item[]; attention: { id: number; employee: string; employee_id: number; date: string; in: string }[] }>();

const rejecting = ref<number | null>(null);
const form = useForm({ decision: 'approve' as 'approve' | 'reject', comment: '' });

function decide(item: Item, decision: 'approve' | 'reject') {
    form.decision = decision;
    form.post(route('approvals.decide', { step: item.step_id }), {
        preserveScroll: true,
        onSuccess: () => {
            rejecting.value = null;
            form.reset();
        },
    });
}

const icon: Record<string, unknown> = { leave: Plane, attendance_correction: Wrench, overtime: Timer, bank_change: Landmark };
</script>

<template>
    <Head title="Inbox" />
    <RosterLayout current="Inbox">
        <main class="page">
            <div class="phead">
                <div>
                    <h1>Inbox</h1>
                    <p>{{ items.length ? `${items.length} ${items.length === 1 ? 'request waits' : 'requests wait'} for you` : 'Nothing waiting for you' }}</p>
                </div>
            </div>

            <div class="grid-main">
                <div class="stack">
                    <div v-if="!items.length" class="card empty"><Inbox class="icon" /><b>You're all caught up</b><span>New leave, overtime, correction and bank account requests appear here.</span></div>
                    <article v-for="item in items" :key="item.step_id" class="card req">
                        <div class="req-h">
                            <Avatar :name="item.requester.name" />
                            <span class="who2">
                                <Link :href="route('employees.show', { employee: item.requester.id })" class="name">{{ item.requester.name }}</Link>
                                <span>{{ item.requester.title }} · sent {{ formatDateTime(item.submitted_at) }}</span>
                            </span>
                            <span class="tags">
                                <span v-if="item.steps > 1" class="badge info">Approval {{ item.step }} of {{ item.steps }}</span>
                                <span v-if="item.hr_queue" class="badge brand">HR queue</span>
                                <span v-if="item.delegated_from" class="badge warning"><Repeat2 class="icon" />For {{ item.delegated_from }}, on leave</span>
                            </span>
                        </div>
                        <div class="req-b">
                            <span class="kind" :style="item.color ? { background: item.color } : undefined"><component :is="icon[item.type]" class="icon sm" /></span>
                            <div>
                                <b>{{ item.title }}</b>
                                <p class="num">{{ item.summary }}</p>
                                <blockquote v-if="item.reason">“{{ item.reason }}”</blockquote>
                                <ul v-if="item.facts.length" class="facts">
                                    <li v-for="f in item.facts" :key="f">{{ f }}</li>
                                </ul>
                            </div>
                        </div>
                        <div v-if="rejecting === item.step_id" class="req-reject">
                            <label :for="`c${item.step_id}`">Reason for rejecting</label>
                            <input :id="`c${item.step_id}`" v-model="form.comment" class="input" maxlength="500" autocomplete="off" placeholder="e.g. Release week; could you move it to the following week?" />
                            <span v-if="form.errors.comment" class="err">{{ form.errors.comment }}</span>
                        </div>
                        <div class="req-f">
                            <template v-if="rejecting === item.step_id">
                                <button type="button" class="btn ghost" @click="rejecting = null">Back</button>
                                <button type="button" class="btn danger" :disabled="form.processing" @click="decide(item, 'reject')"><X class="icon sm" />Reject</button>
                            </template>
                            <template v-else>
                                <button type="button" class="btn" @click="(rejecting = item.step_id), form.clearErrors()"><X class="icon sm" />Reject</button>
                                <button type="button" class="btn primary" :disabled="form.processing" @click="decide(item, 'approve')"><Check class="icon sm" />Approve</button>
                            </template>
                        </div>
                    </article>
                </div>
                <aside class="stack">
                    <section class="card">
                        <div class="card-h"><h2>Needs attention</h2></div>
                        <div class="card-b">
                            <p v-if="!attention.length" class="small muted">No missing clock-outs in your team.</p>
                            <ul v-else class="attn">
                                <li v-for="a in attention" :key="a.id">
                                    <CircleAlert class="icon sm" />
                                    <span><Link :href="route('employees.show', { employee: a.employee_id })">{{ a.employee }}</Link><br /><span class="small muted">{{ formatDate(a.date) }} · in {{ a.in }}, no clock-out</span></span>
                                </li>
                            </ul>
                            <p v-if="attention.length" class="small muted hint2"><Clock3 class="icon xs" /> Ask them to send a correction from My time. It will then appear in this inbox for you to approve.</p>
                        </div>
                    </section>
                </aside>
            </div>
        </main>
    </RosterLayout>
</template>
