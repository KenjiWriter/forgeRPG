# Architect — Design Reference

> This file is the source of truth for high-level design decisions, database schemas, and game mechanics logic.
> The **Game Architect** agent owns and maintains this file.
> The **GameDev Programmer** agent reads this file before starting any implementation task.

---

## Tech Stack

| Layer | Technology | Version |
|-------|-----------|---------|
| Language | PHP | 8.4 |
| Backend | Laravel | v13 |
| Auth | Laravel Fortify | v1 |
| Frontend | Vue.js + Inertia.js | Inertia v3 |
| Typed Routes | Laravel Wayfinder | v0 |
| Database | MySQL / SQLite (testing) | — |
| Queue | Laravel Queues (database driver) | — |
| Testing | Pest | v4 |

---

## Game Loop

```
[Island] → [Mine Ore] → [Forge Item] → [Equip] → [Fight Enemy] → [Earn Runes] → [Unlock Island]
                                                        ↑                               |
                                                        └───────────────────────────────┘
```

---

## Core Mechanics

### Mining
- Active click-driven mini-game gated by a **stamina bar** (max 100 pts, regenerates 10 pts/sec).
- Each click deals `Final Mining DMG = (Pickaxe.mining_dmg_bonus + Player.mining_speed_stat) * Stamina Multiplier`.
- Stamina multiplier: ≥80% → 1.00×, 50–79% → 0.75×, 20–49% → 0.50×, <20% → 0.25×.
- Mining nodes have `current_hp` / `max_hp`. **Loot drops ONLY when `current_hp` reaches 0 (node destroyed)** — not per click.
- On destruction: server runs a Loot Roll for each ore eligible from that node type (see Mining Loot Drop Logic).
- Node HP and stamina are broadcast in real-time via **Laravel Reverb**.

### Forging
- Three-stage mini-game: **Smelting → Smithing → Quenching**.
- All client inputs (timing events, gesture positions) are sent raw and re-validated server-side.
- Final **Forge Grade (I–X)** is determined by a weighted combined score: Smelting 30%, Smithing 50%, Quenching 20%.
- Item stats are computed at forge time using the Forge Signature formula and stored; never recalculated at runtime.

### Fighting
- Combat model: **Undecided** (turn-based vs real-time — see Open Design Decisions).
- Damage formula: `Hit DMG = attacker.attack - defender.defense` (minimum 1). Dodge applies before defense.
- Enemies drop runes, raw ore, EXP per their `drop_table` JSON.

### Runes
- **Forge Runes** — Consumed during Stage 1 smelting to modify ore heat curve. Increases potential max Forge Grade.
- **Skill Runes** — Slotted into player skill slots. Not consumed; can be removed and re-slotted.

---

## Database Structure

> Schema decisions are recorded here. Run `php artisan make:migration` to implement.
> All FK columns should have `_id` suffix and reference the parent table's `id`.

### `users`
Already exists. **Add columns**: `experience` (unsignedBigInteger, default 0), `level` (unsignedSmallInteger, default 1), `current_island_id` (FK → islands, nullable).

### `player_stats`
One row per user. Stores base stat values before equipment bonuses are applied.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| user_id | bigint | FK → users, unique |
| hp | unsignedSmallInteger | Base max HP floor |
| attack | unsignedSmallInteger | Base attack floor |
| defense | unsignedSmallInteger | Base defense floor |
| mining_speed | unsignedSmallInteger | Base click-rate cap |
| attack_speed | unsignedSmallInteger | Base combat action frequency |
| dodge | unsignedTinyInteger | Base dodge % (0–100) |
| stamina | unsignedTinyInteger | Current stamina (0–100), real-time via Reverb |
| stamina_last_updated_at | timestamp | Used to calculate regenerated stamina on read |
| updated_at | timestamp | |

> Effective stats = `player_stats` base + SUM of all equipped `items` bonuses. Computed at query time, never stored.

### `equipment_slots`
Tracks which item is currently equipped in each of the 6 slots.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| user_id | bigint | FK → users |
| slot | enum | helmet, armor, pants, boots, weapon, pickaxe |
| item_id | bigint | FK → items, nullable |
| updated_at | timestamp | |

Unique constraint: `(user_id, slot)`.

### `inventories`
Holds all player-owned ore stacks, items, and runes not currently equipped.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| user_id | bigint | FK → users |
| holdable_type | string | Polymorphic: `ore_type`, `item`, `rune` |
| holdable_id | bigint | FK to polymorphic target |
| quantity | unsignedInteger | Always ≥ 1 |
| created_at / updated_at | timestamps | |

### `islands`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| name | string | |
| min_level | unsignedSmallInteger | Minimum player level to unlock |
| unlock_condition | json | `{ "rune_ids": [...], "min_level": N }` |
| created_at / updated_at | timestamps | |

### `ore_types`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| name | string | e.g., "Stone", "Mythril" |
| rarity | enum | common, uncommon, rare, epic, legendary, **mythical** |
| base_chance | unsignedSmallInteger | Denominator X of "1 in X" drop probability |
| multiplier | decimal(4,2) | Forge stat multiplier (e.g., 1.65) |
| price | unsignedInteger | Sell price in cents (e.g., $3.75 → 375) |
| elemental_affinity | enum | fire, water, earth, void, neutral |
| base_attack | unsignedSmallInteger | Contributes to forged item attack stat |
| base_defense | unsignedSmallInteger | Contributes to forged item defense stat |
| base_hp | unsignedSmallInteger | Contributes to forged item HP bonus |

### `node_types`
Lookup/config table for the types of mining nodes that exist in the game.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| slug | string | Unique machine name: `pebble`, `rock`, `boulder`, `basalt_rock`, `basalt_core`, `basalt_vein`, `volcanic_rock`, `icy_pebble`, `icy_rock` |
| name | string | Display name |
| tier | unsignedTinyInteger | 1–6; higher tier = harder nodes, rarer ores |
| base_hp | unsignedInteger | Max HP for nodes of this type (seeded from DATA_REFERENCE.md) |
| respawn_minutes | unsignedSmallInteger | Minutes before a depleted node respawns |

### `node_type_ore_sources`
Pivot: defines which ores are **eligible** to drop from each node type when destroyed.

| Column | Type | Notes |
|--------|------|-------|
| node_type_id | bigint | FK → node_types |
| ore_type_id | bigint | FK → ore_types |

Unique constraint: `(node_type_id, ore_type_id)`. Seeded from `DATA_REFERENCE.md → Node Type → Ore Drop Sources`.

### `location_node_types`
Pivot: defines which node types can spawn at each island/location.

| Column | Type | Notes |
|--------|------|-------|
| island_id | bigint | FK → islands |
| node_type_id | bigint | FK → node_types |

Unique constraint: `(island_id, node_type_id)`. Seeded from `DATA_REFERENCE.md → Locations & Caves`.

### `mining_nodes`
Instances of nodes placed on an island. A node has one type; when destroyed it yields ores from the node type's eligible drop pool.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| island_id | bigint | FK → islands |
| node_type_id | bigint | FK → node_types |
| max_hp | unsignedInteger | Copied from node_types.base_hp at spawn time |
| current_hp | unsignedInteger | Depleted by player clicks; broadcast via Reverb |
| respawns_at | timestamp | Nullable; set when current_hp reaches 0 |
| created_at / updated_at | timestamps | |

> **Note**: `ore_type_id` has been removed. A node does not drop a single ore; it rolls against all eligible ores for its `node_type_id` via `node_type_ore_sources`.

### `pickaxes`
Shop catalog of purchasable pickaxe types. Purchasing creates an `items` row with `slot_type = pickaxe` and stats sourced from this record.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| name | string | e.g., "Iron Pickaxe" |
| price | unsignedInteger | Purchase price in cents |
| power | unsignedSmallInteger | Maps to `items.mining_dmg_bonus` |
| luck_boost | unsignedTinyInteger | Maps to `items.luck_bonus`; integer %, e.g., 10 = 10% |
| speed_modifier | decimal(4,2) | Maps to `items.mining_speed_bonus`; e.g., 1.20 = 20% faster |
| slots | unsignedTinyInteger | Number of rune slots on the pickaxe |
| requires_island_id | bigint | FK → islands, nullable; prevents purchase before unlock |

### `items`
Stores forged items with all stats pre-computed at forge time.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| name | string | Generated name at forge time |
| slot_type | enum | helmet, armor, pants, boots, weapon, pickaxe |
| forge_grade | unsignedTinyInteger | 1–10 (maps to Grade I–X) |
| forge_signature | string(64) | SHA-256 hash of sorted ore inputs (see Forge Signature) |
| hp_bonus | unsignedSmallInteger | |
| attack_bonus | unsignedSmallInteger | |
| defense_bonus | unsignedSmallInteger | |
| mining_speed_bonus | unsignedSmallInteger | Pickaxe only |
| mining_dmg_bonus | unsignedSmallInteger | Pickaxe only |
| luck_bonus | unsignedTinyInteger | Pickaxe only; affects ore drop quantity/rarity |
| attack_speed_bonus | unsignedSmallInteger | Weapon only |
| dodge_bonus | unsignedTinyInteger | Boots/Pants only |
| elemental_affinity | enum | fire, water, earth, void, neutral |
| created_at | timestamp | |

### `forge_sessions`
Audit log of every forging attempt. Used for recipe discovery and analytics.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| user_id | bigint | FK → users |
| ore_inputs | json | `[{ "ore_type_id": N, "quantity": N, "rarity": "..." }]` |
| forge_rune_id | bigint | FK → runes, nullable |
| smelting_score | unsignedTinyInteger | 0–100 |
| smithing_score | unsignedTinyInteger | 0–100 |
| quench_score | unsignedTinyInteger | 0–100 |
| combined_score | unsignedTinyInteger | Weighted final score |
| forge_grade | unsignedTinyInteger | 1–10 |
| result_item_id | bigint | FK → items, nullable (null if forfeit) |
| created_at | timestamp | |

### `runes`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| name | string | |
| category | enum | forge, skill |
| effect | json | Structured effect definition |
| description | string | Player-facing text |

### `enemies`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| island_id | bigint | FK → islands |
| name | string | |
| hp | unsignedInteger | |
| attack | unsignedSmallInteger | |
| defense | unsignedSmallInteger | |
| attack_speed | unsignedSmallInteger | |
| elemental_affinity | enum | fire, water, earth, void, neutral |
| drop_table | json | `[{ "type": "rune\|ore\|xp", "id": N, "quantity": N, "probability": 0.0–1.0 }]` |

### `level_definitions`
Seeded config table for EXP thresholds per level.

| Column | Type | Notes |
|--------|------|-------|
| level | unsignedSmallInteger | PK |
| exp_required | unsignedBigInteger | Cumulative EXP to reach this level |
| unlock_note | string | nullable, human-readable note (e.g., "Unlocks Island 2") |

---

## Mining Loot Drop Logic

### When Loot Triggers

**Loot drops ONLY when a node's `current_hp` reaches 0.** No ore is awarded per-click. This is authoritative server logic; the client never determines loot.

### Loot Roll Algorithm

When a node is destroyed, the server executes the following for the destroying player:

```
function rollNodeLoot(node, player):

  luck_boost     = player.equipped_pickaxe.luck_bonus    // integer %, e.g., 25
  eligible_ores  = node.nodeType.ore_sources             // from node_type_ore_sources pivot
  loot_awarded   = []

  for each ore in eligible_ores:
    base_chance        = ore.base_chance                 // integer denominator X of "1 in X"
    adjusted_chance    = FLOOR(base_chance / (1 + luck_boost / 100))
    adjusted_chance    = MAX(adjusted_chance, 1)         // floor of 1: always at least 1-in-1
    roll               = random_int(1, adjusted_chance)  // inclusive

    if roll == 1:
      loot_awarded.append(ore)
      INSERT INTO inventories (user_id, holdable_type='ore_type', holdable_id=ore.id, quantity=1)
      // quantity is always 1 per successful roll in this design

  exp_gained = calculateExpForNode(node.nodeType.tier)
  UPDATE users SET experience = experience + exp_gained

  return loot_awarded
```

### Luck Formula Detail

```
Adjusted Denominator = FLOOR(base_chance / (1 + luck_boost / 100))
```

| Ore | base_chance | luck_boost = 0 | luck_boost = 25 | luck_boost = 50 | luck_boost = 100 |
|-----|------------|----------------|-----------------|-----------------|------------------|
| Copper | 3 | 1 in 3 | 1 in 2 | 1 in 2 | 1 in 1 |
| Mushroomite | 22 | 1 in 22 | 1 in 17 | 1 in 14 | 1 in 11 |
| Diamond | 192 | 1 in 192 | 1 in 153 | 1 in 128 | 1 in 96 |
| Magmaite | 3,003 | 1 in 3,003 | 1 in 2,402 | 1 in 2,002 | 1 in 1,501 |

> **Security rule**: `luck_boost` is always read from the server-side equipped pickaxe record. The client never sends a luck value.

### EXP Award on Destruction

EXP is awarded per node destroyed, not per ore dropped.

```
Base Node EXP = node_types.tier * 10   (e.g., tier 3 Boulder = 30 EXP)
```

> **⚠️ PLACEHOLDER** — EXP values need balancing against `level_definitions` thresholds.

### Next Node Spawn

After loot is resolved:
1. Set `mining_nodes.respawns_at = NOW() + node_types.respawn_minutes`
2. Broadcast `NodeDepleted { node_id, respawns_at, next_node_type_slug }` via Reverb.
3. `next_node_type_slug` is always the **same type** as the destroyed node (nodes respawn as the same type). Variation is a future feature.

---

## Forge Signature & Item Stat Calculation

### Forge Signature

The **Forge Signature** is a deterministic SHA-256 hash that uniquely identifies a combination of ore inputs, independent of player or session. It is the primary mechanism for item type identity and recipe discovery.

**Input normalization** (must be applied before hashing to ensure determinism):
1. Sort ore inputs by `ore_type_id` ascending.
2. For each ore type, record `{ ore_type_id, quantity_bucket, rarity }`.  
   `quantity_bucket` maps raw quantity to a discrete tier (1–5) to avoid infinite hash variations:
   - 1–5 units → bucket 1, 6–15 → bucket 2, 16–30 → bucket 3, 31–50 → bucket 4, 51+ → bucket 5.
3. Append the `forge_rune_id` (or `null` if none).
4. Serialize as canonical JSON and SHA-256 hash the result.

```
forge_signature = SHA-256(
  json_encode([
    sorted ore inputs as { ore_type_id, quantity_bucket, rarity },
    forge_rune_id | null
  ])
)
```

### Item Stat Calculation Formula

All item stats are computed **at forge time** by the `ForgeEngine` service and persisted to the `items` table. They are never recalculated after creation.

```
Base Stat     = SUM of (ore_type.base_{stat} * quantity_weight) for all input ores
              where quantity_weight = quantity_bucket / 5  (normalized 0.2–1.0)

Grade Factor  = Forge Grade Factor from FEATURES.md grade table
              (I=0.50, II=0.60, ..., X=2.00)

Level Multiplier = 1 + (player.level - 1) * 0.02   (2% per level, capped at level 50 = 2.0×)

Final Stat    = ROUND(Base Stat * Grade Factor * Level Multiplier)
```

**Example**: A Grade VII weapon forged by a Level 10 player using Ironite (base_attack=20) × quantity_bucket 3:
```
Base Attack   = 20 * (3/5) = 12
Grade Factor  = 1.15  (Grade VII)
Level Multi   = 1 + (10-1) * 0.02 = 1.18
Final Attack  = ROUND(12 * 1.15 * 1.18) = ROUND(16.29) = 16
```

---

## Session State Flows

### Mining Session

```
Player opens Island view
  → Server loads island's active mining_nodes (current_hp > 0)
  → Reverb channel: player subscribes to `island.{island_id}.nodes`

Player clicks a Mining Node
  → Client sends POST /mine { node_id }
  → Server:
    1. Validate: player is on correct island, node current_hp > 0
    2. Validate: node_type is a mining type (not an enemy-source node)
    3. Read player stamina: stamina = last_stamina + elapsed_seconds * regen_rate (clamped 0–100)
    4. Reject click if stamina < minimum threshold (configurable, default 5)
    5. Compute Stamina Multiplier from current stamina level
    6. Compute Final Mining DMG = (pickaxe.power + player_stats.mining_speed) * Stamina Multiplier
    7. Deduct Final Mining DMG from node.current_hp (floor 0)
    8. Deduct stamina cost (default: 8 pts per click)
    9. Persist node.current_hp, player.stamina, player.stamina_last_updated_at
    10. Broadcast via Reverb: NodeUpdated { node_id, current_hp }
    11. Broadcast via Reverb: StaminaUpdated { user_id, stamina }
    12. IF node.current_hp == 0:  ← LOOT ONLY HERE
          a. Load eligible ores: node_type_ore_sources WHERE node_type_id = node.node_type_id
          b. Load player luck_boost from equipped pickaxe
          c. Run rollNodeLoot(node, player) — see Mining Loot Drop Logic
          d. For each dropped ore: INSERT/INCREMENT inventories row
          e. Award EXP (tier * 10 base) → check level threshold → update users.experience / level
          f. Broadcast LevelUp if threshold crossed
          g. Set node.respawns_at = NOW() + node_types.respawn_minutes
          h. Broadcast: NodeDepleted { node_id, respawns_at, next_node_type_slug: node.nodeType.slug }
  → Client receives response:
      { dmg_dealt, stamina_remaining, node_destroyed: bool, loot: [{ ore_id, name }] | null }
```

### Forging Session

```
Player opens Forge with selected ore stacks
  → Client sends POST /forge/begin { ore_inputs: [{ ore_type_id, quantity }] }
  → Server:
    1. Validate: player owns sufficient ore quantities
    2. Compute forge_signature from normalized inputs
    3. Deduct ore from inventories (locked/reserved until session completes or expires)
    4. Create forge_sessions row (status: in_progress)
    5. Return { forge_session_id, stage: "smelting" }

Stage 1 — Smelting (client mini-game completes)
  → Client sends POST /forge/{session}/smelting { events: [ { type, timestamp_ms, position } ] }
  → Server re-validates timing events, computes smelting_score (0–100)
  → Returns { smelting_score, next_stage: "smithing" }

Stage 2 — Smithing (client mini-game completes)
  → Client sends POST /forge/{session}/smithing { hits: [ { circle_id, timestamp_ms } ] }
  → Server compares timestamps against generated circle windows, computes smithing_score
  → Returns { smithing_score, next_stage: "quenching" }

Stage 3 — Quenching (client selects quench point)
  → Client sends POST /forge/{session}/quench { quench_timestamp_ms }
  → Server maps timestamp to cooling curve position → quench_score
  → Computes combined_score = (smelting * 0.30) + (smithing * 0.50) + (quench * 0.20)
  → Maps combined_score to forge_grade (I–X)
  → Runs ForgeEngine: calculates all item stats using Forge Signature formula
  → Creates items row
  → Adds item to player inventories
  → Updates forge_sessions: status=complete, result_item_id
  → Returns { item, forge_grade, combined_score }
```

---

## Real-Time (Laravel Reverb) Integration Points

| Event | Channel | Payload | When Fired |
|-------|---------|---------|------------|
| `StaminaUpdated` | `private-user.{user_id}` | `{ stamina, stamina_last_updated_at }` | Every successful mine click |
| `NodeUpdated` | `presence-island.{island_id}` | `{ node_id, current_hp }` | Every mine click that hits a node |
| `NodeDepleted` | `presence-island.{island_id}` | `{ node_id, respawns_at }` | When node HP reaches 0 |
| `NodeRespawned` | `presence-island.{island_id}` | `{ node_id, max_hp }` | When respawn timer fires (scheduled job) |
| `LevelUp` | `private-user.{user_id}` | `{ new_level, unlocked_island_id? }` | When EXP threshold crossed |

All island node channels use **Presence** so the frontend can show which players are on the island. Stamina and level-up channels are **Private** (per-user only).

---

## Open Design Decisions

| # | Decision | Options | Status |
|---|----------|---------|--------|
| 1 | Combat model | Turn-based vs real-time | **Undecided** |
| 2 | Rune consumption | ~~Consumed on use vs slotted~~ | **Resolved** — see Decisions Log |
| 3 | Mining model | ~~Stamina-based vs cooldown timer~~ | **Resolved** — see Decisions Log |
| 4 | Forge recipe discovery | Full mystery vs partial hints | **Undecided** |
| 5 | Multiplayer scope | Shared world vs instanced islands | **Undecided** |
| 6 | Stamina minimum threshold | Value below which clicking is blocked | **Undecided** (default proposed: 5) |
| 7 | Node respawn cooldown duration | Per rarity tier or flat? | **Undecided** |
| 8 | Forge score weighting | Smelting 30% / Smithing 50% / Quench 20% | **Proposed** — needs playtesting validation |

---

## Domain Glossary

> Maintained by the **Game Architect** agent. All terms used in blueprints and code must be defined here.

| Term | Definition |
|------|-----------|
| **Forge Signature** | A SHA-256 hash of normalized, sorted ore inputs (ore_type_id, quantity_bucket, rarity) plus forge_rune_id. Deterministic for identical inputs. Used for item type identity, deduplication, and recipe discovery tracking. |
| **Forge Grade** | Quality tier of a forged item (I–X). Determined by the combined forge session score. Maps to a Grade Factor multiplier applied to all item stats (I=0.50× … X=2.00×). |
| **Grade Factor** | The per-grade stat multiplier used in the item stat formula. See FEATURES.md grade table. |
| **Combined Score** | Weighted forge mini-game score: `(smelting_score * 0.30) + (smithing_score * 0.50) + (quench_score * 0.20)`. Range 0–100. Maps to Forge Grade I–X. |
| **Quantity Bucket** | A discrete tier (1–5) mapping raw ore quantity to a normalized input. Prevents infinite hash variations: 1–5 units → 1, 6–15 → 2, 16–30 → 3, 31–50 → 4, 51+ → 5. |
| **Stamina Bar** | Player resource pool (0–100 pts) consumed by mining clicks. Regenerates at 10 pts/sec while idle. State is persisted lazily (timestamp + last value) and rehydrated on read. Broadcast via Reverb. |
| **Stamina Multiplier** | A factor (0.25–1.00) applied to Mining DMG based on current stamina level. Penalises rapid clicking that drains stamina. |
| **Mining DMG** | Damage dealt to a Mining Node per click. Formula: `(Pickaxe.mining_dmg_bonus + Player.mining_speed_stat) * Stamina Multiplier`. |
| **Node HP** | The health pool of a Mining Node (`current_hp` / `max_hp`). Depleted by Mining DMG. When 0, the node yields loot and enters respawn cooldown. Broadcast via Reverb. |
| **Rarity Tier** | An ordered enum: `common < uncommon < rare < epic < legendary < mythical`. Affects node HP, ore base stats, base_chance, and forge stat multipliers. See DATA_REFERENCE.md → Rarity Tiers for colour codes. |
| **Node Type** | A configuration record (`node_types` table) defining a class of mining node: its slug, tier, base HP, and respawn time. Node types determine which ores are eligible to drop. Examples: `pebble`, `basalt_vein`, `volcanic_rock`. |
| **Loot Roll** | Server-side probabilistic check run once per eligible ore when a node is destroyed. A random integer is rolled from 1 to `Adjusted Denominator`; the ore drops if the result equals 1. |
| **Adjusted Denominator** | The effective drop-chance denominator after applying pickaxe luck: `FLOOR(base_chance / (1 + luck_boost / 100))`. Always ≥ 1. |
| **Luck Boost** | Integer percentage on a pickaxe (`pickaxes.luck_boost`) that reduces the Adjusted Denominator, improving drop chances. A 50% luck boost on a 1-in-22 ore produces a 1-in-14 effective chance. |
| **Forge Rune** | A rune of category `forge` applied to Stage 1 (Smelting). Consumed on use. Modifies the heat curve sweet spot width, raising achievable smelting_score ceiling. |
| **Skill Rune** | A rune of category `skill` slotted into a player skill slot. Not consumed; can be removed and re-slotted. Unlocks or enhances player abilities. |
| **Mining Node** | A `mining_nodes` DB record representing a specific ore deposit on an island. Has current_hp / max_hp and a respawns_at timestamp. |
| **Drop Table** | A JSON-encoded probability map on an enemy record. Format: `[{ "type": "rune\|ore\|xp", "id": N, "quantity": N, "probability": 0.0–1.0 }]`. Evaluated server-side only. |
| **Island Unlock Condition** | A JSON field on an island record. Format: `{ "rune_ids": [...], "min_level": N }`. Evaluated server-side; never trusted from the client. |
| **Level Multiplier** | Stat scaling factor applied at forge time: `1 + (player.level - 1) * 0.02`. Caps at level 50 (2.0×). |
| **ForgeEngine** | Laravel service class responsible for all forge computation: normalising inputs, hashing the Forge Signature, computing stats, and persisting the item. |
| **Bellows Mechanic** | Stage 1 Smelting mini-game. Player drags a bellows handle up/down to add heat; a balance bar must be kept in the sweet spot zone. |
| **Timing Circle** | Stage 2 Smithing mini-game element (Osu!-style). A shrinking ring the player must click when it hits the target. Scored PERFECT / GOOD / MISS by timing window. |

---

## System Integration

### Equipment & Pickaxes: Single `items` Source of Truth

All equippable gear — forged armor, weapons, and pickaxes — is stored in the **`items` table**. This is intentional and authoritative.

The `pickaxes` table is a **shop catalog only**: it defines purchasable pickaxe types with their stat templates (`power`, `luck_boost`, `speed_modifier`, `slots`, `price`). When a player acquires a pickaxe (purchase or new-user initialization), a concrete **`Item` row** is created with `slot_type = 'pickaxe'` and stats copied from the catalog entry.

**Equipment queries always go through `items` via `equipment_slots`:**
```php
// Correct: resolve equipped pickaxe stats
$pickaxe = $user->equipmentSlots()
    ->where('slot', 'pickaxe')
    ->with('item')
    ->first()
    ?->item;

// Incorrect: never query user_pickaxes (table does not exist)
```

**Effective stats** are computed at query time by summing the player's `player_stats` base values with the `stat_bonus` columns across all equipped `items`. They are never stored as a derived column.

---

## Current Tasks

### ✅ Schema & Migrations [COMPLETED — 2026-05-05]
- [x] Create migration: extend `users` table (experience, level, current_island_id)
- [x] Create migration: `player_stats` table
- [x] Create migration: `equipment_slots` table
- [x] Create migration: `inventories` table (polymorphic: ore_type, item, rune)
- [x] Create migration: `islands` table (= locations/caves)
- [x] Create migration: `node_types` table
- [x] Create migration: `node_type_ore_sources` pivot table
- [x] Create migration: `location_node_types` pivot table
- [x] Create migration: `ore_types` table (with base_chance, multiplier, price in cents, rarity incl. mythical)
- [x] Create migration: `mining_nodes` table (node_type_id FK, no ore_type_id)
- [x] Create migration: `pickaxes` table (shop catalog)
- [x] Create migration: `items` table (full stat columns + forge_grade + forge_signature)
- [x] Create migration: `forge_sessions` table
- [x] Create migration: `runes` table
- [x] Create migration: `enemies` table
- [x] Create migration: `level_definitions` table
- [x] Seed all 38 ores from DATA_REFERENCE.md
- [x] Seed all 9 mining node types from DATA_REFERENCE.md
- [x] Seed node_type_ore_sources pivot from DATA_REFERENCE.md
- [x] Seed 4 islands (locations) from DATA_REFERENCE.md
- [x] Seed location_node_types pivot from DATA_REFERENCE.md
- [x] Seed 7 pickaxe types from DATA_REFERENCE.md
- [x] UserObserver: auto-create PlayerStat + equip Wooden Pickaxe on user creation

### ✅ Mining Engine & API Implementation [COMPLETED — 2026-05-05]
- [x] Implement `POST /api/mining/hit` endpoint (see API_CONTRACT.md)
  - Server-side stamina rehydration (last_value + elapsed_seconds * 10 pts/sec)
  - 4-tier Stamina Multiplier (≥80→1.00, 50–79→0.75, 20–49→0.50, <20→0.25)
  - Compute Final Mining DMG: `MAX(1, ROUND((pickaxe.mining_dmg_bonus + mining_speed) * multiplier))`
  - Deduct DMG from `mining_nodes.current_hp` (floor 0)
  - Deduct 8 stamina pts per click (clamped to 0)
  - Persist node HP + stamina state
  - Broadcast `NodeUpdated` (presence) and `StaminaUpdated` (private) via Reverb
  - On destruction (HP = 0): run `rollNodeLoot()`, award EXP (`tier * 10`), handle level-up, set `respawns_at`, broadcast `NodeDepleted` and optionally `LevelUp`
- [x] Implement Reverb broadcast events: `NodeUpdated`, `NodeDepleted`, `StaminaUpdated`, `LevelUp`
- [x] 10 feature tests covering all code paths (`tests/Feature/Mining/MiningHitTest.php`)

### ⏳ Reverb Installation & Configuration [NEXT]
- [ ] `composer require laravel/reverb`
- [ ] `php artisan reverb:install` — publishes `config/reverb.php`
- [ ] Set `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET` in `.env`
- [ ] Configure `BROADCAST_DRIVER=reverb` in `.env`
- [ ] Update `bootstrap/app.php` to register the `BroadcastServiceProvider` if needed
- [ ] Register `routes/channels.php` — add Presence auth for `island.{island_id}.nodes` and Private auth for `user.{id}`
- [ ] Start Reverb server: `php artisan reverb:start`
- [ ] Confirm broadcast events fire correctly end-to-end

### Backend (Remaining)
- [ ] Implement `ForgeEngine` service class (Forge Signature hash + stat calculation)
- [ ] Implement `POST /api/forge/begin`, `/smelting`, `/smithing`, `/quench` endpoints
- [ ] Implement equipment endpoints: equip/unequip item
- [ ] Implement effective-stats query (base + equipment bonuses)

### Frontend
- [ ] Island view with real-time mining node HP bars (Reverb subscription)
- [ ] Stamina bar component (Reverb subscription, lazy regen animation)
- [ ] Forge mini-game UI: Stage 1 Bellows, Stage 2 Timing Circles, Stage 3 Quench curve
- [ ] Inventory & Equipment panel (6 slots + item grid)
- [ ] Pickaxe shop UI

### Design (Undecided)
- [ ] Resolve open decision #1: Combat model
- [ ] Resolve open decision #4: Forge recipe discovery
- [ ] Resolve open decision #5: Multiplayer scope
- [ ] Confirm stamina minimum threshold (decision #6)
- [ ] Confirm node respawn cooldown formula (decision #7)
- [ ] Validate forge score weighting after first playtest (decision #8)

---

## Architectural Decisions Log

| Date | Decision | Rationale |
|------|----------|-----------|
| — | All game logic is server-side | Prevent client manipulation of stats/economy |
| — | Items store computed stats at forge time | Avoid recalculating on every combat/equip request |
| 2026-05-05 | Mining model: stamina-based bar | Active stamina bar creates skill expression via click rhythm; more engaging than a passive cooldown timer |
| 2026-05-05 | Rune consumption: Forge Runes consumed, Skill Runes slotted | Forge Runes are consumable crafting modifiers; Skill Runes are persistent character upgrades — different economic roles |
| 2026-05-05 | Stamina persisted as (value + timestamp) not polled value | Avoids constant DB writes during regen; rehydrated accurately on read with `last_value + elapsed * regen_rate` |
| 2026-05-05 | Forge Signature uses quantity buckets (1–5) not raw quantities | Prevents combinatorial explosion of unique signatures while preserving meaningful input variation |
| 2026-05-05 | `inventories` table uses polymorphic morphs | One table for ore, items, and runes avoids three separate join tables and keeps inventory queries uniform |
| 2026-05-05 | Forge score weights: Smelting 30%, Smithing 50%, Quench 20% | Smithing (rhythm game) is the highest-skill stage and should dominate grade; smelting and quench are supporting gates |
| 2026-05-05 | Loot drops ONLY on node destruction (HP = 0) | Prevents micro-reward spam per click; makes each mining session feel like a meaningful event with a clear reward moment |
| 2026-05-05 | mining_nodes has no ore_type_id; uses node_type_ore_sources pivot | A single node can yield multiple ore types based on its node type; a single ore_type_id would be incorrect and would require one node per ore |
| 2026-05-05 | Rarity enum extended to include `mythical` (above legendary) | Demonite and Darkryte have distinct presentation (red colour, extreme rarity 1/3666–1/6655) justifying a separate tier above legendary |
| 2026-05-05 | Ore prices stored as integer cents | Avoids floating-point precision issues (e.g., $36.45 → 3645); consistent with standard e-commerce DB patterns |
| 2026-05-05 | Pickaxes table is a shop catalog; purchasing creates an items row | Unifies pickaxe and forged-item equipment under one `items` + `equipment_slots` system; avoids separate equipment query path |
