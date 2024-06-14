Laravel Settings
=======================
![Packagist Downloads](https://img.shields.io/packagist/dt/bonk007/system-settings)
![GitHub License](https://img.shields.io/github/license/bonk007/laravel-settings)
![Packagist Stars](https://img.shields.io/packagist/stars/bonk007/system-settings)
![GitHub forks](https://img.shields.io/github/forks/bonk007/laravel-settings)

1. [Install](#install)
2. [How to](#how-to)
   - [Set Value](#set-settings-value)
   - [Get Value](#get-settings-value)
   - [Remove Value](#remove-setting)
4. [Configurable Model](#configurable-model)
   - [How to Define](#how-to-define)
   - [How it works](#how-it-works)
6. [Accepted Value](#accepted-value)

# Install
Install the package using composer 
``` 
composer require bonk007/system-settings
``` 
then run migration 
```
php artisan migrate
```
that's all :zap:

# How to

## Set setting's value
```php
settings()->set('<group>.<key>', <value>);
```
example
```php
settings()->set('global-settings.maintenance_scheduled_at', Carbon::parse('2024-07-01 00:00:00'));
```
if you need to set some value for specific configurable model (learn: what is configurable model)
```php
settings()->for(<model instance>)
  ->set('<group>.<key>', <value>);
```
example
```php
settings()->for(\App\Models\Organization::find(6))
  ->set('invoice.number_format', 'INV/{SEQUENCE}/{MONTH}/{YEAR}');
```
or you can use
```php
settings()->set('<group>.<key>.<table of configurable model>.<primary key>', <value>);
```
example
```php
settings()->set('invoice.number_format.organizations.6', 'INV/{SEQUENCE}/{MONTH}/{YEAR}');
```

## Get setting's value
```php
settings('<group>.<key>', <default value>);
```
for specific configurable model
```php
settings()->for(<configurable model>)
  ->get('<group>.<key>', <default value>);
```
or using simple way
```php
settings('<group>.<key>.<table of configurable model>.<primary key>', <default value>)
```
example
```php
settings('global-settings.maintenance_scheduled_at');
settings('invoice.number_format.organizations.6');
settings()->for(\App\Models\Organization::find(6))
  ->get('invoice.number_format');
```
## Remove setting
```php
settings->unset('<group>.<key>')
```
with specific configurable model

```php
settings->unset('<group>.<key>.<table of configurable model>.<primary key>')
```
or 
```php
settings()->for(<configurable model>)
  ->unset('<group>.<key>');
```

example
```php
settings()->unset('global-settings.maintenance_scheduled_at');
settings()->unset('invoice.number_format.organizations.6');
settings()->for(\App\Models\Organization::find(6))
  ->unset('invoice.number_format');
```

# Configurable Model
Configurable model is a Eloquent Model represents an instance that owns custom configurations value.

## How to define
Model should implement `\Settings\Configurable::class` interface
example
```php
class User extends Model implements Configurable
{
  // ...
}
```
**Be careful**, using shortcut `settings()->set('<group>.<key>.<table of configurable model>.<primary key>', <value>);`, there is possibility you will store non-configurable model into settings table, then you can't use `settings()->for(\App\Models\Organization::find(6))` for any function.

## How it works
Configurable model will be stored as polymorphic relation at settings table. The field columns are `configurable_table` and `configurable_id`. By default `configurable_id` has `unsigned bigint` type, but you can change the type by define static variable `\Settings\Manager::$configurableMorphType` value with `uuid|int|string` at `AppServiceProvider` before you run `artisan migrate`.
```
class AppServiceProvider extends ServiceProvider
{
  public function register()
  {
    // ...
    \Settings\Manager::$configurableMorphType = 'uuid';
  }
}
```

# Accepted Value
- `string`
- `boolean`
- `double/float`
- `integer`
- `array`
- `\DatetimeInterface`
- Eloquent Model
- Model Collection
- Basic Collection
