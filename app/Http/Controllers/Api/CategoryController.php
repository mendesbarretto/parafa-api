<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $categories = Category::select('id', 'name', 'url', 'department_id')
                ->withCount('customers')
                ->limit(100)
                ->get();

            return response()->json([
                'data' => $categories,
                'total' => Category::count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $category = Category::select('id', 'name', 'url', 'department_id')
                ->withCount('customers')
                ->where(function ($query) use ($id) {
                    $query->where('id', $id)->orWhere('url', $id);
                })
                ->firstOrFail();

            return response()->json($category);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
