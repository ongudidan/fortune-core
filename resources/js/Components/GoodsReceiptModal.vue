<script setup>
import { ref } from 'vue';
import SearchableSelect from './SearchableSelect.vue';

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  },
  suppliers: {
    type: Array,
    default: () => []
  },
  locations: {
    type: Array,
    default: () => []
  },
  products: {
    type: Array,
    default: () => []
  },
  units: {
    type: Array,
    default: () => []
  }
});

const emit = defineEmits(['close', 'success']);

const supplierId = ref(null);
const locationId = ref(props.locations[0]?.id || null);
const notes = ref('');
const isSubmitting = ref(false);
const errorMessage = ref('');

const rows = ref([
  { product_id: null, unit_id: null, quantity: 1, unit_cost: 0 }
]);

const addRow = () => {
  rows.value.push({ product_id: null, unit_id: null, quantity: 1, unit_cost: 0 });
};

const removeRow = (index) => {
  if (rows.value.length > 1) {
    rows.value.splice(index, 1);
  }
};

const totalIntakeCost = () => {
  return rows.value.reduce((acc, r) => acc + ((parseFloat(r.quantity) || 0) * (parseFloat(r.unit_cost) || 0)), 0);
};

const submitReceipt = async () => {
  if (!supplierId.value) {
    errorMessage.value = 'Please select a supplier.';
    return;
  }
  if (!locationId.value) {
    errorMessage.value = 'Please select a destination warehouse / store.';
    return;
  }

  const validItems = rows.value.filter(r => r.product_id && r.quantity > 0 && r.unit_cost >= 0);
  if (validItems.length === 0) {
    errorMessage.value = 'Please add at least one valid item with product, quantity, and unit cost.';
    return;
  }

  isSubmitting.value = true;
  errorMessage.value = '';

  try {
    const payload = {
      supplier_id: supplierId.value,
      location_id: locationId.value,
      notes: notes.value,
      items: validItems.map(r => ({
        product_id: r.product_id,
        unit_id: r.unit_id,
        quantity: parseFloat(r.quantity),
        unit_cost: parseFloat(r.unit_cost)
      }))
    };

    const res = await fetch('/api/v1/fortune/purchases/goods-receipt', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
      },
      body: JSON.stringify(payload)
    });

    const data = await res.json();
    if (!res.ok) {
      throw new Error(data.message || 'Goods receipt failed.');
    }

    emit('success', data);
    emit('close');
  } catch (err) {
    errorMessage.value = err.message || 'An error occurred during goods receipt.';
  } finally {
    isSubmitting.value = false;
  }
};
</script>

<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4 overflow-y-auto">
    <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 w-full max-w-3xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
      <!-- Modal Header -->
      <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-800/40">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-blue-500/10 flex items-center justify-center text-blue-600 dark:text-blue-400">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
            </svg>
          </div>
          <div>
            <h3 class="text-base font-semibold text-slate-900 dark:text-white">Goods Received Note (GRN)</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">Intake stock, create FIFO batches & post Debit 1300 / Credit 2050</p>
          </div>
        </div>
        <button
          @click="emit('close')"
          type="button"
          class="text-slate-400 hover:text-slate-500 dark:hover:text-slate-300 p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <!-- Modal Body -->
      <div class="p-6 space-y-5">
        <div v-if="errorMessage" class="p-3 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 text-red-600 dark:text-red-400 text-xs rounded-xl flex items-center gap-2">
          <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <span>{{ errorMessage }}</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Supplier *</label>
            <SearchableSelect
              v-model="supplierId"
              :options="suppliers.map(s => ({ value: s.id, label: s.name }))"
              placeholder="Select Supplier"
            />
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Destination Location *</label>
            <SearchableSelect
              v-model="locationId"
              :options="locations.map(l => ({ value: l.id, label: l.name }))"
              placeholder="Select Warehouse"
            />
          </div>
        </div>

        <!-- Intake Line Items Table -->
        <div>
          <div class="flex items-center justify-between mb-2">
            <label class="text-xs font-semibold text-slate-700 dark:text-slate-300">Intake Line Items</label>
            <button
              @click="addRow"
              type="button"
              class="text-xs text-blue-600 dark:text-blue-400 font-medium hover:underline flex items-center gap-1"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
              </svg>
              <span>Add Item</span>
            </button>
          </div>

          <div class="border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden">
            <table class="w-full text-xs text-left">
              <thead class="bg-slate-50 dark:bg-slate-800 text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                <tr>
                  <th class="p-2.5">Product</th>
                  <th class="p-2.5 w-28">Unit</th>
                  <th class="p-2.5 w-24">Qty</th>
                  <th class="p-2.5 w-28">Unit Cost</th>
                  <th class="p-2.5 w-28 text-right">Subtotal</th>
                  <th class="p-2.5 w-10"></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <tr v-for="(row, idx) in rows" :key="idx" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40">
                  <td class="p-2">
                    <SearchableSelect
                      v-model="row.product_id"
                      :options="products.map(p => ({ value: p.id, label: p.name }))"
                      placeholder="Product"
                    />
                  </td>
                  <td class="p-2">
                    <SearchableSelect
                      v-model="row.unit_id"
                      :options="units.map(u => ({ value: u.id, label: u.name }))"
                      placeholder="Base Unit"
                    />
                  </td>
                  <td class="p-2">
                    <input
                      v-model.number="row.quantity"
                      type="number"
                      step="0.01"
                      min="0.01"
                      class="w-full px-2 py-1.5 text-xs bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg font-mono text-center"
                    />
                  </td>
                  <td class="p-2">
                    <input
                      v-model.number="row.unit_cost"
                      type="number"
                      step="0.01"
                      min="0"
                      class="w-full px-2 py-1.5 text-xs bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg font-mono text-right"
                    />
                  </td>
                  <td class="p-2 text-right font-mono font-semibold text-slate-900 dark:text-white">
                    KES {{ (((row.quantity || 0) * (row.unit_cost || 0))).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                  </td>
                  <td class="p-2 text-center">
                    <button
                      v-if="rows.length > 1"
                      @click="removeRow(idx)"
                      type="button"
                      class="text-red-400 hover:text-red-600 transition p-1"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                      </svg>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Total Cost Banner -->
        <div class="p-4 bg-slate-50 dark:bg-slate-800/60 rounded-xl flex items-center justify-between">
          <span class="text-xs text-slate-600 dark:text-slate-400 font-medium">Total GRN Value:</span>
          <span class="text-base font-bold font-mono text-blue-600 dark:text-blue-400">
            KES {{ totalIntakeCost().toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
          </span>
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-end gap-3 bg-slate-50/50 dark:bg-slate-800/40">
        <button
          @click="emit('close')"
          type="button"
          class="px-4 py-2 text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl transition"
        >
          Cancel
        </button>
        <button
          @click="submitReceipt"
          :disabled="isSubmitting"
          type="button"
          class="px-5 py-2.5 text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 rounded-xl shadow-lg shadow-blue-600/20 transition flex items-center gap-2"
        >
          <svg v-if="isSubmitting" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
          </svg>
          <span>Receive Goods & Post GL</span>
        </button>
      </div>
    </div>
  </div>
</template>
