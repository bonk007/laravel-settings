<?php

namespace Settings;

use Illuminate\Cache\CacheManager;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Settings\Contracts\Configurable;
use stdClass;

class Manager
{
    /**
     * Type of configurable instance ID
     * @var string
     */
    public static string $configurableMorphType = 'int';

    /**
     * Determines table name in database
     * @var string
     */
    public static string $settingsTableName = 'system_settings';

    /**
     * Determines cache prefix key
     * @var string
     */
    public static string $cachePrefix = 'system_settings';

    /**
     * Configurable model instance
     * @var \Settings\Contracts\Configurable|null
     */
    protected ?Configurable $configurable = null;

    /**
     * Value adapter instance
     * @var \Settings\ValueAdapter
     */
    protected ValueAdapter $adapter;

    /**
     * Determines the settings value will be stored to cache or not
     * @var bool
     */
    protected bool $cached = true;


    public function __construct(protected Connection $dbConnection, protected CacheManager $cacheManager)
    {
        $this->adapter = new ValueAdapter();
    }

    /**
     * Indicates the value will be fetched directly from database rather than cache
     * @return $this
     */
    public function noCache(): self
    {
        $this->cached = false;
        return $this;
    }

    /**
     * Set configurable model
     * @param \Settings\Contracts\Configurable $configurable
     * @return $this
     */
    public function for(Configurable $configurable): self
    {
        $this->configurable = $configurable;
        return $this;
    }

    /**
     * Get setting's value following the key name
     * @throws \JsonException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function get(string $key, $default = null): mixed
    {
        if ($this->cached && null !== ($result = $this->fetchFromCache($key))) {
            return $result;
        }

        $result = $this->fetchFromDatabase($key);

        if (null === $result) {
            return $default;
        }

        if ($result instanceof Collection) {
            return $this->saveToCache($key,
                $result->mapWithKeys(function (stdClass $object) {
                    $keyParts = [$object->group_name, $object->key_name];
                    if (null !== $object->configurable_id && null !== $object->configurable_type) {
                        $keyParts = [...$keyParts, $object->configurable_type, $object->configurable_id];
                    }

                    $key = implode('.', $keyParts);

                    return [$key => $this->adapter->origin($object->value)];
                })
            );
        }

        return $this->saveToCache($key, $this->adapter->origin($result->value));
    }

    /**
     * Save setting's value
     * @throws \JsonException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function set(string $key, mixed $value): mixed
    {
        $origin = $value;
        $value = $this->adapter->savable($origin);
        $this->saveToDatabase($key, $value);

        return $this->saveToCache($key, $origin);
    }

    /**
     * Delete certain setting's value
     * @param string $key
     * @return bool
     */
    public function unset(string $key): bool
    {
        $keyParts = $this->ungroupKey($key);
        $configurableId = $this->configurable?->getKey() ?? $keyParts['configurable_id'] ?? null;
        $configurableTable = $this->configurable?->getTable() ?? $keyParts['configurable_table'] ?? null;

        if (!isset($keyParts['key_name'])) {
            throw new InvalidArgumentException("Key name is required");
        }

        $deleted = $this->dbConnection->table(static::$settingsTableName)
            ->where('group_name', $keyParts['group_name'])
            ->where('key_name', $keyParts['key_name'])
            ->when(null !== $configurableId && null !== $configurableTable, fn (Builder $query)
                => $query->where('configurable_id', $configurableId)
                        ->where('configurable_table', $configurableTable)
            )->delete();

        $this->invalidateCache($key);

        return $deleted > 0;
    }

    /**
     * Fetch settings from database
     *
     * @param string $key
     * @return \Illuminate\Support\Collection|\stdClass|null
     */
    protected function fetchFromDatabase(string $key): Collection|stdClass|null
    {
        $keyParts = $this->ungroupKey($key);
        $configurableId = $this->configurable?->getKey() ?? $keyParts['configurable_id'] ?? null;
        $configurableTable = $this->configurable?->getTable() ?? $keyParts['configurable_table'] ?? null;

        $query = $this->dbConnection->table(static::$settingsTableName)
            ->where('group_name', $keyParts['group_name'])
            ->when(isset($keyParts['key_name']), fn (Builder $query)
                => $query->where('key_name', $keyParts['key_name'])
            )
            ->when(null !== $configurableId && null !== $configurableTable, fn (Builder $query)
                => $query->where('configurable_id', $configurableId)
                    ->where('configurable_table', $configurableTable)
            );

        if (!isset($keyParts['key_name'])) {
            return $query->get();
        }

        return $query->first();

    }

    /**
     * Get formatted cache key
     * @param string $key
     * @return string
     */
    protected function getCacheKey(string $key): string
    {
        $keyParts = $this->ungroupKey($key);

        if (null !== $this->configurable) {
            $keyParts['configurable_table'] = $this->configurable->getTable();
            $keyParts['configurable_id'] = $this->configurable->getKey();
        }

        return static::$cachePrefix . '.' .implode('.', $keyParts);
    }

    /**
     * Perform get setting's value from cache
     * @param string $key
     * @return mixed
     */
    protected function fetchFromCache(string $key): mixed
    {
        return $this->cacheManager->get($this->getCacheKey($key));
    }

    /**
     * Cache setting's value
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    protected function saveToCache(string $key, mixed $value): mixed
    {
        if (true === $this->cached) {
            $this->cacheManager->set($this->getCacheKey($key), $value);
        }

        return $value;
    }

    /**
     * Forget setting value from cache storage
     * @param string $key
     * @return void
     */
    protected function invalidateCache(string $key): void
    {
        $this->cacheManager->forget($this->getCacheKey($key));
    }

    /**
     * Perform store setting's value to database
     * @throws \JsonException
     */
    protected function saveToDatabase(string $key, array $value): void
    {
        $jsonValue = json_encode($value, JSON_THROW_ON_ERROR);
        $keyParts = $this->ungroupKey($key);
        $configurableId = $this->configurable?->getKey() ?? $keyParts['configurable_id'] ?? null;
        $configurableTable = $this->configurable?->getTable() ?? $keyParts['configurable_table'] ?? null;

        if (!isset($keyParts['key_name'])) {
            throw new InvalidArgumentException("Key name is required");
        }

        $this->dbConnection->table(static::$settingsTableName)->updateOrInsert([
            'group_name' => $keyParts['group_name'],
            'key_name' => $keyParts['key_name'],
            'configurable_id' => $configurableId,
            'configurable_table' => $configurableTable,
        ], [
            'group_name' => $keyParts['group_name'],
            'key_name' => $keyParts['key_name'],
            'configurable_id' => $configurableId,
            'configurable_table' => $configurableTable,
            'value' => $jsonValue
        ]);

    }

    /**
     * Separate the given key into group name and key name
     * @param string $key
     * @return array
     */
    protected function ungroupKey(string $key): array
    {
        $parts = explode('.', $key);
        $components = [
            'group_name' => $parts[0]
        ];

        if (isset($parts[1])) {
            $components = [
                ...$components,
                'key_name' => $parts[1]
            ];
        }

        if (isset($parts[2])) {
            $components = [
                ...$components,
                'configurable_table' => $parts[2]
            ];
        }

        if (isset($parts[3])) {
            $components = [
                ...$components,
                'configurable_id' => $parts[3]
            ];
        }

        return $components;
    }
}
