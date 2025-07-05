<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::orderBy('name', 'asc')->get(['id', 'name', 'slug']);
        return response()->json($categories);
    }
}
