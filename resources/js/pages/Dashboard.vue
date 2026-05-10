<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, onUnmounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import { useEcho, useEchoPresence } from '@laravel/echo-vue';
import { useIntervalFn } from '@vueuse/core';
import axios from 'axios';
import { usePlayerStore } from '@/stores/usePlayerStore';
import { collect, hit } from '@/actions/App/Http/Controllers/Mining/MiningController';
import { ChevronDown, ChevronUp } from 'lucide-vue-next';
import InventoryTooltip, {
    type InventoryEquipSuccessPayload,
    type InventoryItemData,
    type InventorySaleSuccessPayload,
} from '@/components/Inventory/InventoryTooltip.vue';
import { formatExactNumber, formatNumber } from '@/lib/utils';

interface Player {
    id: number;
    name: string;
    level: number;
    experience: number;
    gold: number;
    next_level_exp: number;
}

interface PlayerStats {
    stamina: number;
    stamina_last_updated_at: string;
    hp: number;
}

interface Island {
    id: number;
    name: string;
}

interface NodeType {
    slug: string;
    name: string;
    tier: number;
}

interface MiningNode {
    id: number;
    max_hp: number;
    current_hp: number;
    is_respawning: boolean;
    respawns_at: string | null;
    node_type: NodeType;
}

interface InventoryItem extends InventoryItemData {}

interface Pickaxe {
    id: number;
    name: string;
    mining_power: number;
    mining_speed: number;
    luck_bonus: number;
    stamina_regen_bonus: number;
}

interface EquipmentItem {
    id: string;
    name: string;
    mining_power: number;
    mining_speed_bonus: number;
    luck_bonus: number;
    stamina_regen_bonus: number;
    hp_bonus: number;
    defense_bonus: number;
    attack_bonus: number;
    dodge_bonus?: number;
    crit_chance?: number;
    attack_speed_bonus?: number;
    elemental_affinity: string;
    forge_grade: number;
    final_stats: Record<string, number>;
}

interface BaseStats {
    hp: number;
    attack: number;
    defense: number;
    mining_speed: number;
    attack_speed: number;
    dodge: number;
}

interface FlyingNumber {
    id: number;
    damage: number;
    x: number;
    y: number;
}

const props = defineProps<{
    player: Player;
    player_stats: PlayerStats;
    island: Island | null;
    current_node: MiningNode | null;
    inventory: InventoryItem[];
    equipped_pickaxe: Pickaxe | null;
    equipment: Record<string, EquipmentItem | null>;
    base_stats: BaseStats;
}>();

const playerStore = usePlayerStore();
playerStore.initialize(props.player, props.player_stats, props.equipped_pickaxe);

// Local reactive state for node and inventory (updated via WebSocket + hit responses)
const node = ref<MiningNode | null>(props.current_node ? { ...props.current_node } : null);
const inventory = ref<InventoryItem[]>([...props.inventory]);
const equipment = ref<Record<string, EquipmentItem | null>>({ ...props.equipment });

// Inventory panel UI state
const inventoryOpen = ref(false);
const inventoryExpanded = ref(true);
const tooltip = ref<{ item: InventoryItem; anchorX: number; anchorY: number; anchorLeft: number } | null>(null);
let hideTimer: ReturnType<typeof setTimeout> | null = null;

onUnmounted(() => {
    if (hideTimer) clearTimeout(hideTimer);
});

// Helper: Find inventory item that corresponds to an equipped item
function getInventoryItemForEquipped(equippedItem: EquipmentItem): InventoryItem | undefined {
    return inventory.value.find(
        (invItem) => invItem.id === equippedItem.id && invItem.is_equipped === true,
    );
}

function showTooltip(item: InventoryItem, event: MouseEvent): void {
    if (hideTimer) { clearTimeout(hideTimer); hideTimer = null; }
    const rect = (event.currentTarget as HTMLElement).getBoundingClientRect();
    tooltip.value = { item, anchorX: rect.right, anchorY: rect.top, anchorLeft: rect.left };
}

function scheduleHide(): void {
    hideTimer = setTimeout(() => { tooltip.value = null; hideTimer = null; }, 120);
}

function cancelHide(): void {
    if (hideTimer) { clearTimeout(hideTimer); hideTimer = null; }
}

function handleEquipped(payload: InventoryEquipSuccessPayload): void {
    tooltip.value = null;
    if (hideTimer) { clearTimeout(hideTimer); hideTimer = null; }

    const item = inventory.value.find((i) => i.inventory_id === payload.inventoryId);

    // Check if it's an unequip (equippedPickaxe is null)
    if (payload.equippedPickaxe === null && item) {
        item.is_equipped = false;
        // Unequip: clear equipment slots by finding which slot had this item
        Object.keys(equipment.value).forEach((slotKey) => {
            if (equipment.value[slotKey]?.id === item.id) {
                equipment.value[slotKey] = null;
            }
        });
        toast.success(`Unequipped: ${payload.itemName}`);
    } else {
        // Equip: update equipment and mark item as equipped
        if (payload.slot && payload.equippedPickaxe) {
            equipment.value[payload.slot] = payload.equippedPickaxe;
            playerStore.equipPickaxe(payload.equippedPickaxe);
        }
        if (item) {
            item.is_equipped = true;
        }
        toast.success(`Equipped: ${payload.itemName}`);
    }
}

function handleSold(payload: InventorySaleSuccessPayload): void {
    tooltip.value = null;
    if (hideTimer) { clearTimeout(hideTimer); hideTimer = null; }

    const soldItem = inventory.value.find((i) => i.inventory_id === payload.inventoryId);
    const soldItemName = soldItem?.name ?? 'Item';

    if (soldItem) {
        if (payload.remainingQuantity > 0) {
            soldItem.quantity = payload.remainingQuantity;
        } else {
            inventory.value = inventory.value.filter((i) => i.inventory_id !== payload.inventoryId);
        }
    }

    playerStore.setGold(payload.gold);
    toast.success(`${soldItemName} sold for ${payload.totalValue} Gold`);
}

// Visual effects state
const flyingNumbers = ref<FlyingNumber[]>([]);
const isHitting = ref(false);
const isCollecting = ref(false);
const isCrumbling = ref(false);
const flashNode = ref(false);
let flyingNumberCounter = 0;
const nextHitAllowedAt = ref(0);

// Client-side stamina display — pure increment regen, no timestamp math.
// Base regen is 3 pts/sec, then equipped pickaxe bonus is added reactively.
// The WS StaminaUpdated event snaps displayStamina to the server's authoritative value.
const BASE_STAMINA_REGEN_PER_SECOND = 3;
const staminaRegenPerSecond = computed(() => {
    return BASE_STAMINA_REGEN_PER_SECOND + (playerStore.currentPickaxe?.stamina_regen_bonus ?? 0);
});

const STAMINA_TICK_MS = 100;
const displayStamina = ref(props.player_stats.stamina);
useIntervalFn(() => {
    if (displayStamina.value < 100) {
        displayStamina.value = Math.min(
            100,
            displayStamina.value + (staminaRegenPerSecond.value * (STAMINA_TICK_MS / 1000)),
        );
    }
}, STAMINA_TICK_MS);

// Echo: private channel — server snaps stamina to authoritative value after each hit.
useEcho<{ stamina: number; stamina_last_updated_at: string }>(
    `user.${props.player.id}`,
    'StaminaUpdated',
    (payload) => {
        // Snap to server value; interval will regen forward from here.
        displayStamina.value = payload.stamina;
    },
);

useEcho<{ new_level: number }>(
    `user.${props.player.id}`,
    'LevelUp',
    (payload) => {
        playerStore.applyLevelUp(payload.new_level);
    },
);

// Echo: presence channel — node state shared with all players on this island
const islandChannel = `island.${props.island?.id ?? 0}.nodes`;

useEchoPresence<{ node_id: number; current_hp: number }>(
    islandChannel,
    'NodeUpdated',
    (payload) => {
        if (node.value?.id === payload.node_id) {
            node.value.current_hp = payload.current_hp;
        }
    },
);

useEchoPresence<{ node_id: number; respawns_at: string }>(
    islandChannel,
    'NodeDepleted',
    (payload) => {
        if (node.value?.id === payload.node_id) {
            node.value.current_hp = 0;
            node.value.is_respawning = true;
            node.value.respawns_at = payload.respawns_at;
            flashNode.value = true;
            setTimeout(() => {
                flashNode.value = false;
            }, 600);
        }
    },
);

// Hot-swap: replace the active node only when the player has no node to mine.
// This prevents a bulk spawn-nodes run from resetting the current node's HP.
useEchoPresence<{ node: typeof node.value }>(
    islandChannel,
    'NodeSpawned',
    (payload) => {
        if (!node.value || node.value.is_respawning) {
            node.value = payload.node;
        }
    },
);

// Computed display values
const expPercent = computed(() => {
    if (props.player.next_level_exp === 0) return 100;
    return Math.min(100, Math.round((playerStore.experience / props.player.next_level_exp) * 100));
});

const nodeHpPercent = computed(() => {
    if (!node.value) return 0;
    return Math.round((node.value.current_hp / node.value.max_hp) * 100);
});

const staminaPercent = computed(() => Math.round(displayStamina.value));

const hpBarColor = computed(() => {
    if (nodeHpPercent.value > 60) return 'bg-green-500';
    if (nodeHpPercent.value > 30) return 'bg-yellow-500';
    return 'bg-red-500';
});

// Stamina power: 0–100% shown as POW label. Below 20% the bar also shakes.
const powerPercent = computed(() => Math.round(displayStamina.value));
const isLowPower = computed(() => displayStamina.value < 20);
const totalMiningPower = computed(() => playerStore.currentPickaxe?.mining_power ?? 0);
const miningSpeedMultiplier = computed(() => Math.max(0.1, playerStore.currentPickaxe?.mining_speed ?? 1));
const miningHitCooldownMs = computed(() => Math.max(220, Math.round(900 / miningSpeedMultiplier.value)));
const totalMiningLuck = computed(() => playerStore.currentPickaxe?.luck_bonus ?? 0);
const staminaRegenLabel = computed(() => `${staminaRegenPerSecond.value.toFixed(1)}/s`);
const miningSpeedLabel = computed(() => `${miningSpeedMultiplier.value.toFixed(2)}x`);

// Character section totals
const totalMiningPowerStats = computed(() => {
    const baseSpeed = props.base_stats.mining_speed ?? 0;
    const equippedBonus = equipment.value.pickaxe?.mining_power ?? 0;
    return baseSpeed + equippedBonus;
});

const totalMiningLuckStats = computed(() => {
    return equipment.value.pickaxe?.luck_bonus ?? 0;
});

const totalStaminaRegen = computed(() => {
    const baseRegen = 3;
    const equippedBonus = equipment.value.pickaxe?.stamina_regen_bonus ?? 0;
    return baseRegen + equippedBonus;
});

const totalDefense = computed(() => {
    const baseDef = props.base_stats.defense ?? 0;
    let armorBonus = 0;

    if (equipment.value.armor) {
        armorBonus += equipment.value.armor.defense_bonus ?? 0;
    }
    if (equipment.value.helmet) {
        armorBonus += equipment.value.helmet.defense_bonus ?? 0;
    }
    if (equipment.value.pants) {
        armorBonus += equipment.value.pants.defense_bonus ?? 0;
    }

    return baseDef + armorBonus;
});

// Combat stats calculations
const totalHP = computed(() => {
    const baseHP = props.base_stats.hp ?? 0;
    let hpBonus = 0;

    if (equipment.value.armor) {
        hpBonus += equipment.value.armor.hp_bonus ?? 0;
    }
    if (equipment.value.helmet) {
        hpBonus += equipment.value.helmet.hp_bonus ?? 0;
    }
    if (equipment.value.pants) {
        hpBonus += equipment.value.pants.hp_bonus ?? 0;
    }
    if (equipment.value.pickaxe) {
        hpBonus += equipment.value.pickaxe.hp_bonus ?? 0;
    }

    return baseHP + hpBonus;
});

const totalAttackDamage = computed(() => {
    const baseAttack = props.base_stats.attack ?? 0;
    let attackBonus = 0;

    if (equipment.value.weapon) {
        attackBonus += equipment.value.weapon.attack_bonus ?? 0;
    }
    if (equipment.value.pickaxe) {
        attackBonus += equipment.value.pickaxe.attack_bonus ?? 0;
    }

    return baseAttack + attackBonus;
});

const totalCritChance = computed(() => {
    let crit = 0;

    if (equipment.value.weapon) {
        crit += equipment.value.weapon.crit_chance ?? 0;
    }

    return crit;
});

const totalDodgeChance = computed(() => {
    const baseDodge = props.base_stats.dodge ?? 0;
    let dodgeBonus = 0;

    if (equipment.value.armor) {
        dodgeBonus += equipment.value.armor.dodge_bonus ?? 0;
    }
    if (equipment.value.pants) {
        dodgeBonus += equipment.value.pants.dodge_bonus ?? 0;
    }
    if (equipment.value.boots) {
        dodgeBonus += equipment.value.boots.dodge_bonus ?? 0;
    }

    return baseDodge + dodgeBonus;
});

const totalAttackSpeed = computed(() => {
    const baseSpeed = props.base_stats.attack_speed ?? 1;
    let speedBonus = 0;

    if (equipment.value.weapon) {
        speedBonus += equipment.value.weapon.attack_speed_bonus ?? 0;
    }

    return baseSpeed + speedBonus;
});

const SLOT_ICONS: Record<string, string> = {
    helmet: '⛑️',
    armor: '🛡️',
    pants: '👖',
    boots: '👞',
    weapon: '⚔️',
    pickaxe: '⛏️',
};

const SLOT_LABELS: Record<string, string> = {
    helmet: 'Head',
    armor: 'Body',
    pants: 'Legs',
    boots: 'Feet',
    weapon: 'Weapon',
    pickaxe: 'Tool',
};

const rarityBorderMap: Record<string, string> = {
    common: 'border-slate-600',
    uncommon: 'border-green-700',
    rare: 'border-blue-600',
    epic: 'border-purple-600',
    legendary: 'border-orange-500',
    mythical: 'border-yellow-400',
};

const gradeGlowMap: Record<number, string> = {
    1: 'border-slate-600',
    2: 'border-green-700',
    3: 'border-blue-600',
    4: 'border-blue-600',
    5: 'border-purple-600',
    6: 'border-purple-600',
    7: 'border-orange-500',
    8: 'border-orange-500',
    9: 'border-yellow-400',
    10: 'border-yellow-400',
};

function itemBorderClass(item: InventoryItem): string {
    if (item.holdable_type === 'item' && item.forge_grade !== undefined) {
        return gradeGlowMap[item.forge_grade] ?? 'border-slate-600';
    }
    const rarity = (item.rarity ?? 'common').toLowerCase();
    return rarityBorderMap[rarity] ?? 'border-slate-600';
}

// Mining hit
async function onNodeClick(event: MouseEvent): Promise<void> {
    const now = Date.now();

    if (
        !node.value
        || node.value.is_respawning
        || isHitting.value
        || isCollecting.value
        || displayStamina.value < 10
        || now < nextHitAllowedAt.value
    ) {
        return;
    }

    const rect = (event.currentTarget as HTMLElement).getBoundingClientRect();
    const clickX = event.clientX - rect.left;
    const clickY = event.clientY - rect.top;

    isHitting.value = true;
    nextHitAllowedAt.value = now + miningHitCooldownMs.value;

    setTimeout(() => {
        if (Date.now() >= nextHitAllowedAt.value) {
            isHitting.value = false;
        }
    }, miningHitCooldownMs.value);

    // IMMEDIATE DRAIN — subtract 30 stamina locally on click.
    // The WS StaminaUpdated event will confirm this from the server.
    displayStamina.value = Math.max(0, displayStamina.value - 30);

    try {
        const response = await axios.post<{
            damage_dealt: number;
            node_hp_remaining: number;
            is_destroyed: boolean;
            stamina_remaining: number;
            loot: null;
            exp_gained: number;
            level_up: boolean;
        }>(hit.url(), {
            node_id: node.value.id,
        });

        const data = response.data;


        // Spawn flying damage number at click position
        const id = ++flyingNumberCounter;
        flyingNumbers.value.push({ id, damage: data.damage_dealt, x: clickX, y: clickY });
        setTimeout(() => {
            flyingNumbers.value = flyingNumbers.value.filter((n) => n.id !== id);
        }, 900);

        // Node HP is updated below; stamina is authoritative from the WS StaminaUpdated event.
        // Do NOT call applyStaminaUpdate here — it would introduce timestamp skew.
        if (node.value) {
            node.value.current_hp = data.node_hp_remaining;
        }

        // Destruction is now a second phase: collect loot and spawn the next node.
        if (node.value && node.value.current_hp <= 0) {
            await collectDestroyedNode(node.value.id);
        }
    } catch {
        // Node unavailable or stamina exhausted — server error is authoritative
    }
}

async function collectDestroyedNode(nodeId: number): Promise<void> {
    if (isCollecting.value) {
        return;
    }

    isCollecting.value = true;
    isCrumbling.value = true;

    try {
        const response = await axios.post<{
            loot: Array<{ inventory_id: number; ore_id: number; name: string; quantity: number; rarity?: string; base_sell_price?: number }>;
            exp_gained: number;
            level_up: boolean;
            next_node: MiningNode | null;
        }>(collect.url(), {
            node_id: nodeId,
        });

        const data = response.data;

        if (data.exp_gained > 0) {
            playerStore.addExp(data.exp_gained);
        }

        if (data.loot.length) {
            for (const drop of data.loot) {
                const existing = inventory.value.find(
                    (i) => i.holdable_type === 'ore' && i.id === drop.ore_id,
                );

                if (existing) {
                    existing.quantity += drop.quantity;
                } else {
                    inventory.value.push({
                        inventory_id: drop.inventory_id,
                        id: drop.ore_id,
                        name: drop.name,
                        quantity: drop.quantity,
                        holdable_type: 'ore',
                        base_sell_price: drop.base_sell_price ?? 1,
                        rarity: drop.rarity ?? 'common',
                    });
                }
            }
        }

        node.value = null;

        setTimeout(() => {
            node.value = data.next_node;
            isCrumbling.value = false;
            isCollecting.value = false;
        }, 900);
    } catch {
        isCrumbling.value = false;
        isCollecting.value = false;
    }
}
</script>

<template>
    <Head title="Mining" />

    <div class="flex h-full flex-col gap-4 p-4">
        <!-- Header: Level / EXP / Island / Pickaxe -->
        <div
            class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-sidebar-border/70 bg-card px-5 py-4 dark:border-sidebar-border"
        >
            <div class="flex items-center gap-4">
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-bold text-primary-foreground shadow"
                >
                    {{ playerStore.level }}
                </div>
                <div class="flex flex-col gap-1">
                    <div class="flex items-center gap-3">
                        <span class="text-xs font-medium text-muted-foreground"
                            >Level {{ playerStore.level }}</span
                        >
                        <span
                            class="inline-flex items-center gap-1 rounded-full border border-yellow-500/40 bg-yellow-500/10 px-2 py-0.5 text-[11px] font-semibold text-yellow-300"
                            :title="formatExactNumber(playerStore.gold)"
                        >
                            <span aria-hidden="true">🪙</span>
                            {{ formatNumber(playerStore.gold) }} Gold
                        </span>
                    </div>
                    <div class="h-2 w-44 overflow-hidden rounded-full bg-muted">
                        <div
                            class="h-full bg-yellow-500 transition-all duration-300"
                            :style="{ width: expPercent + '%' }"
                        />
                    </div>
                    <span class="text-xs text-muted-foreground" :title="`${formatExactNumber(playerStore.experience)} / ${formatExactNumber(props.player.next_level_exp)} XP`"
                        >{{ formatNumber(playerStore.experience) }} / {{ formatNumber(props.player.next_level_exp) }} XP</span
                    >
                </div>
            </div>

            <div class="text-center">
                <p class="text-xs text-muted-foreground">Island</p>
                <p class="font-semibold">{{ island?.name ?? '—' }}</p>
            </div>

            <div class="text-right">
                <p class="text-xs text-muted-foreground">Pickaxe</p>
                <div v-if="playerStore.currentPickaxe" class="inline-flex items-center gap-1.5 rounded-md border border-sky-500/40 bg-sky-500/10 px-2 py-1">
                    <span aria-hidden="true">⛏️</span>
                    <span class="font-semibold">{{ playerStore.currentPickaxe.name }}</span>
                </div>
                <p v-else class="font-semibold text-muted-foreground">No Pickaxe</p>
            </div>

            <div class="rounded-md border border-slate-700/70 bg-slate-900/30 px-3 py-2">
                <p class="mb-1 text-[11px] uppercase tracking-wide text-muted-foreground">Stat Summary</p>
                <div class="flex items-center gap-3 text-xs">
                    <span class="inline-flex items-center gap-1 text-slate-200">
                        <span aria-hidden="true">⛏️</span>
                        {{ totalMiningPower }}
                    </span>
                    <span class="inline-flex items-center gap-1 text-slate-200">
                        <span aria-hidden="true">🍀</span>
                        {{ totalMiningLuck }}
                    </span>
                    <span class="inline-flex items-center gap-1 text-slate-200">
                        <span aria-hidden="true">⚡</span>
                        {{ staminaRegenLabel }}
                    </span>
                    <span class="inline-flex items-center gap-1 text-slate-200">
                        <span aria-hidden="true">⏱️</span>
                        {{ miningSpeedLabel }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Main area: Mining Zone (with overlay inventory panel) -->
        <div class="flex min-h-0 flex-1 gap-4">
            <!-- Mining Zone -->
            <div
                class="flex flex-1 flex-col items-center justify-center gap-6 rounded-xl border border-sidebar-border/70 bg-card p-8 dark:border-sidebar-border"
            >
                <!-- Active node -->
                <template v-if="node && !node.is_respawning">
                    <p class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">
                        {{ node.node_type.name }}
                        <span class="ml-1 rounded bg-muted px-1.5 py-0.5 text-[10px]"
                            >Tier {{ node.node_type.tier }}</span
                        >
                    </p>

                    <!-- Ore node — clickable -->
                    <div
                        class="relative cursor-pointer select-none"
                        :class="{ 'pointer-events-none': isHitting || isCollecting }"
                        @click="onNodeClick"
                    >
                        <div
                            class="flex h-52 w-52 items-center justify-center rounded-full border-4 border-stone-600 bg-stone-800 text-8xl shadow-2xl transition-transform duration-75 hover:scale-105 active:scale-95 dark:border-stone-500 dark:bg-stone-900"
                            :class="{ 'animate-flash': flashNode, 'animate-crumble': isCrumbling }"
                        >
                            🪨
                        </div>

                        <!-- Flying damage numbers -->
                        <div
                            v-for="n in flyingNumbers"
                            :key="n.id"
                            class="animate-fly-up pointer-events-none absolute font-bold text-red-400 drop-shadow-md"
                            style="font-size: 1.25rem; transform: translate(-50%, -50%)"
                            :style="{ left: n.x + 'px', top: n.y + 'px' }"
                            :title="formatExactNumber(n.damage)"
                        >
                            -{{ formatNumber(n.damage) }}
                        </div>
                    </div>

                    <!-- Node HP bar -->
                    <div class="w-full max-w-xs">
                        <div class="mb-1 flex justify-between text-xs text-muted-foreground">
                            <span>Node HP</span>
                            <span>{{ node.current_hp }} / {{ node.max_hp }}</span>
                        </div>
                        <div class="h-4 overflow-hidden rounded-full bg-muted">
                            <div
                                class="h-full transition-all duration-150"
                                :class="hpBarColor"
                                :style="{ width: nodeHpPercent + '%' }"
                            />
                        </div>
                    </div>
                </template>

                <!-- Respawning state -->
                <template v-else-if="node?.is_respawning">
                    <div class="text-center">
                        <div class="mb-3 text-7xl opacity-40">💥</div>
                        <p class="text-lg font-semibold text-muted-foreground">Node Depleted</p>
                        <p class="mt-1 text-sm text-muted-foreground">Respawning...</p>
                    </div>
                </template>

                <!-- No node -->
                <template v-else>
                    <div class="text-center text-muted-foreground">
                        <div class="mb-3 text-5xl">⛏️</div>
                        <p class="text-base font-semibold">No Nodes Available</p>
                        <p class="mt-1 text-sm">Check back soon.</p>
                    </div>
                </template>

                <!-- Inventory toggle button (floating) -->
                <button
                    v-if="!inventoryOpen"
                    class="fixed bottom-4 right-4 z-40 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground shadow-lg transition hover:bg-primary/90 active:scale-95"
                    @click="inventoryOpen = true"
                    title="Open Inventory"
                >
                    📦 Inventory ({{ inventory.length }})
                </button>
            </div>

            <!-- Inventory Slide-out Panel (from right) - Fixed Positioned -->
            <Transition
                enter-active-class="transition-all duration-300 ease-out"
                leave-active-class="transition-all duration-300 ease-in"
                enter-from-class="translate-x-full"
                leave-to-class="translate-x-full"
            >
                <div v-show="inventoryOpen" class="fixed right-0 top-0 bottom-0 z-40 w-96 bg-slate-950/95 border-l border-sidebar-border/70 shadow-2xl overflow-hidden flex flex-col">
                    <!-- Close button bar -->
                    <div class="flex items-center justify-between px-4 py-3 border-b border-sidebar-border/70 shrink-0">
                        <h2 class="text-lg font-bold">Inventory</h2>
                        <button
                            class="rounded px-2 py-1 text-lg font-bold bg-slate-700 hover:bg-slate-600 text-white transition"
                            @click="inventoryOpen = false"
                            title="Close"
                        >
                            ✕
                        </button>
                    </div>

                    <!-- Inventory and Character sections (scrollable) -->
                    <div class="flex-1 overflow-y-auto flex flex-col gap-4 p-4">
                        <!-- Inventory Grid -->
                        <div class="border border-sidebar-border/70 bg-card/50 p-4 rounded-lg">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="text-sm font-semibold">
                                    Items
                                    <span class="ml-1 text-xs font-normal text-muted-foreground">({{ inventory.length }})</span>
                                </h3>
                                <button
                                    class="text-xs"
                                    @click="inventoryExpanded = !inventoryExpanded"
                                >
                                    <ChevronUp v-if="inventoryExpanded" class="h-4 w-4" />
                                    <ChevronDown v-else class="h-4 w-4" />
                                </button>
                            </div>

                            <div v-if="inventoryExpanded" class="inventory-scrollbox max-h-48 overflow-y-auto">
                                <div
                                    v-if="inventory.length"
                                    class="grid gap-1.5"
                                    style="grid-template-columns: repeat(auto-fill, minmax(72px, 1fr))"
                                >
                                    <div
                                        v-for="item in inventory"
                                        :key="item.inventory_id"
                                        class="relative flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 bg-muted/40 p-1.5 text-center transition-all hover:bg-muted/70 pointer-events-auto"
                                        :class="[
                                            itemBorderClass(item),
                                            item.is_equipped && 'ring-2 ring-emerald-500/60 ring-offset-2 ring-offset-slate-800'
                                        ]"
                                        @mouseenter="showTooltip(item, $event)"
                                        @mouseleave="scheduleHide()"
                                    >
                                        <div class="mb-0.5 text-2xl">
                                            <span v-if="item.holdable_type === 'item'">⚔️</span>
                                            <span v-else>🪨</span>
                                        </div>
                                        <p class="w-full truncate text-center text-[10px] leading-tight text-muted-foreground">
                                            {{ item.name }}
                                        </p>
                                        <span
                                            v-if="item.quantity > 1"
                                            class="absolute right-0.5 top-0.5 rounded bg-black/70 px-1 text-[9px] font-bold text-white"
                                        >
                                            ×{{ item.quantity }}
                                        </span>
                                        <span
                                            v-if="item.is_equipped"
                                            class="absolute -right-1.5 -top-1.5 flex h-5 w-5 items-center justify-center rounded-full bg-emerald-500 text-white shadow-lg ring-2 ring-slate-900"
                                        >
                                            ✓
                                        </span>
                                    </div>
                                </div>
                                <p v-else class="py-6 text-center text-xs text-muted-foreground">
                                    Nothing yet.<br />
                                    Start mining!
                                </p>
                            </div>
                        </div>

            <!-- Character Section -->
                        <div class="border border-sidebar-border/70 bg-card/50 p-4 rounded-lg">
                            <p class="mb-3 text-sm font-semibold">Character</p>

                            <div class="space-y-2">
                                <div
                                    v-for="slot in ['helmet', 'armor', 'pants', 'boots', 'weapon', 'pickaxe']"
                                    :key="slot"
                                    class="group flex items-center justify-between rounded-md border border-slate-700/50 bg-slate-900/30 p-2 transition-all cursor-pointer pointer-events-auto"
                                    :class="equipment[slot] && 'hover:bg-slate-900/60 hover:border-slate-600/80 hover:ring-1 hover:ring-slate-600/40'"
                                    @mouseenter="equipment[slot] && showTooltip(getInventoryItemForEquipped(equipment[slot]!)!, $event)"
                                    @mouseleave="scheduleHide()"
                                >
                                    <div class="flex items-center gap-2">
                                        <span class="text-lg">{{ SLOT_ICONS[slot] || '?' }}</span>
                                        <span class="text-xs font-semibold text-slate-300">{{ SLOT_LABELS[slot] }}</span>
                                    </div>
                                    <div class="text-right">
                                        <p v-if="equipment[slot]" class="text-xs font-bold text-slate-200 group-hover:text-slate-100 transition">
                                            {{ equipment[slot]?.name }}
                                        </p>
                                        <p v-else class="text-xs text-slate-500 group-hover:text-slate-400 transition">—</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Total Stats -->
                        <div class="border border-sidebar-border/70 bg-card/50 p-4 rounded-lg">
                            <p class="mb-4 text-sm font-semibold">Total Stats</p>

                            <!-- Mining Stats Group -->
                            <div class="mb-4 pb-4 border-b border-slate-700/50">
                                <p class="mb-2 text-xs font-semibold text-slate-400 uppercase tracking-wider">Mining Stats</p>
                                <div class="space-y-2">
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="flex items-center gap-1 text-slate-400">
                                            <span>⛏️</span> Mining Power
                                        </span>
                                        <span class="font-mono font-bold text-slate-200">{{ totalMiningPowerStats }}</span>
                                    </div>
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="flex items-center gap-1 text-slate-400">
                                            <span>🍀</span> Luck
                                        </span>
                                        <span class="font-mono font-bold text-slate-200">{{ totalMiningLuckStats }}</span>
                                    </div>
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="flex items-center gap-1 text-slate-400">
                                            <span>⚡</span> Stamina Regen
                                        </span>
                                        <span class="font-mono font-bold text-slate-200">{{ totalStaminaRegen.toFixed(1) }}/s</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Combat Stats Group -->
                            <div>
                                <p class="mb-2 text-xs font-semibold text-slate-400 uppercase tracking-wider">Combat Stats</p>
                                <div class="space-y-2">
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="flex items-center gap-1 text-slate-400">
                                            <span>❤️</span> HP
                                        </span>
                                        <span class="font-mono font-bold text-slate-200">{{ totalHP }}</span>
                                    </div>
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="flex items-center gap-1 text-slate-400">
                                            <span>⚔️</span> Damage
                                        </span>
                                        <span class="font-mono font-bold text-slate-200">{{ totalAttackDamage }}</span>
                                    </div>
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="flex items-center gap-1 text-slate-400">
                                            <span>🛡️</span> Defense
                                        </span>
                                        <span class="font-mono font-bold text-slate-200">{{ totalDefense }}</span>
                                    </div>
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="flex items-center gap-1 text-slate-400">
                                            <span>🎯</span> Crit
                                        </span>
                                        <span class="font-mono font-bold text-slate-200">{{ totalCritChance.toFixed(1) }}%</span>
                                    </div>
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="flex items-center gap-1 text-slate-400">
                                            <span>💨</span> Dodge
                                        </span>
                                        <span class="font-mono font-bold text-slate-200">{{ totalDodgeChance.toFixed(1) }}%</span>
                                    </div>
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="flex items-center gap-1 text-slate-400">
                                            <span>⚙️</span> ATK Speed
                                        </span>
                                        <span class="font-mono font-bold text-slate-200">{{ totalAttackSpeed.toFixed(2) }}x</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>

            <!-- Global inventory tooltip (rendered at body level via fixed positioning) -->
            <Teleport to="body">
                <InventoryTooltip
                    v-if="tooltip"
                    :item="tooltip.item"
                    :anchor-x="tooltip.anchorX"
                    :anchor-y="tooltip.anchorY"
                    :anchor-left="tooltip.anchorLeft"
                    @mouseenter="cancelHide"
                    @mouseleave="scheduleHide"
                    @equipped="handleEquipped"
                    @sold="handleSold"
                />
            </Teleport>
        </div>

        <!-- Bottom HUD: Stamina bar -->
        <div
            class="rounded-xl border border-sidebar-border/70 bg-card px-5 py-4 dark:border-sidebar-border"
        >
            <div class="flex items-center gap-4">
                <div class="w-20 shrink-0">
                    <span
                        class="text-sm font-medium transition-colors duration-200"
                        :class="isLowPower ? 'text-red-400' : 'text-foreground'"
                    >Stamina</span>
                    <div
                        class="mt-0.5 text-[10px] font-bold uppercase tracking-wider transition-colors duration-200"
                        :class="isLowPower ? 'text-red-400' : 'text-muted-foreground'"
                    >
                        POW: {{ powerPercent }}%
                    </div>
                </div>
                <div
                    class="relative flex-1 overflow-hidden rounded-full bg-muted"
                    style="height: 20px"
                    :class="{ 'animate-weak-shake': isLowPower }"
                >
                    <div
                        class="h-full transition-all duration-150"
                        :class="isLowPower ? 'bg-red-500' : 'bg-blue-500'"
                        :style="{ width: staminaPercent + '%' }"
                    />
                </div>
                <span class="w-12 shrink-0 text-right text-sm tabular-nums text-muted-foreground">
                    {{ staminaPercent }}%
                </span>
            </div>
        </div>
    </div>
</template>

<style scoped>
.inventory-scrollbox {
    max-height: 360px;
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: hsl(var(--border)) transparent;
}

.inventory-scrollbox::-webkit-scrollbar {
    width: 5px;
}

.inventory-scrollbox::-webkit-scrollbar-track {
    background: transparent;
}

.inventory-scrollbox::-webkit-scrollbar-thumb {
    background-color: hsl(var(--border));
    border-radius: 9999px;
}

@keyframes fly-up {
    0% {
        opacity: 1;
        transform: translate(-50%, -50%) scale(1);
    }
    100% {
        opacity: 0;
        transform: translate(-50%, -120%) scale(1.4);
    }
}

@keyframes flash {
    0%,
    100% {
        filter: brightness(1);
    }
    20% {
        filter: brightness(2.5);
    }
    50% {
        filter: brightness(1.6) saturate(2);
    }
}

.animate-fly-up {
    animation: fly-up 0.9s ease-out forwards;
    pointer-events: none;
    position: absolute;
    white-space: nowrap;
}

.animate-flash {
    animation: flash 0.6s ease-in-out;
}

@keyframes weak-shake {
    0%, 100% { transform: translateX(0); }
    20%       { transform: translateX(-3px); }
    40%       { transform: translateX(3px); }
    60%       { transform: translateX(-2px); }
    80%       { transform: translateX(2px); }
}

.animate-weak-shake {
    animation: weak-shake 0.4s ease-in-out infinite;
}

@keyframes crumble {
    0% {
        opacity: 1;
        transform: translate(0, 0) scale(1);
    }
    20% {
        transform: translate(-2px, 1px) scale(1.01);
    }
    40% {
        transform: translate(3px, -2px) scale(0.99);
    }
    60% {
        transform: translate(-2px, 2px) scale(0.97);
    }
    100% {
        opacity: 0;
        transform: translate(0, 10px) scale(0.9);
    }
}

.animate-crumble {
    animation: crumble 0.9s ease-in forwards;
}
</style>

