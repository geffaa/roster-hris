import type { RosterShared } from '@/types/roster';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export function useRoster() {
    const page = usePage<RosterShared>();

    const user = computed(() => page.props.auth.user);
    const company = computed(() => page.props.company);
    const can = (permission: string) => page.props.auth.can?.[permission] === true;

    return { page, user, company, can };
}

export function formatMoney(amount: string | number, currency: string): string {
    const value = typeof amount === 'string' ? Number(amount) : amount;
    return new Intl.NumberFormat('en', {
        style: 'currency',
        currency,
        currencyDisplay: 'narrowSymbol',
        maximumFractionDigits: currency === 'IDR' ? 0 : 2,
        minimumFractionDigits: currency === 'IDR' ? 0 : 2,
    }).format(value);
}

export function formatDate(iso: string | null | undefined): string {
    if (!iso) return '—';
    const d = new Date(iso.length === 10 ? `${iso}T00:00:00` : iso);
    return new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }).format(d);
}

export function formatDateTime(iso: string): string {
    return new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }).format(new Date(iso));
}

/** Stable colour class for an avatar from its initials (av-1 … av-7). */
export function avatarClass(seed: string): string {
    let h = 0;
    for (const ch of seed) h = (h * 31 + ch.charCodeAt(0)) >>> 0;
    return `av-${(h % 7) + 1}`;
}

export function initials(name: string): string {
    const parts = name.trim().split(/\s+/);
    return ((parts[0]?.[0] ?? '') + (parts.length > 1 ? parts[parts.length - 1][0] : '')).toUpperCase();
}

/** Read Laravel's XSRF cookie for JSON requests made outside Inertia. */
export function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

/** 12 → "12 min", 135 → "2 h 15 min". */
export function humanMinutes(minutes: number): string {
    return minutes < 60 ? `${minutes} min` : `${Math.floor(minutes / 60)} h ${String(minutes % 60).padStart(2, '0')} min`;
}

/** Local calendar date as YYYY-MM-DD (toISOString would shift to UTC and can return the wrong day). */
export function isoDate(d: Date = new Date()): string {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

/** "2026-10-30" + 1 → "2026-10-31", in local calendar terms. */
export function addDays(iso: string, days: number): string {
    const [y, m, d] = iso.split('-').map(Number);
    return isoDate(new Date(y, m - 1, d + days));
}

/** "Rp 412.9 M", "£77.0 K" for headline totals; full value goes underneath. */
export function compactMoney(amount: string | number, currency: string): string {
    const value = Number(amount);
    const symbol = currency === 'IDR' ? 'Rp ' : (new Intl.NumberFormat('en', { style: 'currency', currency, currencyDisplay: 'narrowSymbol' }).formatToParts(0).find((p) => p.type === 'currency')?.value ?? '');
    const abs = Math.abs(value);
    const [div, unit] = abs >= 1e9 ? [1e9, ' B'] : abs >= 1e6 ? [1e6, ' M'] : abs >= 1e4 ? [1e3, ' K'] : [1, ''];
    const n = div === 1 ? new Intl.NumberFormat('en', { maximumFractionDigits: currency === 'IDR' ? 0 : 2 }).format(abs) : (abs / div).toFixed(1);
    return `${value < 0 ? '−' : ''}${symbol}${n}${unit}`;
}

/** Plain grouped number with the currency's decimals: 14243530 → "14,243,530". */
export function plainMoney(amount: string | number, decimals: number): string {
    return new Intl.NumberFormat('en', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }).format(Number(amount));
}

/** "October 2026" from "2026-10-01". */
export function monthLabel(iso: string): string {
    return new Intl.DateTimeFormat('en-GB', { month: 'long', year: 'numeric' }).format(new Date(`${iso.slice(0, 10)}T00:00:00`));
}
