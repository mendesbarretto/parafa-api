<?php

namespace Tests\Feature;

use App\Models\CityPg;
use App\Models\CompanyPg;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CnpjCacheTest extends TestCase
{
    private string $cacheDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cacheDirectory = sys_get_temp_dir().'/cnpj-cache-test-'.bin2hex(random_bytes(8));
        config([
            'cache.default' => 'file',
            'cache.stores.file.path' => $this->cacheDirectory,
            'database.connections.pgsql2' => config('database.connections.'.config('database.default')),
        ]);
        Cache::purge('file');
        DB::purge('pgsql2');
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->cacheDirectory);
        DB::purge('pgsql2');

        parent::tearDown();
    }

    #[DataProvider('cachedEndpoints')]
    public function test_returns_the_same_company_data_from_file_cache(string $uri, array $expected): void
    {
        $this->createCompanyData();

        $fresh = $this->getJson($uri)->assertOk()->assertJson($expected);
        DB::connection('pgsql2')->enableQueryLog();
        $cached = $this->getJson($uri)->assertOk()->assertJson($expected);

        $this->assertSame($fresh->json(), $cached->json());
        $this->assertSame([], DB::connection('pgsql2')->getQueryLog());
    }

    #[DataProvider('legacyEndpoints')]
    public function test_ignores_legacy_cache_entries_containing_php_objects(string $uri, string $legacyKey, array $expected): void
    {
        $this->createCompanyData();
        Cache::put($legacyKey, [
            'data' => CompanyPg::firstOrFail(),
            'city' => CityPg::firstOrFail(),
            'related' => CompanyPg::all(),
        ], 3600);

        $this->getJson($uri)->assertOk()->assertJson($expected);
    }

    public static function cachedEndpoints(): array
    {
        return [
            'company and relations' => [
                '/api/cnpj/companies/16410532000137',
                [
                    'data' => [
                        'cnpj' => '16410532000137', 'name' => 'Empresa de teste',
                        'legal_nature' => ['name' => 'Associacao'],
                        'activity' => ['name' => 'Atividade de teste'],
                    ],
                    'related' => [['cnpj' => '11111111000191']],
                ],
            ],
            'city and company list' => [
                '/api/cnpj/cities/salvador-ba',
                ['city' => ['name' => 'Salvador'], 'data' => [['cnpj' => '16410532000137']]],
            ],
            'search results' => [
                '/api/cnpj/companies?search=16410532000137',
                ['data' => [['cnpj' => '16410532000137']], 'meta' => ['current_page' => 1]],
            ],
        ];
    }

    public static function legacyEndpoints(): array
    {
        $endpoints = self::cachedEndpoints();

        return [
            'company' => [$endpoints['company and relations'][0], 'cnpj:company:16410532000137', $endpoints['company and relations'][1]],
            'city' => [$endpoints['city and company list'][0], 'cnpj:city:salvador-ba:0', $endpoints['city and company list'][1]],
            'search' => [$endpoints['search results'][0], 'cnpj:companies:'.md5('{"search":"16410532000137"}'), $endpoints['search results'][1]],
        ];
    }

    private function createCompanyData(): void
    {
        $schema = Schema::connection('pgsql2');
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
            foreach (['cnpj', 'name', 'fantasy', 'url', 'street', 'number', 'complement', 'neighborhood', 'zip_code', 'city', 'state', 'opening', 'activities', 'situation'] as $column) {
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
