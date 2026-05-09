<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { forge } from '@/routes';
import OreSelector from '@/components/Forge/OreSelector.vue';
import SmellingStage from '@/components/Forge/SmellingStage.vue';
import SmithingStage from '@/components/Forge/SmithingStage.vue';
import QuenchingStage from '@/components/Forge/QuenchingStage.vue';
import ItemCrafted from '@/components/Forge/ItemCrafted.vue';
import ForgeProgress from '@/components/Forge/ForgeProgress.vue';

interface OreInput {
    ore_type_id: number;
    quantity: number;
}

interface InventoryItem {
    id: number;
    name: string;
    quantity: number;
}

interface ForgeCraftedItem {
    id: string;
    name: string;
    target_slot: string;
    forge_grade: number;
    hp_bonus: number;
    attack_bonus: number;
    defense_bonus: number;
    mining_speed_bonus: number;
    attack_speed_bonus: number;
    dodge_bonus: number;
    final_stats: Record<string, number>;
}

const props = defineProps<{
    inventory: InventoryItem[];
}>();

const hasNoOres = computed(() => props.inventory.length === 0);

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Forge', href: forge() }],
    },
});

// State machine: 'selection' | 'smelting' | 'smithing' | 'quenching' | 'result'
const stage = ref<'selection' | 'smelting' | 'smithing' | 'quenching' | 'result'>('selection');

// Forge session data
const forgeSessionId = ref<string>('');
const selectedOres = ref<OreInput[]>([]);
const targetSlot = ref<string>('');

// Collected scores from mini-games
const smeltingScore = ref(0);
const smithingScore = ref(0);
const quenchScore = ref(0);

// Result data
const resultItem = ref<ForgeCraftedItem | null>(null);
const resultGrade = ref(0);
const resultCombinedScore = ref(0);
const itemName = ref('');

const currentStageLabel = computed(() => {
    const labels = {
        selection: 'Ore Selection',
        smelting: 'Smelting',
        smithing: 'Smithing',
        quenching: 'Quenching',
        result: 'Item Crafted',
    };
    return labels[stage.value];
});

// Handle ore selection completion
function onOreSelectionComplete(
    sessionId: string,
    ores: OreInput[],
    slot: string
) {
    forgeSessionId.value = sessionId;
    selectedOres.value = ores;
    targetSlot.value = slot;
    stage.value = 'smelting';
}

// Handle smelting stage completion
function onSmeltingComplete(score: number) {
    smeltingScore.value = score;
    stage.value = 'smithing';
}

// Handle smithing stage completion
function onSmithingComplete(score: number) {
    smithingScore.value = score;
    stage.value = 'quenching';
}

// Handle quenching stage completion
function onQuenchingComplete(score: number) {
    quenchScore.value = score;
    stage.value = 'result';
}

// Handle item crafted
function onItemCrafted(
    item: ForgeCraftedItem,
    grade: number,
    combinedScore: number,
    name: string
) {
    resultItem.value = item;
    resultGrade.value = grade;
    resultCombinedScore.value = combinedScore;
    itemName.value = name;
    stage.value = 'result';
}

// Handle completion/return to selection
function onReturnToSelection() {
    stage.value = 'selection';
    forgeSessionId.value = '';
    selectedOres.value = [];
    targetSlot.value = '';
    smeltingScore.value = 0;
    smithingScore.value = 0;
    quenchScore.value = 0;
    resultItem.value = null;
    resultGrade.value = 0;
    resultCombinedScore.value = 0;
    itemName.value = '';
}
</script>

<template>
    <Head title="Forge" />

    <div class="min-h-screen bg-slate-950">
        <div class="mx-auto max-w-4xl px-4 py-8">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-4xl font-bold text-amber-400">Forge Engine</h1>
                <p class="mt-2 text-slate-400">
                    Combine ores to craft powerful equipment. Three stages await: Smelting, Smithing, and Quenching.
                </p>
            </div>

            <!-- Progress Tracker -->
            <ForgeProgress :current-stage="stage" :smelting-score="smeltingScore" :smithing-score="smithingScore" :quench-score="quenchScore" />

            <!-- Content Area -->
            <div class="mt-8">
                <!-- Ore Selection -->
                <div
                    v-if="stage === 'selection' && hasNoOres"
                    class="rounded-lg border border-amber-500/40 bg-amber-500/10 p-6"
                >
                    <h2 class="text-lg font-semibold text-amber-300">No ores available</h2>
                    <p class="mt-2 text-sm text-amber-100/90">
                        You need at least 3 ores to forge. Mine or add ores with the developer command before starting a forge session.
                    </p>
                </div>

                <OreSelector
                    v-else-if="stage === 'selection'"
                    :inventory="inventory"
                    @selection-complete="onOreSelectionComplete"
                />

                <!-- Smelting Stage -->
                <SmellingStage
                    v-else-if="stage === 'smelting'"
                    :selected-ores="selectedOres"
                    @complete="onSmeltingComplete"
                />

                <!-- Smithing Stage -->
                <SmithingStage
                    v-else-if="stage === 'smithing'"
                    :selected-ores="selectedOres"
                    @complete="onSmithingComplete"
                />

                <!-- Quenching Stage -->
                <QuenchingStage
                    v-else-if="stage === 'quenching'"
                    :selected-ores="selectedOres"
                    @complete="onQuenchingComplete"
                />

                <!-- Item Crafted Result -->
                <ItemCrafted
                    v-else-if="stage === 'result' && resultItem"
                    :forge-session-id="forgeSessionId"
                    :item="resultItem"
                    :grade="resultGrade"
                    :combined-score="resultCombinedScore"
                    :smelting-score="smeltingScore"
                    :smithing-score="smithingScore"
                    :quench-score="quenchScore"
                    @item-crafted="onItemCrafted"
                    @return-to-selection="onReturnToSelection"
                />
            </div>
        </div>
    </div>
</template>
