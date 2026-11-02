<?php

use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\CompanySwitchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Insights\AuditLogController;
use App\Http\Controllers\Insights\ReportController;
use App\Http\Controllers\Me\AskController;
use App\Http\Controllers\Me\MyAttendanceController;
use App\Http\Controllers\Me\PayslipController;
use App\Http\Controllers\Me\ProfileController;
use App\Http\Controllers\Me\TimeOffController;
use App\Http\Controllers\Me\TodayController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Pay\PayrollRunController;
use App\Http\Controllers\Pay\RulePackController;
use App\Http\Controllers\People\CompensationController;
use App\Http\Controllers\People\DocumentController;
use App\Http\Controllers\People\EmployeeController;
use App\Http\Controllers\People\EmployeeImportController;
use App\Http\Controllers\People\IdentifierRevealController;
use App\Http\Controllers\People\OrgChartController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\Settings\PolicyController;
use App\Http\Controllers\Settings\TimeSettingsController;
use App\Http\Controllers\Time\InboxController;
use App\Http\Controllers\Time\LeaveCalendarController;
use App\Http\Controllers\Time\TeamAttendanceController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'company'])->group(function () {
    // Two-factor enrolment and challenge sit outside the two-factor gate.
    Route::prefix('two-factor')->name('two-factor.')->controller(TwoFactorController::class)->group(function () {
        Route::get('setup', 'setup')->name('setup');
        Route::post('confirm', 'confirm')->middleware('throttle:6,1')->name('confirm');
        Route::get('recovery-codes', 'recoveryCodes')->name('recovery-codes');
        Route::get('challenge', 'challenge')->name('challenge');
        Route::post('verify', 'verify')->middleware('throttle:6,1')->name('verify');
    });

    Route::middleware('two-factor')->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
        Route::post('companies/{company}/switch', CompanySwitchController::class)->name('companies.switch');

        Route::get('employees', [EmployeeController::class, 'index'])->name('employees.index');
        Route::get('org-chart', OrgChartController::class)->name('org-chart');

        // Employee app (phone-first)
        Route::prefix('me')->name('me.')->group(function () {
            Route::get('today', [TodayController::class, 'show'])->name('today');
            Route::post('clock', [TodayController::class, 'clock'])->middleware('throttle:10,1')->name('clock');
            Route::get('time-off', [TimeOffController::class, 'index'])->name('time-off');
            Route::get('time-off/quote', [TimeOffController::class, 'quote'])->name('time-off.quote');
            Route::post('time-off', [TimeOffController::class, 'store'])->name('time-off.store');
            Route::delete('time-off/{leave}', [TimeOffController::class, 'destroy'])->name('time-off.destroy');
            Route::get('attendance', [MyAttendanceController::class, 'index'])->name('attendance');
            Route::post('attendance/{record}/correction', [MyAttendanceController::class, 'correct'])->name('attendance.correct');
            Route::post('overtime', [MyAttendanceController::class, 'overtime'])->name('overtime');
            Route::get('profile', [ProfileController::class, 'show'])->name('profile');
            Route::put('profile/contact', [ProfileController::class, 'contact'])->name('profile.contact');
            Route::post('profile/bank', [ProfileController::class, 'bank'])->middleware('throttle:5,1')->name('profile.bank');
            Route::delete('profile/bank/{change}', [ProfileController::class, 'cancelBank'])->name('profile.bank.cancel');
            Route::put('locale', [ProfileController::class, 'locale'])->name('locale');
            Route::get('ask', [AskController::class, 'index'])->name('ask');
            Route::post('ask', [AskController::class, 'store'])->middleware('throttle:20,1')->name('ask.store');
            Route::get('pay', [PayslipController::class, 'index'])->name('pay');
            Route::get('pay/{payslip}', [PayslipController::class, 'show'])->name('payslip');
            Route::get('pay/{payslip}/pdf', [PayslipController::class, 'pdf'])->middleware('throttle:30,1')->name('payslip.pdf');
        });

        // Managers and HR
        Route::get('inbox', [InboxController::class, 'index'])->name('inbox');
        Route::get('search', SearchController::class)->middleware('throttle:60,1')->name('search');
        Route::get('notifications/{id}', [NotificationController::class, 'open'])->whereUuid('id')->name('notifications.open');
        Route::post('push-subscriptions', [PushSubscriptionController::class, 'store'])->middleware('throttle:10,1')->name('push.store');
        Route::delete('push-subscriptions', [PushSubscriptionController::class, 'destroy'])->name('push.destroy');
        Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
        Route::post('approvals/{step}', [InboxController::class, 'decide'])->name('approvals.decide');
        Route::get('attendance', TeamAttendanceController::class)->name('attendance');
        Route::get('leave-calendar', LeaveCalendarController::class)->name('leave-calendar');
        Route::get('reports', [ReportController::class, 'index'])->name('reports');
        Route::prefix('payroll')->name('payroll.')->controller(PayrollRunController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::get('{run}', 'show')->name('show');
            Route::post('{run}/calculate', 'calculate')->middleware('throttle:10,1')->name('calculate');
            Route::post('{run}/items/{item}/review', 'review')->name('review');
            Route::post('{run}/approve', 'approve')->name('approve');
            Route::post('{run}/lock', 'lock')->name('lock');
            Route::post('{run}/publish', 'publish')->name('publish');
            Route::get('{run}/bank.csv', 'bankFile')->middleware('throttle:10,1')->name('bank');
            Route::get('{run}/summary.csv', 'summary')->name('summary');
            Route::post('{run}/briefing', 'briefing')->middleware('throttle:10,1')->name('briefing');
        });
        Route::prefix('rule-packs')->name('rule-packs.')->controller(RulePackController::class)->group(function () {
            Route::get('{version?}', 'show')->name('show');
            Route::post('{version}/draft', 'draft')->name('draft');
            Route::put('{version}', 'update')->name('update');
            Route::post('{version}/submit', 'submit')->name('submit');
            Route::post('{version}/approve', 'approve')->name('approve');
            Route::post('{version}/reject', 'reject')->name('reject');
            Route::post('{version}/discard', 'discard')->name('discard');
        });
        Route::get('audit-log', AuditLogController::class)->name('audit-log');
        Route::get('settings/policies', [PolicyController::class, 'index'])->name('settings.policies');
        Route::post('settings/policies', [PolicyController::class, 'store'])->name('settings.policies.store');
        Route::delete('settings/policies/{document}', [PolicyController::class, 'destroy'])->name('settings.policies.destroy');
        Route::prefix('settings/time')->name('settings.')->controller(TimeSettingsController::class)->group(function () {
            Route::get('/', 'index')->name('time');
            Route::post('patterns', 'savePattern')->name('patterns.store');
            Route::put('patterns/{pattern}', 'savePattern')->name('patterns.update');
            Route::post('leave-types', 'saveLeaveType')->name('leave-types.store');
            Route::put('leave-types/{type}', 'saveLeaveType')->name('leave-types.update');
        });
        Route::get('reports/export', [ReportController::class, 'export'])->middleware('throttle:20,1')->name('reports.export');

        Route::prefix('employees/import')->name('employees.import.')->controller(EmployeeImportController::class)->group(function () {
            Route::get('/', 'create')->name('create');
            Route::get('template.csv', 'template')->name('template');
            Route::post('/', 'preview')->middleware('throttle:10,1')->name('preview');
            Route::get('{token}', 'show')->whereUuid('token')->name('show');
            Route::post('{token}', 'store')->whereUuid('token')->name('store');
        });

        Route::get('employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');
        Route::post('employees/{employee}/compensation', [CompensationController::class, 'store'])->name('employees.compensation.store');
        Route::post('employees/{employee}/documents', [DocumentController::class, 'store'])->name('employees.documents.store');
        Route::get('employees/{employee}/documents/{document}', [DocumentController::class, 'show'])->name('employees.documents.show');
        Route::delete('employees/{employee}/documents/{document}', [DocumentController::class, 'destroy'])->name('employees.documents.destroy');
        Route::post('employees/{employee}/identifiers/{identifier}/reveal', IdentifierRevealController::class)
            ->middleware('throttle:20,1')->name('employees.identifiers.reveal');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
