<script setup lang="ts">
import Avatar from '@/components/roster/Avatar.vue';
import RDatePicker from '@/components/roster/RDatePicker.vue';
import { addDays, formatDate, formatDateTime, formatMoney, isoDate, xsrfToken } from '@/composables/useRoster';
import RosterLayout from '@/layouts/RosterLayout.vue';
import RSelect from '@/components/roster/RSelect.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Briefcase, Building2, ChevronRight, Download, EyeOff, FileText, History, Mail, Plus, ShieldCheck, Trash2, Upload, User } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

interface Identifier {
    id: number;
    type: string;
    label: string;
    masked: string;
}
interface CompRow {
    id: number;
    effective_from: string;
    base_salary: string;
    currency: string;
    tax_status: string | null;
    reason: string;
    author: string | null;
    is_current: boolean;
}
interface DocRow {
    id: number;
    name: string;
    type: string;
    extension: string;
    uploaded_at: string;
    expires_at: string | null;
}
interface ActivityRow {
    id: number;
    event: string;
    subject: string;
    user: string;
    at: string;
    changes: string[];
    tags: string | null;
}

const props = defineProps<{
    employee: {
        id: number;
        employee_no: string;
        full_name: string;
        job_title: string;
        work_email: string | null;
        status: 'active' | 'on_leave' | 'resigned';
        hire_date: string;
        department: string | null;
        location: string | null;
        manager: { id: number; name: string; title: string } | null;
        reports: { id: number; name: string; title: string }[];
        contract: { type: string; start_date: string; end_date: string | null } | null;
    };
    identifiers: Identifier[];
    compensation: CompRow[] | null;
    activity: ActivityRow[] | null;
    documents: DocRow[] | null;
    documentTypes: { value: string; label: string }[];
    can: { reveal: boolean; manageCompensation: boolean; manageDocuments: boolean };
    currency: string;
}>();

type Tab = 'overview' | 'pay' | 'documents' | 'activity';
const today = isoDate();
const tab = ref<Tab>('overview');

const current = computed(() => props.compensation?.find((c) => c.is_current) ?? null);
const contractLabel: Record<string, string> = { permanent: 'Permanent', fixed_term: 'Fixed term', intern: 'Internship', part_time: 'Part time' };
const statusBadge = { active: ['Active', 'success'], on_leave: ['On leave', 'info'], resigned: ['Left', ''] } as const;

/* Revealing a full ID: one request per click, auto-hidden after 30 seconds. */
const revealed = reactive<Record<number, string>>({});
const revealing = ref<number | null>(null);
const revealError = ref<string | null>(null);

async function reveal(identifier: Identifier) {
    revealing.value = identifier.id;
    revealError.value = null;
    try {
        const res = await fetch(route('employees.identifiers.reveal', { employee: props.employee.id, identifier: identifier.id }), {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-XSRF-TOKEN': xsrfToken() },
            credentials: 'same-origin',
        });
        if (!res.ok) throw new Error(res.status === 429 ? 'Too many reveals. Try again in a minute.' : 'Could not show this number.');
        revealed[identifier.id] = (await res.json()).value;
        setTimeout(() => delete revealed[identifier.id], 30_000);
    } catch (e) {
        revealError.value = (e as Error).message;
    } finally {
        revealing.value = null;
    }
}

/* Salary change: appended as a new effective-dated row, never edited in place. */
const adding = ref(false);
const form = useForm({ effective_from: null as string | null, base_salary: '', tax_status: current.value?.tax_status ?? '', reason: '' });

function saveSalary() {
    form.post(route('employees.compensation.store', { employee: props.employee.id }), {
        preserveScroll: true,
        onSuccess: () => {
            adding.value = false;
            form.reset();
        },
    });
}

/* Documents: custom drop zone; the hidden file input only receives the chosen file. */
const uploading = ref(false);
const docPicker = ref<HTMLInputElement | null>(null);
const docForm = useForm<{ file: File | null; type: string | null; name: string; expires_at: string | null }>({ file: null, type: 'contract', name: '', expires_at: null });
const tomorrow = addDays(isoDate(), 1);

function pickDoc(file: File | undefined) {
    if (!file) return;
    docForm.file = file;
    if (!docForm.name) docForm.name = file.name.replace(/\.[^.]+$/, '');
}
function uploadDoc() {
    docForm.post(route('employees.documents.store', { employee: props.employee.id }), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            uploading.value = false;
            docForm.reset();
        },
    });
}
function removeDoc(doc: DocRow) {
    if (!window.confirm(`Remove “${doc.name}”? This cannot be undone.`)) return;
    router.delete(route('employees.documents.destroy', { employee: props.employee.id, document: doc.id }), { preserveScroll: true });
}
const expiry = (d: DocRow) => {
    if (!d.expires_at) return null;
    const start = new Date();
    start.setHours(0, 0, 0, 0);
    const days = Math.round((new Date(`${d.expires_at}T00:00:00`).getTime() - start.getTime()) / 86_400_000);
    return days < 0 ? { label: 'Expired', cls: 'danger' } : days <= 30 ? { label: `Expires in ${days} d`, cls: 'warning' } : { label: `Until ${formatDate(d.expires_at)}`, cls: '' };
};

const eventLabel = (a: ActivityRow) => {
    if (a.event === 'revealed') return 'Viewed a full ID number';
    const subject = { Employee: 'profile', Compensation: 'salary', EmployeeIdentifier: 'ID number', Document: 'document' }[a.subject] ?? a.subject;
    return `${a.event === 'created' ? 'Added' : a.event === 'updated' ? 'Changed' : 'Removed'} ${subject}`;
};
</script>

<template>
    <Head :title="employee.full_name" />
    <RosterLayout :current="can.manageCompensation || activity ? 'Directory' : 'My profile'">
        <main class="page">
            <nav class="crumbs" aria-label="Breadcrumb">
                <Link :href="route('employees.index')">People</Link><ChevronRight class="icon xs" /><span>{{ employee.full_name }}</span>
            </nav>

            <section class="hero">
                <Avatar :name="employee.full_name" size="lg" />
                <div>
                    <h1>{{ employee.full_name }}</h1>
                    <div class="meta">
                        <span><Briefcase class="icon sm" />{{ employee.job_title }}</span>
                        <span><Building2 class="icon sm" />{{ employee.department ?? 'No department' }} · {{ employee.location ?? 'No location' }}</span>
                        <span v-if="employee.manager"><User class="icon sm" />Reports to <Link :href="route('employees.show', { employee: employee.manager.id })">{{ employee.manager.name }}</Link></span>
                        <span class="badge" :class="statusBadge[employee.status][1]">{{ statusBadge[employee.status][0] }}</span>
                    </div>
                </div>
                <div class="acts">
                    <a v-if="employee.work_email" class="btn" :href="`mailto:${employee.work_email}`"><Mail class="icon sm" />Email</a>
                </div>
            </section>

            <nav class="subtabs" aria-label="Profile sections">
                <a href="#" :aria-current="tab === 'overview' ? 'page' : undefined" @click.prevent="tab = 'overview'">Overview</a>
                <a v-if="compensation" href="#" :aria-current="tab === 'pay' ? 'page' : undefined" @click.prevent="tab = 'pay'">Job &amp; pay</a>
                <a v-if="documents" href="#" :aria-current="tab === 'documents' ? 'page' : undefined" @click.prevent="tab = 'documents'">
                    Documents <span class="ct">{{ documents.length }}</span>
                </a>
                <a v-if="activity" href="#" :aria-current="tab === 'activity' ? 'page' : undefined" @click.prevent="tab = 'activity'">
                    Activity <span class="ct">{{ activity.length }}</span>
                </a>
            </nav>

            <div v-if="tab === 'overview'" class="cols">
                <section class="card" aria-labelledby="pe">
                    <div class="card-h"><h2 id="pe">Personal</h2></div>
                    <div class="card-b">
                        <div class="kv">
                            <span class="k">Employee ID</span><span class="v">{{ employee.employee_no }}</span>
                            <template v-for="i in identifiers" :key="i.id">
                                <span class="k">{{ i.label }}</span>
                                <span class="v">
                                    <span class="masked">
                                        <span class="num">{{ revealed[i.id] ?? i.masked }}</span>
                                        <button v-if="can.reveal && !revealed[i.id]" type="button" :disabled="revealing === i.id" @click="reveal(i)">
                                            {{ revealing === i.id ? '…' : 'Show' }}
                                        </button>
                                        <button v-else-if="revealed[i.id]" type="button" aria-label="Hide" @click="delete revealed[i.id]"><EyeOff class="icon xs" /></button>
                                    </span>
                                </span>
                            </template>
                            <span class="k">Work email</span><span class="v">{{ employee.work_email ?? '—' }}</span>
                        </div>
                        <p v-if="revealError" class="err" role="alert">{{ revealError }}</p>
                        <p class="small muted note-line">
                            <ShieldCheck class="icon xs" />
                            {{ can.reveal ? 'Showing a full ID number is recorded in the audit log.' : 'ID numbers are masked for your role.' }}
                        </p>
                    </div>
                </section>

                <section class="card" aria-labelledby="jb">
                    <div class="card-h"><h2 id="jb">Employment</h2></div>
                    <div class="card-b">
                        <div class="kv">
                            <span class="k">Start date</span><span class="v">{{ formatDate(employee.hire_date) }}</span>
                            <span class="k">Contract</span>
                            <span class="v">
                                {{ employee.contract ? contractLabel[employee.contract.type] : '—' }}
                                <template v-if="employee.contract?.end_date"> · until {{ formatDate(employee.contract.end_date) }}</template>
                            </span>
                            <span class="k">Location</span><span class="v">{{ employee.location ?? '—' }}</span>
                            <template v-if="current">
                                <span class="k">Base salary</span><span class="v">{{ formatMoney(current.base_salary, current.currency) }} / month</span>
                                <span class="k">Tax status</span><span class="v">{{ current.tax_status ?? '—' }}</span>
                            </template>
                        </div>
                    </div>
                </section>

                <section v-if="employee.reports.length" class="card span2" aria-labelledby="rp">
                    <div class="card-h"><h2 id="rp">Direct reports</h2><p>{{ employee.reports.length }}</p></div>
                    <div class="card-b reports">
                        <Link v-for="r in employee.reports" :key="r.id" class="rep" :href="route('employees.show', { employee: r.id })">
                            <Avatar :name="r.name" size="sm" /><span><b>{{ r.name }}</b><span>{{ r.title }}</span></span>
                        </Link>
                    </div>
                </section>
            </div>

            <div v-else-if="tab === 'pay' && compensation" class="stack">
                <section class="card" aria-labelledby="sal">
                    <div class="card-h">
                        <div><h2 id="sal">Salary history</h2><p>Effective-dated. A change adds a new row; past rows are never edited.</p></div>
                        <div class="acts">
                            <button v-if="can.manageCompensation && !adding" type="button" class="btn primary sm" @click="adding = true"><Plus class="icon sm" />Salary change</button>
                        </div>
                    </div>

                    <form v-if="adding" class="addform" @submit.prevent="saveSalary">
                        <div class="field">
                            <label>Effective from</label>
                            <RDatePicker v-model="form.effective_from" label="Effective from" :min="employee.hire_date" />
                            <span v-if="form.errors.effective_from" class="err">{{ form.errors.effective_from }}</span>
                        </div>
                        <div class="field">
                            <label for="base">Monthly base ({{ currency }})</label>
                            <input id="base" v-model="form.base_salary" class="input num" inputmode="decimal" autocomplete="off" placeholder="0" :class="{ invalid: form.errors.base_salary }" />
                            <span v-if="form.errors.base_salary" class="err">{{ form.errors.base_salary }}</span>
                        </div>
                        <div class="field">
                            <label for="tax">Tax status</label>
                            <input id="tax" v-model="form.tax_status" class="input" autocomplete="off" placeholder="e.g. K/1" />
                        </div>
                        <div class="field wide">
                            <label for="why">Reason</label>
                            <input id="why" v-model="form.reason" class="input" autocomplete="off" placeholder="Annual review, promotion…" :class="{ invalid: form.errors.reason }" />
                            <span v-if="form.errors.reason" class="err">{{ form.errors.reason }}</span>
                        </div>
                        <div class="formacts">
                            <button type="button" class="btn ghost" @click="adding = false">Cancel</button>
                            <button type="submit" class="btn primary" :disabled="form.processing">Save change</button>
                        </div>
                    </form>

                    <div class="tablewrap">
                        <table class="t sal">
                            <thead>
                                <tr><th>Effective from</th><th class="n">Monthly base</th><th>Tax status</th><th>Reason</th><th>Recorded by</th></tr>
                            </thead>
                            <tbody>
                                <tr v-for="c in compensation" :key="c.id" :aria-selected="c.is_current">
                                    <td class="num">{{ formatDate(c.effective_from) }} <span v-if="c.is_current" class="badge brand">Current</span><span v-else-if="c.effective_from > today" class="badge warning">Scheduled</span></td>
                                    <td class="n num">{{ formatMoney(c.base_salary, c.currency) }}</td>
                                    <td>{{ c.tax_status ?? '—' }}</td>
                                    <td>{{ c.reason }}</td>
                                    <td class="muted">{{ c.author ?? 'Imported' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <section v-else-if="tab === 'documents' && documents" class="card" aria-labelledby="docs">
                <div class="card-h">
                    <div><h2 id="docs">Documents</h2><p>Stored privately. Only HR, payroll, auditors and {{ employee.full_name.split(' ')[0] }} can open them.</p></div>
                    <div class="acts">
                        <button v-if="can.manageDocuments && !uploading" type="button" class="btn primary sm" @click="uploading = true"><Upload class="icon sm" />Upload</button>
                    </div>
                </div>
                <form v-if="uploading" class="addform" @submit.prevent="uploadDoc">
                    <div
                        class="drop wide"
                        role="button"
                        tabindex="0"
                        @click="docPicker?.click()"
                        @keydown.enter.prevent="docPicker?.click()"
                        @dragover.prevent
                        @drop.prevent="pickDoc(($event as DragEvent).dataTransfer?.files[0])"
                    >
                        <FileText class="icon" />
                        <b>{{ docForm.file ? docForm.file.name : 'Drop a PDF or image, or choose a file' }}</b>
                        <span>PDF, JPG or PNG · up to 10 MB</span>
                        <input ref="docPicker" type="file" accept=".pdf,.jpg,.jpeg,.png" class="sr" tabindex="-1" @change="pickDoc(($event.target as HTMLInputElement).files?.[0])" />
                    </div>
                    <span v-if="docForm.errors.file" class="err wide">{{ docForm.errors.file }}</span>
                    <div class="field">
                        <label>Type</label>
                        <RSelect :model-value="docForm.type" :options="documentTypes" label="Document type" @update:model-value="(v) => (docForm.type = v === null ? null : String(v))" />
                    </div>
                    <div class="field">
                        <label for="dname">Name</label>
                        <input id="dname" v-model="docForm.name" class="input" autocomplete="off" placeholder="e.g. Employment contract 2026" />
                    </div>
                    <div class="field">
                        <label>Expires <span class="muted">(optional)</span></label>
                        <RDatePicker v-model="docForm.expires_at" label="Expiry date" :min="tomorrow" />
                        <span v-if="docForm.errors.expires_at" class="err">{{ docForm.errors.expires_at }}</span>
                    </div>
                    <div class="formacts">
                        <button type="button" class="btn ghost" @click="uploading = false">Cancel</button>
                        <button type="submit" class="btn primary" :disabled="!docForm.file || docForm.processing">Upload</button>
                    </div>
                </form>
                <div v-if="documents.length" class="doclist">
                    <div v-for="d in documents" :key="d.id" class="doc">
                        <span class="dic">{{ d.extension }}</span>
                        <span class="dt"><b>{{ d.name }}</b><span>{{ d.type }} · uploaded {{ formatDate(d.uploaded_at) }}</span></span>
                        <span v-if="expiry(d)" class="badge" :class="expiry(d)!.cls">{{ expiry(d)!.label }}</span>
                        <span class="dacts">
                            <a class="btn sm" :href="route('employees.documents.show', { employee: employee.id, document: d.id })"><Download class="icon sm" />Download</a>
                            <button v-if="can.manageDocuments" type="button" class="btn ghost sm danger" :aria-label="`Remove ${d.name}`" @click="removeDoc(d)"><Trash2 class="icon sm" /></button>
                        </span>
                    </div>
                </div>
                <div v-else class="empty slim"><FileText class="icon" /><b>No documents yet</b><span v-if="can.manageDocuments">Upload the signed contract and ID scans here.</span></div>
            </section>

            <section v-else-if="tab === 'activity' && activity" class="card" aria-labelledby="act">
                <div class="card-h"><h2 id="act">Activity</h2><p>From the audit log</p></div>
                <div class="card-b">
                    <ol v-if="activity.length" class="tline">
                        <li v-for="a in activity" :key="a.id">
                            <time>{{ formatDateTime(a.at) }}</time>
                            <span class="pt" :class="{ f: a.event === 'revealed' }"></span>
                            <span>
                                <b>{{ eventLabel(a) }}</b><br />
                                <span class="small muted">{{ a.user }}<template v-if="a.changes.length"> · {{ a.changes.join(', ').replaceAll('_', ' ') }}</template></span>
                            </span>
                        </li>
                    </ol>
                    <div v-else class="empty slim"><History class="icon" /><b>No changes recorded yet</b></div>
                </div>
            </section>

            <p class="synthetic">Synthetic demo data.</p>
        </main>
    </RosterLayout>
</template>
