<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Car, Clock3, Flame } from '@lucide/vue';
import ToneBadge from '@/components/crm/ToneBadge.vue';
import UserAvatar from '@/components/crm/UserAvatar.vue';
import { useCrm } from '@/composables/useCrm';
import { money, timeAgo } from '@/lib/format';
import { show } from '@/routes/leads';
import type { Lead } from '@/types/crm';

const { lead, dragging = false } = defineProps<{
    lead: Lead;
    dragging?: boolean;
}>();

const { label, tone } = useCrm();
</script>

<template>
    <article
        :class="[
            'group relative rounded-xl border bg-card p-3 text-sm shadow-xs transition-[transform,box-shadow,opacity,border-color] duration-200',
            dragging
                ? 'scale-[0.98] rotate-1 opacity-50'
                : 'hover:border-primary/40',
        ]"
    >
        <!-- A thin accent strip down the left edge for hot leads, so they jump out of a busy column. -->
        <span
            v-if="lead.temperature === 'hot'"
            aria-hidden="true"
            class="absolute inset-y-3 left-0 w-0.5 rounded-full bg-destructive-foreground"
        />

        <div class="flex items-start justify-between gap-2">
            <Link
                :href="show(lead.id)"
                class="leading-tight font-medium decoration-primary/50 underline-offset-2 after:absolute after:inset-0 hover:underline"
            >
                {{ lead.customer?.name }}
            </Link>
            <UserAvatar :name="lead.assignee?.name" class="relative" />
        </div>

        <p class="mt-1.5 line-clamp-2 text-xs text-muted-foreground">
            {{ lead.interest }}
        </p>

        <p
            v-if="lead.vehicle"
            class="mt-2 flex items-center gap-1.5 truncate text-xs text-foreground/80"
        >
            <Car class="size-3.5 shrink-0 text-muted-foreground" />
            <span class="truncate">{{ lead.vehicle.title }}</span>
        </p>

        <div class="mt-3 flex flex-wrap items-center gap-1.5">
            <ToneBadge :tone="tone('temperatures', lead.temperature)">
                <Flame v-if="lead.temperature === 'hot'" class="size-3" />
                {{ label('temperatures', lead.temperature) }}
            </ToneBadge>
            <span
                v-if="lead.budget_max"
                class="text-[11px] text-muted-foreground tabular-nums"
            >
                Up to {{ money(lead.budget_max) }}
            </span>
            <span
                :class="[
                    'ml-auto flex items-center gap-1 text-[11px]',
                    lead.is_stale
                        ? 'font-medium text-destructive-foreground'
                        : 'text-muted-foreground',
                ]"
                :title="lead.is_stale ? 'No contact in over 3 days' : undefined"
            >
                <Clock3 class="size-3" />
                {{ timeAgo(lead.last_contacted_at) }}
            </span>
        </div>
    </article>
</template>
