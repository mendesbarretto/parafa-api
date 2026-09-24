<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql2';

    public function up(): void
    {
        Schema::connection('pgsql2')->create('cnpj_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('cnpj', 14)->index();
            $table->string('name', 150);
            $table->string('email');
            $table->string('relationship', 40);
            $table->string('action', 20);
            $table->text('message');
            $table->string('token_hash', 64);
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->string('status', 30)->default('pending_email')->index();
            $table->string('reviewed_by')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
        Schema::connection('pgsql2')->create('cnpj_suppressions', function (Blueprint $table): void {
            $table->id();
            $table->string('cnpj', 14)->unique();
            $table->uuid('request_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql2')->dropIfExists('cnpj_suppressions');
        Schema::connection('pgsql2')->dropIfExists('cnpj_requests');
    }
};
