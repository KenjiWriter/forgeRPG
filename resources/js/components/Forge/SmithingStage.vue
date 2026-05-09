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
    tone: 'perfect' | 'late';
}

const GAME_DURATION_MS = 10000;
const CIRCLE_WINDOW_MS = 1500;
const RING_SCALE_START = 2;
const RING_SCALE_END = 0.5;
const RING_PERFECT_LIGHT_MIN = 0.9;
const RING_PERFECT_LIGHT_MAX = 1.1;
const EARLY_HIT_MIN = 1.2;
const PERFECT_MIN = 0.9;
const PERFECT_MAX = 1.1;
const LATE_MIN = 0.5;
const LATE_MAX = 0.9;

const gameTime = ref(0);
const gameActive = ref(true);
const gameFinished = ref(false);
const gameStartTime = ref(0);
const totalScoreEarned = ref(0);
const totalScorePossible = ref(0);

const perfectHits = ref(0);
const lateHits = ref(0);
const earlyHits = ref(0);
const missedHits = ref(0);

const activeTarget = ref<ActiveTarget | null>(null);
const hitFx = ref<HitFx[]>([]);
let nextTargetId = 1;
let nextFxId = 1;
let animationFrameId: number | null = null;
let lastFrameAt = 0;

const score = computed(() => {
    if (totalScorePossible.value === 0) {
        return 0;
    }

    return Math.max(0, Math.min(100, Math.round((totalScoreEarned.value / totalScorePossible.value) * 100)));
});

const totalAttempts = computed(() => {
    return perfectHits.value + lateHits.value + earlyHits.value + missedHits.value;
});

const accuracy = computed(() => {
    if (totalAttempts.value === 0) {
        return 0;
    }

    return Math.round(((perfectHits.value + lateHits.value) / totalAttempts.value) * 100);
});

const timePercent = computed(() => Math.min(100, (gameTime.value / GAME_DURATION_MS) * 100));

function getRingScale(target: ActiveTarget): number {
    const progress = Math.min(1, (performance.now() - target.spawnedAtMs) / target.durationMs);
    return RING_SCALE_START - (RING_SCALE_START - RING_SCALE_END) * progress;
}

function getRingVisualStyle(target: ActiveTarget): Record<string, string> {
    const scale = getRingScale(target);
    const isPerfectLight = scale >= RING_PERFECT_LIGHT_MIN && scale <= RING_PERFECT_LIGHT_MAX;
    const isLateWindow = scale < PERFECT_MIN && scale >= LATE_MIN;

    return {
        transform: `scale(${scale})`,
        borderColor: isPerfectLight ? '#22c55e' : isLateWindow ? '#f59e0b' : '#bae6fd',
        boxShadow: isPerfectLight
            ? '0 0 10px rgba(34, 197, 94, 0.65)'
            : isLateWindow
            ? '0 0 9px rgba(245, 158, 11, 0.45)'
            : '0 0 5px rgba(125, 211, 252, 0.25)',
    };
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
    totalScorePossible.value += 100;
    totalScoreEarned.value += Math.max(0, 100 - points);
}

function hitActiveTarget(): void {
    if (!gameActive.value || !activeTarget.value) {
        return;
    }

    const target = activeTarget.value;
    const ringScale = getRingScale(target);
    totalScorePossible.value += 100;

    if (ringScale > EARLY_HIT_MIN) {
        earlyHits.value++;
        totalScoreEarned.value += 50;
    } else if (ringScale >= PERFECT_MIN && ringScale <= PERFECT_MAX) {
        perfectHits.value++;
        totalScoreEarned.value += 100;
        pushHitFx(target.x, target.y, 'perfect');
    } else if (ringScale >= LATE_MIN && ringScale < PERFECT_MIN) {
        lateHits.value++;
        totalScoreEarned.value += 50;
        pushHitFx(target.x, target.y, 'late');
    } else if (ringScale < LATE_MIN) {
        missedHits.value++;
        totalScoreEarned.value += 0;
    } else {
        // Tiny gap safety branch should count as miss.
        missedHits.value++;
        totalScoreEarned.value += 0;
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
    applyPenalty(100);
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
            totalScorePossible.value += 100;
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
                <p class="text-xs text-slate-500">PERFECT / LATE</p>
                <p class="text-2xl font-bold text-amber-400">{{ perfectHits }} / {{ lateHits }}</p>
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
                    <div
                        data-hit-circle
                        class="relative flex h-12 w-12 items-center justify-center"
                        @click.stop="hitActiveTarget"
                    >
                        <button
                            type="button"
                            data-hit-circle
                            class="relative h-10 w-10 rounded-full border-2 border-white/80 bg-slate-200/20 focus:outline-none"
                        >
                            <span class="absolute inset-2 rounded-full bg-cyan-400/70" />
                        </button>

                        <div
                            class="pointer-events-none absolute inset-0 rounded-full border-2 transition-colors duration-75"
                            :style="getRingVisualStyle(activeTarget)"
                        />
                    </div>
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
                                : 'border-amber-300 bg-amber-400/25',
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
                    <p>Early &gt; 1.2 = 50%. Perfect 1.1 to 0.9 = 100% (green). Late 0.9 to 0.5 = 50% (orange). Below 0.5 = miss.</p>
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
