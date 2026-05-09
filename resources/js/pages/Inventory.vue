<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import axios from 'axios';
import { usePlayerStore } from '@/stores/usePlayerStore';
import { equipByItemId } from '@/actions/App/Http/Controllers/Inventory/InventoryController';
import { formatNumber } from '@/lib/utils';
import InventoryTooltip, {
    type InventoryEquipSuccessPayload,
    type InventoryItemData,
    type InventorySaleSuccessPayload,
} from '@/components/Inventory/InventoryTooltip.vue';
import { ChevronDown, ChevronUp } from 'lucide-vue-next';

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
    elemental_affinity: string;
    forge_grade: number;
    final_stats: Record<string, number>;
}

interface Player {
    id: number;
    name: string;
    level: number;
    gold: number;
}

interface PlayerStats {
    hp: number;
    attack: number;
    defense: number;
    mining_speed: number;
    attack_speed: number;
    dodge: number;
}

interface InventoryItem extends InventoryItemData {
    is_equipped?: boolean;
}

const props = defineProps<{
    player: Player;
    inventory_items: InventoryItem[];
    equipment: Record<string, EquipmentItem | null>;
    player_stats: PlayerStats;
}>();

const playerStore = usePlayerStore();

const inventory = ref<InventoryItem[]>([...props.inventory_items]);
const equipment = ref<Record<string, EquipmentItem | null>>({ ...props.equipment });
const tooltip = ref<{ item: InventoryItem; anchorX: number; anchorY: number; anchorLeft: number } | null>(null);
const inventoryExpanded = ref(true);
let hideTimer: ReturnType<typeof setTimeout> | null = null;

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

const totalMiningPower = computed(() => {
    const baseSpeed = props.player_stats.mining_speed ?? 0;
    const equippedBonus = equipment.value.pickaxe?.mining_power ?? 0;
    return baseSpeed + equippedBonus;
});

const totalMiningLuck = computed(() => {
    return equipment.value.pickaxe?.luck_bonus ?? 0;
});

const totalStaminaRegen = computed(() => {
    const baseRegen = 3; // From MiningService
    const equippedBonus = equipment.value.pickaxe?.stamina_regen_bonus ?? 0;
    return baseRegen + equippedBonus;
});

const totalDefense = computed(() => {
    const baseDef = props.player_stats.defense ?? 0;
    let armorBonus = 0;

    if (equipment.value.armor) {
        armorBonus += equipment.value.armor.defense_bonus ?? 0;
    }
    if (equipment.value.helmet) {
        armorBonus += equipment.value.helmet.defense_bonus ?? 0;
    }

    return baseDef + armorBonus;
});

const rarity_colormap: Record<string, string> = {
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
    return rarity_colormap[rarity] ?? 'border-slate-600';
}

function showTooltip(item: InventoryItem, event: MouseEvent): void {
    if (hideTimer) clearTimeout(hideTimer);
    const rect = (event.currentTarget as HTMLElement).getBoundingClientRect();
    tooltip.value = { item, anchorX: rect.right, anchorY: rect.top, anchorLeft: rect.left };
}

function scheduleHide(): void {
    hideTimer = setTimeout(() => {
        tooltip.value = null;
        hideTimer = null;
    }, 120);
}

function cancelHide(): void {
    if (hideTimer) {
        clearTimeout(hideTimer);
        hideTimer = null;
    }
}

function handleEquipped(payload: InventoryEquipSuccessPayload): void {
    tooltip.value = null;
    if (hideTimer) {
        clearTimeout(hideTimer);
        hideTimer = null;
    }

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
        }
        if (item) {
            item.is_equipped = true;
        }
        toast.success(`Equipped: ${payload.itemName}`);
    }
}

function handleSold(payload: InventorySaleSuccessPayload): void {
    tooltip.value = null;
    if (hideTimer) {
        clearTimeout(hideTimer);
        hideTimer = null;
    }

    const soldItem = inventory.value.find((i) => i.inventory_id === payload.inventoryId);
    const soldItemName = soldItem?.name ?? 'Item';

    if (soldItem) {
        if (payload.remainingQuantity > 0) {
            soldItem.quantity = payload.remainingQuantity;
        } else {
            inventory.value = inventory.value.filter((i) => i.inventory_id !== payload.inventoryId);
        }
    }

    toast.success(`${soldItemName} sold for ${payload.totalValue} Gold`);
}
</script>

<template>
    <Head title="Inventory" />

    <div class="flex h-full flex-col gap-4 p-4">
        <!-- Header -->
        <div class="flex items-center justify-between rounded-lg border border-sidebar-border/70 bg-card px-4 py-3">
            <h1 class="text-2xl font-bold">Inventory</h1>
            <span
                class="inline-flex items-center gap-1 rounded-full border border-yellow-500/40 bg-yellow-500/10 px-2 py-0.5 text-xs font-semibold text-yellow-300"
                :title="player.gold.toString()"
            >
                <span aria-hidden="true">🪙</span>
                {{ formatNumber(player.gold) }} Gold
            </span>
        </div>

        <!-- Main Content: Inventory Grid + Character Section -->
        <div class="flex min-h-0 flex-1 gap-4">
            <!-- Inventory Grid -->
            <div class="flex flex-1 flex-col rounded-lg border border-sidebar-border/70 bg-card">
                <div class="flex items-center justify-between px-4 py-3" @click="inventoryExpanded = !inventoryExpanded">
                    <h2 class="text-sm font-semibold">
                        Items
                        <span class="ml-1 text-xs font-normal text-muted-foreground">({{ inventory.length }})</span>
                    </h2>
                    <ChevronUp v-if="inventoryExpanded" class="h-4 w-4 text-muted-foreground" />
                    <ChevronDown v-else class="h-4 w-4 text-muted-foreground" />
                </div>

                <div v-if="inventoryExpanded" class="inventory-scrollbox flex-1 overflow-y-auto p-3">
                    <div
                        v-if="inventory.length"
                        class="grid gap-1.5"
                        style="grid-template-columns: repeat(auto-fill, minmax(72px, 1fr))"
                    >
                        <div
                            v-for="item in inventory"
                            :key="item.inventory_id"
                            class="relative flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 bg-muted/40 p-1.5 text-center transition-all hover:bg-muted/70"
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
                        Visit the Shop!
                    </p>
                </div>
            </div>

            <!-- Character & Stats Section -->
            <div class="flex w-80 flex-shrink-0 flex-col gap-4">
                <!-- Equipment Slots -->
                <div class="rounded-lg border border-sidebar-border/70 bg-card p-4">
                    <p class="mb-3 text-sm font-semibold">Character</p>

                    <div class="space-y-2">
                        <div
                            v-for="(slot, slotKey) in ['helmet', 'armor', 'pickaxe']"
                            :key="slotKey"
                            class="flex items-center justify-between rounded-md border border-slate-700/50 bg-slate-900/30 p-2"
                        >
                            <div class="flex items-center gap-2">
                                <span class="text-lg">{{ SLOT_ICONS[slot] || '?' }}</span>
                                <span class="text-xs font-semibold text-slate-300">{{ SLOT_LABELS[slot] }}</span>
                            </div>
                            <div class="text-right">
                                <p v-if="equipment[slot]" class="text-xs font-bold text-slate-200">
                                    {{ equipment[slot]?.name }}
                                </p>
                                <p v-else class="text-xs text-slate-500">—</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Stats -->
                <div class="rounded-lg border border-sidebar-border/70 bg-card p-4">
                    <p class="mb-3 text-sm font-semibold">Total Stats</p>

                    <div class="space-y-2">
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-slate-400">Mining Power</span>
                            <span class="font-mono font-bold text-slate-200">{{ totalMiningPower }}</span>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-slate-400">Luck</span>
                            <span class="font-mono font-bold text-slate-200">{{ totalMiningLuck }}</span>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-slate-400">Stamina Regen</span>
                            <span class="font-mono font-bold text-slate-200">{{ totalStaminaRegen.toFixed(1) }}/s</span>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-slate-400">Defense</span>
                            <span class="font-mono font-bold text-slate-200">{{ totalDefense }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tooltip -->
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
</template>

<style scoped>
.inventory-scrollbox {
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
</style>
