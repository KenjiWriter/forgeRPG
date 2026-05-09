<script setup lang="ts">
import { Flame, Hammer, Droplet, CheckCircle } from 'lucide-vue-next';

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
    { id: 'smelting', label: 'Smelting', icon: Flame },
    { id: 'smithing', label: 'Smithing', icon: Hammer },
    { id: 'quenching', label: 'Quenching', icon: Droplet },
    { id: 'result', label: 'Complete', icon: CheckCircle },
];
</script>

<template>
    <div class="space-y-4">
        <!-- Stage Progress Dots -->
        <div class="flex items-center justify-between gap-2">
            <div v-for="(stage, index) in stages" :key="stage.id" class="flex flex-1 items-center gap-2">
                <!-- Stage Indicator -->
                <div
                    :class="[
                        'flex h-10 w-10 items-center justify-center rounded-full transition',
                        ['smelting', 'smithing', 'quenching', 'result'].indexOf(currentStage) >= index
                            ? 'bg-amber-500 text-white'
                            : 'bg-slate-700 text-slate-400',
                    ]"
                >
                    <component :is="stage.icon" class="h-5 w-5" />
                </div>

                <!-- Connector Line -->
                <div
                    v-if="index < stages.length - 1"
                    :class="[
                        'flex-1 h-0.5 transition',
                        ['smelting', 'smithing', 'quenching', 'result'].indexOf(currentStage) > index
                            ? 'bg-amber-500'
                            : 'bg-slate-700',
                    ]"
                />
            </div>
        </div>

        <!-- Stage Labels and Scores -->
        <div class="grid grid-cols-4 gap-2 text-center text-sm">
            <div
                v-for="(stage, index) in stages"
                :key="`label-${stage.id}`"
                :class="[
                    'rounded px-2 py-1 transition',
                    ['smelting', 'smithing', 'quenching', 'result'].indexOf(currentStage) >= index
                        ? 'bg-amber-500/20 text-amber-400'
                        : 'bg-slate-800 text-slate-500',
                ]"
            >
                <p class="font-medium">{{ stage.label }}</p>
                <p v-if="stage.id === 'smelting' && smeltingScore > 0" class="text-xs text-amber-300">
                    {{ smeltingScore }}%
                </p>
                <p v-else-if="stage.id === 'smithing' && smithingScore > 0" class="text-xs text-amber-300">
                    {{ smithingScore }}%
                </p>
                <p v-else-if="stage.id === 'quenching' && quenchScore > 0" class="text-xs text-amber-300">
                    {{ quenchScore }}%
                </p>
            </div>
        </div>
    </div>
</template>
