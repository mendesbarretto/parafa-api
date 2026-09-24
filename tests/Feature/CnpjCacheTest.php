<?php

namespace Tests\Feature;

use App\Models\CityPg;
use App\Models\CompanyPg;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesCnpjData;
use Tests\TestCase;

class CnpjCacheTest extends TestCase
{
    use CreatesCnpjData;

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
        $companyQueries = array_filter(DB::connection('pgsql2')->getQueryLog(), fn (array $query): bool => ! str_contains($query['query'], 'cnpj_suppressions') && ! str_contains($query['query'], 'cnpj_requests'));
        $this->assertSame([], array_values($companyQueries));
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
}
