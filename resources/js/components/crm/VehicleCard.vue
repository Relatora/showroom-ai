<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import ToneBadge from '@/components/crm/ToneBadge.vue';
import VehicleArt from '@/components/crm/VehicleArt.vue';
import { useCrm } from '@/composables/useCrm';
import { km, money } from '@/lib/format';
import { show } from '@/routes/inventory';
import type { Vehicle } from '@/types/crm';

const { vehicle } = defineProps<{ vehicle: Vehicle }>();

const { label, tone } = useCrm();

/**
 * The one spec that matters most for this kind of vehicle. Nobody shopping for an EV asks
 * about fuel economy, and nobody shopping for a pickup leads with seating.
 */
function headlineSpec(v: Vehicle): string {
    if (v.range_km) {
        return `${v.range_km} km range`;
    }

    if (v.body_style === 'truck' && v.towing_lb) {
        return `Tows ${v.towing_lb.toLocaleString('en-CA')} lb`;
    }

    if (v.seats >= 7) {
        return `${v.seats} seats`;
    }

    return v.fuel_l_per_100km
        ? `${v.fuel_l_per_100km} L/100 km`
        : v.drivetrain_label;
}
</script>

<template>
    <article
        class="group relative flex flex-col overflow-hidden rounded-xl border bg-card transition-colors hover:border-primary/40"
    >
        <div class="relative bg-muted/60 px-8 pt-6 pb-3">
            <VehicleArt
                :body-style="vehicle.body_style"
                :color="vehicle.exterior_hex"
            />
            <div class="absolute top-3 left-3 flex gap-1.5">
                <ToneBadge
                    v-if="vehicle.status !== 'available'"
                    :tone="tone('vehicleStatuses', vehicle.status)"
                >
                    {{ label('vehicleStatuses', vehicle.status) }}
                </ToneBadge>
                <ToneBadge
                    v-if="vehicle.condition !== 'used'"
                    :tone="tone('vehicleConditions', vehicle.condition)"
                >
                    {{ label('vehicleConditions', vehicle.condition) }}
                </ToneBadge>
            </div>
            <span
                v-if="vehicle.leads_count"
                class="absolute top-3 right-3 rounded-full bg-background/90 px-2 py-0.5 text-[11px] font-semibold ring-1 ring-border"
                :title="`${vehicle.leads_count} open lead(s) interested`"
            >
                {{ vehicle.leads_count }} interested
            </span>
        </div>

        <div class="flex flex-1 flex-col p-4">
            <Link
                :href="show(vehicle.id)"
                class="leading-snug font-bold after:absolute after:inset-0"
            >
                {{ vehicle.year }} {{ vehicle.make }} {{ vehicle.model }}
            </Link>
            <p class="text-sm text-muted-foreground">
                {{ vehicle.trim }}, {{ vehicle.exterior_color }}
            </p>

            <div class="mt-auto flex items-end justify-between gap-2 pt-4">
                <p class="text-xl font-extrabold tabular-nums">
                    {{ money(vehicle.price) }}
                </p>
                <p class="text-right text-xs text-muted-foreground">
                    <span class="block font-semibold text-foreground">{{
                        headlineSpec(vehicle)
                    }}</span>
                    {{ km(vehicle.km) }}
                </p>
            </div>
        </div>
    </article>
</template>
