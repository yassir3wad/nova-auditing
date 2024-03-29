<?php

namespace Yassir3wad\NovaAuditing\Http\Controllers;

use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Collection;
use Laravel\Nova\Contracts\ListableField;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Nova;
use Laravel\Nova\ResourceToolElement;
use \OwenIt\Auditing\Models\Audit;

class AuditController extends BaseController
{
    public function fields(NovaRequest $request, $resource, Audit $audit)
    {
        $changes = [];

        $model = $audit->auditable;

        $resource = tap(Nova::resourceForKey(request('resource')), function ($resource) {
            abort_if(is_null($resource), 404);
        });

        $model->audits()->latest('id')
            ->where("id", ">=", $audit->id)
            ->each(function (Audit $audit) use (&$model, &$changes) {
                $changes = array_values(array_unique(array_merge(array_keys($audit->getModified()), $changes)));
                $model->transitionTo($audit, true);
            });

        $previous = new $resource($model);
        $current = new $resource($model->newModelQuery()->find($model->getKey()));

        return response()->json([
            'previous' => $this->filter($previous->detailFields($request))->values()->all(),
            'current' => $this->filter($current->detailFields($request))->values()->all(),
            'changes' => $changes
        ]);
    }

    private function filter(Collection $collection)
    {
        return $collection->reject(function ($field) {
            return $field instanceof ListableField ||
                $field instanceof ResourceToolElement ||
                $field->attribute === 'ComputedField' ||
                ($field instanceof ID);
        });
    }
}
