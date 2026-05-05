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
- Mining nodes have `current_hp` / `max_hp`. Depletion yields raw ore + EXP, then node enters respawn cooldown.
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
| name | string | e.g., "Ironite", "Voidstone" |
| rarity | enum | common, uncommon, rare, epic, legendary |
| elemental_affinity | enum | fire, water, earth, void, neutral |
| base_attack | unsignedSmallInteger | Contributes to forged item attack |
| base_defense | unsignedSmallInteger | Contributes to forged item defense |
| base_hp | unsignedSmallInteger | Contributes to forged item HP bonus |
| base_value | unsignedInteger | Economy reference price |

### `mining_nodes`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| island_id | bigint | FK → islands |
| ore_type_id | bigint | FK → ore_types |
| max_hp | unsignedInteger | Full health of the node |
| current_hp | unsignedInteger | Depleted by player clicks; broadcast via Reverb |
| respawns_at | timestamp | Nullable; set on node depletion |
| created_at / updated_at | timestamps | |

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
    2. Read player stamina: stamina = last_stamina - elapsed_seconds * regen_rate (clamped 0–100)
    3. Reject click if stamina < minimum threshold (configurable, default 5)
    4. Compute Stamina Multiplier from stamina level
    5. Compute Final Mining DMG
    6. Deduct Final Mining DMG from node.current_hp (floor 0)
    7. Deduct stamina cost (default: 8 pts per click)
    8. Persist node.current_hp, player.stamina, player.stamina_last_updated_at
    9. Broadcast via Reverb: NodeUpdated { node_id, current_hp }
    10. Broadcast via Reverb: StaminaUpdated { user_id, stamina }
    11. If node.current_hp == 0:
          → Roll loot from ore_type.rarity + player luck_bonus
          → Insert rows into inventories
          → Award EXP → check level up → update users.experience / level
          → Set node.respawns_at = now() + respawn_cooldown
          → Broadcast: NodeDepleted { node_id, respawns_at }
  → Client receives response { dmg_dealt, stamina_remaining, loot? }
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
| **Rarity Tier** | An ordered enum: `common < uncommon < rare < epic < legendary`. Affects node HP, ore base stats, drop rates, and forge stat multipliers. |
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

## Current Tasks

### Schema & Migrations
- [ ] Create migration: extend `users` table (experience, level, current_island_id)
- [ ] Create migration: `player_stats` table
- [ ] Create migration: `equipment_slots` table
- [ ] Create migration: `inventories` table (polymorphic: ore_type, item, rune)
- [ ] Create migration: `islands` table
- [ ] Create migration: `ore_types` table
- [ ] Create migration: `mining_nodes` table (with current_hp, respawns_at)
- [ ] Create migration: `items` table (full stat columns + forge_grade + forge_signature)
- [ ] Create migration: `forge_sessions` table
- [ ] Create migration: `runes` table
- [ ] Create migration: `enemies` table
- [ ] Create migration: `level_definitions` table
- [ ] Seed starter data (1 island, 3 ore types, 5 mining nodes, 3 enemies, 10 rune types)

### Backend
- [ ] Implement `ForgeEngine` service class (Forge Signature hash + stat calculation)
- [ ] Implement `POST /mine` endpoint (stamina read, DMG calc, node HP deduction, loot roll)
- [ ] Implement `POST /forge/begin`, `/smelting`, `/smithing`, `/quench` endpoints
- [ ] Implement Reverb broadcast events: StaminaUpdated, NodeUpdated, NodeDepleted, NodeRespawned, LevelUp
- [ ] Implement scheduled job: respawn mining nodes when `respawns_at` is past
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
