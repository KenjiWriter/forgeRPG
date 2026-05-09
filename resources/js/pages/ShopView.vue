<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import axios from 'axios';
import { toast } from 'vue-sonner';
import { index as shop } from '@/actions/App/Http/Controllers/Shop/ShopController';
import { purchase as shopPurchase } from '@/routes/shop';
import { formatNumber } from '@/lib/utils';
import { usePlayerStore } from '@/stores/usePlayerStore';

interface Player {
    id: number;
    level: number;
    gold: number;
}

interface ShopItem {
    id: number;
    name: string;
    rarity: string;
    mining_power: number;
    luck_bonus: number;
    buy_price: number;
    min_level: number;
    owned_quantity: number;
}

const props = defineProps<{
    player: Player;
    shop_items: ShopItem[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Shop', href: shop() }],
    },
});

const playerStore = usePlayerStore();
if (playerStore.userId === null || playerStore.userId !== props.player.id) {
    playerStore.userId = props.player.id;
}
playerStore.level = props.player.level;
playerStore.setGold(props.player.gold);

const purchasingId = ref<number | null>(null);

const rarityCardClass: Record<string, string> = {
    common: 'border-slate-500/70 bg-slate-900/65',
    uncommon: 'border-emerald-500/70 bg-emerald-950/35',
    rare: 'border-sky-500/70 bg-sky-950/35',
    epic: 'border-fuchsia-500/70 bg-fuchsia-950/35',
    legendary: 'border-amber-500/70 bg-amber-950/35',
    mythical: 'border-rose-500/70 bg-rose-950/35',
};

const rarityTextClass: Record<string, string> = {
    common: 'text-slate-300',
    uncommon: 'text-emerald-300',
    rare: 'text-sky-300',
    epic: 'text-fuchsia-300',
    legendary: 'text-amber-300',
    mythical: 'text-rose-300',
};

const shopItems = ref<ShopItem[]>(props.shop_items.map((item) => ({ ...item })));

const sortedItems = computed(() => [...shopItems.value].sort((a, b) => a.buy_price - b.buy_price));

function normalizedRarity(rarity: string): string {
    return rarity.toLowerCase();
}

function rarityLabel(rarity: string): string {
    const normalized = normalizedRarity(rarity);
    return normalized.charAt(0).toUpperCase() + normalized.slice(1);
}

function canBuy(item: ShopItem): boolean {
    return playerStore.gold >= item.buy_price && playerStore.level >= item.min_level;
}

function buyButtonLabel(item: ShopItem): string {
    if (purchasingId.value === item.id) {
        return 'Buying...';
    }

    if (playerStore.level < item.min_level) {
        return `Need Lvl ${item.min_level}`;
    }

    if (playerStore.gold < item.buy_price) {
        return 'Not Enough Gold';
    }

    return 'Buy';
}

async function purchaseItem(item: ShopItem): Promise<void> {
    if (!canBuy(item) || purchasingId.value !== null) {
        return;
    }

    purchasingId.value = item.id;

    try {
        const response = await axios.post<{ gold: number; message: string }>(
            shopPurchase.url(item.id),
            {},
            { withXSRFToken: true },
        );

        playerStore.setGold(response.data.gold);
        item.owned_quantity += 1;
        toast.success(response.data.message);
    } catch (error: unknown) {
        const message = axios.isAxiosError(error)
            ? (error.response?.data?.message ?? 'Purchase failed.')
            : 'Purchase failed.';

        toast.error(message);
    } finally {
        purchasingId.value = null;
    }
}
</script>

<template>
    <Head title="Shop" />

    <div class="min-h-screen bg-slate-950">
        <div class="mx-auto max-w-6xl px-4 py-8">
            <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="text-4xl font-bold text-amber-300">Stonewake Shop</h1>
                    <p class="mt-2 text-sm text-slate-300">Spend gold to buy stronger pickaxes for faster mining.</p>
                </div>

                <div class="rounded-lg border border-amber-500/40 bg-amber-900/20 px-4 py-3">
                    <p class="text-xs uppercase tracking-wide text-amber-200/80">Gold</p>
                    <p class="text-2xl font-bold text-amber-200">{{ formatNumber(playerStore.gold) }} G</p>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="item in sortedItems"
                    :key="item.id"
                    class="rounded-xl border p-4 shadow-sm transition hover:-translate-y-0.5"
                    :class="rarityCardClass[normalizedRarity(item.rarity)] ?? rarityCardClass.common"
                >
                    <div class="mb-3 flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-white">{{ item.name }}</h2>
                            <p class="text-xs font-semibold uppercase tracking-wide" :class="rarityTextClass[normalizedRarity(item.rarity)] ?? rarityTextClass.common">
                                {{ rarityLabel(item.rarity) }}
                            </p>
                        </div>
                        <span class="rounded-md border border-slate-500/40 bg-black/20 px-2 py-1 text-xs text-slate-200">
                            Owned: {{ item.owned_quantity }}
                        </span>
                    </div>

                    <div class="space-y-1 text-sm text-slate-200">
                        <p>+{{ item.mining_power }} Mining Power</p>
                        <p>+{{ item.luck_bonus }} Luck</p>
                        <p>Requires Level {{ item.min_level }}</p>
                    </div>

                    <div class="mt-4 flex items-center justify-between">
                        <p class="text-lg font-bold text-amber-200">{{ formatNumber(item.buy_price) }} G</p>
                        <button
                            class="rounded-md px-3 py-2 text-sm font-semibold transition"
                            :class="canBuy(item) && purchasingId !== item.id
                                ? 'bg-emerald-500 text-emerald-950 hover:bg-emerald-400'
                                : 'cursor-not-allowed bg-slate-700 text-slate-300'"
                            :disabled="!canBuy(item) || purchasingId !== null"
                            @click="purchaseItem(item)"
                        >
                            {{ buyButtonLabel(item) }}
                        </button>
                    </div>
                </article>
            </div>
        </div>
    </div>
</template>