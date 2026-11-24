<script setup lang="ts">
import SearchableSelect from './SearchableSelect.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

interface SmsTemplate {
    id: number;
    name: string;
    content: string;
    trigger_type: string | null;
    is_active: boolean;
}

const props = defineProps<{
    show: boolean;
    template?: SmsTemplate;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const form = useForm({
    name: '',
    content: '',
    trigger_type: '',
    is_active: true,
});

watch(
    () => props.template,
    (newTemplate) => {
        if (newTemplate) {
            form.name = newTemplate.name;
            form.content = newTemplate.content;
            form.trigger_type = newTemplate.trigger_type || '';
            form.is_active = newTemplate.is_active;
        } else {
            form.reset();
        }
    },
    { immediate: true },
);

const submit = () => {
    if (props.template) {
        form.put(`/organization/bulk-sms/templates/${props.template.id}`, {
            onSuccess: () => {
                emit('close');
                form.reset();
            },
        });
    } else {
        form.post('/organization/bulk-sms/templates', {
            onSuccess: () => {
                emit('close');
                form.reset();
            },
        });
    }
};

const triggerOptions = [
    { label: 'Manual (No Trigger)', value: '' },
    { label: 'Sale Completed', value: 'sale_completed' },
    { label: 'Invoice Payment Received', value: 'invoice_payment' },
    { label: 'M-Pesa Payment Received', value: 'mpesa_payment' },
    { label: 'Credit Note Issued', value: 'credit_note_issued' },
    { label: 'Stock Low Warning', value: 'low_stock' },
    { label: 'Customer Birthday', value: 'birthday' },
];
</script>

<template>
    <Dialog :open="show" @update:open="$emit('close')">
        <DialogContent
            class="border-border bg-card/95 backdrop-blur-xl sm:max-w-[500px]"
        >
            <DialogHeader>
                <DialogTitle
                    class="text-[14px] font-black tracking-widest text-foreground uppercase"
                >
                    {{ template ? 'Edit SMS Template' : 'New SMS Template' }}
                </DialogTitle>
                <DialogDescription
                    class="text-[10px] font-bold tracking-widest text-muted-foreground uppercase"
                >
                    Define the message content and triggers for automated SMS.
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submit" class="grid gap-5 py-4">
                <div class="grid gap-2">
                    <Label
                        for="name"
                        class="text-[10px] font-black tracking-widest text-muted-foreground uppercase"
                        >Template Name</Label
                    >
                    <Input
                        id="name"
                        v-model="form.name"
                        placeholder="e.g. Sales Receipt Notification"
                        required
                        class="border-border bg-background/50 text-xs focus:ring-primary/20"
                    />
                    <span
                        v-if="form.errors.name"
                        class="text-[10px] font-bold tracking-tight text-rose-500 uppercase"
                        >{{ form.errors.name }}</span
                    >
                </div>

                <div class="grid gap-2">
                    <Label
                        for="trigger_type"
                        class="text-[10px] font-black tracking-widest text-muted-foreground uppercase"
                        >Auto-Trigger Event</Label
                    >
                    <SearchableSelect
                        v-model="form.trigger_type"
                        :options="triggerOptions"
                        label="label"
                        value-key="value"
                        placeholder="Select an event"
                    />
                    <span
                        v-if="form.errors.trigger_type"
                        class="text-[10px] font-bold tracking-tight text-rose-500 uppercase"
                        >{{ form.errors.trigger_type }}</span
                    >
                </div>

                <div class="grid gap-2">
                    <div class="flex items-center justify-between">
                        <Label
                            for="content"
                            class="text-[10px] font-black tracking-widest text-muted-foreground uppercase"
                            >Message Content</Label
                        >
                        <span
                            class="text-[9px] font-black tracking-widest text-primary uppercase"
                            >{{ form.content.length }} characters</span
                        >
                    </div>
                    <Textarea
                        id="content"
                        v-model="form.content"
                        placeholder="Hello {customer_name}, your order {order_number} is ready..."
                        required
                        class="min-h-[120px] border-border bg-background/50 text-xs leading-relaxed focus:ring-primary/20"
                    />
                    <p
                        class="text-[9px] font-bold tracking-tighter text-muted-foreground uppercase"
                    >
                        Use placeholders like {customer_name}, {amount},
                        {order_number} (logic pending implementation).
                    </p>
                    <span
                        v-if="form.errors.content"
                        class="text-[10px] font-bold tracking-tight text-rose-500 uppercase"
                        >{{ form.errors.content }}</span
                    >
                </div>

                <div
                    class="flex items-center justify-between rounded-lg border border-border/50 bg-accent/5 p-3"
                >
                    <div class="grid gap-0.5">
                        <Label
                            for="is_active_template"
                            class="cursor-pointer text-[10px] font-black tracking-widest text-foreground uppercase"
                            >Active Status</Label
                        >
                        <span
                            class="text-[9px] font-bold tracking-widest text-muted-foreground uppercase"
                            >Enable or disable this template</span
                        >
                    </div>
                    <input
                        id="is_active_template"
                        v-model="form.is_active"
                        type="checkbox"
                        class="h-4 w-4 cursor-pointer rounded border-border bg-card/40 text-primary focus:ring-2 focus:ring-accent/20"
                    />
                </div>
            </form>

            <DialogFooter>
                <button
                    type="button"
                    @click="$emit('close')"
                    class="px-4 py-2 text-[10px] font-black tracking-widest text-muted-foreground uppercase transition-colors hover:text-foreground"
                >
                    Cancel
                </button>
                <button
                    @click="submit"
                    :disabled="form.processing"
                    class="rounded-lg bg-primary px-5 py-2 text-[10px] font-black tracking-widest text-primary-foreground uppercase shadow-lg shadow-primary/20 transition-all hover:scale-105 active:scale-95 disabled:opacity-50"
                >
                    {{ template ? 'Save Changes' : 'Create Template' }}
                </button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
