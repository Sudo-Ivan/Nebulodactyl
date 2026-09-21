<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::connection()->getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME);

        switch ($driver) {
            case 'pgsql':
                DB::statement('ALTER TABLE nodes DROP CONSTRAINT IF EXISTS nodes_daemontype_check');
                DB::statement("ALTER TABLE nodes ADD CONSTRAINT nodes_daemontype_check CHECK (\"daemonType\" IN ('wings', 'elytra', 'comet'))");
                DB::statement('ALTER TABLE nodes ALTER COLUMN "daemonType" SET DEFAULT \'comet\'');
                break;
            case 'sqlite':
                // SQLite cannot alter check constraints, a native change()
                // rebuilds the table with the new column definition.
                Schema::table('nodes', function (Blueprint $table) {
                    $table->enum('daemonType', ['wings', 'elytra', 'comet'])->default('comet')->comment('What daemon Type this node uses')->change();
                });
                break;
            default:
                Schema::table('nodes', function (Blueprint $table) {
                    $table->enum('daemonType', ['wings', 'elytra', 'comet'])->default('comet')->comment('What daemon Type this node uses')->change();
                });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection()->getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME);

        switch ($driver) {
            case 'pgsql':
                DB::statement("UPDATE nodes SET \"daemonType\" = 'wings' WHERE \"daemonType\" = 'comet'");
                DB::statement('ALTER TABLE nodes DROP CONSTRAINT IF EXISTS nodes_daemontype_check');
                DB::statement("ALTER TABLE nodes ADD CONSTRAINT nodes_daemontype_check CHECK (\"daemonType\" IN ('wings', 'elytra'))");
                break;
            default:
                DB::table('nodes')->where('daemonType', 'comet')->update(['daemonType' => 'wings']);
                Schema::table('nodes', function (Blueprint $table) {
                    $table->enum('daemonType', ['wings', 'elytra'])->default('wings')->comment('What daemon Type this node uses')->change();
                });
        }
    }
};
