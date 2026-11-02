<?php

namespace App\Http\Controllers\People;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeIdentifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OwenIt\Auditing\Models\Audit;

/** Returns one identifier in clear text and writes a "revealed" audit event. */
class IdentifierRevealController extends Controller
{
    public function __invoke(Request $request, Employee $employee, EmployeeIdentifier $identifier): JsonResponse
    {
        abort_unless($identifier->employee_id === $employee->id, 404);
        $this->authorize('revealIdentifiers', $employee);

        Audit::create([
            'company_id' => $employee->company_id,
            'user_type' => $request->user()::class,
            'user_id' => $request->user()->id,
            'event' => 'revealed',
            'auditable_type' => EmployeeIdentifier::class,
            'auditable_id' => $identifier->id,
            'old_values' => [],
            'new_values' => ['type' => $identifier->type->value],
            'url' => $request->fullUrl(),
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1023),
            'tags' => 'pii',
        ]);

        return response()->json(['value' => $identifier->value]);
    }
}
