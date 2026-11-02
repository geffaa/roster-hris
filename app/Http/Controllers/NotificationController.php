<?php

namespace App\Http\Controllers;

use App\Support\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** The bell: open one notification (marks it read and follows its link) or mark all read. */
class NotificationController extends Controller
{
    public function open(Request $request, string $id, CurrentCompany $company): RedirectResponse
    {
        $notification = $request->user()->notifications()->where('data->company_id', $company->id())->findOrFail($id);
        $notification->markAsRead();
        $url = (string) ($notification->data['url'] ?? '');

        // Only follow paths inside this app, never another host.
        return str_starts_with($url, '/') && ! str_starts_with($url, '//') ? redirect()->to($url) : redirect()->route('dashboard');
    }

    public function readAll(Request $request, CurrentCompany $company): RedirectResponse
    {
        $request->user()->unreadNotifications()->where('data->company_id', $company->id())->update(['read_at' => now()]);

        return back();
    }
}
