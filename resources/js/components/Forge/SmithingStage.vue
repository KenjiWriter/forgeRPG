<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { AlertCircle, Hammer } from 'lucide-vue-next';

interface OreInput {
    ore_type_id: number;
    quantity: number;
}

defineProps<{
    selectedOres: OreInput[];
}>();

const emit = defineEmits<{
    complete: [score: number];
}>();

interface ActiveTarget {
    id: number;
    x: number;
    y: number;
    spawnedAtMs: number;
    durationMs: number;
}

interface HitFx {
    id: number;
    x: number;
    y: number;
    tone: 'perfect' | 'good';
}

const GAME_DURATION_MS = 10000;
const CIRCLE_WINDOW_MS = 1500;
const RING_SCALE_START = 2;
const RING_SCALE_END = 1;
const PERFECT_SCALE_MIN = 0.98;
const PERFECT_SCALE_MAX = 1.06;
const GOOD_SCALE_MIN = 0.9;
const GOOD_SCALE_MAX = 1.2;

const gameTime = ref(0);
const gameActive = ref(true);
const gameFinished = ref(false);
const gameStartTime = ref(0);
const scoreValue = ref(100);

const perfectHits = ref(0);
const goodHits = ref(0);
const earlyHits = ref(0);
const missedHits = ref(0);

const activeTarget = ref<ActiveTarget | null>(null);
const hitFx = ref<HitFx[]>([]);
let nextTargetId = 1;
let nextFxId = 1;
let animationFrameId: number | null = null;
let lastFrameAt = 0;

const score = computed(() => {
    return Math.max(0, Math.min(100, Math.round(scoreValue.value)));
});

const totalAttempts = computed(() => {
    return perfectHits.value + goodHits.value + earlyHits.value + missedHits.value;
});

const accuracy = computed(() => {
    if (totalAttempts.value === 0) {
        return 0;
    }

    return Math.round(((perfectHits.value + goodHits.value) / totalAttempts.value) * 100);
});

const timePercent = computed(() => Math.min(100, (gameTime.value / GAME_DURATION_MS) * 100));

function getRingScale(target: ActiveTarget): number {
    const progress = Math.min(1, (performance.now() - target.spawnedAtMs) / target.durationMs);
    return RING_SCALE_START - (RING_SCALE_START - RING_SCALE_END) * progress;
}

function spawnNewCircle(nowMs: number): void {
    if (!gameActive.value) {
        activeTarget.value = null;
        return;
    }

    activeTarget.value = {
        id: nextTargetId++,
        x: 10 + Math.random() * 80,
        y: 10 + Math.random() * 80,
        spawnedAtMs: nowMs,
        durationMs: CIRCLE_WINDOW_MS,
    };
}

function pushHitFx(x: number, y: number, tone: 'perfect' | 'good'): void {
    const id = nextFxId++;
    hitFx.value.push({ id, x, y, tone });

    window.setTimeout(() => {
        hitFx.value = hitFx.value.filter((fx) => fx.id !== id);
    }, 260);
}

function applyPenalty(points: number): void {
    scoreValue.value = Math.max(0, scoreValue.value - points);
}

function hitActiveTarget(): void {
    if (!gameActive.value || !activeTarget.value) {
        return;
    }

    const target = activeTarget.value;
    const ringScale = getRingScale(target);

    if (ringScale >= PERFECT_SCALE_MIN && ringScale <= PERFECT_SCALE_MAX) {
        perfectHits.value++;
        scoreValue.value = Math.min(100, scoreValue.value + 1.5);
        pushHitFx(target.x, target.y, 'perfect');
    } else if (ringScale >= GOOD_SCALE_MIN && ringScale <= GOOD_SCALE_MAX) {
        goodHits.value++;
        pushHitFx(target.x, target.y, 'good');
    } else if (ringScale > GOOD_SCALE_MAX) {
        earlyHits.value++;
        applyPenalty(12);
    } else {
        missedHits.value++;
        applyPenalty(10);
    }

    spawnNewCircle(performance.now());
}

function onRhythmZoneClick(event: MouseEvent): void {
    if (!gameActive.value) {
        return;
    }

    const clickedElement = event.target as HTMLElement | null;
    const clickedOnCircle = clickedElement?.closest('[data-hit-circle]');
    if (clickedOnCircle) {
        return;
    }

    missedHits.value++;
    applyPenalty(14);
    spawnNewCircle(performance.now());
}

function onKeyDown(event: KeyboardEvent): void {
    if (event.code !== 'Space') {
        return;
    }

    event.preventDefault();
    hitActiveTarget();
}

function completeStage(): void {
    if (!gameFinished.value) {
        return;
    }

    emit('complete', score.value);
}

function tick(now: number): void {
    if (!gameActive.value) {
        return;
    }

    const deltaSeconds = Math.max(0, (now - lastFrameAt) / 1000);
    lastFrameAt = now;

    gameTime.value = now - gameStartTime.value;

    if (!activeTarget.value) {
        spawnNewCircle(now);
    }

    if (activeTarget.value) {
        const elapsed = now - activeTarget.value.spawnedAtMs;
        if (elapsed >= activeTarget.value.durationMs) {
            missedHits.value++;
            applyPenalty(14);
            spawnNewCircle(now);
        }
    }

    // Keep delta-time local and active so the RAF loop remains stable if frame rate drops.
    void deltaSeconds;

    if (gameTime.value >= GAME_DURATION_MS) {
        gameActive.value = false;
        gameFinished.value = true;
        activeTarget.value = null;
        return;
    }

    animationFrameId = window.requestAnimationFrame(tick);
}

onMounted(() => {
    const startNow = performance.now();
    gameStartTime.value = startNow;
    lastFrameAt = startNow;
    window.addEventListener('keydown', onKeyDown);
    animationFrameId = window.requestAnimationFrame(tick);
});

onUnmounted(() => {
    window.removeEventListener('keydown', onKeyDown);

    if (animationFrameId !== null) {
        window.cancelAnimationFrame(animationFrameId);
    }
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
                <p class="text-xs text-slate-500">EARLY / MISSED</p>
                <p class="text-2xl font-bold text-red-400">{{ earlyHits }} / {{ missedHits }}</p>
            </div>
        </div>

        <!-- Progress Bar -->
        <div class="space-y-2">
            <p class="text-xs text-slate-500">GAME PROGRESS</p>
            <div class="h-2 w-full rounded-full bg-slate-800">
                <div class="h-full rounded-full bg-blue-500 transition-all duration-100" :style="{ width: timePercent + '%' }" />
            </div>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
                <p class="mb-1 text-xs text-slate-500">SCORE BAR</p>
                <div class="h-2 w-full rounded-full bg-slate-800">
                    <div class="h-full rounded-full bg-cyan-400 transition-all duration-100" :style="{ width: `${score}%` }" />
                </div>
            </div>
            <div>
                <p class="mb-1 text-xs text-slate-500">ACCURACY BAR</p>
                <div class="h-2 w-full rounded-full bg-slate-800">
                    <div class="h-full rounded-full bg-green-400 transition-all duration-100" :style="{ width: `${accuracy}%` }" />
                </div>
            </div>
        </div>

        <!-- Rhythm Zone -->
        <div class="space-y-3">
            <p class="text-sm font-semibold text-slate-200">Rhythm Zone</p>
            <div
                class="relative h-80 overflow-hidden rounded border border-slate-600 bg-gradient-to-br from-slate-800 to-slate-900"
                @click="onRhythmZoneClick"
            >
                <div
                    v-if="activeTarget"
                    class="absolute"
                    :style="{ left: `${activeTarget.x}%`, top: `${activeTarget.y}%`, transform: 'translate(-50%, -50%)' }"
                >
                    <button
                        type="button"
                        data-hit-circle
                        class="relative h-10 w-10 rounded-full border-2 border-white/80 bg-slate-200/20 focus:outline-none"
                        @click="hitActiveTarget"
                    >
                        <span class="absolute inset-2 rounded-full bg-cyan-400/70" />
                    </button>

                    <div
                        class="pointer-events-none absolute left-1/2 top-1/2 h-10 w-10 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-cyan-300/90"
                        :style="{ transform: `translate(-50%, -50%) scale(${getRingScale(activeTarget)})` }"
                    />
                </div>

                <div
                    v-for="fx in hitFx"
                    :key="fx.id"
                    class="pointer-events-none absolute"
                    :style="{ left: `${fx.x}%`, top: `${fx.y}%`, transform: 'translate(-50%, -50%)' }"
                >
                    <span
                        :class="[
                            'block h-14 w-14 animate-hit rounded-full border-2',
                            fx.tone === 'perfect'
                                ? 'border-green-300 bg-green-400/25'
                                : 'border-blue-300 bg-blue-400/25',
                        ]"
                    />
                </div>

                <div class="absolute right-4 top-4 text-lg font-bold text-slate-300">
                    {{ Math.round(gameTime / 1000) }}s / 10s
                </div>
            </div>
        </div>

        <!-- Instructions -->
        <div class="rounded border border-blue-600/50 bg-blue-900/20 p-3">
            <div class="flex items-start gap-2">
                <AlertCircle class="mt-0.5 h-4 w-4 flex-shrink-0 text-blue-400" />
                <div class="text-xs text-blue-200">
                    <p class="mb-1 font-semibold">Hardcore timing:</p>
                    <p>Perfect when the shrinking ring overlaps the center circle. Early and misses cause heavy score penalties.</p>
                </div>
            </div>
        </div>

        <button
            v-if="gameFinished"
            @click="completeStage"
            class="w-full rounded bg-blue-500 px-4 py-3 font-semibold text-white transition hover:bg-blue-600"
        >
            Proceed to Quenching (Score: {{ score }}%)
        </button>
        <div
            v-else
            class="w-full rounded bg-slate-700 px-4 py-3 text-center font-semibold text-slate-300"
        >
            Smithing in progress... ({{ Math.round(gameTime / 1000) }}/10s)
        </div>
    </div>
</template>

<style scoped>
@keyframes hit {
    0% {
        transform: scale(0.5);
        opacity: 0.95;
    }

    100% {
        transform: scale(1.9);
        opacity: 0;
    }
}

.animate-hit {
    animation: hit 260ms ease-out forwards;
}
</style>
