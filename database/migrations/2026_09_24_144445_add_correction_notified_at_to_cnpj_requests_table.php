<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql2';

    public function up(): void
    {
        Schema::connection('pgsql2')->table('cnpj_requests', function (Blueprint $table): void {
            $table->timestamp('correction_notified_at')->nullable();
            $table->index(['action', 'status', 'verified_at'], 'cnpj_requests_processing_index');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql2')->table('cnpj_requests', function (Blueprint $table): void {
            $table->dropIndex('cnpj_requests_processing_index');
            $table->dropColumn('correction_notified_at');
        });
    }
};
