<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Hammer } from 'lucide-vue-next';

interface OreInput {
    ore_type_id: number;
    quantity: number;
}

interface ActiveTarget {
    id: number;
    spawnedAtMs: number;
    durationMs: number;
}

interface SparkBurst {
    id: number;
    tone: FeedbackTone;
}

interface FloatingFeedback {
    id: number;
    label: string;
    tone: FeedbackTone;
}

type FeedbackTone = 'perfect' | 'good' | 'late' | 'miss';

const props = withDefaults(
    defineProps<{
        selectedOres?: OreInput[];
    }>(),
    {
        selectedOres: () => [],
    },
);

const emit = defineEmits<{
    complete: [score: number];
}>();

const GAME_DURATION_MS = 10000;
const CIRCLE_WINDOW_MS = 1450;
const RING_SCALE_START = 2.05;
const RING_SCALE_END = 0.4;
const EARLY_HIT_MIN = 1.18;
const PERFECT_MIN = 0.88;
const PERFECT_MAX = 1.04;
const LATE_MIN = 0.48;
const FEEDBACK_LIFETIME_MS = 820;
const SPARK_LIFETIME_MS = 460;
const HAMMER_IMPACT_MS = 210;
const SPARK_RAYS = [0, 1, 2, 3, 4, 5, 6, 7];

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
const sparkBursts = ref<SparkBurst[]>([]);
const floatingFeedback = ref<FloatingFeedback[]>([]);
const hammerActive = ref(false);

let nextTargetId = 1;
let nextFxId = 1;
let animationFrameId: number | null = null;
let hammerResetTimeout: number | null = null;

function clamp(value: number, min: number, max: number): number {
    return Math.min(max, Math.max(min, value));
}

const score = computed(() => {
    if (totalScorePossible.value === 0) {
        return 0;
    }

    return clamp(Math.round((totalScoreEarned.value / totalScorePossible.value) * 100), 0, 100);
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

const timePercent = computed(() => clamp((gameTime.value / GAME_DURATION_MS) * 100, 0, 100));

const qualityPercent = computed(() => {
    return totalAttempts.value === 0 ? 50 : Math.round((score.value + accuracy.value) / 2);
});

const oreIntensity = computed(() => {
    return clamp(props.selectedOres.reduce((sum, ore) => sum + ore.quantity * 4, 0), 0, 24);
});

const heatPercent = computed(() => {
    const remainingHeat = 92 - timePercent.value * 0.56;
    const qualityBonus = qualityPercent.value * 0.18;
    const hitBonus = perfectHits.value * 4 + lateHits.value * 2 - missedHits.value * 5;

    return clamp(Math.round(remainingHeat + qualityBonus + oreIntensity.value + hitBonus), 18, 100);
});

const stageSeconds = computed(() => Math.min(10, (gameTime.value / 1000).toFixed(1)));

const heatLabel = computed(() => {
    if (heatPercent.value >= 82) {
        return 'White Hot';
    }

    if (heatPercent.value >= 60) {
        return 'Forge Ready';
    }

    if (heatPercent.value >= 38) {
        return 'Cooling';
    }

    return 'Brittle';
});

const ingotStyle = computed<Record<string, string>>(() => {
    if (heatPercent.value >= 82) {
        return {
            background: 'linear-gradient(135deg, #fff4c2 0%, #ffb347 30%, #ff6b2d 70%, #6a1f12 100%)',
            boxShadow: '0 0 16px rgba(255, 195, 77, 0.85), 0 0 34px rgba(255, 98, 32, 0.55), inset 0 0 10px rgba(255, 245, 204, 0.55)',
        };
    }

    if (heatPercent.value >= 60) {
        return {
            background: 'linear-gradient(135deg, #ffd580 0%, #ff8a3d 35%, #d9481c 70%, #4b1b11 100%)',
            boxShadow: '0 0 14px rgba(255, 140, 61, 0.78), 0 0 28px rgba(217, 72, 28, 0.5), inset 0 0 8px rgba(255, 230, 160, 0.45)',
        };
    }

    if (heatPercent.value >= 38) {
        return {
            background: 'linear-gradient(135deg, #ffad60 0%, #c2431f 35%, #7f1d1d 75%, #321313 100%)',
            boxShadow: '0 0 10px rgba(194, 67, 31, 0.65), 0 0 20px rgba(127, 29, 29, 0.45), inset 0 0 6px rgba(255, 179, 102, 0.32)',
        };
    }

    return {
        background: 'linear-gradient(135deg, #8d5b42 0%, #5f2c22 45%, #2a1b1b 100%)',
        boxShadow: '0 0 8px rgba(143, 76, 57, 0.4), 0 0 12px rgba(61, 27, 27, 0.3), inset 0 0 4px rgba(255, 187, 133, 0.15)',
    };
});

function getRingScale(target: ActiveTarget): number {
    const progress = clamp((performance.now() - target.spawnedAtMs) / target.durationMs, 0, 1);
    return RING_SCALE_START - (RING_SCALE_START - RING_SCALE_END) * progress;
}

function getRingVisualStyle(target: ActiveTarget, multiplier = 1): Record<string, string> {
    const baseScale = clamp(getRingScale(target) * multiplier, 0.24, 2.4);
    const inPerfectWindow = baseScale >= PERFECT_MIN && baseScale <= PERFECT_MAX;
    const inLateWindow = baseScale >= LATE_MIN && baseScale < PERFECT_MIN;
    const glowColor = inPerfectWindow ? 'rgba(74, 222, 128, 0.9)' : inLateWindow ? 'rgba(251, 191, 36, 0.8)' : 'rgba(56, 189, 248, 0.72)';
    const borderColor = inPerfectWindow ? '#86efac' : inLateWindow ? '#fbbf24' : '#67e8f9';

    return {
        transform: `translate(-50%, -50%) scale(${baseScale})`,
        borderColor,
        boxShadow: `0 0 14px ${glowColor}, inset 0 0 16px rgba(255, 255, 255, 0.08)`,
        opacity: `${clamp(0.26 + (baseScale / 2.2), 0.24, 0.95)}`,
    };
}

function triggerHammerImpact(): void {
    hammerActive.value = false;

    if (hammerResetTimeout !== null) {
        window.clearTimeout(hammerResetTimeout);
    }

    window.requestAnimationFrame(() => {
        hammerActive.value = true;
        hammerResetTimeout = window.setTimeout(() => {
            hammerActive.value = false;
            hammerResetTimeout = null;
        }, HAMMER_IMPACT_MS);
    });
}

function spawnNewStrike(nowMs: number): void {
    if (!gameActive.value) {
        activeTarget.value = null;
        return;
    }

    activeTarget.value = {
        id: nextTargetId++,
        spawnedAtMs: nowMs,
        durationMs: CIRCLE_WINDOW_MS,
    };
}

function pushSparkBurst(tone: FeedbackTone): void {
    const id = nextFxId++;
    sparkBursts.value.push({ id, tone });

    window.setTimeout(() => {
        sparkBursts.value = sparkBursts.value.filter((burst) => burst.id !== id);
    }, SPARK_LIFETIME_MS);
}

function pushFloatingFeedback(label: string, tone: FeedbackTone): void {
    const id = nextFxId++;
    floatingFeedback.value.push({ id, label, tone });

    window.setTimeout(() => {
        floatingFeedback.value = floatingFeedback.value.filter((feedback) => feedback.id !== id);
    }, FEEDBACK_LIFETIME_MS);
}

function registerMiss(): void {
    totalScorePossible.value += 100;
    missedHits.value++;
    triggerHammerImpact();
    pushSparkBurst('miss');
    pushFloatingFeedback('MISSED', 'miss');
}

function hitActiveTarget(): void {
    if (!gameActive.value || !activeTarget.value) {
        return;
    }

    const target = activeTarget.value;
    const ringScale = getRingScale(target);
    totalScorePossible.value += 100;
    triggerHammerImpact();

    if (ringScale > EARLY_HIT_MIN) {
        earlyHits.value++;
        totalScoreEarned.value += 55;
        pushSparkBurst('good');
        pushFloatingFeedback('GOOD', 'good');
    } else if (ringScale >= PERFECT_MIN && ringScale <= PERFECT_MAX) {
        perfectHits.value++;
        totalScoreEarned.value += 100;
        pushSparkBurst('perfect');
        pushFloatingFeedback('PERFECT!', 'perfect');
    } else if (ringScale >= LATE_MIN && ringScale < PERFECT_MIN) {
        lateHits.value++;
        totalScoreEarned.value += 65;
        pushSparkBurst('late');
        pushFloatingFeedback('LATE', 'late');
    } else {
        missedHits.value++;
        pushSparkBurst('miss');
        pushFloatingFeedback('MISSED', 'miss');
    }

    spawnNewStrike(performance.now());
}

function onForgeClick(): void {
    if (!gameActive.value) {
        return;
    }

    if (!activeTarget.value) {
        registerMiss();
        return;
    }

    hitActiveTarget();
}

function onKeyDown(event: KeyboardEvent): void {
    if (event.code !== 'Space') {
        return;
    }

    event.preventDefault();

    if (!activeTarget.value) {
        registerMiss();
        return;
    }

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

    gameTime.value = now - gameStartTime.value;

    if (!activeTarget.value) {
        spawnNewStrike(now);
    }

    if (activeTarget.value) {
        const elapsed = now - activeTarget.value.spawnedAtMs;

        if (elapsed >= activeTarget.value.durationMs) {
            totalScorePossible.value += 100;
            missedHits.value++;
            pushFloatingFeedback('MISSED', 'miss');
            spawnNewStrike(now);
        }
    }

    if (gameTime.value >= GAME_DURATION_MS) {
        gameTime.value = GAME_DURATION_MS;
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
    window.addEventListener('keydown', onKeyDown);
    animationFrameId = window.requestAnimationFrame(tick);
});

onUnmounted(() => {
    window.removeEventListener('keydown', onKeyDown);

    if (animationFrameId !== null) {
        window.cancelAnimationFrame(animationFrameId);
    }

    if (hammerResetTimeout !== null) {
        window.clearTimeout(hammerResetTimeout);
    }
});
</script>

<template>
    <div
        class="space-y-6 rounded-xl border-2 border-amber-800/50 bg-gradient-to-b from-stone-900 via-stone-950 to-black p-5 shadow-[0_20px_60px_rgba(0,0,0,0.65)] sm:p-6"
        style="font-family: 'Cinzel', serif;"
    >
        <div class="flex flex-col gap-3 border-b border-amber-700/30 pb-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <div class="mb-2 flex items-center gap-3">
                    <div class="rounded-full border border-amber-500/40 bg-black/40 p-2 shadow-[0_0_14px_rgba(245,158,11,0.18)]">
                        <Hammer class="h-5 w-5 text-amber-300" />
                    </div>
                    <div>
                        <h2 class="text-3xl font-bold tracking-[0.12em] text-amber-300">Smithing Stage</h2>
                        <p class="text-[11px] uppercase tracking-[0.35em] text-amber-100/50">Strike the ingot at the perfect beat</p>
                    </div>
                </div>
                <p class="max-w-3xl text-sm leading-6 text-stone-300/80">
                    Hold the forge heat, let the ring collapse into the hot zone, then strike with SPACE or a click to shape the steel.
                </p>
            </div>

            <div class="rounded-full border border-orange-500/30 bg-orange-950/30 px-4 py-2 text-center shadow-[0_0_18px_rgba(234,88,12,0.18)]">
                <p class="text-[10px] uppercase tracking-[0.35em] text-orange-100/50">Heat State</p>
                <p class="text-lg font-bold text-orange-300">{{ heatLabel }}</p>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)]">
            <div class="smith-hud-panel">
                <p class="smith-hud-label">Score</p>
                <p class="smith-hud-value text-orange-300">{{ score }}%</p>
                <p class="smith-hud-subtext">Forging quality</p>
            </div>

            <div class="smith-hud-panel">
                <p class="smith-hud-label">Accuracy</p>
                <p class="smith-hud-value text-emerald-300">{{ accuracy }}%</p>
                <p class="smith-hud-subtext">Rhythm mastery</p>
            </div>

            <div class="smith-hud-panel">
                <p class="smith-hud-label">Perfect / Late</p>
                <p class="smith-hud-value text-amber-200">{{ perfectHits }} / {{ lateHits }}</p>
                <p class="smith-hud-subtext">Clean shaping blows</p>
            </div>

            <div class="smith-hud-panel">
                <p class="smith-hud-label">Early / Missed</p>
                <p class="smith-hud-value text-red-300">{{ earlyHits }} / {{ missedHits }}</p>
                <p class="smith-hud-subtext">Wasted motion</p>
            </div>
        </div>

        <div class="grid gap-5 xl:grid-cols-[112px_minmax(0,1fr)_220px]">
            <div class="order-2 flex flex-row gap-4 xl:order-1 xl:flex-col">
                <div class="smith-hud-panel flex-1 items-center justify-center xl:min-h-[370px]">
                    <p class="smith-hud-label text-center">Game Progress</p>
                    <div class="mt-3 flex flex-1 items-center justify-center">
                        <div class="thermometer-shell">
                            <div class="thermometer-core">
                                <div
                                    class="thermometer-fill"
                                    :style="{
                                        height: `${timePercent}%`,
                                        boxShadow: `0 0 ${18 + timePercent / 3}px rgba(249, 115, 22, 0.55)`,
                                    }"
                                />
                            </div>
                            <div class="thermometer-bulb">
                                <div class="thermometer-bulb-core" />
                            </div>
                        </div>
                    </div>
                    <p class="smith-hud-subtext mt-4 text-center">{{ stageSeconds }}s / 10s</p>
                </div>
            </div>

            <div class="order-1 xl:order-2">
                <button
                    type="button"
                    class="forge-scene group relative flex h-[520px] w-full items-center justify-center overflow-hidden rounded-[28px] border border-amber-800/45 text-left shadow-[0_24px_80px_rgba(0,0,0,0.75)] focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400/60"
                    @click="onForgeClick"
                >
                    <div class="absolute inset-0 bg-[radial-gradient(circle_at_50%_20%,rgba(255,186,73,0.16),transparent_30%),radial-gradient(circle_at_50%_70%,rgba(255,92,30,0.22),transparent_32%),linear-gradient(180deg,rgba(22,18,18,0.55),rgba(7,5,5,0.9))]" />
                    <div class="forge-cinders absolute inset-0 opacity-60" />
                    <div class="absolute inset-x-8 top-6 flex items-start justify-between text-sm text-stone-200/85">
                        <div>
                            <p class="text-[10px] uppercase tracking-[0.35em] text-amber-100/45">Strike Window</p>
                            <p class="text-base font-semibold text-cyan-100/90">Center the ring on the ingot</p>
                        </div>
                        <div class="text-right">
                            <p class="text-[10px] uppercase tracking-[0.35em] text-amber-100/45">Forge Clock</p>
                            <p class="text-2xl font-bold text-amber-200">{{ stageSeconds }}s</p>
                        </div>
                    </div>

                    <div class="absolute inset-0 flex items-center justify-center">
                        <div class="relative h-[340px] w-[340px] sm:h-[380px] sm:w-[380px]">
                            <div class="absolute left-1/2 top-[22%] z-20 -translate-x-1/2 -translate-y-1/2">
                                <div class="relative h-16 w-16 rounded-full border border-emerald-300/45 bg-emerald-400/8 shadow-[0_0_28px_rgba(74,222,128,0.25)] sm:h-20 sm:w-20">
                                    <div class="absolute inset-[7px] rounded-full border border-emerald-300/60" />
                                </div>
                            </div>

                            <div class="absolute inset-0 z-30 flex items-center justify-center">
                                <div v-if="activeTarget" class="absolute left-1/2 top-[44%] h-24 w-24 sm:h-28 sm:w-28">
                                    <div
                                        class="pointer-events-none absolute left-1/2 top-1/2 h-full w-full rounded-full border-2"
                                        :style="getRingVisualStyle(activeTarget, 1)"
                                    />
                                    <div
                                        class="pointer-events-none absolute left-1/2 top-1/2 h-full w-full rounded-full border"
                                        :style="getRingVisualStyle(activeTarget, 0.82)"
                                    />
                                    <div
                                        class="pointer-events-none absolute left-1/2 top-1/2 h-full w-full rounded-full border border-cyan-200/35"
                                        :style="getRingVisualStyle(activeTarget, 0.64)"
                                    />
                                </div>

                                <div class="absolute left-1/2 top-[44%] z-40 -translate-x-1/2 -translate-y-1/2">
                                    <div class="anvil-platform">
                                        <div class="anvil-top" />
                                        <div class="anvil-horn" />
                                        <div class="anvil-heel" />
                                        <div class="anvil-body" />
                                        <div class="anvil-base" />

                                        <div class="absolute left-1/2 top-[14%] z-20 -translate-x-1/2 -translate-y-1/2">
                                            <div class="relative">
                                                <div class="ingot-shadow" />
                                                <div class="smith-ingot" :style="ingotStyle">
                                                    <div class="smith-ingot-face" />
                                                    <div class="smith-ingot-edge" />
                                                </div>

                                                <div
                                                    v-for="feedback in floatingFeedback"
                                                    :key="feedback.id"
                                                    :class="[
                                                        'floating-feedback',
                                                        `floating-feedback--${feedback.tone}`,
                                                    ]"
                                                >
                                                    {{ feedback.label }}
                                                </div>

                                                <div
                                                    v-for="burst in sparkBursts"
                                                    :key="burst.id"
                                                    class="spark-burst"
                                                >
                                                    <span
                                                        v-for="ray in SPARK_RAYS"
                                                        :key="`${burst.id}-${ray}`"
                                                        :class="[`spark-ray spark-ray--${burst.tone}`]"
                                                        :style="{ '--spark-angle': `${ray * 45}deg` }"
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    :class="[
                                        'smith-hammer absolute left-1/2 top-[17%] z-50 -translate-x-1/2',
                                        hammerActive ? 'smith-hammer--impact' : '',
                                    ]"
                                >
                                    <div class="smith-hammer-head" />
                                    <div class="smith-hammer-neck" />
                                    <div class="smith-hammer-handle" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="absolute inset-x-6 bottom-5 z-50 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div class="rounded-2xl border border-black/40 bg-black/30 px-4 py-3 backdrop-blur-sm">
                            <p class="text-[10px] uppercase tracking-[0.35em] text-amber-100/45">Forge Note</p>
                            <p class="mt-1 text-sm leading-6 text-stone-200/80" style="font-family: 'MedievalSharp', cursive;">
                                Match the collapsing halo with the glowing billet to land a clean hammer strike.
                            </p>
                        </div>

                        <div class="rounded-2xl border border-orange-500/25 bg-orange-950/20 px-4 py-3 text-right backdrop-blur-sm">
                            <p class="text-[10px] uppercase tracking-[0.35em] text-orange-100/45">Billet Heat</p>
                            <p class="text-2xl font-bold text-orange-300">{{ heatPercent }}%</p>
                        </div>
                    </div>
                </button>
            </div>

            <div class="order-3 flex flex-col gap-4">
                <div class="smith-hud-panel">
                    <p class="smith-hud-label">Rhythm Call</p>
                    <p class="text-lg font-semibold leading-7 text-amber-100/95">Let the outer ring collapse into the emerald hot zone, then strike.</p>
                </div>

                <div class="smith-hud-panel">
                    <p class="smith-hud-label">Heat and Quality</p>
                    <div class="space-y-3 text-sm text-stone-200/75">
                        <div>
                            <p class="mb-1 text-[10px] uppercase tracking-[0.25em] text-amber-100/40">Heat</p>
                            <div class="h-3 overflow-hidden rounded-full border border-orange-500/25 bg-black/40">
                                <div
                                    class="h-full rounded-full bg-[linear-gradient(90deg,#7f1d1d_0%,#ea580c_45%,#fbbf24_100%)] transition-all duration-150"
                                    :style="{ width: `${heatPercent}%` }"
                                />
                            </div>
                        </div>

                        <div>
                            <p class="mb-1 text-[10px] uppercase tracking-[0.25em] text-amber-100/40">Quality</p>
                            <div class="h-3 overflow-hidden rounded-full border border-emerald-500/20 bg-black/40">
                                <div
                                    class="h-full rounded-full bg-[linear-gradient(90deg,#14532d_0%,#22c55e_55%,#bbf7d0_100%)] transition-all duration-150"
                                    :style="{ width: `${qualityPercent}%` }"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="smith-hud-panel">
                    <p class="smith-hud-label">Timing Ledger</p>
                    <div class="space-y-2 text-sm text-stone-200/80">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-emerald-300">PERFECT</span>
                            <span>{{ perfectHits }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-orange-300">GOOD</span>
                            <span>{{ earlyHits }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-amber-200">LATE</span>
                            <span>{{ lateHits }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-red-300">MISSED</span>
                            <span>{{ missedHits }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
            <div class="rounded-2xl border border-amber-700/25 bg-black/20 px-4 py-3 text-sm leading-6 text-stone-300/80 shadow-[inset_0_1px_0_rgba(255,210,120,0.06)]">
                Press <span class="font-bold text-amber-200">SPACE</span> or click the scene to strike. Perfect hits keep the ingot blazing and lift the final smithing score.
            </div>

            <button
                v-if="gameFinished"
                type="button"
                class="quench-button min-w-[280px]"
                @click="completeStage"
            >
                <span class="quench-button__label">Proceed to Quenching</span>
                <span class="quench-button__score">Final Score: {{ score }}%</span>
            </button>

            <div
                v-else
                class="inline-flex min-w-[280px] items-center justify-center rounded-2xl border border-stone-500/40 bg-[linear-gradient(180deg,rgba(58,58,58,0.82),rgba(25,25,25,0.96))] px-6 py-4 text-center shadow-[inset_0_1px_0_rgba(255,255,255,0.08),0_10px_24px_rgba(0,0,0,0.35)]"
            >
                <div>
                    <p class="text-[10px] uppercase tracking-[0.35em] text-stone-300/45">Forge Status</p>
                    <p class="mt-1 text-lg font-semibold text-stone-100">Smithing in progress...</p>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.smith-hud-panel {
    position: relative;
    display: flex;
    min-height: 118px;
    flex-direction: column;
    justify-content: center;
    overflow: hidden;
    border-radius: 1.2rem;
    border: 1px solid rgba(180, 120, 42, 0.5);
    background:
        linear-gradient(180deg, rgba(88, 66, 44, 0.18), rgba(20, 16, 13, 0.88)),
        linear-gradient(135deg, rgba(12, 12, 12, 0.96), rgba(37, 28, 22, 0.96));
    padding: 1rem 1.1rem;
    box-shadow:
        inset 0 1px 0 rgba(255, 231, 192, 0.08),
        inset 0 -1px 0 rgba(0, 0, 0, 0.4),
        0 18px 32px rgba(0, 0, 0, 0.32);
}

.smith-hud-panel::before,
.smith-hud-panel::after {
    position: absolute;
    height: 18px;
    width: 18px;
    border: 1px solid rgba(245, 158, 11, 0.32);
    content: '';
}

.smith-hud-panel::before {
    top: 8px;
    left: 8px;
    border-right: 0;
    border-bottom: 0;
}

.smith-hud-panel::after {
    right: 8px;
    bottom: 8px;
    border-top: 0;
    border-left: 0;
}

.smith-hud-label {
    margin-bottom: 0.4rem;
    font-size: 0.66rem;
    text-transform: uppercase;
    letter-spacing: 0.32em;
    color: rgba(254, 243, 199, 0.55);
}

.smith-hud-value {
    font-size: 2rem;
    font-weight: 700;
    line-height: 1;
}

.smith-hud-subtext {
    margin-top: 0.55rem;
    font-size: 0.8rem;
    color: rgba(231, 229, 228, 0.58);
}

.forge-scene {
    background:
        radial-gradient(circle at 50% 32%, rgba(255, 178, 72, 0.14), transparent 28%),
        radial-gradient(circle at 50% 78%, rgba(255, 84, 34, 0.15), transparent 24%),
        linear-gradient(180deg, #241c19 0%, #0e0a09 100%);
}

.forge-cinders {
    background-image:
        radial-gradient(circle, rgba(255, 172, 75, 0.75) 0, rgba(255, 172, 75, 0) 55%),
        radial-gradient(circle, rgba(255, 96, 43, 0.55) 0, rgba(255, 96, 43, 0) 60%),
        radial-gradient(circle, rgba(255, 214, 170, 0.5) 0, rgba(255, 214, 170, 0) 55%);
    background-position: 6% 82%, 78% 18%, 58% 64%;
    background-repeat: no-repeat;
    background-size: 26px 26px, 18px 18px, 14px 14px;
    animation: scene-cinder 4.2s ease-in-out infinite alternate;
}

.thermometer-shell {
    position: relative;
    height: 220px;
    width: 48px;
    padding: 8px;
    border-radius: 999px;
    border: 1px solid rgba(120, 113, 108, 0.65);
    background: linear-gradient(180deg, rgba(78, 72, 66, 0.9), rgba(28, 25, 23, 1));
    box-shadow:
        inset 0 2px 5px rgba(255, 255, 255, 0.08),
        inset 0 -2px 5px rgba(0, 0, 0, 0.55),
        0 16px 24px rgba(0, 0, 0, 0.35);
}

.thermometer-core {
    position: absolute;
    inset: 8px;
    overflow: hidden;
    border-radius: 999px;
    border: 1px solid rgba(245, 158, 11, 0.2);
    background: linear-gradient(180deg, rgba(5, 5, 5, 0.65), rgba(20, 12, 8, 0.95));
}

.thermometer-fill {
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    border-radius: 999px;
    background: linear-gradient(180deg, rgba(254, 215, 170, 0.95), rgba(251, 146, 60, 0.98) 30%, rgba(234, 88, 12, 1) 60%, rgba(153, 27, 27, 0.98) 100%);
    transition: height 100ms linear;
}

.thermometer-bulb {
    position: absolute;
    bottom: -18px;
    left: 50%;
    display: flex;
    height: 64px;
    width: 64px;
    transform: translateX(-50%);
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    border: 1px solid rgba(120, 113, 108, 0.75);
    background: linear-gradient(180deg, rgba(82, 72, 63, 0.96), rgba(28, 25, 23, 1));
    box-shadow:
        inset 0 2px 5px rgba(255, 255, 255, 0.08),
        0 16px 24px rgba(0, 0, 0, 0.35);
}

.thermometer-bulb-core {
    height: 38px;
    width: 38px;
    border-radius: 999px;
    background: radial-gradient(circle, rgba(254, 215, 170, 0.95) 0%, rgba(249, 115, 22, 0.98) 45%, rgba(153, 27, 27, 1) 100%);
    box-shadow: 0 0 18px rgba(249, 115, 22, 0.55);
}

.anvil-platform {
    position: relative;
    height: 240px;
    width: 310px;
}

.anvil-top,
.anvil-horn,
.anvil-heel,
.anvil-body,
.anvil-base {
    position: absolute;
    background: linear-gradient(180deg, #6b7280 0%, #323844 48%, #0f1721 100%);
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.15),
        inset 0 -4px 10px rgba(0, 0, 0, 0.55),
        0 18px 28px rgba(0, 0, 0, 0.48);
}

.anvil-top {
    top: 78px;
    left: 52px;
    z-index: 5;
    height: 30px;
    width: 176px;
    border-radius: 14px 10px 10px 12px;
}

.anvil-horn {
    top: 76px;
    left: 214px;
    z-index: 4;
    height: 36px;
    width: 86px;
    clip-path: polygon(0 0, 100% 44%, 0 100%);
}

.anvil-heel {
    top: 76px;
    left: 22px;
    z-index: 4;
    height: 38px;
    width: 38px;
    border-radius: 8px;
}

.anvil-body {
    top: 110px;
    left: 86px;
    z-index: 3;
    height: 72px;
    width: 138px;
    clip-path: polygon(8% 0, 92% 0, 100% 26%, 80% 100%, 20% 100%, 0 26%);
}

.anvil-base {
    bottom: 16px;
    left: 72px;
    z-index: 2;
    height: 28px;
    width: 166px;
    border-radius: 14px 14px 18px 18px;
}

.ingot-shadow {
    position: absolute;
    left: 50%;
    top: 68%;
    z-index: 10;
    height: 24px;
    width: 120px;
    transform: translate(-50%, -50%);
    border-radius: 999px;
    background: radial-gradient(circle, rgba(0, 0, 0, 0.5), transparent 70%);
}

.smith-ingot {
    position: relative;
    z-index: 20;
    height: 52px;
    width: 122px;
    transform: perspective(300px) rotateX(57deg) rotateZ(-12deg);
    border-radius: 10px;
    border: 1px solid rgba(255, 240, 200, 0.14);
    animation: ingot-pulse 1.8s ease-in-out infinite;
}

.smith-ingot-face,
.smith-ingot-edge {
    position: absolute;
    border-radius: 10px;
}

.smith-ingot-face {
    inset: 7px 12px auto 8px;
    height: 12px;
    background: linear-gradient(90deg, rgba(255, 241, 212, 0.65), rgba(255, 221, 160, 0.05));
}

.smith-ingot-edge {
    right: 8px;
    top: 5px;
    height: calc(100% - 10px);
    width: 18px;
    background: linear-gradient(180deg, rgba(255, 220, 170, 0.35), rgba(50, 18, 12, 0.3));
}

.smith-hammer {
    transform-origin: 24px 78px;
    transition: transform 110ms ease-out;
}

.smith-hammer--impact {
    animation: hammer-slam 210ms ease-out;
}

.smith-hammer-head {
    position: absolute;
    left: -6px;
    top: 0;
    height: 26px;
    width: 92px;
    border-radius: 10px;
    background: linear-gradient(180deg, #9ca3af 0%, #525a68 45%, #131a23 100%);
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.16),
        0 10px 24px rgba(0, 0, 0, 0.42);
}

.smith-hammer-neck {
    position: absolute;
    left: 36px;
    top: 20px;
    height: 18px;
    width: 12px;
    border-radius: 999px;
    background: linear-gradient(180deg, #b45309, #78350f);
}

.smith-hammer-handle {
    position: absolute;
    left: 33px;
    top: 24px;
    height: 154px;
    width: 18px;
    border-radius: 999px;
    background: linear-gradient(180deg, #d6a265 0%, #8b5e34 35%, #4a2d18 100%);
    box-shadow: inset 0 1px 0 rgba(255, 247, 220, 0.12);
}

.floating-feedback {
    position: absolute;
    left: 50%;
    top: -54px;
    z-index: 60;
    transform: translateX(-50%);
    font-size: 1.1rem;
    font-weight: 700;
    letter-spacing: 0.18em;
    white-space: nowrap;
    text-shadow: 0 0 14px currentColor;
    animation: feedback-rise 820ms ease-out forwards;
    pointer-events: none;
}

.floating-feedback--perfect {
    color: #86efac;
}

.floating-feedback--good {
    color: #fdba74;
}

.floating-feedback--late {
    color: #fcd34d;
}

.floating-feedback--miss {
    color: #fca5a5;
}

.spark-burst {
    position: absolute;
    left: 50%;
    top: 50%;
    z-index: 55;
    transform: translate(-50%, -50%);
    pointer-events: none;
}

.spark-ray {
    position: absolute;
    left: 50%;
    top: 50%;
    height: 52px;
    width: 4px;
    transform-origin: center bottom;
    transform: translate(-50%, -100%) rotate(var(--spark-angle));
    border-radius: 999px;
    opacity: 0;
    animation: spark-streak 460ms ease-out forwards;
}

.spark-ray--perfect {
    background: linear-gradient(180deg, rgba(220, 252, 231, 0.95), rgba(74, 222, 128, 0.12));
    box-shadow: 0 0 14px rgba(74, 222, 128, 0.45);
}

.spark-ray--good {
    background: linear-gradient(180deg, rgba(255, 237, 213, 0.95), rgba(251, 146, 60, 0.12));
    box-shadow: 0 0 14px rgba(251, 146, 60, 0.45);
}

.spark-ray--late {
    background: linear-gradient(180deg, rgba(254, 243, 199, 0.95), rgba(245, 158, 11, 0.12));
    box-shadow: 0 0 14px rgba(245, 158, 11, 0.45);
}

.spark-ray--miss {
    background: linear-gradient(180deg, rgba(254, 202, 202, 0.95), rgba(239, 68, 68, 0.12));
    box-shadow: 0 0 12px rgba(239, 68, 68, 0.35);
}

.quench-button {
    position: relative;
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    overflow: hidden;
    border-radius: 1rem;
    border: 1px solid rgba(120, 113, 108, 0.75);
    background:
        linear-gradient(180deg, rgba(107, 114, 128, 0.95), rgba(55, 65, 81, 0.96) 30%, rgba(15, 23, 42, 0.98) 100%);
    padding: 1rem 1.8rem;
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.12),
        inset 0 -2px 0 rgba(0, 0, 0, 0.45),
        0 18px 32px rgba(0, 0, 0, 0.45);
    transition: transform 140ms ease, box-shadow 140ms ease, border-color 140ms ease;
}

.quench-button:hover {
    transform: translateY(-1px);
    border-color: rgba(251, 191, 36, 0.55);
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.16),
        0 0 24px rgba(251, 191, 36, 0.18),
        0 18px 32px rgba(0, 0, 0, 0.45);
}

.quench-button__label {
    font-size: 1rem;
    font-weight: 700;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: #fef3c7;
}

.quench-button__score {
    font-size: 0.78rem;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: rgba(253, 230, 138, 0.72);
}

@keyframes ingot-pulse {
    0%,
    100% {
        transform: perspective(300px) rotateX(57deg) rotateZ(-12deg) scale(1);
    }

    50% {
        transform: perspective(300px) rotateX(57deg) rotateZ(-12deg) scale(1.04);
    }
}

@keyframes hammer-slam {
    0% {
        transform: translate(-50%, 0) rotate(-18deg);
    }

    55% {
        transform: translate(-50%, 120px) rotate(3deg);
    }

    100% {
        transform: translate(-50%, 0) rotate(-18deg);
    }
}

@keyframes spark-streak {
    0% {
        opacity: 0;
        transform: translate(-50%, -100%) rotate(var(--spark-angle)) scaleY(0.35);
    }

    25% {
        opacity: 1;
    }

    100% {
        opacity: 0;
        transform: translate(-50%, -150%) rotate(var(--spark-angle)) scaleY(1.12);
    }
}

@keyframes feedback-rise {
    0% {
        opacity: 0;
        transform: translate(-50%, 6px) scale(0.92);
    }

    15% {
        opacity: 1;
        transform: translate(-50%, -6px) scale(1);
    }

    100% {
        opacity: 0;
        transform: translate(-50%, -58px) scale(1.05);
    }
}

@keyframes scene-cinder {
    0% {
        transform: translateY(0);
        opacity: 0.45;
    }

    100% {
        transform: translateY(-8px);
        opacity: 0.78;
    }
}
</style>
