<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Check } from '@lucide/vue';
import { computed } from 'vue';
import LeadCard from '@/components/crm/LeadCard.vue';
import ToneBadge from '@/components/crm/ToneBadge.vue';
import VehicleArt from '@/components/crm/VehicleArt.vue';
import VehicleCard from '@/components/crm/VehicleCard.vue';
import { useCrm } from '@/composables/useCrm';
import { count, km, money } from '@/lib/format';
import { index as inventoryIndex } from '@/routes/inventory';
import type { Lead, Vehicle } from '@/types/crm';

const { vehicle, leads, similar } = defineProps<{
    vehicle: Vehicle;
    leads: Lead[];
    similar: Vehicle[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Inventory', href: inventoryIndex() }],
    },
});

const { label, tone } = useCrm();

// Laid out like the window sticker on a car: label on the left, value on the right.
const specs = computed(() =>
    [
        ['Body', label('bodyStyles', vehicle.body_style)],
        ['Powertrain', label('powertrains', vehicle.powertrain)],
        ['Drivetrain', vehicle.drivetrain_label],
        ['Seats', String(vehicle.seats)],
        ['Kilometres', km(vehicle.km)],
        ['Exterior', vehicle.exterior_color],
        ['Interior', vehicle.interior_color],
        ['Towing', vehicle.towing_lb ? `${count(vehicle.towing_lb)} lb` : null],
        ['Electric range', vehicle.range_km ? `${vehicle.range_km} km` : null],
        [
            'Fuel economy',
            vehicle.fuel_l_per_100km
                ? `${vehicle.fuel_l_per_100km} L/100 km combined`
                : null,
        ],
    ].filter((row): row is [string, string] => row[1] !== null),
);
</script>

<template>
    <Head :title="vehicle.title" />

    <div class="flex min-w-0 flex-1 flex-col gap-6 p-4 md:p-6">
        <Link
            :href="inventoryIndex()"
            class="flex w-fit items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" /> All inventory
        </Link>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_24rem]">
            <div class="flex min-w-0 flex-col gap-6">
                <section
                    class="rounded-xl border bg-muted/50 px-[8%] pt-10 pb-6"
                >
                    <VehicleArt
                        :body-style="vehicle.body_style"
                        :color="vehicle.exterior_hex"
                    />
                </section>

                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <ToneBadge
                            :tone="tone('vehicleStatuses', vehicle.status)"
                            dot
                        >
                            {{ label('vehicleStatuses', vehicle.status) }}
                        </ToneBadge>
                        <ToneBadge
                            :tone="tone('vehicleConditions', vehicle.condition)"
                        >
                            {{ label('vehicleConditions', vehicle.condition) }}
                        </ToneBadge>
                        <span class="text-xs text-muted-foreground"
                            >{{ vehicle.days_on_lot }} days on the lot</span
                        >
                    </div>
                    <h1 class="mt-2 text-3xl font-extrabold tracking-tight">
                        {{ vehicle.title }}
                    </h1>
                    <p
                        class="mt-2 max-w-prose text-[15px] leading-relaxed text-muted-foreground"
                    >
                        {{ vehicle.description }}
                    </p>
                </div>

                <section aria-labelledby="interested-heading">
                    <h2 id="interested-heading" class="mb-3 text-sm font-bold">
                        Interested customers
                        <span class="font-normal text-muted-foreground"
                            >({{ leads.length }})</span
                        >
                    </h2>
                    <div v-if="leads.length" class="grid gap-3 sm:grid-cols-2">
                        <LeadCard
                            v-for="lead in leads"
                            :key="lead.id"
                            :lead="lead"
                        />
                    </div>
                    <p
                        v-else
                        class="rounded-xl border border-dashed px-4 py-6 text-sm text-muted-foreground"
                    >
                        None of your open leads have asked about this one yet.
                    </p>
                </section>
            </div>

            <!--
                The window sticker. Paper-white in both themes, because a real sticker doesn't go
                dark at night either.
            -->
            <aside
                class="h-fit rounded-lg bg-white p-6 text-neutral-900 shadow-sm ring-1 ring-black/10 lg:sticky lg:top-6"
                aria-label="Window sticker"
            >
                <p class="text-sm font-bold">
                    {{ vehicle.year }} {{ vehicle.make }}
                </p>
                <p class="text-2xl leading-tight font-extrabold">
                    {{ vehicle.model }} {{ vehicle.trim }}
                </p>
                <p class="mt-1 font-mono text-[11px] text-neutral-500">
                    Stock {{ vehicle.stock_number }}, VIN {{ vehicle.vin }}
                </p>

                <div class="my-4 border-y-2 border-neutral-900 py-3">
                    <p class="text-xs font-semibold text-neutral-600">Price</p>
                    <p class="text-4xl font-extrabold tabular-nums">
                        {{ money(vehicle.price) }}
                    </p>
                </div>

                <dl class="divide-y divide-neutral-200 text-sm">
                    <div
                        v-for="[name, value] in specs"
                        :key="name"
                        class="flex justify-between gap-4 py-1.5"
                    >
                        <dt class="text-neutral-600">{{ name }}</dt>
                        <dd class="text-right font-semibold">{{ value }}</dd>
                    </div>
                </dl>

                <h2 class="mt-5 text-xs font-bold">Equipment</h2>
                <ul class="mt-2 grid gap-1 text-sm">
                    <li
                        v-for="feature in vehicle.features"
                        :key="feature"
                        class="flex items-start gap-2"
                    >
                        <Check
                            class="mt-0.5 size-3.5 shrink-0 text-emerald-700"
                        />
                        {{ feature }}
                    </li>
                </ul>
            </aside>
        </div>

        <section v-if="similar.length" aria-labelledby="similar-heading">
            <h2 id="similar-heading" class="mb-3 text-sm font-bold">
                If they like this, show them
            </h2>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <VehicleCard v-for="v in similar" :key="v.id" :vehicle="v" />
            </div>
        </section>
    </div>
</template>
