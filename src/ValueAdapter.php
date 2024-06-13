<?php

namespace Settings;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as ModelCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon as LaravelCarbon;

class ValueAdapter
{
    /**
     * Convert any value into acceptable value format
     *
     * @param mixed $payload
     * @return array
     */
    public function savable(mixed $payload): array
    {
        if (is_bool($payload)) {
            return $this->structuringPayload('bool', $payload);
        }

        if (is_int($payload)) {
            return $this->structuringPayload('int', $payload);
        }

        if (is_float($payload)) {
            return $this->structuringPayload('float', $payload);
        }

        if (is_array($payload)) {
            return $this->structuringPayload('array', $payload);
        }

        if ($payload instanceof \DateTimeInterface) {
            return $this->structuringPayload(Carbon::class, $payload);
        }

        if ($payload instanceof Model) {
            return $this->structuringPayload(Model::class, $payload);
        }

        if ($payload instanceof ModelCollection) {
            return $this->structuringPayload(ModelCollection::class, $payload);
        }

        if ($payload instanceof Collection) {
            return $this->structuringPayload(Collection::class, $payload);
        }

        return $this->structuringPayload('string', $payload);
    }

    /**
     * Get structured setting's value
     *
     * @param string $type
     * @param mixed $value
     * @return array
     */
    protected function structuringPayload(string $type, mixed $value): array
    {
        return [
            'type' => $type,
            'value' => match($type) {
                Carbon::class => $value->toString(),
                Model::class => [
                    'model' => get_class($value),
                    'id' => $value->getKey()
                ],
                ModelCollection::class => $this->fromModelCollection($value),
                Collection::class => $value->toArray(),
                default => $value
            }
        ];
    }

    /**
     * Get structured payload from Models Collection
     *
     * @param \Illuminate\Database\Eloquent\Collection $collection
     * @return array
     */
    protected function fromModelCollection(ModelCollection $collection): array
    {
        return [
            'model' => $collection->reduce(fn(?string $result, $object, $index) => get_class($object)),
            'ids' => $collection->modelKeys()
        ];
    }

    /**
     * Get origin value
     * @throws \JsonException
     */
    public function origin(array|string $payload): mixed
    {
        $payload = $this->validate($payload);

        return match($payload['type']) {
            'bool' => (bool) $payload['value'],
            'int' => (int) $payload['value'],
            'float' => (float) $payload['value'],
            Collection::class => new Collection($payload['value']),
            ModelCollection::class => $this->toModelsCollection($payload['value']),
            Model::class => $this->toEloquentModel($payload['value']),
            Carbon::class => LaravelCarbon::parse($payload['value']),
            default => $payload['value']
        };
    }

    /**
     * Bring back Eloquent model from the given data
     *
     * @param array $data
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    protected function toEloquentModel(array $data): ?Model
    {
        $modelClass = $data['model'];
        $id = $data['id'];

        $this->validateModelClass($modelClass);

        return $modelClass::find($id);
    }

    /**
     * Bring back Eloquent collection from the given data
     *
     * @param array $data
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function toModelsCollection(array $data): ModelCollection
    {
        $modelClass = $data['model'];
        $modelIdentifiers = $data['ids'];

        $this->validateModelClass($modelClass);

        return $modelClass::query()->whereIn('id', $modelIdentifiers)->get();
    }

    /**
     * Check existence and valid model class name
     *
     * @param string $modelClass
     * @return void
     */
    protected function validateModelClass(string $modelClass): void
    {
        if (!class_exists($modelClass) || !in_array(Model::class, class_parents($modelClass), true)) {
            throw new \InvalidArgumentException("Invalid model class $modelClass!");
        }
    }

    /**
     * Make sure the payload given has matched structure
     * @throws \JsonException
     */
    protected function validate(array|string $payload): array
    {
        $data = is_array($payload)
            ? $payload
            : json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

        if (!isset($data['value'], $data['type'])) {
            throw new \InvalidArgumentException("Invalid structure! The value couldn't be decoded");
        }

        return $data;
    }
}
