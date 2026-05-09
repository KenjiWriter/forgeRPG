<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, onUnmounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import { useEcho, useEchoPresence } from '@laravel/echo-vue';
import { useIntervalFn } from '@vueuse/core';
import axios from 'axios';
import { usePlayerStore } from '@/stores/usePlayerStore';
import { collect, hit } from '@/actions/App/Http/Controllers/Mining/MiningController';
import { equip as inventoryEquip } from '@/actions/App/Http/Controllers/Inventory/InventoryController';
import { ChevronDown, ChevronUp } from 'lucide-vue-next';
import InventoryTooltip, {
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
    mining_dmg_bonus: number;
    luck_bonus: number;
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
}>();

const playerStore = usePlayerStore();
playerStore.initialize(props.player, props.player_stats, props.equipped_pickaxe);

// Local reactive state for node and inventory (updated via WebSocket + hit responses)
const node = ref<MiningNode | null>(props.current_node ? { ...props.current_node } : null);
const inventory = ref<InventoryItem[]>([...props.inventory]);

// Inventory panel UI state
const inventoryExpanded = ref(true);
const tooltip = ref<{ item: InventoryItem; anchorX: number; anchorY: number; anchorLeft: number } | null>(null);
let hideTimer: ReturnType<typeof setTimeout> | null = null;

onUnmounted(() => {
    if (hideTimer) clearTimeout(hideTimer);
});

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

async function handleEquip(inventoryId: number): Promise<void> {
    tooltip.value = null;
    if (hideTimer) { clearTimeout(hideTimer); hideTimer = null; }
    try {
        await axios.post(inventoryEquip.url(inventoryId), {}, { withXSRFToken: true });
    } catch {
        // Equip errors are non-critical for now
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

// Client-side stamina display — pure increment regen, no timestamp math.
// Each 100ms tick adds 0.3 pts  → 3 pts/sec → full 0→100 in ~33 s.
// The WS StaminaUpdated event snaps displayStamina to the server's authoritative value.
const STAMINA_REGEN_PER_TICK = 0.3; // 3 pts/sec at 100ms intervals
const displayStamina = ref(props.player_stats.stamina);
useIntervalFn(() => {
    if (displayStamina.value < 100) {
        displayStamina.value = Math.min(100, displayStamina.value + STAMINA_REGEN_PER_TICK);
    }
}, 100);

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
    if (!node.value || node.value.is_respawning || isHitting.value || isCollecting.value || displayStamina.value < 10) {
        return;
    }

    const rect = (event.currentTarget as HTMLElement).getBoundingClientRect();
    const clickX = event.clientX - rect.left;
    const clickY = event.clientY - rect.top;

    isHitting.value = true;

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
    } finally {
        isHitting.value = false;
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
                <p class="font-semibold">{{ equipped_pickaxe?.name ?? '—' }}</p>
            </div>
        </div>

        <!-- Main area: Mining Zone + Inventory -->
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
            </div>

            <!-- Inventory sidebar -->
            <div
                class="flex w-64 shrink-0 flex-col rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border"
            >
                <!-- Inventory header / toggle -->
                <div
                    class="flex cursor-pointer select-none items-center justify-between px-4 py-3"
                    @click="inventoryExpanded = !inventoryExpanded"
                >
                    <h3 class="text-sm font-semibold">
                        Inventory
                        <span class="ml-1 text-xs font-normal text-muted-foreground">({{ inventory.length }})</span>
                    </h3>
                    <ChevronUp v-if="inventoryExpanded" class="h-4 w-4 text-muted-foreground" />
                    <ChevronDown v-else class="h-4 w-4 text-muted-foreground" />
                </div>

                <!-- Grid scrollbox -->
                <div
                    v-if="inventoryExpanded"
                    class="inventory-scrollbox p-3"
                >
                    <div
                        v-if="inventory.length"
                        class="grid gap-1.5"
                        style="grid-template-columns: repeat(auto-fill, minmax(72px, 1fr))"
                    >
                        <div
                            v-for="item in inventory"
                            :key="item.inventory_id"
                            class="relative flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 bg-muted/40 p-1.5 text-center transition-colors hover:bg-muted/70"
                            :class="itemBorderClass(item)"
                            @mouseenter="showTooltip(item, $event)"
                            @mouseleave="scheduleHide()"
                        >
                            <!-- Slot icon -->
                            <div class="mb-0.5 text-2xl">
                                <span v-if="item.holdable_type === 'item'">⚔️</span>
                                <span v-else>🪨</span>
                            </div>
                            <!-- Item name truncated -->
                            <p class="w-full truncate text-center text-[10px] leading-tight text-muted-foreground">
                                {{ item.name }}
                            </p>
                            <!-- Quantity badge -->
                            <span
                                v-if="item.quantity > 1"
                                class="absolute right-0.5 top-0.5 rounded bg-black/70 px-1 text-[9px] font-bold text-white"
                            >
                                ×{{ item.quantity }}
                            </span>
                        </div>
                    </div>
                    <p v-else class="py-6 text-center text-xs text-muted-foreground">
                        Nothing yet.<br />Start mining!
                    </p>
                </div>
            </div>

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
                    @equip="handleEquip"
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

