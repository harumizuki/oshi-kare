<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexTargetRequest;
use App\Http\Requests\StoreTargetRequest;
use App\Http\Requests\UpdateTargetRequest;
use App\Http\Resources\TargetResource;
use App\Models\Target;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
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

    public function show(Target $target): TargetResource
    {
        Gate::authorize('view', $target);

        $target->load('category');

        return new TargetResource($target);
    }

    public function update(UpdateTargetRequest $request, Target $target): TargetResource
    {
        Gate::authorize('update', $target);

        $target->update($request->validated());
        $target->load('category');

        return new TargetResource($target);
    }

    public function destroy(Target $target): Response
    {
        Gate::authorize('delete', $target);

        $target->delete();

        return response()->noContent();
    }
}
