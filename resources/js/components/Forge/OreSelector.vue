<script setup lang="ts">
import { ref, computed } from 'vue';
import axios from 'axios';
import { init as forgeInit } from '@/routes/forge';

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
    selectionComplete: [sessionId: string, ores: OreInput[], targetSlot: string];
}>();

const selectedOres = ref<{ ore_type_id: number; quantity: number }[]>([]);
const targetSlot = ref('helmet');
const processing = ref(false);
const errorMessage = ref('');

const selectedOreCount = computed(() => {
    return selectedOres.value.reduce((total, ore) => total + ore.quantity, 0);
});

const potentialQuality = computed(() => {
    if (selectedOres.value.length === 0) return 'None';
    const avgOreId = selectedOres.value.reduce((sum, ore) => sum + ore.ore_type_id, 0) / selectedOres.value.length;
    if (avgOreId <= 2) return 'Common';
    if (avgOreId <= 5) return 'Uncommon';
    if (avgOreId <= 8) return 'Rare';
    if (avgOreId <= 9) return 'Epic';
    return 'Legendary';
});

const selectedOreNames = computed(() => {
    return selectedOres.value
        .map((ore) => {
            const inv = props.inventory.find((i) => i.id === ore.ore_type_id);
            return inv ? `${inv.name} (${ore.quantity})` : '';
        })
        .filter(Boolean)
        .join(', ');
});

const hasExactlyThreeOres = computed(() => selectedOreCount.value === 3);

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
        const response = await axios.post(
            forgeInit.url(),
            {
                target_slot: targetSlot.value,
                ore_inputs: expandedOreInputs,
            },
            { withCredentials: true, withXSRFToken: true }
        );

        const data = response.data as { forge_session_id?: string; session_id?: string };
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
                errorMessage.value = Object.values(validationErrors).flat().join(' ');
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
    <div class="space-y-8 rounded-lg p-8" style="background: linear-gradient(135deg, rgba(0,0,0,0.4) 0%, rgba(180,83,9,0.1) 100%); border: 3px solid #a16207; box-shadow: inset 0 0 20px rgba(217, 119, 6, 0.2), 0 8px 16px rgba(0, 0, 0, 0.8);">
        <!-- Equipment Slot Selection -->
        <div>
            <div class="mb-4 flex items-center gap-2">
                <span class="text-2xl">📜</span>
                <label class="block text-lg font-bold text-amber-300" style="font-family: 'Cinzel', serif;">
                    Choose Your Craft
                </label>
            </div>
            <p class="mb-4 text-sm text-amber-100/60">Select which equipment slot to forge</p>
            <div class="blueprint-bg rounded-lg p-4 mb-4">
                <div class="grid grid-cols-3 gap-3 sm:grid-cols-6">
                    <button
                        v-for="slot in ['helmet', 'armor', 'pants', 'boots', 'weapon', 'pickaxe']"
                        :key="slot"
                        @click="targetSlot = slot"
                        :class="[
                            'relative rounded-lg border-2 px-3 py-3 text-center text-sm font-semibold transition transform hover:scale-105',
                            targetSlot === slot
                                ? 'border-amber-400 bg-amber-600 text-white shadow-lg'
                                : 'border-amber-700/50 bg-stone-800 text-amber-200 hover:border-amber-500 hover:bg-stone-700',
                        ]"
                    >
                        <div class="text-lg mb-1">
                            {{ slot === 'helmet' ? '🗡️' : slot === 'armor' ? '🛡️' : slot === 'pants' ? '👖' : slot === 'boots' ? '👢' : slot === 'weapon' ? '⚔️' : '⛏️' }}
                        </div>
                        {{ slot }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Ore Selection -->
        <div>
            <div class="mb-4 flex items-center gap-2">
                <span class="text-2xl">🪣</span>
                <label class="block text-lg font-bold text-amber-300" style="font-family: 'Cinzel', serif;">
                    Bucket of Raw Materials
                </label>
            </div>
            <p class="mb-4 text-sm text-amber-100/60">Select exactly 3 ores to begin crafting</p>
            <div class="ore-bucket rounded-lg p-6 space-y-3">
                <div v-for="item in inventory" :key="item.id" class="flex items-center justify-between rounded-lg border-2 border-amber-700/40 bg-stone-900/50 p-3">
                    <div>
                        <p class="font-medium text-amber-100">{{ item.name }}</p>
                        <p class="text-xs text-amber-100/50">Available: <span class="font-bold text-amber-300">{{ item.quantity }}</span></p>
                    </div>
                    <button
                        @click="toggleOre(item.id)"
                        :class="[
                            'rounded-lg px-4 py-2 text-sm font-bold transition transform hover:scale-105',
                            selectedOres.some((o) => o.ore_type_id === item.id)
                                ? 'bg-gradient-to-br from-amber-500 to-amber-600 text-white hover:from-amber-600 hover:to-amber-700 shadow-lg'
                                : 'border-2 border-amber-700 bg-stone-700 text-amber-200 hover:bg-stone-600',
                        ]"
                    >
                        {{ selectedOres.some((o) => o.ore_type_id === item.id) ? '✓ Selected' : 'Add' }}
                    </button>
                </div>
                <div v-if="inventory.length === 0" class="rounded border-2 border-dashed border-amber-700/50 p-6 text-center text-amber-100/50">
                    No ores in inventory. Mine some first!
                </div>
            </div>
        </div>

        <!-- Quantity Adjustment -->
        <div v-if="selectedOres.length > 0" class="space-y-3 rounded-lg bg-stone-900/50 p-4 border border-amber-800/50">
            <label class="block text-sm font-semibold text-amber-300" style="font-family: 'Cinzel', serif;">
                ⚙️ Ore Quantities ({{ selectedOreCount }}/3)
            </label>
            <div v-for="(ore, index) in selectedOres" :key="`qty-${ore.ore_type_id}`" class="flex items-center justify-between rounded-lg bg-stone-800 px-3 py-2 border border-amber-700/30">
                <span class="text-sm text-amber-100">{{ inventory.find((i) => i.id === ore.ore_type_id)?.name }}</span>
                <div class="flex items-center gap-2">
                    <button @click="decreaseQuantity(index)" class="rounded-lg bg-stone-700 px-3 py-1 text-amber-300 hover:bg-stone-600 font-bold border border-amber-700/50">
                        −
                    </button>
                    <span class="w-8 text-center text-amber-300 font-bold">{{ ore.quantity }}</span>
                    <button @click="increaseQuantity(index)" class="rounded-lg bg-stone-700 px-3 py-1 text-amber-300 hover:bg-stone-600 font-bold border border-amber-700/50">
                        +
                    </button>
                </div>
            </div>
        </div>

        <!-- Potential Quality -->
        <div class="rounded-lg border-2 border-amber-700 bg-gradient-to-br from-amber-900/30 to-orange-900/20 p-4">
            <div class="flex items-center gap-3">
                <span class="text-2xl">✨</span>
                <div>
                    <p class="text-sm font-semibold text-amber-300" style="font-family: 'Cinzel', serif;">Potential Quality</p>
                    <p class="text-sm text-amber-100">{{ hasExactlyThreeOres ? potentialQuality : 'Select exactly 3 ores' }}</p>
                </div>
            </div>
        </div>

        <!-- Ready State -->
        <div v-if="hasExactlyThreeOres" class="rounded-lg border-2 border-green-700 bg-gradient-to-br from-green-900/30 to-emerald-900/20 p-4">
            <p class="text-sm font-semibold text-green-300">✅ Ready to Forge! {{ selectedOreNames }}</p>
        </div>

        <!-- Need More Ores -->
        <div v-else-if="selectedOres.length > 0" class="rounded-lg border-2 border-orange-700 bg-gradient-to-br from-orange-900/30 to-red-900/20 p-4">
            <p class="text-sm font-semibold text-orange-300">⚠️ Need exactly 3 ores total (currently {{ selectedOreCount }})</p>
        </div>

        <!-- Start Button -->
        <button
            @click="startForge"
            :disabled="!hasExactlyThreeOres || processing"
            :class="[
                'w-full rounded-lg px-6 py-4 font-bold text-lg transition transform hover:scale-105 active:scale-95',
                hasExactlyThreeOres && !processing
                    ? 'bg-gradient-to-b from-amber-500 via-amber-600 to-orange-600 text-white hover:from-amber-600 hover:via-amber-700 hover:to-orange-700 shadow-lg'
                    : 'bg-stone-700 text-stone-400 cursor-not-allowed opacity-50',
            ]"
        >
            🔨 {{ processing ? 'Initializing Forge...' : 'Start Forge' }} 🔨
        </button>

        <!-- Error -->
        <div v-if="errorMessage" class="rounded-lg border-2 border-red-700 bg-red-900/30 p-4 text-sm text-red-300 font-medium">
            ❌ {{ errorMessage }}
        </div>
    </div>
</template>
