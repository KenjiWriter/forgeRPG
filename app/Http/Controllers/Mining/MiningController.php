<?php

namespace App\Http\Controllers\Mining;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mining\HitRequest;
use App\Models\MiningNode;
use App\Services\MiningService;
use Illuminate\Http\JsonResponse;

class MiningController extends Controller
{
    public function __construct(private readonly MiningService $miningService) {}

    public function hit(HitRequest $request): JsonResponse
    {
        $node = MiningNode::findOrFail($request->integer('node_id'));
        $result = $this->miningService->hit($request->user(), $node);

        return response()->json($result);
    }
}
