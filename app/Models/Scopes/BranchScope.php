<?php

namespace App\Models\Scopes;

use App\Exceptions\NoBranchContextException;
use App\Services\BranchContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class BranchScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(BranchContext::class);

        if ($context->isBypassed()) {
            return;
        }

        $branch = $context->get();

        if (! $branch) {
            if ($context->isNoneResolved()) {
                $builder->whereRaw('1 = 0');

                return;
            }

            $modelClass = $model::class;

            throw new NoBranchContextException(
                "Query on branch-scoped model [{$modelClass}] attempted without branch context. ".
                'Set the branch via BranchContext::set(), or explicitly call BranchContext::bypass() for a documented cross-branch context.'
            );
        }

        $builder->where($model->getTable().'.branch_id', $branch->id);
    }
}
