<script setup>
import { ref, computed, watch } from 'vue';
import SearchableSelect from './SearchableSelect.vue';

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  },
  products: {
    type: Array,
    default: () => []
  },
  locations: {
    type: Array,
    default: () => []
  },
  units: {
    type: Array,
    default: () => []
  }
});

const emit = defineEmits(['close', 'success']);

const locationId = ref(props.locations[0]?.id || null);
const productId = ref(null);
const unitId = ref(null);
const factor = ref(1.0);
const newCost = ref(0);
const reason = ref('');
const isSubmitting = ref(false);
const errorMessage = ref('');

const selectedProduct = computed(() => {
  return props.products.find(p => p.id === productId.value) || null;
});

const currentStock = computed(() => {
  return selectedProduct.value?.available_quantity ?? (selectedProduct.value?.current_stock ?? 0);
});

const currentAverageCost = computed(() => {
  return selectedProduct.value?.average_cost ?? (selectedProduct.value?.cost_price ?? 0);
});

watch(selectedProduct, (prod) => {
  if (prod) {
    unitId.value = prod.unit_id || prod.base_unit_id;
    newCost.value = currentAverageCost.value;
  }
});

const newBaseCost = computed(() => {
  const f = parseFloat(factor.value) || 1.0;
  return f > 0 ? (parseFloat(newCost.value) || 0) / f : 0;
});

const diffPerUnit = computed(() => {
  return newBaseCost.value - currentAverageCost.value;
});

const totalValuationImpact = computed(() => {
  return diffPerUnit.value * currentStock.value;
});

const isGain = computed(() => totalValuationImpact.value >= 0);

const submitAdjustment = async () => {
  if (!productId.value) {
    errorMessage.value = 'Please select a product to adjust.';
    return;
  }
  if (!locationId.value) {
    errorMessage.value = 'Please select a location.';
    return;
  }
  if (!reason.value.trim()) {
    errorMessage.value = 'Please enter a reason for this valuation revaluation.';
    return;
  }

  isSubmitting.value = true;
  errorMessage.value = '';

  try {
    const payload = {
      product_id: productId.value,
      location_id: locationId.value,
      unit_id: unitId.value,
      factor: parseFloat(factor.value) || 1.0,
      new_cost: parseFloat(newCost.value) || 0,
      reason: reason.value
    };

    const res = await fetch('/api/v1/fortune/inventory/cost-adjustments', {
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
      throw new Error(data.message || 'Cost adjustment failed.');
    }

    emit('success', data);
    emit('close');
  } catch (err) {
    errorMessage.value = err.message || 'An error occurred during cost adjustment.';
  } finally {
    isSubmitting.value = false;
  }
};
</script>

<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4 overflow-y-auto">
    <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 w-full max-w-lg overflow-hidden animate-in fade-in zoom-in-95 duration-200">
      <!-- Modal Header -->
      <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-800/40">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-purple-500/10 flex items-center justify-center text-purple-600 dark:text-purple-400">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
            </svg>
          </div>
          <div>
            <h3 class="text-base font-semibold text-slate-900 dark:text-white">Inventory Cost Revaluation</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">Revalue inventory on hand & update batches</p>
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
      <div class="p-6 space-y-4">
        <div v-if="errorMessage" class="p-3 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 text-red-600 dark:text-red-400 text-xs rounded-xl flex items-center gap-2">
          <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <span>{{ errorMessage }}</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Location *</label>
            <SearchableSelect
              v-model="locationId"
              :options="locations.map(l => ({ value: l.id, label: l.name }))"
              placeholder="Select Location"
            />
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Product *</label>
            <SearchableSelect
              v-model="productId"
              :options="products.map(p => ({ value: p.id, label: p.name }))"
              placeholder="Select Product"
            />
          </div>
        </div>

        <!-- Current Stock & Cost Card -->
        <div v-if="selectedProduct" class="p-3 bg-slate-50 dark:bg-slate-800/60 rounded-xl grid grid-cols-2 gap-3 text-xs">
          <div>
            <span class="text-slate-500 dark:text-slate-400">Current Stock on Hand:</span>
            <div class="font-bold text-slate-900 dark:text-white font-mono text-sm">{{ currentStock.toLocaleString() }} Units</div>
          </div>
          <div>
            <span class="text-slate-500 dark:text-slate-400">Current Average Cost:</span>
            <div class="font-bold text-slate-900 dark:text-white font-mono text-sm">KES {{ currentAverageCost.toFixed(2) }}</div>
          </div>
        </div>

        <!-- New Cost Inputs -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">New Unit Cost (KES) *</label>
            <input
              v-model.number="newCost"
              type="number"
              step="0.0001"
              min="0"
              class="w-full px-3 py-2 text-sm bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-purple-500 font-mono"
            />
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Conversion Factor</label>
            <input
              v-model.number="factor"
              type="number"
              step="0.0001"
              min="0.0001"
              class="w-full px-3 py-2 text-sm bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-purple-500 font-mono"
            />
          </div>
        </div>

        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Reason for Adjustment *</label>
          <input
            v-model="reason"
            type="text"
            placeholder="e.g. Supplier invoice revision / Market valuation update"
            class="w-full px-3 py-2 text-sm bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-purple-500"
          />
        </div>

        <!-- Live GL Impact Preview -->
        <div v-if="selectedProduct && currentStock > 0" class="p-3.5 rounded-xl border" :class="isGain ? 'bg-emerald-50 dark:bg-emerald-950/30 border-emerald-200 dark:border-emerald-800' : 'bg-rose-50 dark:bg-rose-950/30 border-rose-200 dark:border-rose-800'">
          <div class="flex items-center justify-between text-xs mb-2">
            <span class="font-semibold" :class="isGain ? 'text-emerald-800 dark:text-emerald-300' : 'text-rose-800 dark:text-rose-300'">
              {{ isGain ? '▲ Valuation Gain' : '▼ Valuation Write-down' }}
            </span>
            <span class="font-mono font-bold text-sm" :class="isGain ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400'">
              {{ isGain ? '+' : '' }}KES {{ totalValuationImpact.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
            </span>
          </div>
          <div class="text-[11px] space-y-0.5" :class="isGain ? 'text-emerald-700/80 dark:text-emerald-400/80' : 'text-rose-700/80 dark:text-rose-400/80'">
            <div>• {{ isGain ? 'Debit 1300 (Inventory Asset)' : 'Debit 5500 (Loss on Revaluation)' }}: KES {{ Math.abs(totalValuationImpact).toFixed(2) }}</div>
            <div>• {{ isGain ? 'Credit 5500 (Inventory Adjustment Gain)' : 'Credit 1300 (Inventory Asset)' }}: KES {{ Math.abs(totalValuationImpact).toFixed(2) }}</div>
          </div>
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
          @click="submitAdjustment"
          :disabled="isSubmitting"
          type="button"
          class="px-5 py-2.5 text-xs font-semibold text-white bg-purple-600 hover:bg-purple-700 disabled:opacity-50 rounded-xl shadow-lg shadow-purple-600/20 transition flex items-center gap-2"
        >
          <svg v-if="isSubmitting" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
          </svg>
          <span>Approve & Post GL Revaluation</span>
        </button>
      </div>
    </div>
  </div>
</template>
