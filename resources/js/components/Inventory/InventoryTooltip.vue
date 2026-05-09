<script setup lang="ts">
import { computed } from 'vue';

export interface InventoryItemData {
    inventory_id: number;
    id: number | string;
    name: string;
    quantity: number;
    holdable_type: 'ore' | 'item';
    // Ore-specific
    rarity?: string;
    // Item-specific
    forge_grade?: number;
    target_slot?: string;
    elemental_affinity?: string;
    final_stats?: Record<string, number>;
}

const props = defineProps<{
    item: InventoryItemData;
    /** Right edge of the triggering item tile (client coords) */
    anchorX: number;
    /** Top edge of the triggering item tile (client coords) */
    anchorY: number;
    /** Left edge of the triggering item tile — used when flipping to the left */
    anchorLeft: number;
}>();

const emit = defineEmits<{
    equip: [inventoryId: number];
    sell: [inventoryId: number];
}>();

const TOOLTIP_W = 264;
const TOOLTIP_H = 300;
const MARGIN = 8;
const GAP = 4;

const gradeLabels: Record<number, string> = {
    1: 'I', 2: 'II', 3: 'III', 4: 'IV', 5: 'V',
    6: 'VI', 7: 'VII', 8: 'VIII', 9: 'IX', 10: 'X',
};

const gradeDescriptions: Record<number, string> = {
    1: 'Common', 2: 'Uncommon', 3: 'Rare', 4: 'Rare',
    5: 'Epic', 6: 'Epic', 7: 'Legendary', 8: 'Legendary',
    9: 'Mythic', 10: 'Divine',
};

const rarityColorMap: Record<string, string> = {
    common: 'text-slate-400 border-slate-500',
    uncommon: 'text-green-400 border-green-600',
    rare: 'text-blue-400 border-blue-600',
    epic: 'text-purple-400 border-purple-600',
    legendary: 'text-orange-400 border-orange-500',
    mythical: 'text-yellow-300 border-yellow-400',
};

const gradeRarityColor: Record<number, string> = {
    1: 'text-slate-400 border-slate-500',
    2: 'text-green-400 border-green-600',
    3: 'text-blue-400 border-blue-600',
    4: 'text-blue-400 border-blue-600',
    5: 'text-purple-400 border-purple-600',
    6: 'text-purple-400 border-purple-600',
    7: 'text-orange-400 border-orange-500',
    8: 'text-orange-400 border-orange-500',
    9: 'text-yellow-300 border-yellow-400',
    10: 'text-yellow-300 border-yellow-400',
};

const rarityLabel = computed(() => {
    if (props.item.holdable_type === 'item' && props.item.forge_grade !== undefined) {
        return gradeDescriptions[props.item.forge_grade] ?? 'Common';
    }
    return props.item.rarity
        ? props.item.rarity.charAt(0).toUpperCase() + props.item.rarity.slice(1)
        : 'Common';
});

const colorClass = computed(() => {
    if (props.item.holdable_type === 'item' && props.item.forge_grade !== undefined) {
        return gradeRarityColor[props.item.forge_grade] ?? 'text-slate-400 border-slate-500';
    }
    const key = (props.item.rarity ?? 'common').toLowerCase();
    return rarityColorMap[key] ?? 'text-slate-400 border-slate-500';
});

const borderClass = computed(() =>
    colorClass.value.split(' ').filter((c) => c.startsWith('border')).join(' '),
);
const textClass = computed(() =>
    colorClass.value.split(' ').filter((c) => c.startsWith('text')).join(' '),
);

const statLabels: Record<string, string> = {
    hp_bonus: 'HP',
    attack_bonus: 'ATK',
    defense_bonus: 'DEF',
    mining_speed_bonus: 'Mine Spd',
    mining_dmg_bonus: 'Mine DMG',
    luck_bonus: 'Luck',
    attack_speed_bonus: 'ATK Spd',
    dodge_bonus: 'Dodge',
};

const visibleStats = computed(() => {
    if (!props.item.final_stats) return [];
    return Object.entries(props.item.final_stats)
        .filter(([, value]) => value > 0)
        .map(([key, value]) => ({ key, label: statLabels[key] ?? key, value }));
});

const isEquippable = computed(() => props.item.holdable_type === 'item');

const tooltipStyle = computed(() => {
    const vw = window.innerWidth;
    const vh = window.innerHeight;
    let left = props.anchorX + GAP;
    if (left + TOOLTIP_W > vw - MARGIN) {
        left = props.anchorLeft - TOOLTIP_W - GAP;
    }
    let top = props.anchorY;
    if (top + TOOLTIP_H > vh - MARGIN) {
        top = Math.max(MARGIN, vh - TOOLTIP_H - MARGIN);
    }
    left = Math.max(MARGIN, Math.min(left, vw - TOOLTIP_W - MARGIN));
    top = Math.max(MARGIN, top);
    return { left: `${left}px`, top: `${top}px`, width: `${TOOLTIP_W}px` };
});
</script>

<template>
    <div
        class="fixed z-50 rounded-xl border-2 bg-slate-900/95 p-3 shadow-2xl backdrop-blur-sm"
        :class="borderClass"
        :style="tooltipStyle"
    >
        <div class="mb-2 border-b pb-2" :class="borderClass">
            <p class="text-sm font-bold leading-tight text-white">{{ item.name }}</p>
            <div class="mt-1 flex items-center gap-2">
                <span
                    v-if="item.holdable_type === 'item' && item.forge_grade !== undefined"
                    class="rounded bg-slate-800 px-1.5 py-0.5 text-[10px] font-bold uppercase"
                    :class="textClass"
                >
                    Grade {{ gradeLabels[item.forge_grade] }}
                </span>
                <span class="text-xs font-semibold" :class="textClass">
                    {{ rarityLabel }}
                </span>
            </div>
            <p
                v-if="item.holdable_type === 'item' && item.target_slot"
                class="mt-0.5 text-[10px] capitalize text-slate-400"
            >
                {{ item.target_slot }}
                <span v-if="item.elemental_affinity" class="ml-1 text-slate-500">
                    [{{ item.elemental_affinity }}]
                </span>
            </p>
        </div>
        <div v-if="visibleStats.length" class="mb-2 space-y-0.5">
            <div
                v-for="stat in visibleStats"
                :key="stat.key"
                class="flex items-center justify-between text-xs"
            >
                <span class="text-slate-400">{{ stat.label }}</span>
                <span class="font-mono font-bold text-white">+{{ stat.value }}</span>
            </div>
        </div>
        <div v-if="item.holdable_type === 'ore'" class="mb-2 text-xs text-slate-400">
            Quantity: <span class="font-bold text-white">{{ item.quantity }}</span>
        </div>
        <div class="flex gap-1.5">
            <button
                v-if="isEquippable"
                class="flex-1 rounded bg-blue-600 px-2 py-1.5 text-xs font-bold text-white transition hover:bg-blue-500 active:bg-blue-700"
                @click.stop="emit('equip', item.inventory_id)"
            >
                Złóż (Equip)
            </button>
            <button
                class="flex-1 rounded bg-red-800 px-2 py-1.5 text-xs font-bold text-white transition hover:bg-red-700 active:bg-red-900"
                @click.stop="emit('sell', item.inventory_id)"
            >
                Sprzedaj (Sell)
            </button>
        </div>
    </div>
</template>
