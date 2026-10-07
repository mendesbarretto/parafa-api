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
        config(['database.connections.pgsql2' => config('database.connections.'.config('database.default'))]);
        DB::purge('pgsql2');
        DB::connection('pgsql2')->beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::connection('pgsql2')->rollBack();
        DB::purge('pgsql2');
        parent::tearDown();
    }

    public function test_empty_registry_returns_zero_shards(): void
    {
        $this->createCompanyData();
        DB::connection('pgsql2')->table('companies')->delete();

        $this->getJson('/api/cnpj/sitemaps')->assertOk()->assertExactJson(['pages' => 0]);
    }

    public function test_index_cache_avoids_database_queries_on_repeated_requests(): void
    {
        $this->createCompanyData();
        $this->getJson('/api/cnpj/sitemaps')->assertOk()->assertExactJson(['pages' => 1]);
        $connection = DB::connection('pgsql2');
        $connection->enableQueryLog();

        $this->getJson('/api/cnpj/sitemaps')->assertOk()->assertExactJson(['pages' => 1]);

        $this->assertSame([], $connection->getQueryLog());
    }

    public function test_index_refreshes_new_shard_boundaries_after_cache_expiration(): void
    {
        $this->freezeTime();
        $this->createCompanyData();
        $this->getJson('/api/cnpj/sitemaps')->assertExactJson(['pages' => 1]);
        DB::connection('pgsql2')->table('companies')->where('id', 2)->update(['id' => 5001]);

        $this->getJson('/api/cnpj/sitemaps')->assertExactJson(['pages' => 1]);
        $this->travel(61)->minutes();

        $this->getJson('/api/cnpj/sitemaps')->assertExactJson(['pages' => 2]);
    }

    public function test_suppressed_companies_stay_hidden_with_cached_shard_boundaries(): void
    {
        $this->createCompanyData();
        $connection = DB::connection('pgsql2');
        $connection->table('companies')->where('id', 2)->update(['id' => 5001]);
        $connection->table('cnpj_suppressions')->insert(['cnpj' => '11111111000191']);

        $this->getJson('/api/cnpj/sitemaps')->assertExactJson(['pages' => 2]);
        $this->getJson('/api/cnpj/sitemaps/2')->assertOk()->assertExactJson(['data' => []]);
        $this->getJson('/api/cnpj/sitemaps/1')->assertJsonCount(1, 'data');

        $connection->table('cnpj_suppressions')->insert(['cnpj' => '16410532000137']);

        $this->getJson('/api/cnpj/sitemaps')->assertExactJson(['pages' => 2]);
        $this->getJson('/api/cnpj/sitemaps/1')->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_postgres_index_lookup_reads_one_company_instead_of_scanning_the_registry(): void
    {
        $connection = DB::connection('pgsql2');
        if ($connection->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('The production query plan must be verified on PostgreSQL.');
        }

        $this->createCompanyData();
        $connection->statement("INSERT INTO companies (id, city_id, cnpj) SELECT n, 2927408, lpad(n::text, 14, '0') FROM generate_series(3, 10000) n");
        $connection->table('cnpj_suppressions')->insert(['cnpj' => '00000000010000']);
        $connection->statement('ANALYZE companies');
        $connection->statement('ANALYZE cnpj_suppressions');
        $connection->enableQueryLog();

        $this->getJson('/api/cnpj/sitemaps')->assertOk()->assertExactJson(['pages' => 2]);

        $queries = array_values(array_filter($connection->getQueryLog(), fn (array $query): bool => str_contains($query['query'], '"companies"')));
        $this->assertCount(1, $queries);
        $result = $connection->select('EXPLAIN (ANALYZE, FORMAT JSON) '.$queries[0]['query'], $queries[0]['bindings']);
        $plan = json_decode($result[0]->{'QUERY PLAN'}, true, flags: JSON_THROW_ON_ERROR)[0]['Plan'];
        $nodes = [$plan];
        $companyScans = 0;
        while ($nodes !== []) {
            $node = array_pop($nodes);
            if (($node['Relation Name'] ?? null) === 'companies') {
                $companyScans++;
                $this->assertContains($node['Node Type'], ['Index Scan', 'Index Only Scan']);
                $this->assertLessThanOrEqual(1, $node['Actual Rows'] * $node['Actual Loops']);
            }
            array_push($nodes, ...($node['Plans'] ?? []));
        }
        $this->assertSame(1, $companyScans);
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

    public function test_sitemap_page_reflects_company_changes_on_the_next_request(): void
    {
        $this->createCompanyData();

        $this->getJson('/api/cnpj/sitemaps/1')->assertJsonPath('data.0.name', 'Empresa de teste');

        DB::connection('pgsql2')->table('companies')
            ->where('cnpj', '16410532000137')
            ->update(['name' => 'Empresa atualizada']);

        $this->getJson('/api/cnpj/sitemaps/1')->assertJsonPath('data.0.name', 'Empresa atualizada');
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
