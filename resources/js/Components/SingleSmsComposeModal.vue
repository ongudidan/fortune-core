<script setup lang="ts">
import SearchableSelect from './SearchableSelect.vue';
import { useForm } from '@inertiajs/vue3';
import {
    Building2,
    CreditCard,
    GitBranch,
    MessageSquare,
    Phone,
    RefreshCw,
    Send,
    X,
    Zap,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface SmsConfig {
    id: number;
    sender_id: string;
    company: { id: number; name: string };
    branch: { id: number; name: string } | null;
    sms_provider: { id: number; name: string } | null;
}

interface Company {
    id: number;
    name: string;
}
interface SmsTemplate {
    id: number;
    name: string;
    content: string;
}

const props = defineProps<{
    configs: SmsConfig[];
    companies: Company[];
    templates: SmsTemplate[];
}>();

const emit = defineEmits<{
    (e: 'sent'): void;
    (e: 'close'): void;
}>();

// ── State ───────────────────────────────────────────────────────────
const selectedCompanyId = ref<number | null>(null);
const selectedConfigId = ref<number | null>(null);
const selectedTemplateId = ref<number | null>(null);

// Live balance — always from the API, never from props/DB
const liveBalance = ref<number | null>(null);
const isFetchingBalance = ref(false);

const form = useForm({
    sms_config_id: null as number | null,
    phone_number: '',
    message: '',
    template_id: null as number | null,
});

// ── Computed ────────────────────────────────────────────────────────
const filteredConfigs = computed(() => {
    if (!selectedCompanyId.value) return [];
    return props.configs.filter(
        (c) => c.company.id === selectedCompanyId.value,
    );
});

const configOptions = computed(() =>
    filteredConfigs.value.map((c) => ({
        id: c.id,
        name: c.branch ? c.branch.name : `${c.company.name} (No branch)`,
    })),
);

const selectedConfig = computed(
    () => props.configs.find((c) => c.id === selectedConfigId.value) ?? null,
);

const charCount = computed(() => form.message.length);
const smsCount = computed(() => Math.ceil(charCount.value / 160) || 1);

// ── Live Balance ─────────────────────────────────────────────────────
const fetchLiveBalance = async (configId: number) => {
    isFetchingBalance.value = true;
    liveBalance.value = null;
    try {
        const res = await fetch(
            '/organization/bulk-sms/single/refresh-balance/' + configId,
        );
        if (res.ok) {
            const data = await res.json();
            liveBalance.value = data.balance;
        } else {
            liveBalance.value = null;
        }
    } catch {
        liveBalance.value = null;
    } finally {
        isFetchingBalance.value = false;
    }
};

// ── Watchers & Autofill ──────────────────────────────────────────────
const checkAndAutoSelect = () => {
    if (props.companies && props.companies.length === 1 && !selectedCompanyId.value) {
        selectedCompanyId.value = props.companies[0].id;
    }
    if (selectedCompanyId.value && configOptions.value.length === 1 && !selectedConfigId.value) {
        selectedConfigId.value = configOptions.value[0].id;
    }
};

watch(
    () => props.companies,
    () => {
        checkAndAutoSelect();
    },
    { immediate: true },
);

watch(
    configOptions,
    (options) => {
        if (options && options.length === 1 && !selectedConfigId.value) {
            selectedConfigId.value = options[0].id;
        }
    },
    { immediate: true },
);

watch(selectedCompanyId, (newCompanyId) => {
    selectedConfigId.value = null;
    form.sms_config_id = null;
    liveBalance.value = null;
    if (newCompanyId) {
        if (configOptions.value.length === 1) {
            selectedConfigId.value = configOptions.value[0].id;
        }
    }
});

watch(
    selectedConfigId,
    (id) => {
        form.sms_config_id = id;
        liveBalance.value = null;
        if (id) {
            fetchLiveBalance(id);
        }
    },
    { immediate: true },
);

watch(selectedTemplateId, (id) => {
    if (id) {
        const tpl = props.templates.find((t) => t.id === id);
        if (tpl) {
            form.message = tpl.content;
            form.template_id = tpl.id;
        }
    }
});

// ── Actions ─────────────────────────────────────────────────────────
const resetForm = () => {
    form.reset();
    form.clearErrors();
    selectedCompanyId.value = null;
    selectedConfigId.value = null;
    selectedTemplateId.value = null;
    liveBalance.value = null;
    checkAndAutoSelect();
};

const handleClose = () => {
    resetForm();
    emit('close');
};

const submit = () => {
    form.post('/organization/bulk-sms/single/send', {
        onSuccess: () => {
            resetForm();
            emit('sent');
        },
    });
};
</script>

<template>
    <Teleport to="body">
        <transition
            enter-active-class="transition-all duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-all duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
            >
                <transition
                    enter-active-class="transition-all duration-200"
                    enter-from-class="opacity-0 scale-95 -translate-y-2"
                    enter-to-class="opacity-100 scale-100 translate-y-0"
                >
                    <div
                        class="flex max-h-[90vh] w-full max-w-xl flex-col overflow-hidden rounded-2xl border border-border bg-card/95 shadow-2xl"
                    >
                        <!-- Header -->
                        <div
                            class="flex shrink-0 items-center justify-between border-b border-border px-5 py-4"
                        >
                            <div class="flex items-center gap-2.5">
                                <div
                                    class="flex h-8 w-8 items-center justify-center rounded-lg border border-sky-500/20 bg-sky-500/10"
                                >
                                    <Send class="h-3.5 w-3.5 text-sky-500" />
                                </div>
                                <div>
                                    <p
                                        class="text-sm font-black tracking-tight text-foreground uppercase"
                                    >
                                        Compose SMS
                                    </p>
                                    <p
                                        class="text-[9px] font-bold tracking-widest text-muted-foreground uppercase"
                                    >
                                        Single message delivery
                                    </p>
                                </div>
                            </div>
                            <button
                                @click="handleClose"
                                class="rounded-lg p-1.5 text-muted-foreground transition-colors hover:bg-accent/10 hover:text-foreground"
                            >
                                <X class="h-4 w-4" />
                            </button>
                        </div>

                        <!-- Body -->
                        <div class="flex-1 space-y-4 overflow-y-auto p-5">
                            <!-- Gateway: Company → Branch -->
                            <div
                                class="space-y-3 rounded-xl border border-border bg-sky-500/5 p-4"
                            >
                                <div class="flex items-center gap-2">
                                    <Zap class="h-3.5 w-3.5 text-sky-500" />
                                    <span
                                        class="text-[10px] font-black tracking-tight uppercase"
                                        >Gateway</span
                                    >
                                </div>

                                <div class="grid grid-cols-2 gap-3">
                                    <div class="flex flex-col gap-1">
                                        <label
                                            class="flex items-center gap-1 text-[8px] font-black tracking-widest text-muted-foreground uppercase"
                                        >
                                            <Building2 class="h-2 w-2" />
                                            Company
                                        </label>
                                        <SearchableSelect
                                            v-model="selectedCompanyId"
                                            :options="companies"
                                            label="name"
                                            value-key="id"
                                            placeholder="Select company..."
                                        />
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <label
                                            class="flex items-center gap-1 text-[8px] font-black tracking-widest text-muted-foreground uppercase"
                                        >
                                            <GitBranch class="h-2 w-2" /> Branch
                                        </label>
                                        <SearchableSelect
                                            v-model="selectedConfigId"
                                            :options="configOptions"
                                            label="name"
                                            value-key="id"
                                            placeholder="Select branch..."
                                            :disabled="!selectedCompanyId"
                                        />
                                        <span
                                            v-if="form.errors.sms_config_id"
                                            class="text-[9px] font-bold text-rose-500 uppercase"
                                            >{{
                                                form.errors.sms_config_id
                                            }}</span
                                        >
                                    </div>
                                </div>

                                <!-- Balance panel -->
                                <transition
                                    enter-active-class="transition-all duration-200"
                                    enter-from-class="opacity-0 -translate-y-1"
                                    enter-to-class="opacity-100 translate-y-0"
                                >
                                    <div
                                        v-if="selectedConfig"
                                        class="flex items-center justify-between rounded-lg border border-emerald-500/10 bg-emerald-500/5 p-2.5"
                                    >
                                        <div class="flex items-center gap-2">
                                            <CreditCard
                                                class="h-3.5 w-3.5 text-emerald-500"
                                            />
                                            <div>
                                                <p
                                                    class="flex items-center gap-1 text-[8px] font-black tracking-widest text-muted-foreground uppercase"
                                                >
                                                    Balance
                                                    <RefreshCw
                                                        v-if="isFetchingBalance"
                                                        class="h-2.5 w-2.5 animate-spin text-sky-500"
                                                    />
                                                    <span
                                                        v-else
                                                        class="text-[7px] font-bold text-emerald-500"
                                                        >✓ LIVE</span
                                                    >
                                                </p>
                                                <p
                                                    v-if="isFetchingBalance"
                                                    class="font-mono text-base font-black text-muted-foreground"
                                                >
                                                    …
                                                </p>
                                                <p
                                                    v-else
                                                    class="font-mono text-base font-black text-emerald-600"
                                                >
                                                    {{ liveBalance ?? '—' }}
                                                    <span
                                                        class="text-[9px] font-bold"
                                                        >units</span
                                                    >
                                                </p>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <p
                                                class="text-[8px] font-black text-sky-600 uppercase"
                                            >
                                                {{
                                                    selectedConfig.sms_provider
                                                        ?.name ?? '—'
                                                }}
                                            </p>
                                            <p
                                                class="text-[8px] text-muted-foreground"
                                            >
                                                {{ selectedConfig.sender_id }}
                                            </p>
                                        </div>
                                    </div>
                                </transition>
                            </div>

                            <!-- Recipient -->
                            <div
                                class="space-y-2 rounded-xl border border-border p-4"
                            >
                                <div class="flex items-center gap-2">
                                    <Phone class="h-3.5 w-3.5 text-primary" />
                                    <span
                                        class="text-[10px] font-black tracking-tight uppercase"
                                        >Recipient</span
                                    >
                                </div>
                                <div class="relative">
                                    <Phone
                                        class="absolute top-1/2 left-3 h-3 w-3 -translate-y-1/2 text-muted-foreground"
                                    />
                                    <input
                                        v-model="form.phone_number"
                                        type="tel"
                                        placeholder="e.g. 0712345678"
                                        class="w-full rounded-lg border-border bg-background/50 py-2 pr-4 pl-8 text-sm text-foreground transition-all placeholder:text-muted-foreground focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none"
                                    />
                                </div>
                                <span
                                    v-if="form.errors.phone_number"
                                    class="text-[9px] font-bold text-rose-500 uppercase"
                                    >{{ form.errors.phone_number }}</span
                                >
                                <p
                                    class="text-[8px] text-muted-foreground italic"
                                >
                                    Numbers are auto-formatted to 254XXXXXXXXX
                                    before sending.
                                </p>
                            </div>

                            <!-- Message -->
                            <div
                                class="space-y-3 rounded-xl border border-border p-4"
                            >
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <MessageSquare
                                            class="h-3.5 w-3.5 text-primary"
                                        />
                                        <span
                                            class="text-[10px] font-black tracking-tight uppercase"
                                            >Message</span
                                        >
                                    </div>
                                    <div
                                        class="flex items-center gap-2 text-[9px] font-black tracking-widest text-muted-foreground uppercase"
                                    >
                                        <span>{{ charCount }} chars</span>
                                        <span
                                            class="rounded border border-border bg-accent/10 px-1.5 py-0.5"
                                            >{{ smsCount }} SMS</span
                                        >
                                    </div>
                                </div>
                                <SearchableSelect
                                    v-model="selectedTemplateId"
                                    :options="templates"
                                    label="name"
                                    value-key="id"
                                    placeholder="Use a template (optional)..."
                                />
                                <textarea
                                    v-model="form.message"
                                    rows="5"
                                    placeholder="Type your message here..."
                                    class="w-full resize-none rounded-lg border-border bg-background/50 px-3 py-2.5 text-sm text-foreground transition-all placeholder:text-muted-foreground focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none"
                                ></textarea>
                                <span
                                    v-if="form.errors.message"
                                    class="text-[9px] font-bold text-rose-500 uppercase"
                                    >{{ form.errors.message }}</span
                                >
                            </div>
                        </div>

                        <!-- Footer -->
                        <div
                            class="flex shrink-0 items-center justify-between border-t border-border px-5 py-4"
                        >
                            <span
                                v-if="form.processing"
                                class="flex items-center gap-1.5 text-[9px] font-black tracking-widest text-sky-500 uppercase"
                            >
                                <RefreshCw class="h-3 w-3 animate-spin" />
                                Queuing...
                            </span>
                            <span v-else></span>
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    @click="handleClose"
                                    class="rounded-lg px-4 py-1.5 text-[10px] font-black tracking-widest text-muted-foreground uppercase transition-colors hover:bg-accent/10"
                                >
                                    Cancel
                                </button>
                                <button
                                    @click="submit"
                                    :disabled="
                                        form.processing ||
                                        !form.sms_config_id ||
                                        !form.phone_number ||
                                        !form.message
                                    "
                                    class="flex items-center gap-1.5 rounded-lg bg-foreground px-6 py-1.5 text-[10px] font-black tracking-wider text-background uppercase shadow-lg transition-all hover:opacity-90 active:scale-95 disabled:opacity-50"
                                >
                                    <Send class="h-3 w-3" />
                                    Send Message
                                </button>
                            </div>
                        </div>
                    </div>
                </transition>
            </div>
        </transition>
    </Teleport>
</template>
