# Data Reference

> Official balancing data — seeder source of truth for migrations and seeders.
> Sections marked **⚠️ PLACEHOLDER** contain inferred or estimated values that require confirmation before seeding.
> Enemy-drop ores (Boneite, Slimite, Dark Boneite) belong to the enemy loot system, not the mining system.

---

## Locations & Caves

> **⚠️ PLACEHOLDER** — Location names and `min_level` requirements need confirmation.
> Node type assignments are inferred from which ores list those nodes as their source.
> In code, "Location" = `islands` table row. "Cave" is the player-facing name.

| # | Name | Min Level | Node Types Spawned |
|---|------|-----------|-------------------|
| 1 | Stonewake's Cross | 1 | Pebble, Rock, Boulder |
| 2 | Forgotten Kingdom | TBD | Basalt Rock, Basalt Core, Basalt Vein |
| 3 | The Volcanic Rift | TBD | Volcanic Rock |
| 4 | Frostspire Expanse | TBD | Icy Pebble, Icy Rock |

> **Cross-zone note**: Some ores (Silver, Gold, Platinum, Eye Ore, Topaz, Cuprite, Rivalite, Uranium, Mythril, Lightite, Emerald, Ruby) drop from nodes spanning multiple locations. The `node_type_ore_sources` pivot table encodes this — a node type's availability in a location is configured in `location_node_types`.

---

## Node Types

> HP values are **⚠️ PLACEHOLDER** — scale by node tier. Respawn times are estimates.
> Enemy-source rows are listed for completeness; they are not mining nodes.

| Slug | Display Name | Tier | Placeholder HP | Respawn (min) | Source |
|------|-------------|------|---------------|--------------|--------|
| `pebble` | Pebble | 1 | 100 | 2 | Mining |
| `rock` | Rock | 2 | 300 | 5 | Mining |
| `boulder` | Boulder | 3 | 800 | 10 | Mining |
| `basalt_rock` | Basalt Rock | 4 | 1,500 | 15 | Mining |
| `basalt_core` | Basalt Core | 5 | 2,500 | 20 | Mining |
| `basalt_vein` | Basalt Vein | 6 | 4,000 | 30 | Mining |
| `volcanic_rock` | Volcanic Rock | 5 | 3,000 | 25 | Mining |
| `icy_pebble` | Icy Pebble | 5 | 3,500 | 25 | Mining |
| `icy_rock` | Icy Rock | 6 | 5,500 | 40 | Mining |
| `skeleton` | Skeleton | — | — | — | Enemy Drop |
| `elite_skeleton` | Elite Skeleton | — | — | — | Enemy Drop |
| `slime` | Slime | — | — | — | Enemy Drop |

---

## Node Type → Ore Drop Sources

> Pivot data for the `node_type_ore_sources` table.
> This defines which ores are **eligible** to roll when a node of that type is destroyed.
> A roll only runs for ores listed here; the chance column on `ore_types` then determines if the drop occurs.

| Node Type | Eligible Ores (name, for reference) |
|-----------|-------------------------------------|
| `pebble` | Stone, Sand Stone, Copper, Iron, Poopite |
| `rock` | Sand Stone, Copper, Iron, Tin, Silver, Mushroomite, Bananite, Cardboardite, Poopite |
| `boulder` | Copper, Iron, Tin, Silver, Gold, Mushroomite, Platinum, Bananite, Cardboardite, Aite, Poopite |
| `basalt_rock` | Silver, Gold, Platinum, Cobalt, Titanium, Lapis Lazuli, Eye Ore |
| `basalt_core` | Cobalt, Titanium, Lapis Lazuli, Quartz, Amethyst, Topaz, Diamond, Sapphire, Cuprite, Emerald, Eye Ore |
| `basalt_vein` | Quartz, Amethyst, Topaz, Diamond, Sapphire, Cuprite, Emerald, Ruby, Rivalite, Uranium, Mythril, Lightite, Eye Ore |
| `volcanic_rock` | Volcanic Rock, Topaz, Cuprite, Obsidian, Rivalite, Eye Ore, Fireite, Magmaite, Demonite, Darkryte |
| `icy_pebble` | Emerald, Ruby, Rivalite, Uranium, Mythril, Lightite |
| `icy_rock` | Uranium, Mythril, Lightite |
| `skeleton` | Boneite |
| `elite_skeleton` | Dark Boneite |
| `slime` | Slimite |

---

## Ore Table

> Prices are stored in the DB as **cents** (integer, ×100). Display values shown here as `$X.XX`.
> `base_chance` is the denominator X of "1 in X" — stored as an integer.
> `multiplier` is the forge stat multiplier — stored as `decimal(4,2)`.

### Zone 1 — Stonewake's Cross (Pebble / Rock / Boulder)

| # | Name | Rarity | Chance (1 in X) | Multiplier | Price | Source Nodes |
|---|------|--------|----------------|------------|-------|-------------|
| 1 | Stone | Common | 1 | 0.20× | $3.00 | Pebble |
| 2 | Sand Stone | Common | 2 | 0.25× | $3.75 | Pebble, Rock |
| 3 | Copper | Common | 3 | 0.30× | $4.50 | Pebble, Rock, Boulder |
| 4 | Iron | Common | 5 | 0.35× | $5.25 | Pebble, Rock, Boulder |
| 5 | Tin | Uncommon | 7 | 0.425× | $6.38 | Rock, Boulder |
| 6 | Silver | Uncommon | 12 | 0.50× | $7.50 | Rock, Boulder, Basalt Rock |
| 7 | Gold | Uncommon | 16 | 0.65× | $19.50 | Boulder, Basalt Rock |
| 8 | Mushroomite | Rare | 22 | 0.80× | $12.00 | Rock, Boulder |
| 9 | Platinum | Rare | 28 | 0.80× | $12.00 | Boulder, Basalt Rock |
| 10 | Bananite | Uncommon | 30 | 0.85× | $12.75 | Rock, Boulder |
| 11 | Cardboardite | Common | 31 | 0.70× | $10.50 | Rock, Boulder |
| 12 | Aite | Epic | 44 | 1.10× | $16.50 | Boulder |
| 13 | Poopite | Epic | 131 | 1.20× | $18.00 | Pebble, Rock, Boulder |

### Zone 2 — Forgotten Kingdom (Basalt Rock / Basalt Core / Basalt Vein)

| # | Name | Rarity | Chance (1 in X) | Multiplier | Price | Source Nodes |
|---|------|--------|----------------|------------|-------|-------------|
| 14 | Cobalt | Uncommon | 37 | 1.00× | $15.00 | Basalt Rock, Basalt Core |
| 15 | Titanium | Uncommon | 50 | 1.15× | $17.25 | Basalt Rock, Basalt Core |
| 16 | Lapis Lazuli | Uncommon | 73 | 1.30× | $19.50 | Basalt Rock, Basalt Core |
| 17 | Quartz | Rare | 90 | 1.50× | $22.50 | Basalt Core, Basalt Vein |
| 18 | Amethyst | Rare | 115 | 1.65× | $24.75 | Basalt Core, Basalt Vein |
| 19 | Topaz | Rare | 143 | 1.75× | $26.25 | Basalt Core, Basalt Vein, Volcanic Rock |
| 20 | Diamond | Rare | 192 | 2.00× | $30.00 | Basalt Core, Basalt Vein |
| 21 | Sapphire | Rare | 247 | 2.25× | $33.75 | Basalt Core, Basalt Vein |
| 22 | Cuprite | Epic | 303 | 2.43× | $36.45 | Basalt Core, Basalt Vein, Volcanic Rock |
| 23 | Emerald | Epic | 363 | 2.55× | $38.25 | Basalt Core, Basalt Vein, Icy Pebble |
| 24 | Ruby | Epic | 487 | 2.95× | $44.25 | Basalt Vein, Icy Pebble |
| 25 | Rivalite | Epic | 569 | 3.33× | $49.95 | Basalt Vein, Volcanic Rock, Icy Pebble |
| 26 | Uranium | Legendary | 777 | 3.00× | $66.00 | Basalt Vein, Icy Pebble, Icy Rock |
| 27 | Mythril | Legendary | 813 | 3.50× | $52.50 | Basalt Vein, Icy Pebble, Icy Rock |
| 28 | Lightite | Legendary | 3,333 | 4.60× | $69.00 | Basalt Vein, Icy Pebble, Icy Rock |
| 29 | Eye Ore | Legendary | 1,333 | 4.00× | $60.00 | Basalt Rock, Basalt Core, Basalt Vein, Volcanic Rock |

### Zone 3 — The Volcanic Rift (Volcanic Rock)

| # | Name | Rarity | Chance (1 in X) | Multiplier | Price | Source Nodes |
|---|------|--------|----------------|------------|-------|-------------|
| 30 | Volcanic Rock | Rare | 55 | 1.55× | $23.25 | Volcanic Rock |
| 31 | Obsidian | Epic | 333 | 2.35× | $35.25 | Volcanic Rock |
| 32 | Fireite | Legendary | 2,187 | 4.50× | $67.50 | Volcanic Rock |
| 33 | Magmaite | Legendary | 3,003 | 5.00× | $75.00 | Volcanic Rock |
| 34 | Demonite | Mythical | 3,666 | 5.50× | $82.50 | Volcanic Rock |
| 35 | Darkryte | Mythical | 6,655 | 6.30× | $94.50 | Volcanic Rock |

### Zone 4 — Frostspire Expanse (Icy Pebble / Icy Rock)

*No exclusive ores. Zone 4 nodes (Icy Pebble, Icy Rock) are alternate sources for high-tier ores already listed in Zone 2. See Node Type → Ore Drop Sources table.*

### Enemy Drops (Not Mining)

> These ores use the same `base_chance` / `multiplier` / `price` columns on `ore_types`, but their loot roll is triggered by enemy kill, not node destruction. The mining drop logic does NOT apply to them.

| # | Name | Rarity | Chance (1 in X) | Multiplier | Price | Source Enemy |
|---|------|--------|----------------|------------|-------|-------------|
| 36 | Boneite | Rare | 222 | 1.20× | $18.00 | Skeleton |
| 37 | Dark Boneite | Rare | 555 | 2.25× | $33.75 | Elite Skeleton |
| 38 | Slimite | Epic | 247 | 2.25× | $33.75 | Slime |

---

## Pickaxes

> **⚠️ PLACEHOLDER** — No official pickaxe balancing data was provided. The table below uses inferred tier progression. All values require confirmation.
> `price_cents` = price × 100 (stored as integer).
> `power` maps to `items.mining_dmg_bonus`. `luck_boost` maps to `items.luck_bonus`. `speed_modifier` maps to `items.mining_speed_bonus`.

| # | Name | Price | Power | Luck Boost | Speed Modifier | Rune Slots | Notes |
|---|------|-------|-------|-----------|----------------|-----------|-------|
| 1 | Wooden Pickaxe | Free | 5 | 0 | 1.00× | 0 | Starter tool, given on registration |
| 2 | Stone Pickaxe | $50 | 12 | 5 | 1.10× | 1 | First shop purchase |
| 3 | Iron Pickaxe | $200 | 22 | 10 | 1.20× | 1 | |
| 4 | Golden Pickaxe | $800 | 38 | 20 | 1.35× | 2 | |
| 5 | Diamond Pickaxe | $3,000 | 60 | 35 | 1.55× | 2 | |
| 6 | Mythril Pickaxe | $12,000 | 90 | 50 | 1.75× | 3 | Requires Forgotten Kingdom access |
| 7 | Volcanic Pickaxe | $50,000 | 130 | 70 | 2.00× | 3 | Requires Volcanic Rift access |

---

## Rarity Tiers

| Tier | Enum Value | Colour (reference) |
|------|-----------|-------------------|
| Common | `common` | Grey (#a4a4a4) |
| Uncommon | `uncommon` | Green (#7baf75) |
| Rare | `rare` | Blue (#7bbdf6) |
| Epic | `epic` | Purple (#bf75e9) |
| Legendary | `legendary` | Gold (#ffb846) |
| Mythical | `mythical` | Red (#ff4d4d) |
