<?php

namespace App\Http\Controllers\People;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CompensationController extends Controller
{
    public function store(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorize('manageCompensation', $employee);

        $data = $request->validate([
            'effective_from' => ['required', 'date', 'after_or_equal:'.$employee->hire_date->toDateString()],
            'base_salary' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'currency' => ['nullable', 'string', 'size:3'],
            'tax_status' => ['nullable', 'string', 'max:20'],
            'reason' => ['required', 'string', 'max:200'],
        ]);

        $employee->compensations()->create([
            ...$data,
            'currency' => strtoupper($data['currency'] ?? $employee->company->base_currency),
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Salary change saved and recorded in the audit log.');
    }
}
