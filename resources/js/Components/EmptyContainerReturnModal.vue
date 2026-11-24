<script setup lang="ts">
import { ref, computed } from 'vue';
import { Undo2, X, AlertCircle } from 'lucide-vue-next';
import SearchableSelect from './SearchableSelect.vue';

interface CustomerOption {
    id: number;
    name: string;
    phone?: string;
}

interface ContainerOption {
    id: number;
    name: string;
}

const props = defineProps<{
    open: boolean;
    customers: CustomerOption[];
    containers: ContainerOption[];
    defaultCustomerId?: number | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'submit', payload: {
        customerId: number;
        emptyProductId: number;
        quantity: number;
        notes?: string;
    }): void;
}>();

const customerId = ref<number | null>(props.defaultCustomerId || null);
const emptyProductId = ref<number | null>(null);
const quantity = ref<number>(1);
const notes = ref<string>('');

const customerOptions = computed(() =>
    props.customers.map((c) => ({
        value: c.id,
        label: `${c.name} ${c.phone ? `(${c.phone})` : ''}`,
    }))
);

const containerOptions = computed(() =>
    props.containers.map((cnt) => ({
        value: cnt.id,
        label: cnt.name,
    }))
);

const handleSubmit = () => {
    if (!customerId.value || !emptyProductId.value || quantity.value <= 0) {
        return;
    }

    emit('submit', {
        customerId: customerId.value,
        emptyProductId: emptyProductId.value,
        quantity: quantity.value,
        notes: notes.value,
    });
};
</script>

<template>
    <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs">
        <div class="bg-white w-full max-w-md rounded-2xl shadow-xl border border-slate-200 overflow-hidden animate-in fade-in zoom-in-95 duration-150">
            <!-- Header -->
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-teal-50 text-teal-600 flex items-center justify-center">
                        <Undo2 class="w-5 h-5" />
                    </div>
                    <div>
                        <h3 class="font-semibold text-slate-900">Record Empty Container Return</h3>
                        <p class="text-xs text-slate-500">Return physical glass crates, bottles, or gas cylinders</p>
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

            <!-- Form -->
            <form class="p-6 space-y-4" @submit.prevent="handleSubmit">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1.5">Returning Customer</label>
                    <SearchableSelect
                        :model-value="customerId || ''"
                        :options="customerOptions"
                        placeholder="Select customer"
                        @update:model-value="(val) => (customerId = val ? Number(val) : null)"
                    />
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1.5">Container Product</label>
                    <SearchableSelect
                        :model-value="emptyProductId || ''"
                        :options="containerOptions"
                        placeholder="Select empty crate/bottle type"
                        @update:model-value="(val) => (emptyProductId = val ? Number(val) : null)"
                    />
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1.5">Quantity Returned</label>
                    <input
                        v-model.number="quantity"
                        type="number"
                        min="1"
                        step="1"
                        class="w-full px-3 py-2 text-sm rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500"
                        required
                    />
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1.5">Return Notes / Manifest</label>
                    <textarea
                        v-model="notes"
                        rows="2"
                        placeholder="Gate pass number, driver name, condition notes..."
                        class="w-full px-3 py-2 text-sm rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500"
                    ></textarea>
                </div>

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
                        class="px-5 py-2 text-sm font-medium text-white bg-teal-600 hover:bg-teal-700 rounded-lg shadow-sm transition-colors"
                    >
                        Accept Return
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
