<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/*
|--------------------------------------------------------------------------
| Nebula overlay host certificates
|--------------------------------------------------------------------------
|
| Records every certificate the panel has signed for the Nebula overlay.
| Each enrolled node gets one active row carrying its overlay address and
| the issued certificate, which lets the panel prefer overlay addresses for
| daemon traffic and provides an audit trail for revocation.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nebula_hosts', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('node_id')->nullable();
            $table->string('name');
            $table->string('ip', 45)->unique();
            $table->text('public_key');
            $table->string('fingerprint', 64)->index();
            $table->text('certificate');
            $table->string('serial', 64)->nullable();
            $table->json('groups')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->foreign('node_id')->references('id')->on('nodes')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nebula_hosts');
    }
};
