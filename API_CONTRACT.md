# API Contract

> Maintained by the **Game Architect** agent.
> All endpoints must be implemented server-side with full re-validation. Client inputs are never trusted for game-state calculations.
> Last updated: 2026-05-05

---

## Conventions

- All routes are prefixed `/api/` and require an authenticated session.
- All responses use `application/json`.
- HTTP errors follow Laravel's default validation response format (`{ message, errors }`).
- Integer stats (HP, stamina, damage) are always returned as whole integers — no floats.
- Prices are in **cents** throughout (e.g., 375 = $3.75).

---

## Authentication

All game API routes sit behind the `auth` session guard (standard Laravel web auth). Unauthenticated requests return `401 Unauthorized`.

---

## Mining API

### `POST /api/mining/hit`

Processes a single mining click against a node. The server re-computes stamina and damage authoritatively; the client's `stamina_percent` is used only as a cross-check signal and for optimistic UI rollback.

#### Authorization
- Player must be authenticated.
- Player's `current_island_id` must match the island that owns the target `node_id`.
- Node must not be in respawn state (`respawns_at` is null or in the past).

#### Request Body
```json
{
    "node_id": 14,
    "stamina_percent": 72.5
}
```

| Field | Type | Required | Notes |
|---|---|---|---|
| `node_id` | integer | ✅ | Must be a valid `mining_nodes.id` accessible to the player |
| `stamina_percent` | number (0–100) | ✅ | Client-reported stamina at click time. Used for drift detection only; server re-calculates authoritatively |

#### Server-Side Processing
1. Load `MiningNode` by `node_id`. Return `404` if not found or not on player's island.
2. Return `409 Conflict` with `{ reason: "node_respawning", respawns_at }` if node is depleted and awaiting respawn.
3. **Rehydrate stamina**: `stamina = MIN(100, last_stamina + floor(elapsed_seconds * 10))` where `elapsed_seconds = now() - stamina_last_updated_at`.
4. Return `422` with `{ reason: "stamina_depleted" }` if rehydrated stamina < 5 (minimum threshold — TBD, see Open Decision #6).
5. **Compute Stamina Multiplier**:
   - stamina ≥ 80 → `1.00`
   - stamina 50–79 → `0.75`
   - stamina 20–49 → `0.50`
   - stamina < 20 → `0.25`
6. **Compute Mining DMG**: `FLOOR((equipped_pickaxe.mining_dmg_bonus + player_stats.mining_speed) * stamina_multiplier)`, minimum 1.
7. Deduct DMG from `mining_nodes.current_hp` (floor 0).
8. Deduct stamina cost (default: 8 pts per click; clamped to 0).
9. Persist `mining_nodes.current_hp`, `player_stats.stamina`, `player_stats.stamina_last_updated_at`.
10. Broadcast `NodeUpdated` on `presence-island.{island_id}`.
11. Broadcast `StaminaUpdated` on `private-user.{user_id}`.
12. **If `current_hp === 0`** (node destroyed):
    - Run `rollNodeLoot(node, player)` — see `architect.md § Mining Loot Drop Logic`.
    - INSERT/INCREMENT `inventories` for each dropped ore.
    - Award EXP (`node_types.tier * 10`), check level thresholds.
    - Set `mining_nodes.respawns_at = NOW() + INTERVAL node_types.respawn_minutes MINUTE`.
    - Broadcast `NodeDepleted` on `presence-island.{island_id}`.
    - Broadcast `LevelUp` on `private-user.{user_id}` if threshold crossed.

#### Success Response — Node still alive
`200 OK`
```json
{
    "damage_dealt": 12,
    "node_current_hp": 88,
    "loot_dropped": null
}
```

#### Success Response — Node destroyed (HP reaches 0)
`200 OK`
```json
{
    "damage_dealt": 9,
    "node_current_hp": 0,
    "loot_dropped": [
        { "ore_type_id": 3, "name": "Copper Ore", "rarity": "common" },
        { "ore_type_id": 11, "name": "Ironite", "rarity": "uncommon" }
    ],
    "respawns_at": "2026-05-05T14:32:00Z",
    "exp_gained": 30
}
```

| Response Field | Type | Notes |
|---|---|---|
| `damage_dealt` | integer | Final Mining DMG applied (after stamina multiplier) |
| `node_hp_remaining` | integer | `current_hp` after this hit (0 if destroyed) |
| `is_destroyed` | boolean | `true` when `current_hp` reaches 0 |
| `stamina_remaining` | number | Effective stamina after deducting 8-pt cost |
| `loot` | array\|null | `null` while node alive; array of `{ ore_id, name }` on destruction |
| `exp_gained` | integer | EXP awarded this hit (0 unless node destroyed) |
| `new_player_exp` | integer | Player's cumulative EXP after this hit |
| `level_up` | boolean | `true` if this hit pushed the player to a new level |

#### Actual Response Shape — Node Still Alive
`200 OK`
```json
{
    "damage_dealt": 15,
    "node_hp_remaining": 285,
    "is_destroyed": false,
    "stamina_remaining": 92,
    "loot": null,
    "exp_gained": 0,
    "new_player_exp": 0,
    "level_up": false
}
```

#### Actual Response Shape — Node Destroyed
`200 OK`
```json
{
    "damage_dealt": 15,
    "node_hp_remaining": 0,
    "is_destroyed": true,
    "stamina_remaining": 84,
    "loot": [
        { "ore_id": 3, "name": "Copper" },
        { "ore_id": 8, "name": "Mushroomite" }
    ],
    "exp_gained": 30,
    "new_player_exp": 150,
    "level_up": false
}
```

#### Error Responses

| Status | Condition |
|---|---|
| `401 Unauthorized` | Not authenticated |
| `422 Unprocessable` | `node_id` missing/invalid, `stamina_percent` out of range, node is respawning, or stamina < 5 threshold |
| `404 Not Found` | `node_id` does not exist |

#### Implementation Notes
- Damage formula: `MAX(1, ROUND((pickaxe.mining_dmg_bonus + player_stats.mining_speed) * stamina_multiplier))`
- Stamina is always rehydrated server-side; `stamina_percent` in the payload is accepted but not used in calculations.
- Inventory upsert: increments `quantity` if an ore row already exists for that user; inserts a new row with `quantity=1` otherwise.
- Reverb broadcast fires synchronously in the same request cycle (queued once Reverb queue worker is running).
| `node_current_hp` | integer | Node HP remaining after this hit |
| `loot_dropped` | array \| null | Ores awarded. `null` if node not destroyed |
| `respawns_at` | ISO 8601 string \| null | Set when node is destroyed; null otherwise |
| `exp_gained` | integer \| null | EXP awarded to player; only present when node is destroyed |

#### Error Responses

| HTTP | Condition | `reason` |
|---|---|---|
| `401` | Not authenticated | — |
| `404` | `node_id` not found or not on player's island | — |
| `409` | Node is in respawn cooldown | `"node_respawning"` |
| `422` | Stamina below minimum threshold | `"stamina_depleted"` |
| `422` | Validation failure (missing fields, wrong types) | Laravel validation errors |

---

### `GET /api/islands/{island}/nodes`

> **Status: Draft** — to be specified fully before implementation.

Returns all active (non-respawning) `mining_nodes` for the given island. Used to populate the island view on page load and after `NodeRespawned` events.

#### Success Response
`200 OK`
```json
{
    "nodes": [
        {
            "id": 14,
            "node_type_slug": "rock",
            "max_hp": 200,
            "current_hp": 88,
            "respawns_at": null
        }
    ]
}
```

---

## Upcoming Endpoints (Stubs)

> These are not yet specified. Add full contracts here before handing off to GameDev Programmer.

| Method | Path | Description |
|---|---|---|
| `POST` | `/api/forge/begin` | Start a forge session; reserve ore from inventory |
| `POST` | `/api/forge/{session}/smelting` | Submit Stage 1 smelting events |
| `POST` | `/api/forge/{session}/smithing` | Submit Stage 2 smithing hit events |
| `POST` | `/api/forge/{session}/quench` | Submit Stage 3 quench timestamp; finalise item |
| `GET` | `/api/inventory` | List player's inventory (ore stacks, items, runes) |
| `POST` | `/api/equipment/equip` | Equip an item into a slot |
| `DELETE` | `/api/equipment/{slot}` | Unequip current item from a slot |
| `GET` | `/api/pickaxes` | List purchasable pickaxes from shop catalog |
| `POST` | `/api/pickaxes/{pickaxe}/purchase` | Purchase a pickaxe (creates Item row) |
