<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexTargetRequest;
use App\Http\Requests\StoreTargetRequest;
use App\Http\Resources\TargetResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

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

    public function store(StoreTargetRequest $request): JsonResponse
    {
        $target = $request->user()
            ->targets()
            ->create($request->validated());

        $target->load('category');

        return (new TargetResource($target))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
