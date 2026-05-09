<script setup lang="ts">
import { ref } from 'vue';
import { forge } from '@/routes';
import { Trophy, Zap, Shield, Sword, Pickaxe, Footprints } from 'lucide-vue-next';

interface ForgeCraftedItem {
    id: string;
    name: string;
    target_slot: string;
    forge_grade: number;
    hp_bonus: number;
    attack_bonus: number;
    defense_bonus: number;
    mining_speed_bonus: number;
    attack_speed_bonus: number;
    dodge_bonus: number;
    final_stats: Record<string, number>;
}

const props = defineProps<{
    forgeSessionId: string;
    item: ForgeCraftedItem;
    grade: number;
    combinedScore: number;
    smeltingScore: number;
    smithingScore: number;
    quenchScore: number;
}>();

const emit = defineEmits<{
    itemCrafted: [item: ForgeCraftedItem, grade: number, combinedScore: number, name: string];
    returnToSelection: [];
}>();

const itemName = ref(props.item.name || `Forged ${props.item.target_slot}`);
const processing = ref(false);
const errorMessage = ref('');

const gradeLabels: Record<number, string> = {
    1: 'I',
    2: 'II',
    3: 'III',
    4: 'IV',
    5: 'V',
    6: 'VI',
    7: 'VII',
    8: 'VIII',
    9: 'IX',
    10: 'X',
};

const gradeColors: Record<number, string> = {
    1: 'text-slate-400 bg-slate-800',
    2: 'text-slate-300 bg-slate-700',
    3: 'text-green-400 bg-green-950',
    4: 'text-green-400 bg-green-950',
    5: 'text-blue-400 bg-blue-950',
    6: 'text-blue-400 bg-blue-950',
    7: 'text-purple-400 bg-purple-950',
    8: 'text-purple-400 bg-purple-950',
    9: 'text-orange-400 bg-orange-950',
    10: 'text-yellow-400 bg-yellow-950',
};

const gradeDescriptions: Record<number, string> = {
    1: 'Common',
    2: 'Uncommon',
    3: 'Rare',
    4: 'Rare',
    5: 'Epic',
    6: 'Epic',
    7: 'Legendary',
    8: 'Legendary',
    9: 'Mythic',
    10: 'Divine',
};

async function acquireItem() {
    processing.value = true;
    errorMessage.value = '';

    try {
        const response = await fetch(forge.complete(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            credentials: 'include',
            body: JSON.stringify({
                forge_session_id: props.forgeSessionId,
                smelting_score: props.smeltingScore,
                smithing_score: props.smithingScore,
                quench_score: props.quenchScore,
                item_name: itemName.value,
            }),
        });

        if (response.ok) {
            const data = await response.json();
            emit('itemCrafted', props.item, props.grade, props.combinedScore, itemName.value);
        } else {
            const error = await response.json();
            errorMessage.value = error.message || 'Failed to craft item';
        }
    } catch (error) {
        errorMessage.value = 'Network error. Please try again.';
    } finally {
        processing.value = false;
    }
}

function returnToSelection() {
    emit('returnToSelection');
}
</script>

<template>
    <div class="space-y-6">
        <!-- Celebration Header -->
        <div class="rounded-lg border border-amber-600 bg-gradient-to-r from-amber-950 to-orange-950 p-8 text-center">
            <Trophy class="mb-4 inline-block h-16 w-16 text-amber-400" />
            <h1 class="text-4xl font-bold text-amber-400">Item Crafted!</h1>
            <p class="mt-2 text-amber-200">Your forged item is ready to equip.</p>
        </div>

        <!-- Item Card -->
        <div class="rounded-lg border border-slate-700 bg-slate-900 p-8">
            <!-- Item Name Input -->
            <div class="mb-6">
                <label class="block text-sm font-semibold text-slate-200 mb-2">Item Name</label>
                <input
                    v-model="itemName"
                    type="text"
                    maxlength="255"
                    placeholder="Enter item name (optional)"
                    class="w-full rounded border border-slate-600 bg-slate-800 px-3 py-2 text-slate-200 placeholder-slate-500 focus:border-amber-500 focus:outline-none"
                />
            </div>

            <!-- Grade and Slot -->
            <div class="mb-6 grid grid-cols-3 gap-4">
                <div>
                    <p class="text-xs text-slate-500 mb-1">GRADE</p>
                    <div :class="['rounded px-4 py-3 text-center font-bold text-2xl', gradeColors[grade]]">
                        {{ gradeLabels[grade] }}
                    </div>
                    <p class="text-xs text-slate-400 text-center mt-1">{{ gradeDescriptions[grade] }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-500 mb-1">QUALITY SCORE</p>
                    <div class="rounded bg-slate-800 px-4 py-3 text-center font-bold text-2xl text-cyan-400">
                        {{ combinedScore }}%
                    </div>
                </div>
                <div>
                    <p class="text-xs text-slate-500 mb-1">SLOT</p>
                    <div class="rounded bg-slate-800 px-4 py-3 text-center font-bold text-2xl text-blue-400">
                        {{ item.target_slot }}
                    </div>
                </div>
            </div>

            <!-- Stage Scores -->
            <div class="mb-6 grid grid-cols-3 gap-3">
                <div class="rounded border border-orange-600/50 bg-orange-900/20 p-3">
                    <p class="text-xs text-orange-400 font-semibold">SMELTING</p>
                    <p class="text-xl font-bold text-orange-300">{{ smeltingScore }}%</p>
                </div>
                <div class="rounded border border-blue-600/50 bg-blue-900/20 p-3">
                    <p class="text-xs text-blue-400 font-semibold">SMITHING</p>
                    <p class="text-xl font-bold text-blue-300">{{ smithingScore }}%</p>
                </div>
                <div class="rounded border border-cyan-600/50 bg-cyan-900/20 p-3">
                    <p class="text-xs text-cyan-400 font-semibold">QUENCHING</p>
                    <p class="text-xl font-bold text-cyan-300">{{ quenchScore }}%</p>
                </div>
            </div>

            <!-- Item Stats -->
            <div>
                <p class="mb-3 text-sm font-semibold text-slate-200">Final Stats</p>
                <div class="space-y-2">
                    <div class="flex items-center justify-between rounded bg-slate-800 px-4 py-2">
                        <span class="flex items-center gap-2 text-slate-300">
                            <Zap class="h-4 w-4" />
                            Health
                        </span>
                        <span class="font-semibold text-green-400">+{{ item.hp_bonus }}</span>
                    </div>
                    <div class="flex items-center justify-between rounded bg-slate-800 px-4 py-2">
                        <span class="flex items-center gap-2 text-slate-300">
                            <Sword class="h-4 w-4" />
                            Attack
                        </span>
                        <span class="font-semibold text-red-400">+{{ item.attack_bonus }}</span>
                    </div>
                    <div class="flex items-center justify-between rounded bg-slate-800 px-4 py-2">
                        <span class="flex items-center gap-2 text-slate-300">
                            <Shield class="h-4 w-4" />
                            Defense
                        </span>
                        <span class="font-semibold text-blue-400">+{{ item.defense_bonus }}</span>
                    </div>
                    <div class="flex items-center justify-between rounded bg-slate-800 px-4 py-2">
                        <span class="flex items-center gap-2 text-slate-300">
                            <Pickaxe class="h-4 w-4" />
                            Mining Speed
                        </span>
                        <span class="font-semibold text-orange-400">+{{ item.mining_speed_bonus }}</span>
                    </div>
                    <div class="flex items-center justify-between rounded bg-slate-800 px-4 py-2">
                        <span class="flex items-center gap-2 text-slate-300">
                            <Zap class="h-4 w-4" />
                            Attack Speed
                        </span>
                        <span class="font-semibold text-purple-400">+{{ item.attack_speed_bonus }}</span>
                    </div>
                    <div class="flex items-center justify-between rounded bg-slate-800 px-4 py-2">
                        <span class="flex items-center gap-2 text-slate-300">
                            <Footprints class="h-4 w-4" />
                            Dodge
                        </span>
                        <span class="font-semibold text-cyan-400">+{{ item.dodge_bonus }}%</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="grid grid-cols-2 gap-4">
            <button
                @click="acquireItem"
                :disabled="processing"
                :class="[
                    'rounded px-4 py-3 font-semibold transition',
                    processing
                        ? 'bg-slate-700 text-slate-400 cursor-not-allowed opacity-50'
                        : 'bg-amber-500 text-white hover:bg-amber-600',
                ]"
            >
                {{ processing ? 'Acquiring...' : '✓ Acquire Item' }}
            </button>
            <button
                @click="returnToSelection"
                :disabled="processing"
                :class="[
                    'rounded px-4 py-3 font-semibold transition',
                    processing
                        ? 'bg-slate-700 text-slate-400 cursor-not-allowed opacity-50'
                        : 'border border-slate-600 text-slate-300 hover:border-slate-500 hover:bg-slate-800',
                ]"
            >
                ← Forge Another
            </button>
        </div>

        <!-- Error display -->
        <div v-if="errorMessage" class="rounded border border-red-600/50 bg-red-900/20 p-3 text-sm text-red-400">
            {{ errorMessage }}
        </div>
    </div>
</template>
