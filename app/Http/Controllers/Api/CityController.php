<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\City;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = City::query();

        if ($request->has('state')) {
            $query->byState($request->state);
        }

        $cities = $query->withCount('customers')->get();

        return response()->json($cities);
    }

    public function show(string $id): JsonResponse
    {
        $city = City::with('customers')->findOrFail($id);

        return response()->json($city);
    }

    public function states(): JsonResponse
    {
        $states = City::select('state')
            ->distinct()
            ->orderBy('state')
            ->pluck('state');

        return response()->json($states);
    }
}
