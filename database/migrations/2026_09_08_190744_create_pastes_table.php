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
        Schema::create('pastes', function (Blueprint $table) {
            $table->string('id', 32)->primary();
            $table->longText('payload');
            $table->string('format', 20)->default('plaintext');
            $table->boolean('has_password')->default(false);
            $table->boolean('burn_after_reading')->default(false);
            $table->string('delete_token_hash', 64);
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pastes');
    }
};
