<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CityPg;
use App\Models\CompanyPg;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CnpjController extends Controller
{
    public function companies(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => 'sometimes|string|max:120',
            'city' => 'sometimes|string|max:120',
            'state' => 'sometimes|string|size:2',
            'city_id' => 'sometimes|string|max:40',
            'per_page' => 'sometimes|integer|min:1|max:50',
            'page' => 'sometimes|integer|min:1',
        ]);

        $query = CompanyPg::query()->select([
            'id', 'url', 'name', 'fantasy', 'cnpj', 'street', 'number',
            'complement', 'neighborhood', 'zip_code', 'city', 'state',
            'opening', 'activities', 'situation',
        ])->orderByDesc('id');

        if (isset($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($companyQuery) use ($search) {
                $companyQuery->where('cnpj', 'ilike', "%{$search}%")
                    ->orWhere('name', 'ilike', "%{$search}%")
                    ->orWhere('fantasy', 'ilike', "%{$search}%");
            });
        }

        $query->when(isset($validated['city']), fn ($q) => $q->where('city', 'ilike', $validated['city']))
            ->when(isset($validated['state']), fn ($q) => $q->where('state', strtoupper($validated['state'])))
            ->when(isset($validated['city_id']), fn ($q) => $q->where('city_id', $validated['city_id']));

        $companies = $query->simplePaginate($validated['per_page'] ?? 20);

        return response()->json([
            'data' => $companies->items(),
            'meta' => [
                'current_page' => $companies->currentPage(),
                'per_page' => $companies->perPage(),
                'has_more_pages' => $companies->hasMorePages(),
            ],
        ]);
    }

    public function company(string $cnpj): JsonResponse
    {
        $company = CompanyPg::with(['legalNature', 'activity'])
            ->where('cnpj', $cnpj)
            ->firstOrFail();

        $related = CompanyPg::query()
            ->select(['id', 'url', 'name', 'fantasy', 'cnpj', 'city', 'state'])
            ->where('city_id', $company->city_id)
            ->where('id', '!=', $company->id)
            ->orderBy('id')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $company,
            'related' => $related,
        ]);
    }

    public function city(string $citySlug, Request $request): JsonResponse
    {
        $state = strtoupper(substr($citySlug, -2));
        $cityUrl = substr($citySlug, 0, -3);
        $city = CityPg::where('url', $cityUrl)->where('state', $state)->firstOrFail();
        $after = $request->integer('after', 0);

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

        return response()->json([
            'city' => $city,
            'data' => $query,
            'meta' => [
                'next_after' => $query->last()?->id,
                'has_more_pages' => $query->count() === 10,
            ],
        ]);
    }

    public function cities(): JsonResponse
    {
        $cities = CityPg::query()
            ->select(['id', 'name', 'state', 'url'])
            ->selectSub(
                CompanyPg::query()
                    ->selectRaw('count(*)')
                    ->whereRaw('companies.city_id::text = cities.id'),
                'companies_count'
            )
            ->orderBy('state')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $cities]);
    }

    public function bestCities(): JsonResponse
    {
        $cities = CityPg::query()
            ->select(['id', 'name', 'state', 'url'])
            ->selectSub(
                CompanyPg::query()
                    ->selectRaw('count(*)')
                    ->whereRaw('companies.city_id::text = cities.id'),
                'companies_count'
            )
            ->orderByDesc('companies_count')
            ->limit(20)
            ->get();

        return response()->json(['data' => $cities]);
    }
}