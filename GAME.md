# Game Overview

## Concept

A browser-based **RPG/MMO simulation game** centered around three core pillars:

1. **Mining** — Explore procedurally distributed islands to extract rare ores from mining nodes. Ores have rarity tiers (common, uncommon, rare, epic, legendary) and unique elemental properties.
2. **Forging** — Combine harvested ores at the forge to craft items. The system supports **1,000+ item variations** driven by ore type combinations, rarity, quantity ratios, and modifier runes.
3. **Fighting** — Equip forged items and engage enemies in combat. Defeating enemies drops runes, XP, and rare materials. New islands are unlocked through progression.

### Secondary Mechanics

- **Runes** — Collectible modifiers used to enhance forged items and unlock character skills.
- **Islands** — Exploration zones with unique ore deposits, enemy types, and forging recipes.
- **Player Progression** — Skill trees, equipment tiers, and mastery levels for each pillar (Mining, Forging, Fighting).

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Language | PHP 8.4 |
| Backend Framework | Laravel v13 |
| Authentication | Laravel Fortify v1 |
| Frontend Framework | Vue.js (via Inertia.js v3) |
| Routing (Frontend) | Laravel Wayfinder v0 |
| Testing | Pest v4 / PHPUnit v12 |
| Code Style | Laravel Pint v1 |
| Dev Server | Laravel Sail v1 |
| Build Tool | Vite |

---

## Project Goals

1. Build a playable, scalable browser-based RPG/MMO with no native client dependency.
2. Keep all authoritative game state server-side; the client is purely presentational.
3. Design the forging system to support combinatorial complexity without exponential DB growth.
4. Make the game loop engaging through tight feedback loops: mine → forge → fight → unlock.

---

## Key Constraints

- All game logic (damage calculation, forge outcomes, ore drop rates) is computed server-side.
- Client never sends final item stats — only player actions (e.g., "forge with these ores").
- Economy balance is enforced via seeded configuration tables, not hardcoded values.

---

## Reference Files

- `GAME.md` — This file. Project overview, goals, and tech stack.
- `architect.md` — High-level design decisions, DB schemas, current tasks.
- `CLAUDE.md` — Laravel Boost coding guidelines (do not modify).
