import { defineStore } from 'pinia';
import { ref } from 'vue';

interface Pickaxe {
    id: number;
    name: string;
    mining_power: number;
    mining_speed: number;
    luck_bonus: number;
    stamina_regen_bonus: number;
}

export const usePlayerStore = defineStore('player', () => {
    const userId = ref<number | null>(null);
    const level = ref<number>(1);
    const experience = ref<number>(0);
    const gold = ref<number>(0);
    const nextLevelExp = ref<number>(100);
    const hp = ref<number>(100);
    const maxHp = ref<number>(100);
    /** Server-side stamina value captured at staminaLastUpdatedAt */
    const stamina = ref<number>(100);
    const staminaLastUpdatedAt = ref<Date | null>(null);
    const currentPickaxe = ref<Pickaxe | null>(null);

    function initialize(
        player: { id: number; level: number; experience: number; gold: number; next_level_exp: number },
        stats: { stamina: number; stamina_last_updated_at: string; hp: number },
        pickaxe?: Pickaxe | null,
    ): void {
        userId.value = player.id;
        level.value = player.level;
        experience.value = player.experience;
        gold.value = player.gold;
        nextLevelExp.value = player.next_level_exp;
        hp.value = stats.hp;
        stamina.value = stats.stamina;
        staminaLastUpdatedAt.value = new Date(stats.stamina_last_updated_at);
        currentPickaxe.value = pickaxe ?? null;
    }

    function applyStaminaUpdate(newStamina: number, lastUpdatedAt: string): void {
        stamina.value = newStamina;
        staminaLastUpdatedAt.value = new Date(lastUpdatedAt);
    }

    function applyLevelUp(newLevel: number): void {
        level.value = newLevel;
    }

    function addExp(amount: number): void {
        experience.value += amount;
    }

    function setGold(value: number): void {
        gold.value = Math.max(0, Math.floor(value));
    }

    function setHp(value: number): void {
        hp.value = Math.max(0, Math.min(maxHp.value, value));
    }

    function equipPickaxe(pickaxe: Pickaxe): void {
        currentPickaxe.value = pickaxe;
    }

    return {
        userId,
        level,
        experience,
        gold,
        nextLevelExp,
        hp,
        maxHp,
        stamina,
        staminaLastUpdatedAt,
        currentPickaxe,
        initialize,
        applyStaminaUpdate,
        applyLevelUp,
        addExp,
        setGold,
        setHp,
        equipPickaxe,
    };
});

