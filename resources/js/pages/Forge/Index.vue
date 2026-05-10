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
    final_stats: Record<string, number>;
}

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Forge', href: forge() }],
    },
});

const props = defineProps<{
    inventory: InventoryItem[];
}>();

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

function onOreSelectionComplete(sessionId: string, ores: OreInput[], slot: string) {
    const totalSelectedOres = ores.reduce((sum, ore) => sum + ore.quantity, 0);
    if (totalSelectedOres !== 3) return;
    forgeSessionId.value = sessionId;
    selectedOres.value = ores;
    targetSlot.value = slot;
    stage.value = 'smelting';
}

function onSmeltingComplete(score: number) {
    smeltingScore.value = score;
    stage.value = 'smithing';
}

function onSmithingComplete(score: number) {
    smithingScore.value = score;
    stage.value = 'quenching';
}

async function onQuenchingComplete(score: number) {
    quenchScore.value = score;
    completionError.value = '';
    if (!forgeSessionId.value) {
        completionError.value = 'Forge session is missing. Please restart forging.';
        return;
    }
    if (isCompletingForge.value) return;
    isCompletingForge.value = true;
    try {
        const response = await axios.post(forgeComplete.url(), {
            forge_session_id: forgeSessionId.value,
            smelting_score: smeltingScore.value,
            smithing_score: smithingScore.value,
            quench_score: quenchScore.value,
            item_name: itemName.value || `Forged ${targetSlot.value}`,
        }, { withCredentials: true, withXSRFToken: true });
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
    if (!resultItem.value) return;
    stage.value = 'result';
}

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

    <div class="min-h-screen bg-gradient-to-br from-stone-900 via-stone-950 to-stone-900 p-4 sm:p-6">
        <!-- Medieval Forge Header -->
        <div class="mb-12 forge-border rounded-lg p-6 forge-corner">
            <div class="flex items-center gap-4">
                <div class="text-5xl">🔨</div>
                <div>
                    <h1 class="font-bold text-amber-400" style="font-family: 'Cinzel', serif; font-size: 2.5rem; letter-spacing: 0.05em;">The Blacksmith's Forge</h1>
                    <p class="mt-2 text-amber-100/70">
                        Combine raw ore with skill to forge legendary equipment. Each stage tests your craftsmanship.
                    </p>
                </div>
            </div>
        </div>

        <div class="mb-8">
            <ForgeProgress :current-stage="stage" :smelting-score="smeltingScore" :smithing-score="smithingScore" :quench-score="quenchScore" />
        </div>

        <!-- Main Content -->
        <div class="medieval-card rounded-lg border-2 border-amber-800/50 p-6">
            <!-- Ore Selection Stage -->
            <OreSelector
                v-if="stage === 'selection'"
                :inventory="inventory"
                @selection-complete="onOreSelectionComplete"
            />

            <!-- Smelting Stage -->
            <SmellingStage
                v-if="stage === 'smelting'"
                @complete="onSmeltingComplete"
            />

            <!-- Smithing Stage -->
            <SmithingStage
                v-if="stage === 'smithing'"
                @complete="onSmithingComplete"
            />

            <!-- Quenching Stage -->
            <QuenchingStage
                v-if="stage === 'quenching'"
                @complete="onQuenchingComplete"
            />

            <!-- Item Crafted Result -->
            <ItemCrafted
                v-if="stage === 'result' && resultItem"
                :item="resultItem"
                :grade="resultGrade"
                :combined-score="resultCombinedScore"
                :item-name="itemName"
                @return-to-selection="onReturnToSelection"
            />

            <!-- Error Message -->
            <div
                v-if="completionError"
                class="rounded-lg border-2 border-red-700 bg-red-900/30 p-4 text-sm text-red-300 font-medium"
            >
                ❌ {{ completionError }}
            </div>

            <!-- Loading State -->
            <div
                v-if="isCompletingForge && stage === 'quenching'"
                class="rounded-lg border-2 border-amber-700 bg-amber-900/30 p-4 text-center text-amber-300 font-semibold"
            >
                ⚔️ Completing your forge...
            </div>
        </div>
    </div>
</template>
