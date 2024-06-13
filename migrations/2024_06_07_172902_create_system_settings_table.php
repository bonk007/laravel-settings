<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(\Settings\Manager::$settingsTableName, function (Blueprint $table) {
            $table->string('group_name')->index();
            $table->string('key_name')->index();
            $this->defineConfigurableIdColumn($table);
            $table->string('configurable_table')->nullable()->index();
            $table->json('value')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(\Settings\Manager::$settingsTableName);
    }

    private function defineConfigurableIdColumn(Blueprint $table): void
    {
        switch (\Settings\Manager::$configurableMorphType) {
            case 'int':
                $table->unsignedInteger('configurable_id')->nullable()->index();
                break;
            case 'bigint':
                $table->unsignedBigInteger('configurable_id')->nullable()->index();
                break;
            case 'uuid':
                $table->uuid('configurable_id')->nullable()->index();
                break;
            default:
                $table->string('configurable_id')->nullable()->index();
        }
    }
};
