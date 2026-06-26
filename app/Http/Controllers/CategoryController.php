<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = $request->user()->categories()->create($request->validated());

        return response()->json([
            'id' => $category->id,
            'name' => $category->name,
            'type' => $category->type->value,
        ], 201);
    }
}
