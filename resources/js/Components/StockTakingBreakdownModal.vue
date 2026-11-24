<script setup lang="ts">
import { ref, computed } from 'vue';
import { ClipboardCheck, X, Calculator } from 'lucide-vue-next';
import SearchableSelect from './SearchableSelect.vue';

interface PackUnitOption {
    id: number;
    name: string;
    factor: number;
}

const props = defineProps<{
    open: boolean;
    productName: string;
    bookStock: number;
    baseUnitName?: string;
    availablePackUnits: PackUnitOption[];
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'confirm', payload: {
        packagesCounted: number;
        looseUnitsCounted: number;
        packUnitId: number | null;
        normalizedTotal: number;
        variance: number;
    }): void;
}>();

const selectedPackUnitId = ref<number | null>(
    props.availablePackUnits.length > 0 ? props.availablePackUnits[0].id : null
);
const packagesCounted = ref<number>(0);
const looseUnitsCounted = ref<number>(0);

const packUnitOptions = computed(() =>
    props.availablePackUnits.map((u) => ({
        value: u.id,
        label: `${u.name} (x${u.factor} base units)`,
    }))
);

const currentFactor = computed(() => {
    const selected = props.availablePackUnits.find((u) => u.id === selectedPackUnitId.value);
    return selected ? selected.factor : 1.0;
});

const normalizedTotal = computed(() => {
    return Math.round(((packagesCounted.value || 0) * currentFactor.value + (looseUnitsCounted.value || 0)) * 10000) / 10000;
});

const variance = computed(() => {
    return Math.round((normalizedTotal.value - (props.bookStock || 0)) * 10000) / 10000;
});

const handleConfirm = () => {
    emit('confirm', {
        packagesCounted: packagesCounted.value,
        looseUnitsCounted: looseUnitsCounted.value,
        packUnitId: selectedPackUnitId.value,
        normalizedTotal: normalizedTotal.value,
        variance: variance.value,
    });
};
</script>

<template>
    <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs">
        <div class="bg-white w-full max-w-md rounded-2xl shadow-xl border border-slate-200 overflow-hidden animate-in fade-in zoom-in-95 duration-150">
            <!-- Header -->
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                        <ClipboardCheck class="w-5 h-5" />
                    </div>
                    <div>
                        <h3 class="font-semibold text-slate-900">Physical Stock Count Breakdown</h3>
                        <p class="text-xs text-slate-500">{{ productName }}</p>
                    </div>
                </div>
                <button
                    type="button"
                    class="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors"
                    @click="emit('close')"
                >
                    <X class="w-5 h-5" />
                </button>
            </div>

            <!-- Content -->
            <div class="p-6 space-y-4">
                <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-100 flex items-center justify-between">
                    <div>
                        <span class="text-xs font-semibold text-slate-500 uppercase">System Book Stock</span>
                        <div class="text-base font-bold text-slate-900">
                            {{ (bookStock || 0).toLocaleString() }} {{ baseUnitName || 'pieces' }}
                        </div>
                    </div>
                    <Calculator class="w-5 h-5 text-slate-400" />
                </div>

                <!-- Pack Selection -->
                <div v-if="availablePackUnits.length > 0">
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1.5">Packaging Size (Outer Pack)</label>
                    <SearchableSelect
                        :model-value="selectedPackUnitId || ''"
                        :options="packUnitOptions"
                        placeholder="Select carton/bale size"
                        @update:model-value="(val) => (selectedPackUnitId = val ? Number(val) : null)"
                    />
                </div>

                <!-- Packages Input -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase mb-1.5">Cartons / Cases</label>
                        <input
                            v-model.number="packagesCounted"
                            type="number"
                            min="0"
                            step="any"
                            class="w-full px-3 py-2 text-sm rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase mb-1.5">Loose Pieces</label>
                        <input
                            v-model.number="looseUnitsCounted"
                            type="number"
                            min="0"
                            step="any"
                            class="w-full px-3 py-2 text-sm rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500"
                        />
                    </div>
                </div>

                <!-- Resulting Normalized Count & Variance -->
                <div class="p-3.5 rounded-xl border bg-slate-50/75 space-y-2">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-slate-600">Normalized Physical Total:</span>
                        <span class="font-bold text-slate-900">{{ normalizedTotal }} {{ baseUnitName || 'pieces' }}</span>
                    </div>
                    <div class="flex items-center justify-between text-sm pt-2 border-t border-slate-200/60">
                        <span class="text-slate-600">Variance:</span>
                        <span
                            class="font-bold font-mono"
                            :class="variance === 0 ? 'text-slate-700' : variance > 0 ? 'text-emerald-600' : 'text-rose-600'"
                        >
                            {{ variance > 0 ? `+${variance}` : variance }} {{ baseUnitName || 'pieces' }}
                        </span>
                    </div>
                </div>

                <!-- Buttons -->
                <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-100">
                    <button
                        type="button"
                        class="px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-800 hover:bg-slate-100 rounded-lg transition-colors"
                        @click="emit('close')"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="px-5 py-2 text-sm font-medium text-white bg-amber-600 hover:bg-amber-700 rounded-lg shadow-sm transition-colors"
                        @click="handleConfirm"
                    >
                        Apply Physical Count
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
