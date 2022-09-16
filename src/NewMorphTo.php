<?php

namespace DigitalCreative\InlineMorphTo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\HasOne;
use Laravel\Nova\Http\Controllers\CreationFieldController;
use Laravel\Nova\Http\Controllers\ResourceIndexController;
use Laravel\Nova\Http\Controllers\ResourceShowController;
use Laravel\Nova\Http\Controllers\UpdateFieldController;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Nova;
use Laravel\Nova\Resource;
use Laravel\Nova\Util;

class NewMorphTo extends Field
{
    /**
     * The field's component.
     *
     * @var string
     */
    public $component = "new-morph-to";

    /**
     * The class name of the related resource.
     *
     * @var string
     */
    public $resourceClass;

    /**
     * The URI key of the related resource.
     *
     * @var string
     */
    public $resourceName;

    /**
     * The key of the related Eloquent model.
     *
     * @var string
     */
    public $morphToId;

    /**
     * The type of the related Eloquent model.
     *
     * @var string
     */
    public $morphToType;

    /**
     * The types of resources that may be polymorphically related to this resource.
     *
     * @var array
     */
    public $morphToTypes = [];

    /**
     * The column that should be displayed for the field.
     *
     * @var \Closure|array
     */
    public $display;

    /**
     * Indicates if the related resource can be viewed.
     *
     * @var bool
     */
    public $viewable = true;

    /**
     * The default related class value for the field.
     *
     * @var Closure|string
     */
    public $defaultResourceCallable;

    /**
     * Create a new field.
     *
     * @param  string  $name
     * @param  string|null  $attribute
     * @return void
     */
    public function __construct($name, $attribute = null)
    {
        parent::__construct($name, $attribute);
    }

    /**
     * Resolve the field's value.
     *
     * @param  mixed  $resource
     * @param  string|null  $attribute
     * @return void
     */
    public function resolve($resource, $attribute = null)
    {
        $value = null;

        if (! $value) {
            $value = $resource->{$this->attribute}()->withoutGlobalScopes()->getResults();
        }

        [$this->morphToId, $this->morphToType] = [
            optional($value)->getKey(),
            $this->resolveMorphType($resource),
        ];

        if ($resourceClass = $this->resolveResourceClass($value)) {
            $this->resourceName = $resourceClass::uriKey();
        }

        $attribute = $attribute ?? $this->attribute;
        if ($relationInstance = $resource->$attribute) {
            foreach ($this->getResourceFields($relationInstance) as $field) {
                if ($field->computed()) {
                    $field->computedCallback = $field->computedCallback->bindTo(
                        Nova::newResourceFromModel($relationInstance)
                    );
                }
                $field->resolve($relationInstance);
            }
        }

        if ($value) {
            if (! is_string($this->resourceClass)) {
                $this->morphToType = $value->getMorphClass();
                $this->value = (string) $value->getKey();

                if ($this->value != $value->getKey()) {
                    $this->morphToId = (string) $this->morphToId;
                }

                $this->viewable = false;
            } else {
                $resource = new $this->resourceClass($value);

                $this->morphToId = Util::safeInt($this->morphToId);

                $this->value = $this->formatDisplayValue(
                    $value, Nova::resourceForModel($value)
                );

                $this->viewable = $this->viewable
                    && $resource->authorizedToView(request());
            }
        }
    }

    /**
     * Resolve the current resource key for the resource's morph type.
     *
     * @param  mixed  $resource
     * @return string|null
     */
    protected function resolveMorphType($resource)
    {
        if (! $type = optional($resource->{$this->attribute}())->getMorphType()) {
            return;
        }

        $value = $resource->{$type};

        if ($morphResource = Nova::resourceForModel(Relation::getMorphedModel($value) ?? $value)) {
            return $morphResource::uriKey();
        }
    }

    /**
     * Resolve the resource class for the field.
     *
     * @param  \Illuminate\Database\Eloquent\Model
     * @return string|null
     */
    protected function resolveResourceClass($model)
    {
        return $this->resourceClass = Nova::resourceForModel($model);
    }

    /**
     * Format the associatable display value.
     *
     * @param  mixed  $resource
     * @param  string  $relatedResource
     * @return string
     */
    protected function formatDisplayValue($resource, $relatedResource)
    {
        if (! $resource instanceof Resource) {
            $resource = Nova::newResourceFromModel($resource);
        }

        if ($display = $this->displayFor($relatedResource)) {
            return call_user_func($display, $resource);
        }

        return (string) $resource->title();
    }

    /**
     * Specify if the related resource can be viewed.
     *
     * @param  bool  $value
     * @return $this
     */
    public function viewable($value = true)
    {
        $this->viewable = $value;

        return $this;
    }

    /**
     * Get the column that should be displayed for a given type.
     *
     * @param  string  $type
     * @return \Closure|null
     */
    public function displayFor($type)
    {
        if (is_array($this->display) && $type) {
            return $this->display[$type] ?? null;
        }

        return $this->display;
    }

    /**
     * Set the types of resources that may be related to the resource.
     *
     * @param  array  $types
     * @return $this
     */
    public function types(array $types)
    {
        $this->morphToTypes = collect($types)->map(function ($resourceClass, $key) {
            return [
                'type' => $resourceClass,
                'singularLabel' => is_numeric($key) ? $resourceClass::singularLabel() : $key,
                'display' => (is_string($resourceClass) && is_numeric($key)) ? $resourceClass::label() : $key,
                'value' => $resourceClass::uriKey(),
                'fields' => $this->resolveResourceFields($resourceClass),
            ];
        })->values()->all();

        quo($this->morphToTypes);

        return $this;
    }

    private function resolveResourceFields($resourceClass, $request = null)
    {
        $resourceInstance = Nova::resourceInstanceForKey($resourceClass::uriKey());
        if (! $resourceInstance) {
            return [];
        }

        $request = $request ?? app(NovaRequest::class);
        $controller = $request->route()->controller;

        switch(get_class($controller)) {
            case CreationFieldController::class:
                return $resourceInstance->creationFields($request);

            case UpdateFieldController::class:
                return $resourceInstance->updateFields($request);

            case ResourceShowController::class:
                return $resourceInstance->detailFields($request);

            case ResourceIndexController::class:
                return $resourceInstance->indexFields($request);
        }
        return $resourceInstance->availableFields($request);
    }

    /**
     * Resolve the field's value for display.
     *
     * @param mixed $resource
     * @param string|null $attribute
     * @return void
     */
    public function resolveForDisplay($resource, $attribute = null)
    {
        $attribute = $attribute ?? $this->attribute;

        parent::resolveForDisplay($resource, $attribute);

        if ($relationInstance = $resource->$attribute) {
            foreach ($this->getResourceFields($relationInstance) as $field) {
                $field->resolveForDisplay($relationInstance);
            }
        }
    }

    private function getResourceFields(Model $model)
    {
        $resourceClass = Nova::resourceForModel($model);

        return collect($this->morphToTypes)->firstWhere('type', $resourceClass)['fields'];
    }

    /**
     * Hydrate the given attribute on the model based on the incoming request.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @param  object  $model
     * @return mixed
     */
    public function fill(NovaRequest $request, $model)
    {
        $resourceUriKey = $request->get($this->attribute);
        $resourceClass = Nova::resourceInstanceForKey($resourceUriKey);
        //$resourceClass = collect($this->morphToTypes)->first('value', $resourceUriKey)['type'];
        $relatedInstance = $model->{$this->attribute} ?? $resourceClass::newModel();
        $resource = new $resourceClass($relatedInstance);

        if ($relatedInstance->exists) {
            $resource->validateForUpdate($request);
        }
        else {
            $resource->validateForCreation($request);
        }

        $fields = $this->resolveResourceFields($resourceClass, $request);
        $callbacks = [];
        foreach ($fields as $field) {
            $callbacks[] = $field->fill($request, $relatedInstance);
        }

        $relatedInstance->saveOrFail();

        $model->{$this->attribute}()->associate($relatedInstance);

        return function () use ($callbacks) {
            foreach ($callbacks as $callback) {
                if (is_callable($callback)) {
                    call_user_func($callback);
                }
            }
        };
    }

    /**
     * Resolve the given attribute from the given resource.
     *
     * @param mixed $resource
     * @param string $attribute
     *
     * @return mixed
     */
    protected function resolveAttribute($resource, $attribute)
    {
        if ($relationInstance = $resource->$attribute) {
            $resource = Nova::resourceForModel($relationInstance);
            foreach ($this->getResourceFields($relationInstance) as $field) {
                if ($field instanceof HasOne
                    || $field instanceof HasMany
                    || $field instanceof BelongsToMany
                ) {
                    $field->meta[ 'inlineMorphTo' ] = [
                        'viaResourceId' => $relationInstance->id,
                        'viaResource' => $resource::uriKey()
                    ];
                }
            }
        }

        return $resource;
    }

    /**
     * Get the validation rules for this field.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function getRules(NovaRequest $request)
    {
        $possibleTypes = collect($this->morphToTypes)->map->value->values();

        $rules = [
            $this->attribute => ['required', 'in:'.$possibleTypes->implode(',')]
        ];

        // prepare rules for morphto fields rules
        if (in_array($request->get($this->attribute), $possibleTypes->toArray())) {
            $rules = array_merge($rules, $this->morphToRules($request));
        }

        return array_merge_recursive(parent::getRules($request), $rules);
    }

    private function morphToRules($request)
    {
        $resourceUriKey = $request->{$this->attribute};
        $resourceClass = Nova::resourceInstanceForKey($resourceUriKey);

        return $this->resolveResourceFields($resourceClass, $request)->mapWithKeys(function ($field) use ($request) {
            return $field->getRules($request);
        })->toArray();
    }

    /**
     * Set the default relation resource class to be selected.
     *
     * @param  \Closure|string  $resourceClass
     * @return $this
     */
    public function defaultResource($resourceClass)
    {
        $this->defaultResourceCallable = $resourceClass;

        return $this;
    }

    /**
     * Resolve the default resource class for the field.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return string|void
     */
    protected function resolveDefaultResource(NovaRequest $request)
    {
        if ($request->isCreateOrAttachRequest() || $request->isResourceIndexRequest() || $request->isActionRequest()) {
            if (is_null($this->value) && $this->defaultResourceCallable instanceof Closure) {
                $class = call_user_func($this->defaultResourceCallable, $request);
            } else {
                $class = $this->defaultResourceCallable;
            }

            if (! empty($class) && class_exists($class)) {
                return $class::uriKey();
            }
        }
    }

    /**
     * Prepare the field for JSON serialization.
     *
     * @return array
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        $request = app(NovaRequest::class);
        $originalResource = $request->route()->resource;
        $resourceClass = $this->resourceClass;

        /**
         * Temporarily remap the route resource key so every sub field thinks its being resolved by its original parent
         */
        foreach ($this->morphToTypes as $resource) {
            $resource['fields']->transform(function ($field) use ($request, $resource) {
                $request->route()->setParameter('resource', $resource['value']);
                return $field->jsonSerialize();
            });
        }

        $request->route()->setParameter('resource', $originalResource);

        return with(app(NovaRequest::class), function ($request) use ($resourceClass) {
            return array_merge([
                'morphToId' => $this->morphToId,
                'morphToType' => $this->morphToType,
                'morphToTypes' => $this->morphToTypes,
                'resourceLabel' => $resourceClass ? $resourceClass::singularLabel() : null,
                'resourceName' => $this->resourceName,
                'viewable' => $this->viewable,
                'defaultResource' => $this->resolveDefaultResource($request),
                // this will make the field to be render as panel on detail view
                'listable' => true,
            ], parent::jsonSerialize());
        });
    }
}
