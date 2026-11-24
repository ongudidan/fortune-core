<script setup lang="ts">
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '../Layouts/AppLayout.vue';
const dashboard = (route: string) => route;
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowRight,
    FileText,
    Mail,
    Megaphone,
    MessageSquare,
    Send,
    Server,
    Settings,
} from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    stats: {
        total_templates: number;
        active_configs: number;
        total_branches: number;
        total_providers: number;
        total_single_sms: number;
        total_campaigns: number;
        active_system_messages: number;
    };
}

const props = defineProps<Props>();

const page = usePage();
const can = (permission: string) => {
    return (page.props.auth as any).permissions.includes(permission);
};

const smsAlert = computed(() => {
    return (
        (page.props.auth as any)?.sms_alert ||
        (page.props as any)?.sms_alert ||
        null
    );
});

const breadcrumbs = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Bulk SMS Management', href: '/organization/bulk-sms' },
];

const smsCards = [
    {
        title: 'SMS Templates',
        description:
            'Create and manage message templates for various triggers.',
        icon: FileText,
        href: '/organization/bulk-sms/templates',
        color: 'text-amber-500',
        borderColor: 'border-amber-500/20',
        bgColor: 'bg-amber-500/5',
        stat: props.stats.total_templates,
        label: 'Total Templates',
        permission: 'SMS: Templates: View',
    },
    {
        title: 'API Configurations',
        description:
            'Configure TextSMS API credentials and Sender IDs for branches.',
        icon: Settings,
        href: '/organization/bulk-sms/configs',
        color: 'text-emerald-500',
        borderColor: 'border-emerald-500/20',
        bgColor: 'bg-emerald-500/5',
        stat: props.stats.active_configs,
        label: 'Active Configs',
        permission: 'SMS: Configs: View',
    },
    {
        title: 'Send Single SMS',
        description:
            'Compose a targeted message and send it directly to a single recipient.',
        icon: Send,
        href: '/organization/bulk-sms/single',
        color: 'text-sky-500',
        borderColor: 'border-sky-500/20',
        bgColor: 'bg-sky-500/5',
        stat: props.stats.total_single_sms,
        label: 'Messages Sent',
        permission: 'SMS: Single: View',
    },
    {
        title: 'Bulk Campaigns',
        description:
            'Broadcast messages to all your customers at once, with scheduling support.',
        icon: Megaphone,
        href: '/organization/bulk-sms/campaigns',
        color: 'text-violet-500',
        borderColor: 'border-violet-500/20',
        bgColor: 'bg-violet-500/5',
        stat: props.stats.total_campaigns,
        label: 'Campaigns Sent',
        permission: 'SMS: Campaigns: View',
    },
    {
        title: 'Message Logs',
        description:
            'Full audit trail — track all sent messages and delivery statuses.',
        icon: Mail,
        href: '/organization/bulk-sms/logs',
        color: 'text-indigo-500',
        borderColor: 'border-indigo-500/20',
        bgColor: 'bg-indigo-500/5',
        stat: 'Logs',
        label: 'Audit Trail',
        permission: 'SMS: Logs: View',
    },
    {
        title: 'System Messages',
        description:
            'Automated templates for purchases, returns, payments, and reminders.',
        icon: MessageSquare,
        href: '/organization/bulk-sms/system-messages',
        color: 'text-blue-500',
        borderColor: 'border-blue-500/20',
        bgColor: 'bg-blue-500/5',
        stat: props.stats.active_system_messages,
        label: 'Active Automations',
        permission: 'SMS: SystemMessages: View',
    },
    {
        title: 'SMS Providers',
        description: 'Manage SMS gateway providers like TextSMS, Advanta, etc.',
        icon: Server,
        href: '/organization/bulk-sms/providers',
        color: 'text-rose-500',
        borderColor: 'border-rose-500/20',
        bgColor: 'bg-rose-500/5',
        stat: props.stats.total_providers,
        label: 'Active Providers',
        permission: 'SMS: Providers: View',
    },
];
</script>

<template>
    <Head title="Bulk SMS Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="relative flex min-h-screen flex-1 flex-col overflow-hidden p-6 md:p-8"
        >
            <!-- Page Header -->
            <div class="relative z-10 mb-8 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-lg border border-border bg-accent/5 text-primary"
                    >
                        <Mail class="h-5 w-5 text-sky-600" />
                    </div>
                    <div>
                        <h1
                            class="text-xl font-bold tracking-tight tracking-wider text-foreground uppercase"
                        >
                            Bulk SMS Management
                        </h1>
                        <p
                            class="mt-0.5 text-[10px] font-bold tracking-[0.2em] text-muted-foreground uppercase"
                        >
                            Communication & Notification Hub
                        </p>
                    </div>
                </div>

                <div
                    class="hidden items-center gap-4 rounded-lg border border-border bg-accent/10 px-4 py-2 backdrop-blur-sm md:flex"
                >
                    <div class="flex flex-col items-center">
                        <span
                            class="text-[8px] leading-tight font-black tracking-widest text-muted-foreground uppercase"
                            >Branches Configured</span
                        >
                        <span class="text-xs font-bold text-foreground"
                            >{{ stats.active_configs }} /
                            {{ stats.total_branches }}</span
                        >
                    </div>
                </div>
            </div>

            <!-- Dashboard Grid -->
            <div class="relative z-10 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <template v-for="card in smsCards" :key="card.title">
                    <Link
                        v-if="can(card.permission)"
                        :href="card.href"
                        class="group relative h-full"
                    >
                        <Card
                            :class="[
                                'relative flex h-full flex-col justify-between overflow-hidden rounded-lg border-border bg-card/40 backdrop-blur-xl transition-all duration-300 group-hover:border-accent group-hover:bg-card/50',
                                card.title === 'API Configurations' && smsAlert?.has_alert
                                    ? 'border-amber-500/40 bg-amber-500/[0.03] shadow-sm shadow-amber-500/5'
                                    : '',
                            ]"
                        >
                            <CardHeader class="p-5">
                                <div
                                    class="mb-4 flex items-center justify-between"
                                >
                                    <div
                                        :class="[
                                            card.bgColor,
                                            card.borderColor,
                                            'rounded-md border p-2.5 transition-all duration-300 group-hover:scale-105',
                                        ]"
                                    >
                                        <component
                                            :is="card.icon"
                                            :class="['h-5 w-5', card.color]"
                                        />
                                    </div>
                                    <div class="flex flex-col items-end">
                                        <div
                                            v-if="
                                                card.title ===
                                                    'API Configurations' &&
                                                smsAlert?.has_alert
                                            "
                                            class="mb-1 flex items-center gap-1 rounded-full border border-amber-500/30 bg-amber-500/15 px-2 py-0.5 text-[9px] font-bold text-amber-500 animate-pulse"
                                        >
                                            <AlertTriangle class="h-3 w-3" />
                                            <span>Low Balance</span>
                                        </div>
                                        <span
                                            class="text-lg leading-none font-bold text-foreground"
                                            >{{ card.stat }}</span
                                        >
                                        <span
                                            class="mt-1 text-[8px] font-black tracking-widest text-muted-foreground uppercase"
                                            >{{ card.label }}</span
                                        >
                                    </div>
                                </div>

                                <CardTitle
                                    class="text-sm font-bold tracking-wider text-foreground uppercase transition-colors group-hover:text-primary"
                                >
                                    {{ card.title }}
                                </CardTitle>
                                <CardDescription
                                    class="mt-1.5 text-xs leading-relaxed font-medium text-muted-foreground"
                                >
                                    {{ card.description }}
                                </CardDescription>

                                <!-- Low SMS Balance Warning Badge within Card -->
                                <div
                                    v-if="
                                        card.title === 'API Configurations' &&
                                        smsAlert?.has_alert
                                    "
                                    class="mt-3 flex items-center justify-between gap-2 rounded-lg border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-xs"
                                >
                                    <div class="flex items-center gap-2">
                                        <AlertTriangle
                                            class="h-4 w-4 shrink-0 text-amber-500"
                                        />
                                        <div class="text-[11px] leading-tight">
                                            <span
                                                class="font-bold text-amber-600 dark:text-amber-400"
                                                >{{ smsAlert.balance }} units
                                                left</span
                                            >
                                            <span class="text-muted-foreground block text-[10px]">
                                                Threshold:
                                                {{ smsAlert.threshold }} units
                                                ({{ smsAlert.provider_name }})
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </CardHeader>

                            <CardContent class="px-5 pb-5">
                                <div
                                    :class="[
                                        'flex items-center text-[9px] font-black tracking-widest uppercase transition-colors',
                                        card.title === 'API Configurations' &&
                                        smsAlert?.has_alert
                                            ? 'text-amber-500 group-hover:text-amber-400'
                                            : 'text-primary/70 group-hover:text-primary',
                                    ]"
                                >
                                    <span>{{
                                        card.title === 'API Configurations' &&
                                        smsAlert?.has_alert
                                            ? 'Manage Configs'
                                            : 'Go to Module'
                                    }}</span>
                                    <ArrowRight
                                        class="ml-1.5 h-3 w-3 transition-transform group-hover:translate-x-1"
                                    />
                                </div>
                            </CardContent>
                        </Card>
                    </Link>
                </template>
            </div>

            <!-- Status Dots -->
            <div
                class="relative z-10 mt-auto flex justify-center pt-12 opacity-30"
            >
                <div class="flex items-center gap-6">
                    <div
                        v-for="i in 3"
                        :key="i"
                        class="h-1 w-1 rounded-full bg-foreground/20"
                    ></div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
