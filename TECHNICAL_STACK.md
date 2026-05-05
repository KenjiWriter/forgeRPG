# Technical Stack Reference

> Living document maintained by the **Game Architect** agent.
> The **GameDev Programmer** agent must consult this before writing any query, migration, or relationship.
> Last updated: 2026-05-05

---

## Models & Relationships

### `User` (`app/Models/User.php`)
| Relationship | Method | Target |
|---|---|---|
| hasOne | `stats()` | `PlayerStat` |
| hasMany | `equipmentSlots()` | `EquipmentSlot` |
| hasMany | `inventory()` | `Inventory` |
| hasMany | `forgeSessions()` | `ForgeSession` |
| belongsTo | `currentIsland()` | `Island` via `current_island_id` |

Extra columns on `users`: `experience` (bigint, default 0), `level` (smallint, default 1), `current_island_id` (FK → islands, nullable).

---

### `PlayerStat` (`app/Models/PlayerStat.php`)
| Relationship | Method | Target |
|---|---|---|
| belongsTo | `user()` | `User` |

`$timestamps = false`. Has a manual `updated_at` column.  
Key columns: `hp`, `attack`, `defense`, `mining_speed`, `attack_speed`, `dodge`, `stamina` (0–100), `stamina_last_updated_at`.

---

### `EquipmentSlot` (`app/Models/EquipmentSlot.php`)
| Relationship | Method | Target |
|---|---|---|
| belongsTo | `user()` | `User` |
| belongsTo | `item()` | `Item` |

`$timestamps = false`. Has a manual `updated_at` column.  
`slot` is an enum: `helmet`, `armor`, `pants`, `boots`, `weapon`, `pickaxe`.  
Unique constraint: `(user_id, slot)`.

---

### `Inventory` (`app/Models/Inventory.php`)
| Relationship | Method | Target |
|---|---|---|
| belongsTo | `user()` | `User` |
| morphTo | `holdable()` | `OreType` \| `Item` \| `Rune` |

Polymorphic columns: `holdable_type` (string), `holdable_id` (bigint). `quantity` is always ≥ 1.

---

### `Item` (`app/Models/Item.php`)
No Eloquent relationships defined on the model itself. Referenced via `EquipmentSlot.item_id` and `Inventory.holdable_id`.

`$timestamps = false`. Has a manual `created_at` column.

Key columns: `slot_type` (enum), `forge_grade` (1–10), `forge_signature` (SHA-256 hex), and all stat bonus columns (`hp_bonus`, `attack_bonus`, `defense_bonus`, `mining_speed_bonus`, `mining_dmg_bonus`, `luck_bonus`, `attack_speed_bonus`, `dodge_bonus`), `elemental_affinity`.

---

### `Island` (`app/Models/Island.php`)
| Relationship | Method | Target |
|---|---|---|
| belongsToMany | `nodeTypes()` | `NodeType` via `location_node_types` |
| hasMany | `miningNodes()` | `MiningNode` |
| hasMany | `enemies()` | `Enemy` |

---

### `NodeType` (`app/Models/NodeType.php`)
| Relationship | Method | Target |
|---|---|---|
| belongsToMany | `oreTypes()` | `OreType` via `node_type_ore_sources` |
| belongsToMany | `islands()` | `Island` via `location_node_types` |
| hasMany | `miningNodes()` | `MiningNode` |

Key columns: `slug` (unique), `tier` (1–6), `base_hp`, `respawn_minutes`.

---

### `OreType` (`app/Models/OreType.php`)
| Relationship | Method | Target |
|---|---|---|
| belongsToMany | `nodeTypes()` | `NodeType` via `node_type_ore_sources` |

Key columns: `rarity` (enum: `common`, `uncommon`, `rare`, `epic`, `legendary`, `mythical`), `base_chance` (drop denominator), `multiplier` (decimal:2), `price` (integer cents), `elemental_affinity`, `base_attack`, `base_defense`, `base_hp`.

---

### `MiningNode` (`app/Models/MiningNode.php`)
| Relationship | Method | Target |
|---|---|---|
| belongsTo | `island()` | `Island` |
| belongsTo | `nodeType()` | `NodeType` |

Helper methods: `isDestroyed(): bool` (current_hp === 0), `isRespawning(): bool` (respawns_at is in future).  
Key columns: `max_hp`, `current_hp`, `respawns_at` (nullable timestamp).

---

### `Pickaxe` (`app/Models/Pickaxe.php`)
| Relationship | Method | Target |
|---|---|---|
| belongsTo | `requiredIsland()` | `Island` via `requires_island_id` |

**This is a shop catalog table only.** Purchasing creates an `Item` row with `slot_type = pickaxe`. Key columns: `power` (→ `items.mining_dmg_bonus`), `luck_boost` (integer %, → `items.luck_bonus`), `speed_modifier` (decimal:2), `slots`, `price` (integer cents).

---

### `ForgeSession` (`app/Models/ForgeSession.php`)
| Relationship | Method | Target |
|---|---|---|
| belongsTo | `user()` | `User` |
| belongsTo | `forgeRune()` | `Rune` via `forge_rune_id` |
| belongsTo | `resultItem()` | `Item` via `result_item_id` |

`$timestamps = false`. Has manual `created_at`.  
Key columns: `ore_inputs` (JSON array), `smelting_score`, `smithing_score`, `quench_score`, `combined_score`, `forge_grade`, `result_item_id` (nullable — null if forfeited).

---

### `Rune` (`app/Models/Rune.php`)
No Eloquent relationships. Referenced via `ForgeSession.forge_rune_id` and `Inventory.holdable_id`.

`category` enum: `forge`, `skill`. `effect` is a JSON blob.

---

### `Enemy` (`app/Models/Enemy.php`)
| Relationship | Method | Target |
|---|---|---|
| belongsTo | `island()` | `Island` |

`drop_table` is a JSON array. `elemental_affinity` enum: `fire`, `water`, `earth`, `void`, `neutral`.

---

### `LevelDefinition` (`app/Models/LevelDefinition.php`)
No relationships. Config/seed table only.

`$incrementing = false`. Primary key: `level` (int). No timestamps. Key columns: `exp_required` (bigint), `unlock_note` (nullable string).

---

## Attribute Casts Reference

| Model | Column | Cast | Notes |
|---|---|---|---|
| `User` | `email_verified_at` | `datetime` | |
| `User` | `password` | `hashed` | Auto-hashed by Laravel |
| `User` | `two_factor_confirmed_at` | `datetime` | |
| `PlayerStat` | `stamina_last_updated_at` | `datetime` | Used for lazy stamina regen |
| `PlayerStat` | `updated_at` | `datetime` | Manual column, not auto-managed |
| `EquipmentSlot` | `updated_at` | `datetime` | Manual column |
| `Item` | `created_at` | `datetime` | Manual column |
| `OreType` | `multiplier` | `decimal:2` | e.g., `1.65` |
| `Island` | `unlock_condition` | `array` | JSON: `{ "rune_ids": [...], "min_level": N }` |
| `MiningNode` | `respawns_at` | `datetime` | Nullable |
| `ForgeSession` | `ore_inputs` | `array` | JSON array of ore input objects |
| `ForgeSession` | `created_at` | `datetime` | Manual column |
| `Enemy` | `drop_table` | `array` | JSON drop probability map |
| `Rune` | `effect` | `array` | JSON effect definition |

> **Prices as cents**: `ore_types.price` and `pickaxes.price` are stored as integer cents (e.g., $3.75 → 375). There is no cast; treat them as integers and divide by 100 only at display time.

---

## New User Initialization Logic

**Class**: `app/Observers/UserObserver.php`  
**Trigger**: `UserObserver::created(User $user)` fires after every new user row is inserted.

### Step-by-step sequence

1. **Create `PlayerStat` row** with seeded base values:
   - `hp = 100`, `attack = 10`, `defense = 5`
   - `mining_speed = 10`, `attack_speed = 10`, `dodge = 0`
   - `stamina = 100`, `stamina_last_updated_at = now()`

2. **Look up starter pickaxe**: Queries `pickaxes` table for `name = 'Wooden Pickaxe'`.

3. **Create `Item` row** as a concrete equipment instance:
   - `slot_type = 'pickaxe'`
   - `forge_grade = 1`
   - `mining_dmg_bonus = pickaxe.power` (falls back to 5 if seeder not yet run)
   - `luck_bonus = pickaxe.luck_boost` (falls back to 0)

4. **Create 6 `EquipmentSlot` rows** for the user:
   - Slots: `helmet`, `armor`, `pants`, `boots`, `weapon`, `pickaxe`
   - The `pickaxe` slot's `item_id` is set to the newly created Item's id
   - All other slots have `item_id = null`

> **Dependency**: The observer reads from the `pickaxes` table. The `PickaxeSeeder` must run before the first user is created in production. In tests, use factories that bypass the observer or ensure seeder is called.

---

## Pivot Tables

| Table | Left FK | Right FK | Purpose |
|---|---|---|---|
| `node_type_ore_sources` | `node_type_id` | `ore_type_id` | Which ores are eligible to drop from a node type |
| `location_node_types` | `island_id` | `node_type_id` | Which node types can spawn on an island |
