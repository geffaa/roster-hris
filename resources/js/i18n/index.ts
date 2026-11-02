import type { RosterShared } from '@/types/roster';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import en from './en';
import id from './id';

export type Dict = typeof en;
const dicts: Record<'en' | 'id', Dict> = { en, id };

/**
 * Tiny translator for the employee app: t('pay.title'), t('pay.paid', { date }).
 * The admin workspace stays in English; the employee app and payslips follow the person's language.
 */
export function useT() {
    const page = usePage<RosterShared>();
    const locale = computed<'en' | 'id'>(() => page.props.auth.user?.locale ?? 'en');
    const t = (key: string, vars: Record<string, string | number> = {}): string => {
        const lookup = (d: Dict) => key.split('.').reduce<unknown>((o, k) => (o && typeof o === 'object' ? (o as Record<string, unknown>)[k] : undefined), d);
        const raw = lookup(dicts[locale.value]) ?? lookup(en) ?? key;
        return String(raw).replace(/\{(\w+)\}/g, (_, k) => String(vars[k] ?? `{${k}}`));
    };
    const dateLocale = computed(() => (locale.value === 'id' ? 'id-ID' : 'en-GB'));
    /** "5 Nov 2026" / "5 Nov" / "Rab" in the person's language. */
    const d = (iso: string | null | undefined, opts: Intl.DateTimeFormatOptions = { day: 'numeric', month: 'short', year: 'numeric' }) =>
        iso ? new Intl.DateTimeFormat(dateLocale.value, opts).format(new Date(iso.length === 10 ? `${iso}T00:00:00` : iso)) : '—';
    const month = (iso: string) => d(iso.slice(0, 10), { month: 'long', year: 'numeric' });
    /** A payslip line label/basis in the person's language (labels by code; common basis phrases translated). */
    const line = (l: { code: string; label: string; basis: string | null }) => {
        if (locale.value === 'en') return { label: l.label, basis: l.basis };
        const label = dicts.id.lines[l.code] ?? l.label;
        const basis = (l.basis ?? '')
            .replace(/^(\d+) of (\d+) days$/, '$1 dari $2 hari')
            .replace(/^Recurring, fixed$/, 'Tetap, bulanan')
            .replace(/^Recurring$/, 'Bulanan')
            .replace(/^One-off$/, 'Sekali bayar')
            .replace(/^Base ÷ (\d+) working days × ([\d.]+)$/, 'Gaji pokok ÷ $1 hari kerja × $2')
            .replace(/ of /g, ' dari ')
            .replace(/\(cap\)/g, '(batas)')
            .replace(/h on workdays/g, 'jam hari kerja')
            .replace(/h on rest days/g, 'jam hari libur')
            .replace(/hourly/g, 'per jam')
            .replace(/^Year-end recalculation: refund of tax withheld earlier$/, 'Hitung ulang akhir tahun: pengembalian pajak')
            .replace(/^Year-end recalculation \(Article 17\)$/, 'Hitung ulang akhir tahun (Pasal 17)');
        return { label: l.code === 'unpaid_leave' ? label : label, basis: basis || null };
    };
    return { t, locale, dateLocale, d, month, line };
}
