<script setup>
import { ref, computed, watch } from 'vue';
import SearchableSelect from './SearchableSelect.vue';

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  },
  items: {
    type: Array,
    default: () => []
  },
  totalAmount: {
    type: Number,
    default: 0
  },
  customers: {
    type: Array,
    default: () => []
  },
  locations: {
    type: Array,
    default: () => []
  },
  customerWalletBalance: {
    type: Number,
    default: 0
  }
});

const emit = defineEmits(['close', 'success']);

const customerId = ref(null);
const locationId = ref(props.locations[0]?.id || null);
const cashPaid = ref(0);
const mpesaPaid = ref(0);
const walletPaid = ref(0);
const mpesaPhone = ref('');
const notes = ref('');
const isSubmitting = ref(false);
const errorMessage = ref('');

watch(() => props.show, (newVal) => {
  if (newVal) {
    cashPaid.value = props.totalAmount;
    mpesaPaid.value = 0;
    walletPaid.value = 0;
    errorMessage.value = '';
    if (!locationId.value && props.locations.length > 0) {
      locationId.value = props.locations[0].id;
    }
  }
});

const totalPaid = computed(() => {
  return (parseFloat(cashPaid.value) || 0) + (parseFloat(mpesaPaid.value) || 0) + (parseFloat(walletPaid.value) || 0);
});

const changeAmount = computed(() => {
  const diff = totalPaid.value - props.totalAmount;
  return diff > 0 ? diff : 0;
});

const remainingBalance = computed(() => {
  const diff = props.totalAmount - totalPaid.value;
  return diff > 0 ? diff : 0;
});

const maxWalletSpend = computed(() => {
  return Math.min(props.customerWalletBalance || 0, props.totalAmount);
});

const applyFullWallet = () => {
  const spend = maxWalletSpend.value;
  walletPaid.value = spend;
  const rem = props.totalAmount - spend;
  cashPaid.value = rem > 0 ? rem : 0;
  mpesaPaid.value = 0;
};

const submitSale = async () => {
  if (!locationId.value) {
    errorMessage.value = 'Please select a store / outlet location.';
    return;
  }
  if (props.items.length === 0) {
    errorMessage.value = 'Cart items cannot be empty.';
    return;
  }
  if (walletPaid.value > 0 && walletPaid.value > props.customerWalletBalance) {
    errorMessage.value = `Wallet payment cannot exceed available balance of KES ${props.customerWalletBalance.toLocaleString()}`;
    return;
  }

  isSubmitting.value = true;
  errorMessage.value = '';

  try {
    const payload = {
      location_id: locationId.value,
      customer_id: customerId.value,
      items: props.items,
      cash_paid: parseFloat(cashPaid.value) || 0,
      mpesa_paid: parseFloat(mpesaPaid.value) || 0,
      wallet_paid: parseFloat(walletPaid.value) || 0,
      mpesa_phone: mpesaPhone.value,
      notes: notes.value
    };

    const res = await fetch('/api/v1/fortune/sales/checkout', {
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
      throw new Error(data.message || 'Checkout failed.');
    }

    emit('success', data);
    emit('close');
  } catch (err) {
    errorMessage.value = err.message || 'An error occurred during checkout.';
  } finally {
    isSubmitting.value = false;
  }
};
</script>

<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4 overflow-y-auto">
    <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 w-full max-w-xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
      <!-- Modal Header -->
      <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-800/40">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-emerald-500/10 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
          </div>
          <div>
            <h3 class="text-base font-semibold text-slate-900 dark:text-white">Wholesale / Retail Checkout</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">Split-tender settlements & empty container yield</p>
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
        <!-- Error Alert -->
        <div v-if="errorMessage" class="p-3 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 text-red-600 dark:text-red-400 text-xs rounded-xl flex items-center gap-2">
          <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <span>{{ errorMessage }}</span>
        </div>

        <!-- Total Bill Card -->
        <div class="p-4 rounded-xl bg-gradient-to-br from-emerald-500/10 via-teal-500/5 to-transparent border border-emerald-500/20 flex items-center justify-between">
          <div>
            <span class="text-xs font-medium text-emerald-800 dark:text-emerald-300 uppercase tracking-wider">Total Payable Amount</span>
            <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">
              KES {{ totalAmount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
            </div>
          </div>
          <div class="text-right">
            <span class="text-xs text-slate-500 dark:text-slate-400">Items Count</span>
            <div class="text-sm font-semibold text-slate-900 dark:text-white">{{ items.length }} Line(s)</div>
          </div>
        </div>

        <!-- Context Selectors -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Store / Warehouse Location *</label>
            <SearchableSelect
              v-model="locationId"
              :options="locations.map(l => ({ value: l.id, label: l.name }))"
              placeholder="Select Location"
            />
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Customer (Optional)</label>
            <SearchableSelect
              v-model="customerId"
              :options="customers.map(c => ({ value: c.id, label: c.name + (c.phone ? ' (' + c.phone + ')' : '') }))"
              placeholder="Walk-in Customer"
            />
          </div>
        </div>

        <!-- Wallet Prepayment Notice -->
        <div v-if="customerId && customerWalletBalance > 0" class="p-3 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 rounded-xl flex items-center justify-between">
          <div class="flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-amber-500"></span>
            <span class="text-xs text-amber-800 dark:text-amber-300 font-medium">Customer Wallet Available: <strong>KES {{ customerWalletBalance.toLocaleString() }}</strong></span>
          </div>
          <button
            type="button"
            @click="applyFullWallet"
            class="text-xs font-semibold text-amber-700 dark:text-amber-400 hover:underline"
          >
            Apply Wallet
          </button>
        </div>

        <!-- Multi-Tender Inputs -->
        <div class="space-y-3">
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Payment Tenders</label>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
              <label class="block text-[11px] text-slate-500 dark:text-slate-400 mb-1">Cash (KES)</label>
              <input
                v-model.number="cashPaid"
                type="number"
                step="0.01"
                min="0"
                class="w-full px-3 py-2 text-sm bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-emerald-500 font-mono"
              />
            </div>
            <div>
              <label class="block text-[11px] text-slate-500 dark:text-slate-400 mb-1">M-Pesa (KES)</label>
              <input
                v-model.number="mpesaPaid"
                type="number"
                step="0.01"
                min="0"
                class="w-full px-3 py-2 text-sm bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-emerald-500 font-mono"
              />
            </div>
            <div>
              <label class="block text-[11px] text-slate-500 dark:text-slate-400 mb-1">Wallet (Escrow 2015)</label>
              <input
                v-model.number="walletPaid"
                type="number"
                step="0.01"
                min="0"
                :max="customerWalletBalance"
                class="w-full px-3 py-2 text-sm bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-emerald-500 font-mono"
              />
            </div>
          </div>
        </div>

        <!-- M-Pesa Phone (Conditional) -->
        <div v-if="mpesaPaid > 0" class="animate-in fade-in duration-200">
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">M-Pesa Phone / Ref</label>
          <input
            v-model="mpesaPhone"
            type="text"
            placeholder="e.g. 254712345678 or Daraja Code"
            class="w-full px-3 py-2 text-sm bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-emerald-500"
          />
        </div>

        <!-- Tender Breakdown Summary -->
        <div class="p-3 bg-slate-50 dark:bg-slate-800/60 rounded-xl space-y-1.5 text-xs">
          <div class="flex justify-between text-slate-600 dark:text-slate-400">
            <span>Total Tendered:</span>
            <span class="font-mono font-semibold text-slate-900 dark:text-white">KES {{ totalPaid.toLocaleString(undefined, { minimumFractionDigits: 2 }) }}</span>
          </div>
          <div v-if="changeAmount > 0" class="flex justify-between text-emerald-600 dark:text-emerald-400 font-medium">
            <span>Change Return:</span>
            <span class="font-mono font-bold">KES {{ changeAmount.toLocaleString(undefined, { minimumFractionDigits: 2 }) }}</span>
          </div>
          <div v-if="remainingBalance > 0" class="flex justify-between text-amber-600 dark:text-amber-400 font-medium">
            <span>Remaining (Carried on AR 1200):</span>
            <span class="font-mono font-bold">KES {{ remainingBalance.toLocaleString(undefined, { minimumFractionDigits: 2 }) }}</span>
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
          @click="submitSale"
          :disabled="isSubmitting"
          type="button"
          class="px-5 py-2.5 text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 rounded-xl shadow-lg shadow-emerald-600/20 transition flex items-center gap-2"
        >
          <svg v-if="isSubmitting" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
          </svg>
          <span>Complete Sale & Deduct Stock</span>
        </button>
      </div>
    </div>
  </div>
</template>
