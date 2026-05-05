# Features Reference

> Player-facing and developer-facing feature specification.
> All mechanics here must have a corresponding blueprint in `architect.md` before implementation begins.

---

## 1. Core Stats

Every player character has six base stats. Stats are derived from equipment; bare (unequipped) values default to their base floor.

| Stat | Description | Governed By |
|------|-------------|-------------|
| **HP** | Total health pool. Reaching 0 means defeat in combat. | Helmet, Armor, Pants |
| **Attack** | Base damage dealt per combat hit. | Weapon |
| **Defense** | Flat damage reduction on incoming hits. | Armor, Boots |
| **Mining Speed** | Clicks-per-second cap before stamina penalty applies. | Pickaxe |
| **Attack Speed** | Frequency of combat actions. | Weapon |
| **Dodge** | % chance to avoid an incoming attack entirely. | Boots, Pants |

Stats are stored server-side in `player_stats`. Equipment bonuses are applied additively at query time; they are never cached on the player row to avoid desync.

---

## 2. Equipment Slots

Players have **6 equipment slots**. Each slot accepts only items of the matching `slot_type`.

| Slot | Accepted Item Type | Primary Stat Contribution |
|------|--------------------|--------------------------|
| Helmet | `helmet` | HP |
| Armor | `armor` | HP, Defense |
| Pants | `pants` | HP, Dodge |
| Boots | `boots` | Defense, Dodge |
| Weapon | `weapon` | Attack, Attack Speed |
| Pickaxe | `pickaxe` | Mining Speed, Mining DMG Bonus |

- Only **one item per slot** may be equipped at a time.
- Equipping a new item to an occupied slot returns the previous item to the inventory automatically.
- Pickaxes are **not** used in combat; Weapons are **not** used in mining.

---

## 3. Mining System

Mining is an **active, click-driven mini-game** gated by a stamina bar.

### 3.1 Stamina Bar

- Maximum stamina: **100 points** (configurable per island tier).
- Each click consumes stamina proportional to click speed (see DMG formula).
- Stamina regenerates at a fixed rate (default: **10 points/second**) while the player is not clicking.
- The stamina bar state is synced in real-time via **Laravel Reverb** (WebSocket broadcast).

### 3.2 Mining DMG Formula

Mining DMG determines how much HP is removed from a Mining Node per click.

```
Base Mining DMG = Pickaxe.mining_dmg_bonus + Player.mining_speed_stat

Stamina Multiplier:
  - Stamina bar ≥ 80%  → 1.00× (full damage)
  - Stamina bar 50–79% → 0.75×
  - Stamina bar 20–49% → 0.50× (rapid clicking penalty)
  - Stamina bar < 20%  → 0.25×

Final Mining DMG = Base Mining DMG * Stamina Multiplier
```

> **Design Intent**: Rapid, uncapped clicking depletes stamina quickly and falls into the 50% penalty bracket. Measured clicking (allowing partial stamina recovery between clicks) sustains higher damage output. Players are incentivised to find their optimal click rhythm.

### 3.3 Mining Node HP

- Each Mining Node has a `current_hp` and a `max_hp` defined by its **Node Type** (Pebble, Rock, Boulder, etc.).
- Node HP values are seeded from `DATA_REFERENCE.md → Node Types`.
- Node HP changes are broadcast in real-time via Reverb so all players on the island see the same depletion state.
- **Loot is NEVER awarded per click.** The only loot trigger is node destruction (see 3.4).

### 3.4 Loot — On Node Destruction Only

**Loot is awarded exactly once: when `current_hp` reaches 0.** This is enforced server-side.

**Drop Roll (per eligible ore)**:
1. Load all ores eligible for the destroyed node's type from `node_type_ore_sources`.
2. For each eligible ore, compute the **Adjusted Denominator**:
   ```
   Adjusted Denominator = FLOOR(ore.base_chance / (1 + pickaxe.luck_boost / 100))
   ```
3. Roll `random_int(1, Adjusted Denominator)`. If result == 1, the ore drops (1 unit added to inventory).
4. Multiple ores can drop from a single node destruction; each rolls independently.

**EXP Award** (on node destruction, regardless of loot drops):
```
EXP = node_type.tier × 10
```

**Example**: Player with `luck_boost = 50` destroys a Boulder. Aite (1 in 44) effective chance:
```
Adjusted = FLOOR(44 / 1.5) = 29  →  1-in-29 chance
```

> **Design Intent**: Rewarding loot at destruction (not per-click) makes each node feel like a complete event. Players are rewarded for sustained engagement rather than single lucky clicks. The luck system provides meaningful pickaxe upgrade incentive without trivialising rarity.

---

## 4. Forging System (3 Stages)

Forging is a **three-stage mini-game**. The combined performance score across all three stages determines the final **Forge Grade (I–X)** of the crafted item. Each stage is client-rendered but all scores are validated and computed server-side.

### 4.1 Stage 1 — Smelting

**Goal**: Heat the ore to the correct temperature.

**Mechanics**:
1. **Bellows mechanic** — Player drags a bellows handle up and down (vertical drag gesture). Each full up-down stroke adds heat.
2. **Vertical balance bar** — A heat gauge must be held within a target "sweet spot" zone. Staying in the zone accumulates Smelting Score. Overshooting (too hot) or undershooting (too cold) reduces the score.

**Score contribution**: `smelting_score` (0–100, percentage of time spent in the sweet spot).

### 4.2 Stage 2 — Smithing

**Goal**: Shape the heated metal with precision hammer strikes.

**Mechanics**:
- **Rhythm/Timing circles** (Osu!-style) — Hit circles appear on screen. Each circle has an outer ring that shrinks toward the target. Clicking exactly when the ring reaches the target scores a **PERFECT** hit. Early/late clicks score **GOOD** or **MISS**.

**Hit scoring**:

| Result | Timing Window | Score Weight |
|--------|--------------|-------------|
| PERFECT | ±50ms | 1.00 |
| GOOD | ±150ms | 0.60 |
| MISS | > ±150ms | 0.00 |

**Score contribution**: `smithing_score` = average weighted hit score across all circles (0–100).

### 4.3 Stage 3 — Quenching (Grading)

**Goal**: Lock in the item's quality by quenching it in the correct medium at the right moment.

**Mechanics**:
- A cooling temperature curve is shown. The player selects a quench point by clicking.
- The chosen temperature maps to a Forge Grade.

**Forge Grade table** (final item quality):

| Grade | Description | Forge Grade Factor |
|-------|-------------|-------------------|
| I | Crude | 0.50× |
| II | Poor | 0.60× |
| III | Common | 0.70× |
| IV | Decent | 0.80× |
| V | Quality | 0.90× |
| VI | Fine | 1.00× |
| VII | Superior | 1.15× |
| VIII | Exceptional | 1.30× |
| IX | Masterwork | 1.50× |
| X | Legendary | 2.00× |

**Final Grade Determination**:

```
Combined Score = (smelting_score * 0.30) + (smithing_score * 0.50) + (quench_score * 0.20)

Grade I   : Combined Score  0 – 10
Grade II  : Combined Score 11 – 20
Grade III : Combined Score 21 – 30
Grade IV  : Combined Score 31 – 40
Grade V   : Combined Score 41 – 55
Grade VI  : Combined Score 56 – 65
Grade VII : Combined Score 66 – 75
Grade VIII: Combined Score 76 – 85
Grade IX  : Combined Score 86 – 95
Grade X   : Combined Score 96 – 100
```

---

## 5. Progression System

### 5.1 Leveling

- Players earn **EXP** from mining, forging, and fighting.
- EXP thresholds follow a configurable curve (seeded in `level_definitions` config table).
- **New islands unlock** at specific level thresholds defined in `islands.unlock_condition`.

### 5.2 Pickaxe Shop

- A per-island shop selling Pickaxe upgrades.
- Pickaxes have three purchasable upgrade dimensions:

| Upgrade | Effect |
|---------|--------|
| **Speed** | Increases `mining_speed_stat`, raising the stamina-neutral click cap |
| **DMG** | Increases `mining_dmg_bonus` (flat) |
| **Luck** | Increases ore quantity rolls and rare ore drop probability |

- Pickaxe purchases are persisted to the `player_inventories` / `equipment_slots` table. No client-side stat modification.

---

## Developer Notes

- All mini-game scores (`smelting_score`, `smithing_score`, `quench_score`) are sent as raw inputs from the client (e.g., timestamps, positions) and **re-validated server-side**. The client never sends a final score.
- The Forge Grade and all resulting item stats are calculated exclusively in the `ForgeEngine` service class.
- Stamina state is the only stat that lives in near-real-time (Reverb broadcast); all other stats are request-response.
