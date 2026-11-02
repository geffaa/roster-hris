<script setup lang="ts">
import { formatDate } from '@/composables/useRoster';
import RosterLayout from '@/layouts/RosterLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { BookOpen, Check, Clock, FileUp, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

defineProps<{ documents: { id: number; title: string; chunks: number; words: number; updated_at: string | null }[] }>();
const form = useForm({ title: '', body: '', file: null as File | null });
const fileInput = ref<HTMLInputElement | null>(null);
const confirming = ref<number | null>(null);
function pick(e: Event) {
    form.file = (e.target as HTMLInputElement).files?.[0] ?? null;
    if (form.file && !form.title) form.title = form.file.name.replace(/\.(md|txt)$/i, '').replace(/[-_]/g, ' ');
}
function save() {
    form.post(route('settings.policies.store'), { forceFormData: true, preserveScroll: true, onSuccess: () => form.reset() });
}
function remove(id: number) {
    if (confirming.value !== id) return (confirming.value = id);
    router.delete(route('settings.policies.destroy', { document: id }), { preserveScroll: true });
}
</script>

<template>
    <Head title="HR policies" />
    <RosterLayout current="HR policies">
        <main class="page">
            <div class="phead">
                <div>
                    <h1>HR policies</h1>
                    <p>The Ask HR assistant answers employees from these documents only, and cites the section it used.</p>
                </div>
                <div class="acts"><Link class="btn" :href="route('settings.time')"><Clock class="icon sm" />Time settings</Link></div>
            </div>
            <div class="setwrap" :class="{ withform: true }">
                <section class="card">
                    <div class="card-h"><h2>Documents</h2><span class="muted">{{ documents.length }}</span></div>
                    <div v-if="!documents.length" class="empty"><BookOpen class="icon" /><b>No policies yet</b><span>Add one so the assistant has something to answer from.</span></div>
                    <ul v-else class="dwatch">
                        <li v-for="d in documents" :key="d.id">
                            <span class="pal-ic"><BookOpen class="icon sm" /></span>
                            <div><b>{{ d.title }}</b><small>{{ d.words.toLocaleString() }} words · {{ d.chunks }} passages · updated {{ formatDate(d.updated_at) }}</small></div>
                            <button type="button" class="btn ghost sm danger" @click="remove(d.id)"><Trash2 class="icon sm" />{{ confirming === d.id ? 'Tap again to remove' : 'Remove' }}</button>
                        </li>
                    </ul>
                </section>
                <aside class="card setform">
                    <div class="card-h"><h2>Add a policy</h2></div>
                    <form class="card-b stack" @submit.prevent="save">
                        <div class="field"><label for="ptitle">Title</label><input id="ptitle" v-model="form.title" class="input" placeholder="e.g. Business travel" /><span v-if="form.errors.title" class="err">{{ form.errors.title }}</span></div>
                        <div class="field">
                            <label for="pbody">Policy text</label>
                            <textarea id="pbody" v-model="form.body" class="input" rows="9" placeholder="Paste the policy. Use # headings for sections so answers can point to the right part." />
                            <span v-if="form.errors.body" class="err">{{ form.errors.body }}</span>
                        </div>
                        <input ref="fileInput" type="file" accept=".md,.txt,text/plain,text/markdown" class="sr" @change="pick" />
                        <button type="button" class="drop" @click="fileInput?.click()"><FileUp class="icon" /><b>{{ form.file ? form.file.name : 'Or upload a .md or .txt file' }}</b><span class="small">Up to 500 KB</span></button>
                        <span v-if="form.errors.file" class="err">{{ form.errors.file }}</span>
                        <div class="formacts"><button type="submit" class="btn primary" :disabled="form.processing || !form.title || (!form.body && !form.file)"><Check class="icon sm" />Add policy</button></div>
                    </form>
                </aside>
            </div>
            <p class="synthetic">Synthetic demo policies for Nusantara Kreatif, written to match the rules configured in Roster.</p>
        </main>
    </RosterLayout>
</template>
