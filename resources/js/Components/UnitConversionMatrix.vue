<script setup lang="ts">
import { ref } from 'vue';
import { Layers, Plus, Trash2 } from 'lucide-vue-next';
import SearchableSelect from './SearchableSelect.vue';

interface UnitOption {
    id: number;
    name: string;
}

interface ConversionRow {
    unit_id: number | null;
    operator: '*' | '/';
    factor: number;
    wholesale_price?: number | null;
    barcode?: string | null;
}

const props = defineProps<{
    baseUnitName: string;
    units: UnitOption[];
    modelValue: ConversionRow[];
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: ConversionRow[]): void;
}>();

const unitOptions = props.units.map((u) => ({ value: u.id, label: u.name }));

const addRow = () => {
    const updated = [
        ...props.modelValue,
        {
            unit_id: null,
            operator: '*' as const,
            factor: 1,
            wholesale_price: null,
            barcode: '',
        },
    ];
    emit('update:modelValue', updated);
};

const removeRow = (index: number) => {
    const updated = props.modelValue.filter((_, idx) => idx !== index);
    emit('update:modelValue', updated);
};
</script>

<template>
    <div class="bg-white rounded-xl border border-slate-200 p-4 space-y-3">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div class="flex items-center gap-2">
                <Layers class="w-4 h-4 text-indigo-600" />
                <h4 class="text-sm font-semibold text-slate-900">Wholesale Multi-Tier Packaging Conversions</h4>
            </div>
            <button
                type="button"
                class="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-medium text-indigo-600 bg-indigo-50 hover:bg-indigo-100 rounded-lg transition-colors"
                @click="addRow"
            >
                <Plus class="w-3.5 h-3.5" /> Add Tier Pack Size
            </button>
        </div>

        <div v-if="modelValue.length === 0" class="text-xs text-slate-400 py-3 text-center">
            No pack conversions configured. This product is sold only in loose base units ({{ baseUnitName }}).
        </div>

        <div v-else class="space-y-3">
            <div
                v-for="(row, idx) in modelValue"
                :key="idx"
                class="p-3 bg-slate-50/75 rounded-lg border border-slate-100 grid grid-cols-1 md:grid-cols-12 gap-3 items-center text-xs"
            >
                <div class="md:col-span-4">
                    <label class="block font-medium text-slate-600 mb-1">Pack Unit</label>
                    <SearchableSelect
                        :model-value="row.unit_id || ''"
                        :options="unitOptions"
                        placeholder="Select carton/bale"
                        @update:model-value="(val) => (row.unit_id = val ? Number(val) : null)"
                    />
                </div>

                <div class="md:col-span-3">
                    <label class="block font-medium text-slate-600 mb-1">
                        Multiplier (= x {{ baseUnitName }})
                    </label>
                    <input
                        v-model.number="row.factor"
                        type="number"
                        step="any"
                        min="0.0001"
                        placeholder="e.g. 24"
                        class="w-full px-2.5 py-1.5 text-xs rounded-lg border border-slate-200 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                    />
                </div>

                <div class="md:col-span-3">
                    <label class="block font-medium text-slate-600 mb-1">Wholesale Tier Price</label>
                    <input
                        v-model.number="row.wholesale_price"
                        type="number"
                        step="0.01"
                        placeholder="e.g. 2400.00"
                        class="w-full px-2.5 py-1.5 text-xs rounded-lg border border-slate-200 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                    />
                </div>

                <div class="md:col-span-2 flex items-center justify-end pt-4">
                    <button
                        type="button"
                        class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors"
                        title="Remove Pack Size"
                        @click="removeRow(idx)"
                    >
                        <Trash2 class="w-4 h-4" />
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
