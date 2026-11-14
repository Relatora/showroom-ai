<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Flame, KanbanSquare, List, Search } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { computed, ref } from 'vue';
import LeadBoard from '@/components/crm/LeadBoard.vue';
import LeadCard from '@/components/crm/LeadCard.vue';
import { compactMoney, count } from '@/lib/format';
import { index as leadsIndex } from '@/routes/leads';
import type { Lead, Paginated, UserSummary } from '@/types/crm';

type Filters = {
    q?: string;
    temperature?: string;
    assignee?: string;
    stale?: string;
};

const { view, leads, filters, team } = defineProps<{
    view: 'board' | 'list';
    leads: Paginated<Lead> | { data: Lead[] };
    filters: Filters;
    team: UserSummary[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Leads', href: leadsIndex() }],
    },
});

const search = ref(filters.q ?? '');

/**
 * Every filter is a URL query param, so a filtered view can be bookmarked or pasted into Slack.
 * Partial reloads (`only`) keep the request small, and preserveState keeps focus in the search box.
 */
function apply(changes: Partial<Filters & { view: string }>): void {
    const query = { ...filters, view, ...changes };

    router.get(
        leadsIndex().url,
        Object.fromEntries(
            Object.entries(query).filter(([, v]) => v !== '' && v != null),
        ),
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['leads', 'filters', 'view'],
        },
    );
}

const onSearch = useDebounceFn(() => apply({ q: search.value }), 250);

const open = computed(() =>
    leads.data.filter((lead) => !['won', 'lost'].includes(lead.stage)),
);

// Laid out like a destination sign: a name on the left, the "distance" on the right.
const signRows = computed(() => [
    { label: 'Open', value: count(open.value.length) },
    {
        label: 'Hot',
        value: count(open.value.filter((l) => l.temperature === 'hot').length),
    },
    {
        label: 'In play',
        value: compactMoney(
            open.value.reduce((sum, l) => sum + (l.budget_max ?? 0), 0),
        ),
    },
]);

const staleCount = computed(() => open.value.filter((l) => l.is_stale).length);

const chip =
    'inline-flex h-8 items-center gap-1.5 rounded-full border px-3 text-xs font-medium transition-colors hover:border-primary/40 aria-pressed:border-primary aria-pressed:bg-primary/10 aria-pressed:text-primary';
</script>

<template>
    <Head title="Leads" />

    <div class="flex min-w-0 flex-1 flex-col gap-5 p-4 md:p-6">
        <!--
            The page title is a highway sign: the one bold element on the page. The amber tab
            hanging underneath is a shortcut to the stale-lead filter, the same way an advisory
            plaque hangs under a real sign.
        -->
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex flex-col items-start">
                <div class="w-full min-w-64 sign px-6 pt-4 pb-5 sm:w-auto">
                    <h1
                        class="text-[2rem] leading-none font-extrabold tracking-tight"
                    >
                        Leads
                    </h1>
                    <dl
                        class="mt-3 grid grid-cols-[1fr_auto] gap-x-10 gap-y-0.5 text-[15px] font-semibold"
                    >
                        <template v-for="row in signRows" :key="row.label">
                            <dt>{{ row.label }}</dt>
                            <dd class="text-right tabular-nums">
                                {{ row.value }}
                            </dd>
                        </template>
                    </dl>
                </div>
                <button
                    v-if="staleCount > 0"
                    type="button"
                    :aria-pressed="!!filters.stale"
                    class="ml-5 sign-tab px-3 pt-1 pb-1.5 text-xs font-bold transition-transform hover:translate-y-0.5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-warning"
                    @click="apply({ stale: filters.stale ? '' : '1' })"
                >
                    {{
                        filters.stale
                            ? 'Show all leads'
                            : `${staleCount} going stale`
                    }}
                </button>
            </div>

            <div
                role="group"
                aria-label="Layout"
                class="flex rounded-full border bg-muted/50 p-1"
            >
                <button
                    v-for="option in [
                        { value: 'board', label: 'Board', icon: KanbanSquare },
                        { value: 'list', label: 'List', icon: List },
                    ]"
                    :key="option.value"
                    type="button"
                    :aria-pressed="view === option.value"
                    class="flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-medium text-muted-foreground transition-colors aria-pressed:bg-background aria-pressed:text-foreground aria-pressed:shadow-sm"
                    @click="apply({ view: option.value })"
                >
                    <component :is="option.icon" class="size-3.5" />
                    {{ option.label }}
                </button>
            </div>
        </header>

        <!-- Filters -->
        <div class="flex flex-wrap items-center gap-2">
            <label class="relative w-full sm:w-72">
                <span class="sr-only">Search leads</span>
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <input
                    v-model="search"
                    type="search"
                    placeholder="Search name, email or need…"
                    class="h-9 w-full rounded-full border bg-background pr-3 pl-9 text-sm transition-shadow outline-none placeholder:text-muted-foreground focus:border-primary/50 focus:ring-3 focus:ring-primary/15"
                    @input="onSearch"
                />
            </label>

            <button
                type="button"
                :class="chip"
                :aria-pressed="filters.temperature === 'hot'"
                @click="
                    apply({
                        temperature: filters.temperature === 'hot' ? '' : 'hot',
                    })
                "
            >
                <Flame class="size-3.5" /> Hot only
            </button>

            <template v-if="team.length">
                <button
                    type="button"
                    :class="chip"
                    :aria-pressed="filters.assignee === 'unassigned'"
                    @click="
                        apply({
                            assignee:
                                filters.assignee === 'unassigned'
                                    ? ''
                                    : 'unassigned',
                        })
                    "
                >
                    Unassigned
                </button>
                <select
                    :value="
                        filters.assignee && filters.assignee !== 'unassigned'
                            ? filters.assignee
                            : ''
                    "
                    aria-label="Filter by rep"
                    class="h-8 rounded-full border bg-background px-3 text-xs font-medium outline-none focus:border-primary/50"
                    @change="
                        apply({
                            assignee: ($event.target as HTMLSelectElement)
                                .value,
                        })
                    "
                >
                    <option value="">All reps</option>
                    <option
                        v-for="rep in team"
                        :key="rep.id"
                        :value="String(rep.id)"
                    >
                        {{ rep.name }}
                    </option>
                </select>
            </template>
        </div>

        <!-- Board or list -->
        <LeadBoard v-if="view === 'board'" :leads="leads.data" />

        <div v-else class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <LeadCard v-for="lead in leads.data" :key="lead.id" :lead="lead" />
            <p
                v-if="leads.data.length === 0"
                class="col-span-full py-16 text-center text-sm text-muted-foreground"
            >
                No leads match those filters.
            </p>
        </div>

        <nav
            v-if="
                view === 'list' && 'meta' in leads && leads.meta.last_page > 1
            "
            class="flex items-center justify-between text-sm text-muted-foreground"
        >
            <span
                >{{ leads.meta.from }}–{{ leads.meta.to }} of
                {{ leads.meta.total }}</span
            >
            <div class="flex gap-2">
                <Link
                    v-if="leads.links.prev"
                    :href="leads.links.prev"
                    preserve-scroll
                    class="rounded-full border px-3 py-1.5 hover:border-primary/40"
                >
                    Previous
                </Link>
                <Link
                    v-if="leads.links.next"
                    :href="leads.links.next"
                    preserve-scroll
                    class="rounded-full border px-3 py-1.5 hover:border-primary/40"
                >
                    Next
                </Link>
            </div>
        </nav>
    </div>
</template>
