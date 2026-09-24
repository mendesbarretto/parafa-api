<?php

namespace Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait CreatesCnpjData
{
    private function createCompanyData(): void
    {
        $schema = Schema::connection('pgsql2');
        (require database_path('migrations/2026_09_23_204634_create_cnpj_privacy_tables.php'))->up();
        (require database_path('migrations/2026_09_24_144445_add_correction_notified_at_to_cnpj_requests_table.php'))->up();
        $schema->create('secondary_activities', function (Blueprint $table): void {
            $table->integer('company_id');
            $table->integer('activity_id');
        });
        $schema->create('cities', function (Blueprint $table): void {
            $table->temporary();
            $table->string('id')->primary();
            $table->string('name');
            $table->string('state');
            $table->string('url');
        });
        foreach (['legal_natures', 'activities'] as $name) {
            $schema->create($name, function (Blueprint $table): void {
                $table->temporary();
                $table->integer('id')->primary();
                $table->string('name');
            });
        }
        $schema->create('companies', function (Blueprint $table): void {
            $table->temporary();
            $table->integer('id')->primary();
            $table->integer('city_id');
            $table->integer('legal_nature')->nullable();
            $table->integer('main_activity')->nullable();
            foreach (['cnpj', 'name', 'fantasy', 'url', 'street', 'number', 'complement', 'neighborhood', 'zip_code', 'city', 'state', 'opening', 'activities', 'situation', 'last_update'] as $column) {
                $table->string($column)->nullable();
            }
        });

        $connection = DB::connection('pgsql2');
        $connection->table('cities')->insert(['id' => '2927408', 'name' => 'Salvador', 'state' => 'BA', 'url' => 'salvador']);
        $connection->table('legal_natures')->insert(['id' => 1, 'name' => 'Associacao']);
        $connection->table('activities')->insert(['id' => 1, 'name' => 'Atividade de teste']);
        $connection->table('companies')->insert([
            ['id' => 1, 'city_id' => 2927408, 'cnpj' => '16410532000137', 'name' => 'Empresa de teste', 'city' => 'Salvador', 'state' => 'BA', 'legal_nature' => 1, 'main_activity' => 1, 'opening' => '1988-05-03', 'activities' => '[{"code":"123","name":"Atividade"}]'],
            ['id' => 2, 'city_id' => 2927408, 'cnpj' => '11111111000191', 'name' => 'Relacionada', 'city' => 'Salvador', 'state' => 'BA', 'legal_nature' => null, 'main_activity' => null, 'opening' => null, 'activities' => null],
        ]);
    }
}
