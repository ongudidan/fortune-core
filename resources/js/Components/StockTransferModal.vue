<script setup lang="ts">
import { ref, computed } from 'vue';
import { ArrowRightLeft, X, Package } from 'lucide-vue-next';
import SearchableSelect from './SearchableSelect.vue';

interface LocationOption {
    id: number;
    name: string;
}

interface ProductItem {
    id: number;
    name: string;
    on_hand_stock: number;
    unit?: { id: number; name: string };
}

const props = defineProps<{
    open: boolean;
    locations: LocationOption[];
    products: ProductItem[];
    defaultFromLocationId?: number | null;
    defaultProductId?: number | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'submit', payload: {
        fromLocationId: number;
        toLocationId: number;
        productId: number;
        quantity: number;
        notes?: string;
    }): void;
}>();

const fromLocationId = ref<number | null>(props.defaultFromLocationId || null);
const toLocationId = ref<number | null>(null);
const productId = ref<number | null>(props.defaultProductId || null);
const quantity = ref<number>(1);
const notes = ref<string>('');

const fromLocationOptions = computed(() =>
    props.locations.map((loc) => ({ value: loc.id, label: loc.name }))
);

const toLocationOptions = computed(() =>
    props.locations
        .filter((loc) => loc.id !== fromLocationId.value)
        .map((loc) => ({ value: loc.id, label: loc.name }))
);

const productOptions = computed(() =>
    props.products.map((p) => ({
        value: p.id,
        label: `${p.name} (Available: ${p.on_hand_stock})`,
    }))
);

const selectedProduct = computed(() =>
    props.products.find((p) => p.id === productId.value)
);

const handleSubmit = () => {
    if (!fromLocationId.value || !toLocationId.value || !productId.value || quantity.value <= 0) {
        return;
    }

    emit('submit', {
        fromLocationId: fromLocationId.value,
        toLocationId: toLocationId.value,
        productId: productId.value,
        quantity: quantity.value,
        notes: notes.value,
    });
};
</script>

<template>
    <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs">
        <div class="bg-white w-full max-w-lg rounded-2xl shadow-xl border border-slate-200 overflow-hidden animate-in fade-in zoom-in-95 duration-150">
            <!-- Modal Header -->
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                        <ArrowRightLeft class="w-5 h-5" />
                    </div>
                    <div>
                        <h3 class="font-semibold text-slate-900">Initiate Inter-Location Transfer</h3>
                        <p class="text-xs text-slate-500">Transfer stock between warehouses, retail stores, or mobile vans</p>
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

            <!-- Form Body -->
            <form class="p-6 space-y-4" @submit.prevent="handleSubmit">
                <!-- Source Location -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1.5">Origin Location (From)</label>
                    <SearchableSelect
                        :model-value="fromLocationId || ''"
                        :options="fromLocationOptions"
                        placeholder="Select source warehouse or store"
                        @update:model-value="(val) => (fromLocationId = val ? Number(val) : null)"
                    />
                </div>

                <!-- Destination Location -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1.5">Destination Location (To)</label>
                    <SearchableSelect
                        :model-value="toLocationId || ''"
                        :options="toLocationOptions"
                        placeholder="Select target warehouse, store, or van"
                        @update:model-value="(val) => (toLocationId = val ? Number(val) : null)"
                    />
                </div>

                <!-- Product -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1.5">Product to Transfer</label>
                    <SearchableSelect
                        :model-value="productId || ''"
                        :options="productOptions"
                        placeholder="Select product"
                        @update:model-value="(val) => (productId = val ? Number(val) : null)"
                    />
                </div>

                <!-- Quantity -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1.5">Quantity</label>
                    <div class="flex items-center gap-2">
                        <input
                            v-model.number="quantity"
                            type="number"
                            min="1"
                            step="any"
                            class="flex-1 px-3 py-2 text-sm rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
                            required
                        />
                        <span class="text-sm font-medium text-slate-600">{{ selectedProduct?.unit?.name || 'Units' }}</span>
                    </div>
                </div>

                <!-- Notes -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1.5">Transfer Notes</label>
                    <textarea
                        v-model="notes"
                        rows="2"
                        placeholder="Dispatch reference, vehicle number, or instructions..."
                        class="w-full px-3 py-2 text-sm rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
                    ></textarea>
                </div>

                <!-- Footer Buttons -->
                <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-100">
                    <button
                        type="button"
                        class="px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-800 hover:bg-slate-100 rounded-lg transition-colors"
                        @click="emit('close')"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="px-5 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow-sm transition-colors"
                    >
                        Dispatch Transfer
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
