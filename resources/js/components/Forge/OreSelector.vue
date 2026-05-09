<script setup lang="ts">
import { ref, computed } from 'vue';
import axios from 'axios';
import { init as forgeInit } from '@/routes/forge';
import { AlertCircle, Zap } from 'lucide-vue-next';

interface OreInput {
    ore_type_id: number;
    quantity: number;
}

interface InventoryItem {
    id: number;
    name: string;
    quantity: number;
}

const props = defineProps<{
    inventory: InventoryItem[];
}>();

const emit = defineEmits<{
    selectionComplete: [
        sessionId: string,
        ores: OreInput[],
        targetSlot: string
    ];
}>();

const selectedOres = ref<{ ore_type_id: number; quantity: number }[]>([]);
const targetSlot = ref('helmet');
const processing = ref(false);
const errorMessage = ref('');

const selectedOreCount = computed(() => {
    return selectedOres.value.reduce((total, ore) => total + ore.quantity, 0);
});

const hasExactlyThreeOres = computed(() => {
    return selectedOreCount.value === 3;
});

const potentialQuality = computed(() => {
    if (selectedOres.value.length === 0) return 'None';
    
    // Find the rarity tier of selected ores (lower ID = more common, higher = rarer)
    const avgOreId = selectedOres.value.reduce((sum, ore) => sum + ore.ore_type_id, 0) / selectedOres.value.length;
    
    if (avgOreId <= 3) return 'Common';
    if (avgOreId <= 6) return 'Uncommon';
    if (avgOreId <= 9) return 'Rare';
    return 'Legendary';
});

const selectedOreNames = computed(() => {
    return selectedOres.value
        .map((ore) => {
            const item = props.inventory.find((inv) => inv.id === ore.ore_type_id);
            return `${item?.name} (${ore.quantity})`;
        })
        .join(', ');
});

function toggleOre(oreId: number) {
    const existing = selectedOres.value.findIndex((o) => o.ore_type_id === oreId);
    
    if (existing !== -1) {
        selectedOres.value.splice(existing, 1);
    } else if (selectedOres.value.length < 3) {
        selectedOres.value.push({
            ore_type_id: oreId,
            quantity: 1,
        });
    }
}

function increaseQuantity(index: number) {
    const ore = selectedOres.value[index];
    const inventoryItem = props.inventory.find((inv) => inv.id === ore.ore_type_id);
    
    if (inventoryItem && ore.quantity < inventoryItem.quantity) {
        ore.quantity += 1;
    }
}

function decreaseQuantity(index: number) {
    const ore = selectedOres.value[index];
    
    if (ore.quantity > 1) {
        ore.quantity -= 1;
    }
}

async function startForge() {
    if (!hasExactlyThreeOres.value) return;

    processing.value = true;
    errorMessage.value = '';

    const expandedOreInputs: OreInput[] = selectedOres.value.flatMap((ore) =>
        Array.from({ length: ore.quantity }, () => ({
            ore_type_id: ore.ore_type_id,
            quantity: 1,
        }))
    );

    try {
        const response = await axios.post(forgeInit.url(), {
            target_slot: targetSlot.value,
            ore_inputs: expandedOreInputs,
        }, {
            withCredentials: true,
            withXSRFToken: true,
        });

        const data = response.data as {
            forge_session_id?: string;
            session_id?: string;
        };

        const sessionId = data.forge_session_id ?? data.session_id;
        if (!sessionId) {
            errorMessage.value = 'Forge session was not created. Please try again.';
            return;
        }

        emit('selectionComplete', sessionId, expandedOreInputs, targetSlot.value);
    } catch (error: unknown) {
        if (axios.isAxiosError(error)) {
            const validationErrors = error.response?.data?.errors as Record<string, string[]> | undefined;
            const message = error.response?.data?.message as string | undefined;

            if (validationErrors && Object.keys(validationErrors).length > 0) {
                errorMessage.value = Object.values(validationErrors)
                    .flat()
                    .join(' ');
            } else {
                errorMessage.value = message ?? 'Failed to initialize forge.';
            }
        } else {
            errorMessage.value = 'Failed to initialize forge.';
        }
    } finally {
        processing.value = false;
    }
}
</script>

<template>
    <div class="space-y-6 rounded-lg border border-slate-700 bg-slate-900 p-6">
        <!-- Equipment Slot Selection -->
        <div>
            <label class="block text-sm font-semibold text-slate-200">
                Target Equipment Slot
            </label>
            <div class="mt-2 grid grid-cols-3 gap-2 sm:grid-cols-6">
                <button
                    v-for="slot in ['helmet', 'armor', 'pants', 'boots', 'weapon', 'pickaxe']"
                    :key="slot"
                    @click="targetSlot = slot"
                    :class="[
                        'rounded border px-3 py-2 text-center text-sm font-medium transition',
                        targetSlot === slot
                            ? 'border-amber-400 bg-amber-400/20 text-amber-400'
                            : 'border-slate-600 bg-slate-800 text-slate-300 hover:border-slate-500',
                    ]"
                >
                    {{ slot }}
                </button>
            </div>
        </div>

        <!-- Ore Selection -->
        <div>
            <div class="mb-3 flex items-center justify-between">
                <label class="block text-sm font-semibold text-slate-200">
                    Select Ores ({{ selectedOreCount }}/3)
                </label>
                <span class="text-xs text-slate-400">Rule of 3 Ores</span>
            </div>

            <div class="space-y-2">
                <div v-for="item in inventory" :key="item.id" class="rounded border border-slate-700 bg-slate-800 p-3">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <p class="font-medium text-slate-200">{{ item.name }}</p>
                            <p class="text-xs text-slate-400">Owned: {{ item.quantity }}</p>
                        </div>
                        <button
                            @click="toggleOre(item.id)"
                            :class="[
                                'rounded px-3 py-1 text-sm font-medium transition',
                                selectedOres.some((o) => o.ore_type_id === item.id)
                                    ? 'bg-amber-500 text-white hover:bg-amber-600'
                                    : 'border border-slate-600 text-slate-300 hover:border-slate-500',
                            ]"
                        >
                            {{ selectedOres.some((o) => o.ore_type_id === item.id) ? '✓ Selected' : 'Select' }}
                        </button>
                    </div>
                </div>

                <div v-if="inventory.length === 0" class="rounded border border-dashed border-slate-600 p-4 text-center text-slate-400">
                    No ores in inventory. Mine some first!
                </div>
            </div>
        </div>

        <!-- Quantity Adjustment (for selected ores) -->
        <div v-if="selectedOres.length > 0" class="space-y-2">
            <label class="block text-sm font-semibold text-slate-200">
                Ore Quantities
            </label>
            <div v-for="(ore, index) in selectedOres" :key="`qty-${ore.ore_type_id}`" class="flex items-center justify-between rounded bg-slate-800 px-3 py-2">
                <span class="text-sm text-slate-300">
                    {{ inventory.find((i) => i.id === ore.ore_type_id)?.name }}
                </span>
                <div class="flex items-center gap-2">
                    <button @click="decreaseQuantity(index)" class="rounded bg-slate-700 px-2 py-1 text-slate-400 hover:bg-slate-600">
                        −
                    </button>
                    <span class="w-8 text-center text-slate-300">{{ ore.quantity }}</span>
                    <button @click="increaseQuantity(index)" class="rounded bg-slate-700 px-2 py-1 text-slate-400 hover:bg-slate-600">
                        +
                    </button>
                </div>
            </div>
        </div>

        <!-- Potential Quality Preview -->
        <div class="rounded border border-amber-600/50 bg-amber-900/20 p-4">
            <div class="flex items-center gap-3">
                <Zap class="h-5 w-5 text-amber-400" />
                <div>
                    <p class="text-sm font-semibold text-amber-400">Potential Quality</p>
                    <p class="text-sm text-amber-200">
                        {{ hasExactlyThreeOres ? potentialQuality : 'Select exactly 3 ores to preview quality' }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Selected Ores Summary -->
        <div v-if="hasExactlyThreeOres" class="rounded border border-green-600/50 bg-green-900/20 p-4">
            <p class="text-sm font-semibold text-green-400">
                3/3 Ores selected. Ready to forge: {{ selectedOreNames }}
            </p>
        </div>

        <!-- Error message if not complete -->
        <div v-else-if="selectedOres.length > 0" class="rounded border border-orange-600/50 bg-orange-900/20 p-4">
            <div class="flex items-start gap-3">
                <AlertCircle class="mt-0.5 h-5 w-5 flex-shrink-0 text-orange-400" />
                <div>
                    <p class="text-sm font-semibold text-orange-400">
                        Need exactly 3 ores total (currently {{ selectedOreCount }})
                    </p>
                    <p class="text-xs text-orange-200">
                        Rule of 3 Ores requires exactly three selected ores before forge initialization.
                    </p>
                </div>
            </div>
        </div>

        <!-- Start Forge Button -->
        <button
            @click="startForge"
            :disabled="!hasExactlyThreeOres || processing"
            :class="[
                'w-full rounded px-4 py-3 font-semibold transition',
                hasExactlyThreeOres && !processing
                    ? 'bg-amber-500 text-white hover:bg-amber-600 disabled:opacity-50'
                    : 'bg-slate-700 text-slate-400 cursor-not-allowed opacity-50',
            ]"
        >
            {{ processing ? 'Initializing Forge...' : 'Start Forge' }}
        </button>

        <!-- Error display -->
        <div v-if="errorMessage" class="rounded border border-red-600/50 bg-red-900/20 p-3 text-sm text-red-400">
            {{ errorMessage }}
        </div>
    </div>
</template>
