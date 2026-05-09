<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { AlertCircle, Flame } from 'lucide-vue-next';

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

const GAME_DURATION_MS = 10000;
const HEAT_COOL_RATE = 22; // heat/sec
const HEAT_PUSH_RATE = 44.2; // heat/sec while holding push (+30%)
const HEAT_PUSH_TAP_BOOST = 1.2;
const HEAT_RESPONSE_DAMPING = 11;
const HEAT_MAX_RISE_SPEED = 50;
const HEAT_MAX_FALL_SPEED = 36;
const SWEET_SPOT_HALF_SIZE = 9;
const SWEET_SPOT_AMPLITUDE = 32;
const SWEET_SPOT_PERIOD_MS = 7600;
const SWEET_SPOT_CENTER_MIN = 10 + SWEET_SPOT_HALF_SIZE;
const SWEET_SPOT_CENTER_MAX = 90 - SWEET_SPOT_HALF_SIZE;
const SWEET_SPOT_LERP_SPEED = 3.4;

const heat = ref(52);
const heatVelocity = ref(0);
const isPushing = ref(false);
const sweetSpotTargetCenter = ref(50);
const sweetSpotCenter = ref(50);
const timeInSweetSpot = ref(0);
const gameTime = ref(0);
const gameActive = ref(true);
const gameFinished = ref(false);
const gameStartTime = ref(0);

let animationFrameId: number | null = null;
let lastFrameAt = 0;

const sweetSpotStart = computed(() => Math.max(0, sweetSpotCenter.value - SWEET_SPOT_HALF_SIZE));
const sweetSpotEnd = computed(() => Math.min(100, sweetSpotCenter.value + SWEET_SPOT_HALF_SIZE));
const sweetSpotHeight = computed(() => sweetSpotEnd.value - sweetSpotStart.value);

const isInSweetSpot = computed(() => {
    return heat.value >= sweetSpotStart.value && heat.value <= sweetSpotEnd.value;
});

const timePercent = computed(() => Math.min(100, (gameTime.value / GAME_DURATION_MS) * 100));

const score = computed(() => {
    return Math.min(100, Math.round((timeInSweetSpot.value / GAME_DURATION_MS) * 100));
});

const heatIndicatorClass = computed(() => {
    return isInSweetSpot.value ? 'bg-green-400 shadow-green-500/50' : 'bg-red-500 shadow-red-500/40';
});

function startPushing(): void {
    if (!gameActive.value) {
        return;
    }

    if (!isPushing.value) {
        // Instant tactile response when player starts pushing the bellows.
        heat.value = Math.min(100, heat.value + HEAT_PUSH_TAP_BOOST);
    }

    isPushing.value = true;
}

function stopPushing(): void {
    isPushing.value = false;
}

function onKeydown(event: KeyboardEvent): void {
    if (event.code !== 'Space') {
        return;
    }

    event.preventDefault();
    startPushing();
}

function onKeyup(event: KeyboardEvent): void {
    if (event.code !== 'Space') {
        return;
    }

    event.preventDefault();
    stopPushing();
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

    if (lastFrameAt === 0) {
        lastFrameAt = now;
    }

    const deltaSeconds = (now - lastFrameAt) / 1000;
    lastFrameAt = now;

    gameTime.value = now - gameStartTime.value;

    const sweetOscillation = Math.sin((gameTime.value / SWEET_SPOT_PERIOD_MS) * Math.PI * 2);
    const rawTargetCenter = 50 + sweetOscillation * SWEET_SPOT_AMPLITUDE;
    sweetSpotTargetCenter.value = Math.max(
        SWEET_SPOT_CENTER_MIN,
        Math.min(SWEET_SPOT_CENTER_MAX, rawTargetCenter)
    );

    // Heavy-metal pacing: lerp toward target so zone movement feels weighted, not jittery.
    const lerpAlpha = Math.min(1, SWEET_SPOT_LERP_SPEED * deltaSeconds);
    sweetSpotCenter.value += (sweetSpotTargetCenter.value - sweetSpotCenter.value) * lerpAlpha;

    const targetVelocity = isPushing.value ? HEAT_PUSH_RATE : -HEAT_COOL_RATE;
    const dampingAlpha = Math.min(1, HEAT_RESPONSE_DAMPING * deltaSeconds);
    heatVelocity.value += (targetVelocity - heatVelocity.value) * dampingAlpha;
    heatVelocity.value = Math.max(-HEAT_MAX_FALL_SPEED, Math.min(HEAT_MAX_RISE_SPEED, heatVelocity.value));

    const heatDelta = heatVelocity.value * deltaSeconds;
    heat.value = Math.max(0, Math.min(100, heat.value + heatDelta));

    if (isInSweetSpot.value) {
        timeInSweetSpot.value += deltaSeconds * 1000;
    }

    if (gameTime.value >= GAME_DURATION_MS) {
        gameActive.value = false;
        gameFinished.value = true;
        isPushing.value = false;
        heatVelocity.value = 0;
        return;
    }

    animationFrameId = window.requestAnimationFrame(tick);
}

onMounted(() => {
    gameStartTime.value = performance.now();
    animationFrameId = window.requestAnimationFrame(tick);
    window.addEventListener('keydown', onKeydown);
    window.addEventListener('keyup', onKeyup);
});

onUnmounted(() => {
    window.removeEventListener('keydown', onKeydown);
    window.removeEventListener('keyup', onKeyup);

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
                <p class="text-xs text-slate-500">HEAT LEVEL</p>
                <p class="text-2xl font-bold text-red-400">{{ Math.round(heat) }}%</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded bg-slate-800 p-3 sm:col-span-1">
                <p class="text-xs text-slate-500">ELAPSED TIME</p>
                <p class="text-2xl font-bold text-slate-300">{{ Math.round(gameTime / 1000) }}s / 10s</p>
            </div>
            <div class="rounded bg-slate-800 p-3 sm:col-span-2">
                <p class="mb-2 text-xs text-slate-500">GAME PROGRESS</p>
                <div class="h-2 w-full rounded-full bg-slate-700">
                    <div class="h-full rounded-full bg-orange-500 transition-all duration-100" :style="{ width: timePercent + '%' }" />
                </div>
            </div>
        </div>

        <!-- Bellows Mini-game -->
        <div class="space-y-3">
            <p class="text-sm font-semibold text-slate-200">Bellows Pressure (Vertical Furnace)</p>
            <div
                @mousedown="startPushing"
                @mouseup="stopPushing"
                @mouseleave="stopPushing"
                class="rounded border border-slate-600 bg-slate-800 p-4"
            >
                <div class="flex items-end justify-center gap-8">
                    <div class="relative flex h-80 w-20 items-center justify-center overflow-hidden rounded bg-gradient-to-t from-slate-950 via-slate-900 to-red-950/80">
                        <div class="absolute inset-x-0 bottom-0 h-12 bg-orange-500/10 blur-sm" />

                        <!-- Moving sweet spot zone -->
                        <div
                            class="absolute inset-x-2 rounded border border-green-400/80 bg-green-500/20"
                            :style="{ bottom: `${sweetSpotStart}%`, height: `${sweetSpotHeight}%` }"
                        >
                            <div class="flex h-full items-center justify-center text-[10px] font-semibold tracking-wide text-green-300">
                                SWEET
                            </div>
                        </div>

                        <!-- Heat indicator -->
                        <div
                            :class="[
                                'absolute inset-x-0 h-1.5 shadow-lg transition-colors duration-75',
                                heatIndicatorClass,
                            ]"
                            :style="{ bottom: `${heat}%` }"
                        />

                        <!-- Heat marker orb -->
                        <div
                            :class="[
                                'absolute left-1/2 h-4 w-4 -translate-x-1/2 rounded-full border border-slate-100/60 shadow-lg transition-colors duration-75',
                                heatIndicatorClass,
                            ]"
                            :style="{ bottom: `${heat}%` }"
                        />
                    </div>

                    <div class="relative flex h-80 w-8 items-end rounded bg-slate-900">
                        <div class="w-full rounded bg-orange-500/80 transition-all duration-100" :style="{ height: `${timePercent}%` }" />
                        <div class="absolute -right-20 top-1/2 text-xs text-slate-400">Timer</div>
                    </div>
                </div>

                <div class="mt-4 rounded border border-slate-700 bg-slate-900/80 px-3 py-2 text-center text-sm text-slate-300">
                    Hold mouse button or <span class="font-semibold text-amber-300">SPACE</span> to push heat upward.
                    Release to let gravity cool the furnace.
                </div>
            </div>

            <!-- Instructions -->
            <div class="rounded border border-blue-600/50 bg-blue-900/20 p-3">
                <div class="flex items-start gap-2">
                    <AlertCircle class="mt-0.5 h-4 w-4 flex-shrink-0 text-blue-400" />
                    <p class="text-xs text-blue-200">
                        Hardcore mode: heat naturally falls over time. The sweet spot moves continuously up and down.
                        Chase the moving zone to maximize score.
                    </p>
                </div>
            </div>
        </div>

        <button
            v-if="gameFinished"
            @click="completeStage"
            class="w-full rounded bg-amber-500 px-4 py-3 font-semibold text-white transition hover:bg-amber-600"
        >
            Proceed to Smithing (Score: {{ score }}%)
        </button>
        <div
            v-else
            class="w-full rounded bg-slate-700 px-4 py-3 text-center font-semibold text-slate-300"
        >
            Smelting in progress... ({{ Math.round(gameTime / 1000) }}/10s)
        </div>
    </div>
</template>
