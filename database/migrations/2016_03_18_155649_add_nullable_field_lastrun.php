<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddNullableFieldLastrun extends Migration
{
  /**
   * Run the migrations.
   */
  public function up()
  {
    $driver = DB::getDriverName();

    if ($driver === 'sqlite') {
      Schema::table('tasks', function (Blueprint $table) {
        $table->timestamp('last_run')->nullable()->change();
      });
    } elseif ($driver === 'pgsql') {
      // PostgreSQL-specific syntax
      DB::statement('ALTER TABLE ' . DB::getQueryGrammar()->wrapTable('tasks') . ' ALTER COLUMN last_run DROP NOT NULL;');
    } else {
      // MySQL/MariaDB-specific syntax
      DB::statement('ALTER TABLE ' . DB::getQueryGrammar()->wrapTable('tasks') . ' CHANGE `last_run` `last_run` TIMESTAMP NULL;');
    }
  }

  /**
   * Reverse the migrations.
   */
  public function down()
  {
    $driver = DB::getDriverName();

    if ($driver === 'sqlite') {
      Schema::table('tasks', function (Blueprint $table) {
        $table->timestamp('last_run')->nullable(false)->change();
      });
    } elseif ($driver === 'pgsql') {
      // PostgreSQL-specific syntax
      DB::statement('ALTER TABLE ' . DB::getQueryGrammar()->wrapTable('tasks') . ' ALTER COLUMN last_run SET NOT NULL;');
    } else {
      // MySQL/MariaDB-specific syntax
      DB::statement('ALTER TABLE ' . DB::getQueryGrammar()->wrapTable('tasks') . ' CHANGE `last_run` `last_run` TIMESTAMP;');
    }
  }
}
