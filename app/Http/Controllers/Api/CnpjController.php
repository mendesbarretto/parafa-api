<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CityPg;
use App\Models\CompanyPg;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CnpjController extends Controller
{
    public function companies(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => 'sometimes|string|min:3|max:120',
            'city' => 'sometimes|string|max:120',
            'state' => 'sometimes|string|size:2',
            'city_id' => 'sometimes|string|max:40',
            'per_page' => 'sometimes|integer|min:1|max:50',
            'page' => 'sometimes|integer|min:1',
        ]);

        $cacheKey = 'cnpj:companies:v2:'.md5(json_encode($validated));

        $result = Cache::remember($cacheKey, now()->addHours(12), function () use ($validated) {
            $query = CompanyPg::query()->select([
                'id', 'url', 'name', 'fantasy', 'cnpj', 'street', 'number',
                'complement', 'neighborhood', 'zip_code', 'city', 'state',
                'opening', 'activities', 'situation',
            ])->orderByDesc('id');

            if (isset($validated['search'])) {
                $search = trim($validated['search']);
                $cnpj = preg_replace('/\D/', '', $search);

                if (strlen($cnpj) === 14) {
                    $query->where('cnpj', $cnpj);
                } else {
                    $query->where(function ($companyQuery) use ($search) {
                        $companyQuery->where('name', 'ilike', "%{$search}%")
                            ->orWhere('fantasy', 'ilike', "%{$search}%");
                    });
                }
            }

            $query->when(isset($validated['city']), fn ($q) => $q->where('city', 'ilike', $validated['city']))
                ->when(isset($validated['state']), fn ($q) => $q->where('state', strtoupper($validated['state'])))
                ->when(isset($validated['city_id']), fn ($q) => $q->where('city_id', $validated['city_id']));

            $companies = $query->simplePaginate($validated['per_page'] ?? 20);

            return [
                'data' => $companies->getCollection()->toArray(),
                'meta' => [
                    'current_page' => $companies->currentPage(),
                    'per_page' => $companies->perPage(),
                    'has_more_pages' => $companies->hasMorePages(),
                ],
            ];
        });

        return $this->cachedJson($result, 43200);
    }

    public function company(string $cnpj): JsonResponse
    {
        $cacheKey = "cnpj:company:v2:{$cnpj}";

        $result = Cache::remember($cacheKey, now()->addDay(), function () use ($cnpj) {
            $company = CompanyPg::with(['legalNature', 'activity'])
                ->where('cnpj', $cnpj)
                ->firstOrFail();

            $related = CompanyPg::query()
                ->select(['id', 'url', 'name', 'fantasy', 'cnpj', 'city', 'state'])
                ->where('city_id', $company->city_id)
                ->where('id', '!=', $company->id)
                ->orderBy('id')
                ->limit(12)
                ->get();

            return [
                'data' => $company->toArray(),
                'related' => $related->toArray(),
            ];
        });

        return $this->cachedJson($result, 86400);
    }

    public function city(string $citySlug, Request $request): JsonResponse
    {
        $state = strtoupper(substr($citySlug, -2));
        $cityUrl = substr($citySlug, 0, -3);
        $after = $request->integer('after', 0);

        $cacheKey = "cnpj:city:v2:{$citySlug}:{$after}";

        $result = Cache::remember($cacheKey, now()->addDay(), function () use ($cityUrl, $state, $after) {
            $city = CityPg::where('url', $cityUrl)->where('state', $state)->firstOrFail();
            $cityId = (int) $city->id;

            $query = CompanyPg::query()->select([
                'id', 'url', 'name', 'fantasy', 'cnpj', 'street', 'number',
                'complement', 'neighborhood', 'zip_code', 'city', 'state',
                'opening', 'activities', 'situation',
            ])->where('city_id', $cityId)
                ->when($after > 0, fn ($q) => $q->where('id', '>', $after))
                ->orderBy('id')
                ->limit(10)
                ->get();

            return [
                'city' => $city->toArray(),
                'data' => $query->toArray(),
                'meta' => [
                    'next_after' => $query->last()?->id,
                    'has_more_pages' => $query->count() === 10,
                ],
            ];
        });

        return $this->cachedJson($result, 86400);
    }

    public function cities(): JsonResponse
    {
        $cities = Cache::remember('cnpj:cities:v1', now()->addDay(), fn () => CityPg::query()
            ->select(['id', 'name', 'state', 'url'])
            ->orderBy('state')
            ->orderBy('name')
            ->get()
            ->toArray());

        return $this->cachedJson(['data' => $cities], 86400);
    }

    public function bestCities(): JsonResponse
    {
        $cities = Cache::remember('cnpj:featured-cities:v1', now()->addDay(), fn () => CityPg::query()
            ->select(['id', 'name', 'state', 'url'])
            ->orderBy('state')
            ->orderBy('name')
            ->limit(20)
            ->get()
            ->toArray());

        return $this->cachedJson(['data' => $cities], 86400);
    }

    private function cachedJson(array $payload, int $maxAge): JsonResponse
    {
        return response()
            ->json($payload)
            ->header('Cache-Control', "public, max-age=60, s-maxage={$maxAge}, stale-while-revalidate=60");
    }
}
