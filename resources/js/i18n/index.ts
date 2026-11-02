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
    return { t, locale, dateLocale };
}
