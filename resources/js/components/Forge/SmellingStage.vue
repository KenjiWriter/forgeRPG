<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { AlertCircle, Flame } from 'lucide-vue-next';

interface OreInput {
    ore_type_id: number;
    quantity: number;
}

const props = defineProps<{
    selectedOres: OreInput[];
}>();

const emit = defineEmits<{
    complete: [score: number];
}>();

// Bellows mechanics
const isDragging = ref(false);
const bellowsPosition = ref(50); // 0-100 (left to right)
const timeInSweetSpot = ref(0); // milliseconds
const gameTime = ref(0);
const gameActive = ref(true);
const gameStartTime = ref(0);

// Score calculation
const sweetSpotStart = 30;
const sweetSpotEnd = 70;

const isInSweetSpot = computed(() => bellowsPosition.value >= sweetSpotStart && bellowsPosition.value <= sweetSpotEnd);

const timePercent = computed(() => Math.min(100, (gameTime.value / 10000) * 100));

const score = computed(() => {
    // Score is based on time spent in sweet spot
    // Max 10 seconds = 10000ms → 100%
    return Math.min(100, Math.round((timeInSweetSpot.value / 10000) * 100));
});

// Bellows UI helpers
const bellowsVisualPosition = computed(() => `${bellowsPosition.value}%`);
const bellowsHandleColor = computed(() =>
    isInSweetSpot.value ? 'bg-green-500' : 'bg-red-500'
);

function onMouseDown() {
    isDragging.value = true;
}

function onMouseMove(e: MouseEvent) {
    if (!isDragging.value || !gameActive.value) return;

    const container = (e.currentTarget as HTMLElement).querySelector('[data-bellows-track]') as HTMLElement;
    if (!container) return;

    const rect = container.getBoundingClientRect();
    const x = Math.max(0, Math.min(e.clientX - rect.left, rect.width));
    const percent = (x / rect.width) * 100;

    bellowsPosition.value = Math.round(percent);
}

function onMouseUp() {
    isDragging.value = false;
}

function completeStage() {
    gameActive.value = false;
    emit('complete', score.value);
}

onMounted(() => {
    gameStartTime.value = Date.now();

    const interval = setInterval(() => {
        if (!gameActive.value) {
            clearInterval(interval);
            return;
        }

        const now = Date.now();
        gameTime.value = now - gameStartTime.value;

        if (isInSweetSpot.value) {
            timeInSweetSpot.value += 16; // ~60fps
        }

        if (gameTime.value >= 10000) {
            gameActive.value = false;
            clearInterval(interval);
        }
    }, 16);
});
</script>

<template>
    <div class="space-y-6 rounded-lg border border-slate-700 bg-slate-900 p-6">
        <!-- Header -->
        <div>
            <div class="mb-2 flex items-center gap-2">
                <Flame class="h-5 w-5 text-orange-500" />
                <h2 class="text-2xl font-bold text-slate-200">Smelting Stage</h2>
            </div>
            <p class="text-sm text-slate-400">
                Work the bellows to heat the ores. Keep the bellows in the sweet spot (green zone) to maximize heat efficiency.
            </p>
        </div>

        <!-- Info -->
        <div class="grid grid-cols-3 gap-4">
            <div class="rounded bg-slate-800 p-3">
                <p class="text-xs text-slate-500">CURRENT SCORE</p>
                <p class="text-2xl font-bold text-orange-400">{{ score }}%</p>
            </div>
            <div class="rounded bg-slate-800 p-3">
                <p class="text-xs text-slate-500">TIME IN SWEET SPOT</p>
                <p class="text-2xl font-bold text-green-400">{{ Math.round(timeInSweetSpot / 1000) }}s</p>
            </div>
            <div class="rounded bg-slate-800 p-3">
                <p class="text-xs text-slate-500">ELAPSED TIME</p>
                <p class="text-2xl font-bold text-slate-300">{{ Math.round(gameTime / 1000) }}s / 10s</p>
            </div>
        </div>

        <!-- Progress Bar -->
        <div class="space-y-2">
            <p class="text-xs text-slate-500">GAME PROGRESS</p>
            <div class="h-2 w-full rounded-full bg-slate-800">
                <div class="h-full rounded-full bg-orange-500 transition-all duration-100" :style="{ width: timePercent + '%' }" />
            </div>
        </div>

        <!-- Bellows Mini-game -->
        <div class="space-y-3">
            <p class="text-sm font-semibold text-slate-200">Bellows Position</p>

            <!-- Bellows Track -->
            <div
                @mousemove="onMouseMove"
                @mouseleave="onMouseUp"
                @mouseup="onMouseUp"
                class="rounded border border-slate-600 bg-slate-800 p-4"
            >
                <div
                    data-bellows-track
                    class="relative h-12 w-full rounded bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900"
                >
                    <!-- Sweet Spot Zone -->
                    <div
                        class="absolute top-0 h-full bg-green-500/10 border-l-2 border-r-2 border-green-500/50 rounded"
                        :style="{ left: sweetSpotStart + '%', right: 100 - sweetSpotEnd + '%' }"
                    >
                        <p class="h-full flex items-center justify-center text-xs font-semibold text-green-400">
                            SWEET SPOT
                        </p>
                    </div>

                    <!-- Bellows Handle -->
                    <button
                        @mousedown="onMouseDown"
                        :class="[
                            'absolute top-1/2 -translate-y-1/2 w-8 h-full rounded transition cursor-grab active:cursor-grabbing',
                            bellowsHandleColor,
                            isDragging && 'ring-2 ring-amber-400',
                        ]"
                        :style="{ left: bellowsVisualPosition }"
                    />

                    <!-- Bellows Position Text -->
                    <p class="absolute bottom-1 left-2 text-xs text-slate-500">
                        {{ Math.round(bellowsPosition) }}%
                    </p>
                </div>
            </div>

            <!-- Instructions -->
            <div class="rounded border border-blue-600/50 bg-blue-900/20 p-3">
                <div class="flex items-start gap-2">
                    <AlertCircle class="mt-0.5 h-4 w-4 flex-shrink-0 text-blue-400" />
                    <p class="text-xs text-blue-200">
                        Drag the bellows handle to the green sweet spot zone (30-70%) and keep it there as long as possible.
                        Higher time in sweet spot = higher score.
                    </p>
                </div>
            </div>
        </div>

        <!-- Complete Button -->
        <button
            @click="completeStage"
            :disabled="!gameActive && score === 0"
            :class="[
                'w-full rounded px-4 py-3 font-semibold transition',
                gameActive
                    ? 'bg-slate-700 text-slate-400 cursor-not-allowed'
                    : 'bg-amber-500 text-white hover:bg-amber-600',
            ]"
        >
            {{ gameActive ? `Smelting... (${Math.round(gameTime / 1000)}/10s)` : `Proceed to Smithing (Score: ${score}%)` }}
        </button>
    </div>
</template>

<style scoped>
button[data-bellows-track] {
    user-select: none;
}
</style>
