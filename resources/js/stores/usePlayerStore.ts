import { defineStore } from 'pinia';
import { ref } from 'vue';

interface Pickaxe {
    id: number;
    name: string;
    power: number;
}

export const usePlayerStore = defineStore('player', () => {
    const hp = ref<number>(100);
    const maxHp = ref<number>(100);
    const exp = ref<number>(0);
    const level = ref<number>(1);
    const currentPickaxe = ref<Pickaxe | null>(null);

    function setHp(value: number): void {
        hp.value = Math.max(0, Math.min(maxHp.value, value));
    }

    function addExp(amount: number): void {
        exp.value += amount;
    }

    function levelUp(newLevel: number): void {
        level.value = newLevel;
    }

    function equipPickaxe(pickaxe: Pickaxe): void {
        currentPickaxe.value = pickaxe;
    }

    return { hp, maxHp, exp, level, currentPickaxe, setHp, addExp, levelUp, equipPickaxe };
});
