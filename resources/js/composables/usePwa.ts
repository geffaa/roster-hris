import { xsrfToken } from '@/composables/useRoster';
import type { RosterShared } from '@/types/roster';
import { usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

type PushState = 'unsupported' | 'denied' | 'off' | 'on' | 'busy';

interface InstallPromptEvent extends Event {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
}
let deferred: InstallPromptEvent | null = null;
const canInstall = ref(false);
if (typeof window !== 'undefined') {
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferred = e as InstallPromptEvent;
        canInstall.value = true;
    });
    window.addEventListener('appinstalled', () => (canInstall.value = false));
}

function keyBytes(base64: string): Uint8Array {
    const padded = (base64 + '='.repeat((4 - (base64.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');
    return Uint8Array.from(atob(padded), (c) => c.charCodeAt(0));
}

async function send(method: 'POST' | 'DELETE', body: unknown) {
    const res = await fetch('/push-subscriptions', {
        method,
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': xsrfToken() },
        credentials: 'same-origin',
        body: JSON.stringify(body),
    });
    if (!res.ok) throw new Error(`Push ${method} failed`);
}

/** Install to home screen and turn phone notifications on or off. */
export function usePwa() {
    const page = usePage<RosterShared>();
    const key = computed(() => (page.props.vapidPublicKey as string | null) ?? null);
    const push = ref<PushState>('busy');
    const standalone = typeof window !== 'undefined' && (window.matchMedia('(display-mode: standalone)').matches || (navigator as Navigator & { standalone?: boolean }).standalone === true);
    const ios = typeof navigator !== 'undefined' && /iphone|ipad|ipod/i.test(navigator.userAgent);

    async function refresh() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window) || !key.value) return (push.value = 'unsupported');
        if (Notification.permission === 'denied') return (push.value = 'denied');
        const reg = await navigator.serviceWorker.ready;
        push.value = (await reg.pushManager.getSubscription()) ? 'on' : 'off';
    }
    onMounted(() => {
        refresh().catch(() => (push.value = 'unsupported'));
    });

    async function enable() {
        push.value = 'busy';
        try {
            if ((await Notification.requestPermission()) !== 'granted') return refresh();
            const reg = await navigator.serviceWorker.ready;
            const sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: keyBytes(key.value!) as BufferSource });
            await send('POST', sub.toJSON());
            push.value = 'on';
        } catch {
            await refresh();
        }
    }
    async function disable() {
        push.value = 'busy';
        const reg = await navigator.serviceWorker.ready;
        const sub = await reg.pushManager.getSubscription();
        if (sub) {
            await send('DELETE', { endpoint: sub.endpoint }).catch(() => undefined);
            await sub.unsubscribe();
        }
        push.value = 'off';
    }
    async function install() {
        if (!deferred) return;
        await deferred.prompt();
        await deferred.userChoice;
        deferred = null;
        canInstall.value = false;
    }

    return { push, enable, disable, canInstall, install, standalone, ios };
}
