<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Query simplificada para performance
            $query = Customer::select([
                'id', 'name', 'description', 'neighborhood', 'city', 'state',
                'category_id', 'city_id', 'url'
            ])->where('status', '1');

            // Filtros
            if ($request->has('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            if ($request->has('city_id')) {
                $query->where('city_id', $request->city_id);
            }

            if ($request->has('state')) {
                $query->where('state', $request->state);
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'ilike', "%{$search}%")
                      ->orWhere('description', 'ilike', "%{$search}%");
                });
            }

            // Paginação mais conservadora
            $perPage = min($request->get('per_page', 5), 20);
            $customers = $query->orderBy('id', 'desc')->limit($perPage)->get();

            return response()->json([
                'data' => $customers,
                'meta' => [
                    'per_page' => $perPage,
                    'total' => count($customers),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'city_id' => 'required|exists:cities,id',
            'description' => 'nullable|string',
            'address' => 'nullable|string|max:255',
            'number' => 'nullable|integer',
            'complement' => 'nullable|string|max:255',
            'neighborhood' => 'nullable|string|max:255',
            'zipcode' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'site' => 'nullable|string',
            'phone' => 'nullable|string',
        ]);

        $customer = Customer::create($validated);

        return response()->json($customer, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $customer = Customer::select([
                'id', 'name', 'slogan', 'description', 'address', 'number',
                'complement', 'neighborhood', 'zipcode', 'city', 'state',
                'site', 'email', 'category_id', 'city_id', 'url', 'status'
            ])->where('status', '1')->findOrFail($id);

            return response()->json($customer);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'category_id' => 'sometimes|exists:categories,id',
            'city_id' => 'sometimes|exists:cities,id',
            'description' => 'nullable|string',
            'address' => 'nullable|string|max:255',
            'number' => 'nullable|integer',
            'complement' => 'nullable|string|max:255',
            'neighborhood' => 'nullable|string|max:255',
            'zipcode' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'site' => 'nullable|string',
            'status' => 'sometimes|string|max:1',
        ]);

        $customer->update($validated);

        return response()->json($customer);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);
        $customer->delete();

        return response()->json(null, 204);
    }
}
