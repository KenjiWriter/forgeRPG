<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { AlertCircle, Droplet } from 'lucide-vue-next';

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

// Game state
const temperature = ref(100); // 0-100
const targetTemperature = ref(75); // Fixed target
const gameActive = ref(true);
const stopped = ref(false);
const gameStartTime = ref(0);
const stopTime = ref(0);

// Cooling mechanics
const coolingRate = 8; // temp decrease per second
const gameEndTime = 12000; // 12 seconds

const gameTime = computed(() => {
    if (stopped.value) {
        return stopTime.value;
    }
    return Date.now() - gameStartTime.value;
});

const timePercent = computed(() => Math.min(100, (gameTime.value / gameEndTime) * 100));

// Score calculation based on accuracy to target
const temperatureDifference = computed(() => Math.abs(temperature.value - targetTemperature.value));

const score = computed(() => {
    // Perfect is within 5 degrees
    if (temperatureDifference.value <= 5) return 100;
    if (temperatureDifference.value <= 10) return 90;
    if (temperatureDifference.value <= 15) return 75;
    if (temperatureDifference.value <= 20) return 60;
    if (temperatureDifference.value <= 30) return 40;
    if (temperatureDifference.value <= 40) return 20;
    return 0;
});

const accuracy = computed(() => {
    if (temperatureDifference.value <= 5) return 'Perfect!';
    if (temperatureDifference.value <= 10) return 'Excellent';
    if (temperatureDifference.value <= 15) return 'Good';
    if (temperatureDifference.value <= 20) return 'Fair';
    if (temperatureDifference.value <= 30) return 'Poor';
    return 'Failed';
});

const needleColor = computed(() => {
    const diff = temperatureDifference.value;
    if (diff <= 5) return 'text-green-500';
    if (diff <= 15) return 'text-yellow-500';
    if (diff <= 25) return 'text-orange-500';
    return 'text-red-500';
});

const needlePosition = computed(() => `${temperature.value}%`);

function stopQuench() {
    if (!gameActive.value || stopped.value) return;

    gameActive.value = false;
    stopped.value = true;
    stopTime.value = Date.now() - gameStartTime.value;
}

function completeStage() {
    emit('complete', score.value);
}

onMounted(() => {
    gameStartTime.value = Date.now();

    const interval = setInterval(() => {
        if (stopped.value) {
            clearInterval(interval);
            return;
        }

        const elapsed = (Date.now() - gameStartTime.value) / 1000;
        temperature.value = Math.max(0, 100 - elapsed * coolingRate);

        // Auto-stop if reaches 0
        if (temperature.value <= 0) {
            stopped.value = true;
            stopTime.value = Date.now() - gameStartTime.value;
            gameActive.value = false;
            clearInterval(interval);
        }
    }, 50);
});
</script>

<template>
    <div class="space-y-6 rounded-lg border border-slate-700 bg-slate-900 p-6">
        <!-- Header -->
        <div>
            <div class="mb-2 flex items-center gap-2">
                <Droplet class="h-5 w-5 text-cyan-500" />
                <h2 class="text-2xl font-bold text-slate-200">Quenching Stage</h2>
            </div>
            <p class="text-sm text-slate-400">
                Cool the forged item to the optimal temperature. Watch the cooling bar and click STOP when the needle reaches the target zone.
            </p>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-4 gap-4">
            <div class="rounded bg-slate-800 p-3">
                <p class="text-xs text-slate-500">SCORE</p>
                <p class="text-2xl font-bold text-cyan-400">{{ score }}%</p>
            </div>
            <div class="rounded bg-slate-800 p-3">
                <p class="text-xs text-slate-500">ACCURACY</p>
                <p class="text-2xl font-bold" :class="needleColor">{{ accuracy }}</p>
            </div>
            <div class="rounded bg-slate-800 p-3">
                <p class="text-xs text-slate-500">CURRENT TEMP</p>
                <p class="text-2xl font-bold text-orange-400">{{ Math.round(temperature) }}°</p>
            </div>
            <div class="rounded bg-slate-800 p-3">
                <p class="text-xs text-slate-500">TARGET TEMP</p>
                <p class="text-2xl font-bold text-blue-400">{{ targetTemperature }}°</p>
            </div>
        </div>

        <!-- Progress Bar -->
        <div class="space-y-2">
            <p class="text-xs text-slate-500">COOLING PROGRESS</p>
            <div class="h-2 w-full rounded-full bg-slate-800">
                <div class="h-full rounded-full bg-cyan-500 transition-all duration-100" :style="{ width: timePercent + '%' }" />
            </div>
        </div>

        <!-- Cooling Thermometer -->
        <div class="space-y-3">
            <p class="text-sm font-semibold text-slate-200">Temperature Control</p>

            <div class="rounded border border-slate-600 bg-slate-800 p-6">
                <!-- Thermometer Bar -->
                <div class="relative h-32 rounded bg-gradient-to-b from-red-950 via-yellow-950 to-blue-950 overflow-hidden mb-4">
                    <!-- Temperature gradient zones -->
                    <div class="absolute inset-0 w-full h-full">
                        <!-- Hot zone (90-100) - red -->
                        <div class="absolute top-0 h-1/10 w-full bg-red-500/20" />
                        <!-- Target zone (70-80) - blue -->
                        <div
                            class="absolute w-full bg-blue-500/30 border-t-2 border-b-2 border-blue-400"
                            :style="{ top: `${100 - 80}%`, height: '10%' }"
                        />
                        <!-- Cold zone (0-10) - cyan -->
                        <div class="absolute bottom-0 h-1/10 w-full bg-cyan-500/20" />
                    </div>

                    <!-- Needle (current temperature) -->
                    <div
                        class="absolute w-0.5 h-full top-0 transition-all duration-50 bg-white shadow-lg"
                        :style="{ left: needlePosition }"
                    >
                        <div :class="['absolute left-1/2 -translate-x-1/2 top-0 text-sm font-bold', needleColor]">
                            ▼
                        </div>
                    </div>

                    <!-- Temperature scale markers -->
                    <div class="absolute inset-0 flex flex-col justify-between text-xs text-slate-400 px-2 py-1 pointer-events-none">
                        <span>100°</span>
                        <span>50°</span>
                        <span>0°</span>
                    </div>
                </div>

                <!-- Legend -->
                <div class="grid grid-cols-3 gap-2 text-xs">
                    <div class="flex items-center gap-2">
                        <div class="h-3 w-3 rounded bg-red-500" />
                        <span class="text-slate-400">Too Hot</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="h-3 w-3 rounded bg-blue-500" />
                        <span class="text-slate-400">Target Zone</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="h-3 w-3 rounded bg-cyan-500" />
                        <span class="text-slate-400">Too Cold</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Instructions -->
        <div class="rounded border border-blue-600/50 bg-blue-900/20 p-3">
            <div class="flex items-start gap-2">
                <AlertCircle class="mt-0.5 h-4 w-4 flex-shrink-0 text-blue-400" />
                <p class="text-xs text-blue-200">
                    The item is cooling from 100° to 0°. Stop it in the blue target zone (around 75°) for the best results.
                    The closer to {{ targetTemperature }}°, the higher your score!
                </p>
            </div>
        </div>

        <!-- Stop Button -->
        <button
            @click="stopQuench"
            :disabled="!gameActive"
            :class="[
                'w-full rounded px-4 py-3 font-semibold transition',
                gameActive
                    ? 'bg-cyan-500 text-white hover:bg-cyan-600 animate-pulse'
                    : 'bg-slate-700 text-slate-400 cursor-not-allowed opacity-50',
            ]"
        >
            {{ gameActive ? '🛑 STOP QUENCHING' : `Quenching Complete (Score: ${score}%)` }}
        </button>

        <!-- Complete Button -->
        <button
            v-if="stopped"
            @click="completeStage"
            :class="[
                'w-full rounded px-4 py-3 font-semibold transition',
                'bg-cyan-500 text-white hover:bg-cyan-600',
            ]"
        >
            Proceed to Result (Score: {{ score }}%)
        </button>
    </div>
</template>

<style scoped>
@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.7;
    }
}

.animate-pulse {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}
</style>
