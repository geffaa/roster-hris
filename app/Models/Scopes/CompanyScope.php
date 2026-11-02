<?php

namespace App\Models\Scopes;

use App\Support\CurrentCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $companyId = app(CurrentCompany::class)->id();

        if ($companyId !== null) {
            $builder->where($model->qualifyColumn('company_id'), $companyId);

            return;
        }

        // Outside a company context, seeders and artisan commands run unscoped.
        // Any HTTP request (including ones made from tests) without a context sees nothing.
        $inHttpRequest = ! app()->runningInConsole() || request()->route() !== null;

        if ($inHttpRequest) {
            $builder->whereRaw('false');
        }
    }
}
