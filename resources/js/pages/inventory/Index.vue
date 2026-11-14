<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Search, X } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { computed, ref } from 'vue';
import VehicleCard from '@/components/crm/VehicleCard.vue';
import { useCrm } from '@/composables/useCrm';
import { count } from '@/lib/format';
import { index as inventoryIndex } from '@/routes/inventory';
import type { Paginated, Vehicle } from '@/types/crm';

type Filters = Partial<
    Record<
        | 'q'
        | 'body'
        | 'powertrain'
        | 'condition'
        | 'status'
        | 'max_price'
        | 'min_seats'
        | 'min_towing'
        | 'sort',
        string
    >
>;

const { vehicles, filters, summary } = defineProps<{
    vehicles: Paginated<Vehicle>;
    filters: Filters;
    summary: { available: number; aged: number };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Inventory', href: inventoryIndex() }],
    },
});

const { options } = useCrm();
const search = ref(filters.q ?? '');

function apply(changes: Filters): void {
    const query = Object.fromEntries(
        Object.entries({ ...filters, ...changes }).filter(
            ([, v]) => v !== '' && v != null,
        ),
    );

    router.get(inventoryIndex().url, query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['vehicles', 'filters'],
    });
}

// v-model rather than :value, because a bound value set before the <option>s render leaves
// the select blank.
const sort = computed({
    get: () => filters.sort ?? 'newest',
    set: (value: string) => apply({ sort: value }),
});

const onSearch = useDebounceFn(() => apply({ q: search.value }), 250);

const hasFilters = computed(() =>
    Object.entries(filters).some(([key, value]) => key !== 'sort' && value),
);

function clearAll(): void {
    search.value = '';
    router.get(
        inventoryIndex().url,
        filters.sort ? { sort: filters.sort } : {},
        { preserveState: true },
    );
}

// Quick needs, phrased the way customers say them. Each one is just a preset filter.
const needs: { label: string; filters: Filters }[] = [
    { label: '7+ seats', filters: { min_seats: '7' } },
    { label: 'Tows 3,500 lb+', filters: { min_towing: '3500' } },
    { label: 'Under $30k', filters: { max_price: '30000' } },
    { label: 'Electric', filters: { powertrain: 'ev' } },
    { label: 'Hybrid', filters: { powertrain: 'hybrid' } },
];

const isActive = (preset: Filters) =>
    Object.entries(preset).every(([k, v]) => filters[k as keyof Filters] === v);

function toggle(preset: Filters): void {
    apply(
        isActive(preset)
            ? Object.fromEntries(Object.keys(preset).map((k) => [k, '']))
            : preset,
    );
}

const select =
    'h-9 rounded-lg border bg-background px-2.5 text-sm outline-none focus:border-primary/50';
</script>

<template>
    <Head title="Inventory" />

    <div class="flex min-w-0 flex-1 flex-col gap-5 p-4 md:p-6">
        <header class="flex flex-col items-start">
            <div class="sign px-6 pt-4 pb-5">
                <h1
                    class="text-[2rem] leading-none font-extrabold tracking-tight"
                >
                    Inventory
                </h1>
                <dl
                    class="mt-3 grid grid-cols-[1fr_auto] gap-x-10 gap-y-0.5 text-[15px] font-semibold"
                >
                    <dt>On the lot</dt>
                    <dd class="text-right tabular-nums">
                        {{ count(summary.available) }}
                    </dd>
                    <dt>Showing</dt>
                    <dd class="text-right tabular-nums">
                        {{ count(vehicles.meta.total) }}
                    </dd>
                </dl>
            </div>
            <p
                v-if="summary.aged"
                class="-mt-px ml-5 sign-tab px-3 pt-1 pb-1.5 text-xs font-bold"
            >
                {{ summary.aged }} over 60 days on the lot
            </p>
        </header>

        <div class="flex flex-col gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <label class="relative w-full sm:w-80">
                    <span class="sr-only">Search inventory</span>
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Make, model, colour, stock # or VIN"
                        class="h-9 w-full rounded-lg border bg-background pr-3 pl-9 text-sm outline-none placeholder:text-muted-foreground focus:border-primary/50 focus:ring-3 focus:ring-primary/15"
                        @input="onSearch"
                    />
                </label>
                <select
                    :value="filters.body ?? ''"
                    aria-label="Body style"
                    :class="select"
                    @change="
                        apply({
                            body: ($event.target as HTMLSelectElement).value,
                        })
                    "
                >
                    <option value="">Any body</option>
                    <option
                        v-for="o in options.bodyStyles"
                        :key="o.value"
                        :value="o.value"
                    >
                        {{ o.label }}
                    </option>
                </select>
                <select
                    :value="filters.condition ?? ''"
                    aria-label="Condition"
                    :class="select"
                    @change="
                        apply({
                            condition: ($event.target as HTMLSelectElement)
                                .value,
                        })
                    "
                >
                    <option value="">New and used</option>
                    <option
                        v-for="o in options.vehicleConditions"
                        :key="o.value"
                        :value="o.value"
                    >
                        {{ o.label }}
                    </option>
                </select>
                <select
                    v-model="sort"
                    aria-label="Sort"
                    :class="[select, 'sm:ml-auto']"
                >
                    <option value="newest">Newest arrivals</option>
                    <option value="oldest">Longest on the lot</option>
                    <option value="price_low">Price, low to high</option>
                    <option value="price_high">Price, high to low</option>
                    <option value="km_low">Lowest kilometres</option>
                </select>
            </div>

            <div
                class="flex flex-wrap items-center gap-1.5"
                role="group"
                aria-label="Customer needs"
            >
                <span class="mr-1 text-xs text-muted-foreground"
                    >Customer needs:</span
                >
                <button
                    v-for="need in needs"
                    :key="need.label"
                    type="button"
                    :aria-pressed="isActive(need.filters)"
                    class="h-7 rounded-full border px-3 text-xs font-medium transition-colors hover:border-primary/40 aria-pressed:border-primary aria-pressed:bg-primary/10 aria-pressed:text-primary"
                    @click="toggle(need.filters)"
                >
                    {{ need.label }}
                </button>
                <button
                    v-if="hasFilters"
                    type="button"
                    class="ml-1 flex h-7 items-center gap-1 rounded-full px-2 text-xs text-muted-foreground hover:text-foreground"
                    @click="clearAll"
                >
                    <X class="size-3.5" /> Clear
                </button>
            </div>
        </div>

        <div
            v-if="vehicles.data.length"
            class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4"
        >
            <VehicleCard
                v-for="vehicle in vehicles.data"
                :key="vehicle.id"
                :vehicle="vehicle"
            />
        </div>
        <div v-else class="rounded-xl border border-dashed py-16 text-center">
            <p class="font-semibold">Nothing on the lot matches that.</p>
            <p class="mt-1 text-sm text-muted-foreground">
                Loosen a filter, or clear them all to see everything.
            </p>
        </div>

        <nav
            v-if="vehicles.meta.last_page > 1"
            class="flex items-center justify-between text-sm text-muted-foreground"
            aria-label="Pages"
        >
            <span
                >{{ vehicles.meta.from }}–{{ vehicles.meta.to }} of
                {{ vehicles.meta.total }}</span
            >
            <div class="flex gap-2">
                <Link
                    v-if="vehicles.links.prev"
                    :href="vehicles.links.prev"
                    class="rounded-lg border px-3 py-1.5 hover:border-primary/40"
                    >Previous</Link
                >
                <Link
                    v-if="vehicles.links.next"
                    :href="vehicles.links.next"
                    class="rounded-lg border px-3 py-1.5 hover:border-primary/40"
                    >Next</Link
                >
            </div>
        </nav>
    </div>
</template>
