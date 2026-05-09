<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { AlertCircle, Hammer, Zap } from 'lucide-vue-next';

interface OreInput {
    ore_type_id: number;
    quantity: number;
}

interface Circle {
    id: number;
    hitTime: number | null; // null = not hit yet
    hitAccuracy: 'perfect' | 'good' | 'miss' | null;
}

const props = defineProps<{
    selectedOres: OreInput[];
}>();

const emit = defineEmits<{
    complete: [score: number];
}>();

// Game state
const gameTime = ref(0);
const gameActive = ref(true);
const gameStartTime = ref(0);
const gameEndTime = 10000; // 10 seconds

const circles = ref<Circle[]>([]);
const nextCircleId = ref(0);
const circleSpawnRate = ref(800); // ms between circle spawns

// Scoring
const perfectHits = ref(0);
const goodHits = ref(0);
const missedHits = ref(0);

const score = computed(() => {
    const totalHits = perfectHits.value + goodHits.value;
    if (totalHits === 0) return 0;
    
    const perfectPoints = perfectHits.value * 100;
    const goodPoints = goodHits.value * 50;
    const totalPossible = (perfectHits.value + goodHits.value + missedHits.value) * 100;
    
    return Math.round((perfectPoints + goodPoints) / totalPossible * 100);
});

const accuracy = computed(() => {
    const total = perfectHits.value + goodHits.value + missedHits.value;
    if (total === 0) return 0;
    return Math.round(((perfectHits.value + goodHits.value) / total) * 100);
});

const timePercent = computed(() => Math.min(100, (gameTime.value / gameEndTime) * 100));

// Circle appearance/animation
const activeCircles = computed(() => 
    circles.value.filter(c => c.hitTime === null)
);

// Hit detection windows (in ms)
const perfectWindow = 100; // ±100ms
const goodWindow = 200; // ±200ms

function spawnCircle() {
    if (!gameActive.value) return;

    circles.value.push({
        id: nextCircleId.value++,
        hitTime: null,
        hitAccuracy: null,
    });
}

function hitCircle(circleId: number) {
    const circle = circles.value.find(c => c.id === circleId);
    if (!circle || circle.hitTime !== null || !gameActive.value) return;

    // Circle appears at gameTime + 1000ms (1 second to react)
    const appearTime = circle.id * circleSpawnRate.value;
    const timeSinceAppear = gameTime.value - appearTime;

    // Determine accuracy
    if (Math.abs(timeSinceAppear) <= perfectWindow) {
        circle.hitAccuracy = 'perfect';
        perfectHits.value++;
    } else if (Math.abs(timeSinceAppear) <= goodWindow) {
        circle.hitAccuracy = 'good';
        goodHits.value++;
    } else {
        circle.hitAccuracy = 'miss';
        missedHits.value++;
    }

    circle.hitTime = gameTime.value;
}

function completeStage() {
    gameActive.value = false;
    emit('complete', score.value);
}

onMounted(() => {
    gameStartTime.value = Date.now();
    
    let lastSpawnTime = 0;
    const gameInterval = setInterval(() => {
        if (!gameActive.value) {
            clearInterval(gameInterval);
            return;
        }

        const now = Date.now();
        gameTime.value = now - gameStartTime.value;

        // Spawn circles at intervals
        if (gameTime.value - lastSpawnTime >= circleSpawnRate.value && gameTime.value < gameEndTime - 500) {
            spawnCircle();
            lastSpawnTime = gameTime.value;
        }

        // End game when time's up
        if (gameTime.value >= gameEndTime) {
            gameActive.value = false;
            clearInterval(gameInterval);
        }
    }, 16);
});
</script>

<template>
    <div class="space-y-6 rounded-lg border border-slate-700 bg-slate-900 p-6">
        <!-- Header -->
        <div>
            <div class="mb-2 flex items-center gap-2">
                <Hammer class="h-5 w-5 text-blue-500" />
                <h2 class="text-2xl font-bold text-slate-200">Smithing Stage</h2>
            </div>
            <p class="text-sm text-slate-400">
                Strike the rhythm circles at the perfect time. Click or press SPACE when circles appear in the center.
            </p>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-4 gap-4">
            <div class="rounded bg-slate-800 p-3">
                <p class="text-xs text-slate-500">SCORE</p>
                <p class="text-2xl font-bold text-blue-400">{{ score }}%</p>
            </div>
            <div class="rounded bg-slate-800 p-3">
                <p class="text-xs text-slate-500">ACCURACY</p>
                <p class="text-2xl font-bold text-green-400">{{ accuracy }}%</p>
            </div>
            <div class="rounded bg-slate-800 p-3">
                <p class="text-xs text-slate-500">PERFECT / GOOD</p>
                <p class="text-2xl font-bold text-amber-400">{{ perfectHits }} / {{ goodHits }}</p>
            </div>
            <div class="rounded bg-slate-800 p-3">
                <p class="text-xs text-slate-500">MISSED</p>
                <p class="text-2xl font-bold text-red-400">{{ missedHits }}</p>
            </div>
        </div>

        <!-- Progress Bar -->
        <div class="space-y-2">
            <p class="text-xs text-slate-500">GAME PROGRESS</p>
            <div class="h-2 w-full rounded-full bg-slate-800">
                <div class="h-full rounded-full bg-blue-500 transition-all duration-100" :style="{ width: timePercent + '%' }" />
            </div>
        </div>

        <!-- Rhythm Zone -->
        <div class="space-y-3">
            <p class="text-sm font-semibold text-slate-200">Rhythm Zone</p>
            <div class="relative h-80 rounded border border-slate-600 bg-gradient-radial from-slate-800 to-slate-900 overflow-hidden">
                <!-- Center target zone -->
                <div class="absolute inset-0 flex items-center justify-center">
                    <div class="relative h-32 w-32">
                        <!-- Perfect zone -->
                        <div class="absolute inset-0 rounded-full border-4 border-green-500/30" />
                        <!-- Good zone -->
                        <div class="absolute inset-0 rounded-full border-2 border-yellow-500/20" />
                        <!-- Center marker -->
                        <div class="absolute inset-1/2 h-4 w-4 -translate-x-1/2 -translate-y-1/2 transform rounded-full bg-slate-400/50" />
                    </div>
                </div>

                <!-- Active circles -->
                <div v-for="circle in activeCircles" :key="circle.id" class="absolute inset-0 flex items-center justify-center">
                    <div
                        class="h-16 w-16 rounded-full bg-blue-500 animate-ping opacity-75"
                        :style="{ animationDuration: `${1.5}s` }"
                    />
                </div>

                <!-- Hit circles -->
                <div v-for="circle in circles.filter(c => c.hitTime !== null)" :key="`hit-${circle.id}`" class="absolute inset-0 flex items-center justify-center pointer-events-none">
                    <div
                        :class="[
                            'h-16 w-16 rounded-full flex items-center justify-center text-sm font-bold',
                            circle.hitAccuracy === 'perfect'
                                ? 'bg-green-500 text-white'
                                : circle.hitAccuracy === 'good'
                                ? 'bg-yellow-500 text-white'
                                : 'bg-red-500 text-white',
                        ]"
                    >
                        {{ circle.hitAccuracy?.toUpperCase() }}
                    </div>
                </div>

                <!-- Game timer overlay -->
                <div class="absolute top-4 right-4 text-lg font-bold text-slate-300">
                    {{ Math.round(gameTime / 1000) }}s / 10s
                </div>
            </div>
        </div>

        <!-- Instructions -->
        <div class="rounded border border-blue-600/50 bg-blue-900/20 p-3">
            <div class="flex items-start gap-2">
                <AlertCircle class="mt-0.5 h-4 w-4 flex-shrink-0 text-blue-400" />
                <div class="text-xs text-blue-200">
                    <p class="font-semibold mb-1">Hit circles at the right time:</p>
                    <p>🟢 Perfect (±100ms) = 100 pts | 🟡 Good (±200ms) = 50 pts | ❌ Missed = 0 pts</p>
                </div>
            </div>
        </div>

        <!-- Complete Button -->
        <button
            @click="completeStage"
            :disabled="!gameActive && (perfectHits + goodHits) === 0"
            :class="[
                'w-full rounded px-4 py-3 font-semibold transition',
                gameActive
                    ? 'bg-slate-700 text-slate-400 cursor-not-allowed'
                    : 'bg-blue-500 text-white hover:bg-blue-600',
            ]"
        >
            {{ gameActive ? `Smithing... (${Math.round(gameTime / 1000)}/10s)` : `Proceed to Quenching (Score: ${score}%)` }}
        </button>
    </div>
</template>

<style scoped>
@keyframes ping {
    75%, 100% {
        transform: scale(2);
        opacity: 0;
    }
}

.animate-ping {
    animation: ping 1.5s cubic-bezier(0, 0, 0.2, 1) infinite;
}
</style>
