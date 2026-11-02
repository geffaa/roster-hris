<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\PolicyDocument;
use App\Models\User;
use App\Services\Assistant\PolicyIndex;
use Illuminate\Database\Seeder;

/** Synthetic company policies for Nusantara Kreatif, consistent with the rules configured in Roster. */
class PolicySeeder extends Seeder
{
    public function run(): void
    {
        $nk = Company::where('name', 'Nusantara Kreatif')->firstOrFail();
        $hr = User::where('email', 'hr@roster.test')->value('id');
        foreach (['leave' => 'Leave policy', 'attendance' => 'Attendance and working hours', 'overtime' => 'Overtime', 'payroll' => 'Payroll and payslips'] as $file => $title) {
            $doc = PolicyDocument::withoutGlobalScopes()->create(['company_id' => $nk->id, 'title' => $title, 'body' => file_get_contents(database_path("data/policies/{$file}.md")), 'uploaded_by' => $hr]);
            app(PolicyIndex::class)->index($doc);
        }
    }
}
