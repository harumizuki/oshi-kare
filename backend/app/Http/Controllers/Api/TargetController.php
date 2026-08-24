<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexTargetRequest;
use App\Http\Resources\TargetResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TargetController extends Controller
{
    public function index(IndexTargetRequest $request): AnonymousResourceCollection
    {
        $keyword = $request->validated('keyword');

        $targets = $request->user()
            ->targets()
            ->with('category')
            ->when(
                filled($keyword),
                fn ($query) => $query->where('name', 'like', '%'.$keyword.'%')
            )
            ->get();

        return TargetResource::collection($targets);
    }
}
