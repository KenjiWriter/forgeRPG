<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import axios from 'axios';
import { forge } from '@/routes';
import { complete as forgeComplete } from '@/routes/forge';
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
    rarity?: string;
    image_path?: string;
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
const completionError = ref('');
const isCompletingForge = ref(false);

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
    const totalSelectedOres = ores.reduce((sum, ore) => sum + ore.quantity, 0);
    if (totalSelectedOres !== 3) {
        return;
    }

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
async function onQuenchingComplete(score: number) {
    quenchScore.value = score;
    completionError.value = '';

    if (!forgeSessionId.value) {
        completionError.value = 'Forge session is missing. Please restart forging.';
        return;
    }

    if (isCompletingForge.value) {
        return;
    }

    isCompletingForge.value = true;

    try {
        const response = await axios.post(forgeComplete.url(), {
            forge_session_id: forgeSessionId.value,
            smelting_score: smeltingScore.value,
            smithing_score: smithingScore.value,
            quench_score: quenchScore.value,
            item_name: itemName.value || `Forged ${targetSlot.value}`,
        }, {
            withCredentials: true,
            withXSRFToken: true,
        });

        const data = response.data as {
            item: ForgeCraftedItem;
            grade: number;
            combined_score: number;
        };

        resultItem.value = data.item;
        resultGrade.value = data.grade;
        resultCombinedScore.value = data.combined_score;
        stage.value = 'result';
    } catch (error: unknown) {
        if (axios.isAxiosError(error)) {
            const message = error.response?.data?.message as string | undefined;
            completionError.value = message ?? 'Failed to complete forge. Please try again.';
        } else {
            completionError.value = 'Failed to complete forge. Please try again.';
        }
    } finally {
        isCompletingForge.value = false;
    }

    if (!resultItem.value) {
        return;
    }

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
    completionError.value = '';
    isCompletingForge.value = false;
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
                <OreSelector
                    v-if="stage === 'selection'"
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

                <div
                    v-if="stage === 'quenching' && completionError"
                    class="mt-4 rounded border border-red-600/50 bg-red-900/20 p-3 text-sm text-red-400"
                >
                    {{ completionError }}
                </div>

                <div
                    v-if="stage === 'quenching' && isCompletingForge"
                    class="mt-4 rounded border border-cyan-600/40 bg-cyan-900/20 p-3 text-sm text-cyan-300"
                >
                    Forging final item...
                </div>

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
                    @return-to-selection="onReturnToSelection"
                />
            </div>
        </div>
    </div>
</template>
