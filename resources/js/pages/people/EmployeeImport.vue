<script setup lang="ts">
import RosterLayout from '@/layouts/RosterLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ChevronRight, CircleAlert, CircleCheck, Download, FileSpreadsheet, TriangleAlert, Upload } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface PreviewRow {
    line: number;
    data: Record<string, string>;
    errors: string[];
    warnings: string[];
}

const props = defineProps<{
    preview: { token: string; filename: string; rows: PreviewRow[] } | null;
    columns: Record<string, boolean>;
}>();

const form = useForm<{ file: File | null }>({ file: null });
const picker = ref<HTMLInputElement | null>(null);
const dragging = ref(false);

function setFile(file: File | undefined) {
    if (!file) return;
    form.file = file;
    form.post(route('employees.import.preview'), { forceFormData: true });
}

function onDrop(e: DragEvent) {
    dragging.value = false;
    setFile(e.dataTransfer?.files[0]);
}

const counts = computed(() => {
    const rows = props.preview?.rows ?? [];
    return {
        total: rows.length,
        errors: rows.filter((r) => r.errors.length).length,
        warnings: rows.filter((r) => !r.errors.length && r.warnings.length).length,
    };
});
const filter = ref<'all' | 'errors'>('all');
const shown = computed(() => (props.preview?.rows ?? []).filter((r) => filter.value === 'all' || r.errors.length));

const importing = ref(false);
function confirmImport() {
    if (!props.preview) return;
    importing.value = true;
    router.post(route('employees.import.store', { token: props.preview.token }), {}, { onFinish: () => (importing.value = false) });
}
</script>

<template>
    <Head title="Import employees" />
    <RosterLayout current="Directory">
        <main class="page">
            <nav class="crumbs" aria-label="Breadcrumb">
                <Link :href="route('employees.index')">Directory</Link><ChevronRight class="icon xs" /><span>Import</span>
            </nav>
            <div class="phead">
                <div>
                    <h1>Import employees</h1>
                    <p>Upload a CSV or Excel file. Every row is checked first; nothing is saved until you confirm.</p>
                </div>
                <div class="acts">
                    <a class="btn" :href="route('employees.import.template')"><Download class="icon sm" />Template (CSV)</a>
                </div>
            </div>

            <section v-if="!preview" class="card">
                <div class="card-b" style="padding-top: var(--s6)">
                    <div
                        class="drop"
                        :class="{ over: dragging }"
                        role="button"
                        tabindex="0"
                        @click="picker?.click()"
                        @keydown.enter.prevent="picker?.click()"
                        @dragover.prevent="dragging = true"
                        @dragleave="dragging = false"
                        @drop.prevent="onDrop"
                    >
                        <Upload class="icon" />
                        <b>{{ form.processing ? 'Checking the file…' : 'Drop a file here, or choose one' }}</b>
                        <span>.csv or .xlsx · up to 1,000 rows · 5 MB</span>
                        <input ref="picker" type="file" accept=".csv,.xlsx" class="sr" tabindex="-1" @change="setFile(($event.target as HTMLInputElement).files?.[0])" />
                    </div>
                    <p v-if="form.errors.file" class="note danger" role="alert"><CircleAlert class="icon sm" /><span>{{ form.errors.file }}</span></p>
                    <div class="colspec">
                        <h2>Columns</h2>
                        <ul>
                            <li v-for="(required, col) in columns" :key="col"><code>{{ col }}</code><span v-if="required" class="badge">Required</span></li>
                        </ul>
                        <p class="small muted">Dates as 2024-03-18 or 18/03/2024. Departments that don't exist yet are created; locations must already exist. A manager can appear later in the same file.</p>
                    </div>
                </div>
            </section>

            <template v-else>
                <div class="impsum">
                    <span class="file"><FileSpreadsheet class="icon" /><b>{{ preview.filename }}</b></span>
                    <span class="badge">{{ counts.total }} rows</span>
                    <span v-if="counts.errors" class="badge danger"><CircleAlert class="icon" />{{ counts.errors }} with errors</span>
                    <span v-if="counts.warnings" class="badge warning"><TriangleAlert class="icon" />{{ counts.warnings }} with warnings</span>
                    <span v-if="!counts.errors" class="badge success"><CircleCheck class="icon" />Ready to import</span>
                </div>
                <div v-if="counts.errors" class="note danger" role="alert" style="margin-bottom: var(--s5)">
                    <CircleAlert class="icon sm" />
                    <span><b>Fix {{ counts.errors }} {{ counts.errors === 1 ? 'row' : 'rows' }} in your file and upload it again.</b> Importing is blocked until every row passes, so the directory never ends up half-imported.</span>
                </div>
                <section class="card">
                    <div class="card-h">
                        <h2>Preview</h2>
                        <div class="acts">
                            <div class="seg" role="group" aria-label="Show rows">
                                <button type="button" :aria-pressed="filter === 'all'" @click="filter = 'all'">All rows</button>
                                <button type="button" :aria-pressed="filter === 'errors'" :disabled="!counts.errors" @click="filter = 'errors'">Errors only</button>
                            </div>
                        </div>
                    </div>
                    <div class="tablewrap">
                        <table class="t imp">
                            <thead>
                                <tr><th class="n">Line</th><th>Employee</th><th>Department</th><th>Manager</th><th>Hire date</th><th class="n">Base salary</th><th>Check</th></tr>
                            </thead>
                            <tbody>
                                <tr v-for="r in shown" :key="r.line" :class="{ bad: r.errors.length }">
                                    <td class="n num muted">{{ r.line }}</td>
                                    <td>
                                        <b>{{ r.data.full_name || '—' }}</b>
                                        <span class="sub"><span class="nowrap">{{ r.data.employee_no || 'no ID' }}</span> · {{ r.data.job_title || 'no title' }}</span>
                                    </td>
                                    <td>{{ r.data.department || '—' }}</td>
                                    <td class="num nowrap">{{ r.data.manager_employee_no || '—' }}</td>
                                    <td class="num nowrap">{{ r.data.hire_date || '—' }}</td>
                                    <td class="n num">{{ r.data.base_salary || '—' }}</td>
                                    <td>
                                        <div class="checks">
                                            <span v-for="e in r.errors" :key="e" class="chk bad"><CircleAlert class="icon xs" />{{ e }}</span>
                                            <span v-for="w in r.warnings" :key="w" class="chk warn"><TriangleAlert class="icon xs" />{{ w }}</span>
                                            <span v-if="!r.errors.length && !r.warnings.length" class="chk ok"><CircleCheck class="icon xs" />OK</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
                <div class="impacts">
                    <Link class="btn ghost" :href="route('employees.import.create')">Upload a different file</Link>
                    <button type="button" class="btn primary" :disabled="counts.errors > 0 || importing" @click="confirmImport">
                        Import {{ counts.total }} {{ counts.total === 1 ? 'employee' : 'employees' }}
                    </button>
                </div>
            </template>
            <p class="synthetic">Synthetic demo data.</p>
        </main>
    </RosterLayout>
</template>
