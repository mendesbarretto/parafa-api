<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CnpjSuppression;
use App\Models\CompanyPg;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class CnpjSitemapController extends Controller
{
    private const SHARD_SIZE = 5000;

    public function index(): JsonResponse
    {
        $maxId = Cache::remember('cnpj:sitemap:max:'.CnpjSuppression::cacheVersion(), 3600,
            fn (): int => (int) CompanyPg::max('id'));

        return response()->json(['pages' => (int) ceil($maxId / self::SHARD_SIZE)])
            ->header('Cache-Control', 'private, no-store');
    }

    public function companies(string $page): JsonResponse
    {
        abort_unless(ctype_digit($page) && (int) $page >= 1 && (int) $page <= 50000, 404);
        $start = ((int) $page - 1) * self::SHARD_SIZE;
        $data = Cache::remember('cnpj:sitemap:'.(int) $page.':'.CnpjSuppression::cacheVersion(), 3600,
            fn (): array => CompanyPg::where('id', '>', $start)->where('id', '<=', $start + self::SHARD_SIZE)
                ->orderBy('id')->get(['id', 'url', 'cnpj', 'name', 'city', 'state', 'last_update'])->toArray());

        return response()->json(['data' => $data])->header('Cache-Control', 'private, no-store');
    }
}
