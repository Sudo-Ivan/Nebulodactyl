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
        // Make any existing nodes use the Wings daemon protocol.
        DB::table('nodes')
            ->whereIn('daemonType', ['elytra'])
            ->orWhereNull('daemonType')
            ->update(['daemonType' => 'wings']);

        // Flip the column default so newly created nodes use Wings.
        $driver = DB::connection()->getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            Schema::table('nodes', function (Blueprint $table) {
                $table->string('daemonType', 16)->default('wings')->change();
            });
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE nodes ALTER COLUMN "daemonType" SET DEFAULT \'wings\'');
        } else {
            DB::statement("ALTER TABLE nodes ALTER COLUMN daemonType SET DEFAULT 'wings'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection()->getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            Schema::table('nodes', function (Blueprint $table) {
                $table->string('daemonType', 16)->default('elytra')->change();
            });
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE nodes ALTER COLUMN "daemonType" SET DEFAULT \'elytra\'');
        } else {
            DB::statement("ALTER TABLE nodes ALTER COLUMN daemonType SET DEFAULT 'elytra'");
        }
    }
};
