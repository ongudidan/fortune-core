<script setup lang="ts">
import { ref, computed } from 'vue';
import { Search, AlertTriangle, CheckCircle, Package, ArrowRightLeft } from 'lucide-vue-next';
import SearchableSelect from './SearchableSelect.vue';

interface ProductItem {
    id: number;
    name: string;
    sku: string;
    barcode?: string;
    category?: { id: number; name: string };
    unit?: { id: number; name: string };
    on_hand_stock: number;
    reserved_stock?: number;
    reorder_level: number;
    cost_price: number;
    selling_price: number;
}

interface LocationOption {
    id: number;
    name: string;
}

const props = withDefaults(
    defineProps<{
        products: ProductItem[];
        locations?: LocationOption[];
        selectedLocationId?: number | null;
    }>(),
    {
        locations: () => [],
        selectedLocationId: null,
    }
);

const emit = defineEmits<{
    (e: 'update:selectedLocationId', id: number | null): void;
    (e: 'transfer', product: ProductItem): void;
    (e: 'adjust', product: ProductItem): void;
}>();

const searchQuery = ref('');
const filterStatus = ref<'all' | 'low' | 'in_stock'>('all');

const locationSelectOptions = computed(() => [
    { value: '', label: 'All Warehouses & Outlets' },
    ...props.locations.map((loc) => ({ value: loc.id, label: loc.name })),
]);

const filteredProducts = computed(() => {
    return props.products.filter((p) => {
        const matchesSearch =
            !searchQuery.value ||
            p.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            p.sku?.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            p.barcode?.includes(searchQuery.value);

        const isLow = (p.on_hand_stock || 0) <= (p.reorder_level || 0);

        if (filterStatus.value === 'low') {
            return matchesSearch && isLow;
        }
        if (filterStatus.value === 'in_stock') {
            return matchesSearch && (p.on_hand_stock || 0) > 0;
        }

        return matchesSearch;
    });
});
</script>

<template>
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <!-- Controls Header -->
        <div class="p-4 border-b border-slate-100 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-3 flex-1">
                <div class="relative flex-1 max-w-md">
                    <Search class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search product by name, SKU, or barcode..."
                        class="w-full pl-9 pr-3 py-2 text-sm rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
                    />
                </div>

                <div v-if="locations.length > 0" class="w-56">
                    <SearchableSelect
                        :model-value="selectedLocationId || ''"
                        :options="locationSelectOptions"
                        placeholder="Select Location"
                        @update:model-value="(val) => emit('update:selectedLocationId', val ? Number(val) : null)"
                    />
                </div>
            </div>

            <!-- Status Tabs -->
            <div class="flex items-center gap-1 bg-slate-100 p-1 rounded-lg self-start">
                <button
                    type="button"
                    class="px-3 py-1.5 text-xs font-medium rounded-md transition-colors"
                    :class="filterStatus === 'all' ? 'bg-white text-slate-800 shadow-xs' : 'text-slate-500 hover:text-slate-700'"
                    @click="filterStatus = 'all'"
                >
                    All Items ({{ products.length }})
                </button>
                <button
                    type="button"
                    class="px-3 py-1.5 text-xs font-medium rounded-md transition-colors"
                    :class="filterStatus === 'low' ? 'bg-white text-rose-700 shadow-xs' : 'text-slate-500 hover:text-slate-700'"
                    @click="filterStatus = 'low'"
                >
                    Low Stock Alerts
                </button>
                <button
                    type="button"
                    class="px-3 py-1.5 text-xs font-medium rounded-md transition-colors"
                    :class="filterStatus === 'in_stock' ? 'bg-white text-emerald-700 shadow-xs' : 'text-slate-500 hover:text-slate-700'"
                    @click="filterStatus = 'in_stock'"
                >
                    In Stock
                </button>
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="bg-slate-50/75 border-b border-slate-200 text-xs font-semibold uppercase text-slate-500">
                        <th class="py-3 px-4">Product Details</th>
                        <th class="py-3 px-4">Category</th>
                        <th class="py-3 px-4 text-right">Physical Stock</th>
                        <th class="py-3 px-4 text-right">Reorder Level</th>
                        <th class="py-3 px-4 text-right">Wholesale Cost</th>
                        <th class="py-3 px-4 text-right">Selling Price</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="product in filteredProducts" :key="product.id" class="hover:bg-slate-50/50 transition-colors">
                        <td class="py-3.5 px-4">
                            <div class="font-medium text-slate-900">{{ product.name }}</div>
                            <div class="text-xs text-slate-400 flex items-center gap-2 mt-0.5">
                                <span v-if="product.sku">SKU: {{ product.sku }}</span>
                                <span v-if="product.barcode">Barcode: {{ product.barcode }}</span>
                            </div>
                        </td>
                        <td class="py-3.5 px-4 text-slate-600">
                            {{ product.category?.name || '—' }}
                        </td>
                        <td class="py-3.5 px-4 text-right font-semibold text-slate-900">
                            {{ (product.on_hand_stock || 0).toLocaleString() }}
                            <span class="text-xs text-slate-400 font-normal">{{ product.unit?.name || 'units' }}</span>
                        </td>
                        <td class="py-3.5 px-4 text-right text-slate-500">
                            {{ (product.reorder_level || 0).toLocaleString() }}
                        </td>
                        <td class="py-3.5 px-4 text-right text-slate-600 font-mono text-xs">
                            KES {{ Number(product.cost_price || 0).toFixed(2) }}
                        </td>
                        <td class="py-3.5 px-4 text-right text-slate-900 font-mono font-medium text-xs">
                            KES {{ Number(product.selling_price || 0).toFixed(2) }}
                        </td>
                        <td class="py-3.5 px-4 text-center">
                            <span
                                v-if="(product.on_hand_stock || 0) <= (product.reorder_level || 0)"
                                class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-rose-50 text-rose-700 border border-rose-200"
                            >
                                <AlertTriangle class="w-3 h-3" /> Low Stock
                            </span>
                            <span
                                v-else
                                class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200"
                            >
                                <CheckCircle class="w-3 h-3" /> Healthy
                            </span>
                        </td>
                        <td class="py-3.5 px-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button
                                    type="button"
                                    class="p-1.5 text-slate-500 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors"
                                    title="Transfer Stock"
                                    @click="emit('transfer', product)"
                                >
                                    <ArrowRightLeft class="w-4 h-4" />
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="filteredProducts.length === 0">
                        <td colspan="8" class="py-8 text-center text-slate-400">
                            <Package class="w-8 h-8 mx-auto mb-2 opacity-50" />
                            No products match your search or filter.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
