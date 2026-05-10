<script setup lang="ts">
interface Props {
    currentStage: 'selection' | 'smelting' | 'smithing' | 'quenching' | 'result';
    smeltingScore?: number;
    smithingScore?: number;
    quenchScore?: number;
}

withDefaults(defineProps<Props>(), {
    smeltingScore: 0,
    smithingScore: 0,
    quenchScore: 0,
});

const stages = [
    { id: 'selection', label: 'Selection', emoji: '🪣' },
    { id: 'smelting', label: 'Smelting', emoji: '🔥' },
    { id: 'smithing', label: 'Smithing', emoji: '⚒️' },
    { id: 'quenching', label: 'Quenching', emoji: '💧' },
];

const stageOrder = ['selection', 'smelting', 'smithing', 'quenching', 'result'];
</script>

<template>
    <div class="space-y-6 rounded-lg p-6 medieval-card border-2 border-amber-800/50">
        <!-- Header -->
        <div class="flex items-center gap-2 mb-4">
            <span class="text-2xl">📋</span>
            <h2 class="text-lg font-bold text-amber-300" style="font-family: 'Cinzel', serif;">Forge Progress</h2>
        </div>

        <!-- Stage Progress Indicators -->
        <div class="flex items-center justify-between gap-1">
            <template v-for="(stage, index) in stages" :key="stage.id">
                <!-- Stage Indicator -->
                <div
                    :class="[
                        'flex h-12 w-12 items-center justify-center rounded-full transition relative border-2',
                        stageOrder.indexOf(currentStage) >= index
                            ? 'bg-gradient-to-br from-amber-500 to-orange-600 text-white border-amber-300 shadow-lg'
                            : 'bg-stone-800 text-stone-500 border-stone-700',
                    ]"
                >
                    <span class="text-lg">{{ stage.emoji }}</span>
                    <div
                        v-if="stageOrder.indexOf(currentStage) >= index"
                        class="absolute inset-0 rounded-full animate-pulse border-2 border-amber-400/30"
                    />
                </div>

                <!-- Connector Line -->
                <div
                    v-if="index < stages.length - 1"
                    :class="[
                        'flex-1 h-1 transition',
                        stageOrder.indexOf(currentStage) > index
                            ? 'bg-gradient-to-r from-amber-500 to-orange-600 shadow-lg'
                            : 'bg-stone-700',
                    ]"
                />
            </template>
        </div>

        <!-- Stage Labels and Scores -->
        <div class="grid grid-cols-4 gap-3">
            <div
                v-for="(stage, index) in stages"
                :key="`label-${stage.id}`"
                :class="[
                    'rounded-lg px-3 py-3 transition text-center text-sm font-semibold border-2',
                    stageOrder.indexOf(currentStage) >= index
                        ? 'bg-gradient-to-b from-amber-600/30 to-orange-600/20 text-amber-300 border-amber-700'
                        : 'bg-stone-900/50 text-stone-500 border-stone-700',
                ]"
            >
                <p class="font-medium">{{ stage.label }}</p>
                <p v-if="stage.id === 'smelting' && smeltingScore > 0" class="text-xs text-amber-200">
                    {{ smeltingScore }}%
                </p>
                <p v-else-if="stage.id === 'smithing' && smithingScore > 0" class="text-xs text-amber-200">
                    {{ smithingScore }}%
                </p>
                <p v-else-if="stage.id === 'quenching' && quenchScore > 0" class="text-xs text-amber-200">
                    {{ quenchScore }}%
                </p>
            </div>
        </div>
    </div>
</template>
