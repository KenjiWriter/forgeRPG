<?php

namespace App\Http\Controllers\Forge;

use App\Http\Controllers\Controller;
use App\Http\Requests\Forge\ForgeCompleteRequest;
use App\Http\Requests\Forge\ForgeInitRequest;
use App\Models\ForgeSession;
use App\Models\OreType;
use App\Services\ForgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ForgeController extends Controller
{
    public function __construct(private readonly ForgeService $forgeService) {}

    /**
     * Display the Forge page with player inventory.
     */
    public function index(Request $request): Response
    {
        $user = $request->user()->load('inventory');

        // Load player's ore inventory
        $inventory = $user->inventory()
            ->where('holdable_type', OreType::class)
            ->with('holdable')
            ->get()
            ->map(fn ($slot) => [
                'id' => $slot->holdable->id,
                'name' => $slot->holdable->name,
                'quantity' => $slot->quantity,
            ]);

        return Inertia::render('Forge/Index', [
            'inventory' => $inventory,
        ]);
    }

    /**
     * Initialize a forge session with ore inputs.
     */
    public function init(ForgeInitRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        // Validate Rule of 3 Ores
        $validation = $this->forgeService->validateOreInputs($data['ore_inputs']);
        if (! $validation['valid']) {
            abort(422, $validation['error']);
        }

        // Validate player owns sufficient ores
        $ownership = $this->forgeService->validatePlayerOwnsOres($user, $data['ore_inputs']);
        if (! $ownership['valid']) {
            abort(422, $ownership['error']);
        }

        // Consume ores from inventory
        $this->forgeService->consumeOres($user, $data['ore_inputs']);

        // Create forge session
        $session = $this->forgeService->createForgeSession(
            $user,
            $data['target_slot'],
            $data['ore_inputs']
        );

        return response()->json([
            'forge_session_id' => $session->id,
            'target_slot' => $session->target_slot,
            'ore_inputs' => $session->ore_inputs,
            'status' => $session->status,
            'next_stage' => 'smelting',
        ]);
    }

    /**
     * Complete a forge session with collected telemetry scores.
     */
    public function complete(ForgeCompleteRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        /** @var ForgeSession $session */
        $session = ForgeSession::findOrFail($data['forge_session_id']);

        // Ensure the session belongs to this player
        if ($session->player_id !== $user->id) {
            abort(403, 'This forge session does not belong to you.');
        }

        // Ensure the session is in progress
        if ($session->status !== 'in_progress') {
            abort(422, 'This forge session is not in progress.');
        }

        // Complete the forge
        $result = $this->forgeService->completeForge(
            $session,
            $data['smelting_score'],
            $data['smithing_score'],
            $data['quench_score'],
            $data['item_name']
        );

        return response()->json([
            'item' => [
                'id' => $result['item']->id,
                'name' => $result['item']->name,
                'target_slot' => $result['item']->target_slot,
                'forge_grade' => $result['item']->forge_grade,
                'hp_bonus' => $result['item']->hp_bonus,
                'attack_bonus' => $result['item']->attack_bonus,
                'defense_bonus' => $result['item']->defense_bonus,
                'mining_speed_bonus' => $result['item']->mining_speed_bonus,
                'attack_speed_bonus' => $result['item']->attack_speed_bonus,
                'dodge_bonus' => $result['item']->dodge_bonus,
                'final_stats' => $result['item']->final_stats,
            ],
            'grade' => $result['grade'],
            'combined_score' => $result['combined_score'],
        ]);
    }
}
