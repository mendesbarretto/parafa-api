<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesCnpjData;
use Tests\TestCase;

class CnpjSitemapTest extends TestCase
{
    use CreatesCnpjData;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.pgsql2' => config('database.connections.sqlite')]);
        DB::purge('pgsql2');
    }

    public function test_partitions_companies_by_id_including_sparse_ids(): void
    {
        $this->createCompanyData();
        DB::connection('pgsql2')->table('companies')->where('id', 2)->update(['id' => 5001]);
        $this->getJson('/api/cnpj/sitemaps')->assertExactJson(['pages' => 2]);
        $this->getJson('/api/cnpj/sitemaps/1')->assertJsonCount(1, 'data')->assertJsonPath('data.0.cnpj', '16410532000137');
        $this->getJson('/api/cnpj/sitemaps/2')->assertJsonCount(1, 'data')->assertJsonPath('data.0.cnpj', '11111111000191');
        $this->getJson('/api/cnpj/sitemaps/0')->assertNotFound();
        $this->getJson('/api/cnpj/sitemaps/50001')->assertNotFound();
    }

    public function test_city_has_no_extra_page_for_exactly_twenty_companies(): void
    {
        $this->createCompanyData();
        for ($i = 3; $i <= 20; $i++) {
            DB::connection('pgsql2')->table('companies')->insert(['id' => $i, 'cnpj' => str_pad((string) $i, 14, '0', STR_PAD_LEFT), 'city_id' => 2927408]);
        }
        $this->getJson('/api/cnpj/cities/salvador-ba')->assertJsonCount(20, 'data')->assertJsonPath('meta.has_more_pages', false);
    }

    public function test_company_includes_secondary_activities(): void
    {
        $this->createCompanyData();
        DB::connection('pgsql2')->table('secondary_activities')->insert(['company_id' => 1, 'activity_id' => 1]);
        $this->getJson('/api/cnpj/companies/16410532000137')->assertJsonPath('data.secondary_activities.0.activities.name', 'Atividade de teste');
    }
}
