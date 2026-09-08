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
        Schema::create('empresas', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('nome');
            $table->string('categoria');
            $table->text('descricao');
            $table->string('endereco');
            $table->string('bairro');
            $table->string('cidade');
            $table->string('uf', 2);
            $table->string('cep');
            $table->string('telefone');
            $table->string('whatsapp')->nullable();
            $table->string('email')->nullable();
            $table->string('site')->nullable();
            $table->string('horario')->nullable();
            $table->decimal('nota', 2, 1)->default(0);
            $table->integer('avaliacoes')->default(0);
            $table->boolean('verificada')->default(false);
            $table->boolean('ativa')->default(true);
            $table->timestamps();

            $table->index('categoria');
            $table->index('cidade');
            $table->index('uf');
            $table->index('verificada');
            $table->index('ativa');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('empresas');
    }
};
