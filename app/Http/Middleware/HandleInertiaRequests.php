<?php

namespace App\Http\Middleware;

use App\Enums\Permission;
use App\Http\Controllers\Time\InboxController;
use App\Support\CurrentCompany;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $company = app(CurrentCompany::class)->get();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                    'role' => $company ? $user->roleInCurrentCompany()?->label() : null,
                    'employee_id' => $company ? $user->employee?->id : null,
                    'two_factor' => $user->hasTwoFactorEnabled(),
                    'locale' => $user->locale ?? 'en',
                ] : null,
                'can' => $user && $company ? collect(Permission::cases())
                    ->mapWithKeys(fn (Permission $p) => [$p->value => $user->can($p->value)])->all() : [],
            ],
            'company' => $company?->only(['id', 'name', 'country_code', 'base_currency']),
            'companies' => fn () => $user ? $user->companies()->orderBy('name')->get(['companies.id', 'companies.name', 'companies.country_code']) : [],
            'inboxCount' => fn () => $user && $company ? app(InboxController::class)->pendingFor($request)->count() : 0,
            'notifications' => fn () => $user && $company ? [
                'unread' => $user->unreadNotifications()->where('data->company_id', $company->id)->count(),
                'latest' => $user->notifications()->where('data->company_id', $company->id)->latest()->limit(8)->get()
                    ->map(fn ($n) => ['id' => $n->id, ...$n->data, 'read' => $n->read_at !== null, 'at' => $n->created_at->toIso8601String()]),
            ] : ['unread' => 0, 'latest' => []],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
            ],
        ];
    }
}
