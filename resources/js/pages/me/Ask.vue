<script setup lang="ts">
import { xsrfToken } from '@/composables/useRoster';
import { useT } from '@/i18n';
import EmployeeAppLayout from '@/layouts/EmployeeAppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ArrowUp, BookOpen, Lock, Sparkles, UserRound } from 'lucide-vue-next';
import { nextTick, ref } from 'vue';

interface Source {
    kind: 'personal' | 'policy';
    title: string;
    heading: string | null;
    excerpt: string | null;
}
interface Turn {
    id?: number;
    question: string;
    segments: { text: string; cites: number[] }[];
    sources: Source[];
    mode: 'ai' | 'extractive' | 'refused';
    note: string | null;
}
const props = defineProps<{ history: Turn[]; ai: boolean }>();
const { t } = useT();
const turns = ref<Turn[]>([...props.history]);
const q = ref('');
const busy = ref(false);
const error = ref<string | null>(null);
const list = ref<HTMLElement | null>(null);

async function ask(text = q.value) {
    const question = text.trim();
    if (question.length < 3 || busy.value) return;
    busy.value = true;
    error.value = null;
    q.value = '';
    try {
        const res = await fetch(route('me.ask.store'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': xsrfToken() },
            credentials: 'same-origin',
            body: JSON.stringify({ question }),
        });
        if (!res.ok) throw new Error(String(res.status));
        turns.value.push(await res.json());
        await nextTick();
        list.value?.lastElementChild?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } catch {
        error.value = t('ask.failed');
        q.value = question;
    } finally {
        busy.value = false;
    }
}
/** Sources actually cited in this answer, numbered in order of first use. */
function cited(turn: Turn) {
    const order: number[] = [];
    turn.segments.forEach((s) => s.cites.forEach((c) => !order.includes(c) && order.push(c)));
    return order.map((i) => ({ i, n: order.indexOf(i) + 1, s: turn.sources[i] })).filter((x) => x.s);
}
const mark = (turn: Turn, i: number) => cited(turn).find((c) => c.i === i)?.n;
</script>

<template>
    <Head :title="t('ask.title')" />
    <EmployeeAppLayout current="me">
        <div class="hi"><h1>{{ t('ask.title') }}</h1><p>{{ t('ask.sub') }}</p></div>

        <div v-if="!turns.length" class="sx">
            <h2>{{ t('ask.suggestions') }}</h2>
            <div class="sugg">
                <button v-for="k in ['s1', 's2', 's3', 's4']" :key="k" type="button" class="fchip" @click="ask(t(`ask.${k}`))">{{ t(`ask.${k}`) }}</button>
            </div>
        </div>

        <div ref="list" class="chat">
            <article v-for="(turn, n) in turns" :key="turn.id ?? n" class="turn">
                <p class="qbubble">{{ turn.question }}</p>
                <div class="abubble" :class="turn.mode">
                    <p v-if="turn.note" class="small muted anote"><Sparkles class="icon xs" />{{ turn.mode === 'extractive' && !ai ? t('ask.demoNote') : turn.note }}</p>
                    <p class="atext">
                        <template v-for="(s, k) in turn.segments" :key="k"><span :class="{ quote: turn.mode === 'extractive' }">{{ s.text }}<sup v-for="c in s.cites" :key="c" class="cite">{{ mark(turn, c) }}</sup></span>{{ ' ' }}</template>
                    </p>
                    <div v-if="cited(turn).length" class="srcs">
                        <span class="small muted">{{ t('ask.sources') }}</span>
                        <details v-for="c in cited(turn)" :key="c.i" class="src">
                            <summary>
                                <span class="srcn">{{ c.n }}</span>
                                <component :is="c.s.kind === 'personal' ? UserRound : BookOpen" class="icon xs" />
                                {{ c.s.kind === 'personal' ? t('ask.yourData') : c.s.title }}<template v-if="c.s.heading"> · {{ c.s.heading }}</template>
                            </summary>
                            <p v-if="c.s.excerpt" class="small">{{ c.s.excerpt }}</p>
                        </details>
                    </div>
                </div>
            </article>
            <div v-if="busy" class="turn"><div class="abubble thinking small muted">{{ t('ask.thinking') }}</div></div>
        </div>

        <p v-if="error" class="err" role="alert">{{ error }}</p>
        <form class="askbar" @submit.prevent="ask()">
            <input v-model="q" class="input" :placeholder="t('ask.placeholder')" :aria-label="t('ask.title')" maxlength="500" autocomplete="off" />
            <button type="submit" class="btn primary" :disabled="busy || q.trim().length < 3" :aria-label="t('ask.send')"><ArrowUp class="icon sm" /></button>
        </form>
        <p class="small muted privacy"><Lock class="icon xs" />{{ t('ask.private') }}</p>
    </EmployeeAppLayout>
</template>
