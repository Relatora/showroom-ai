<script setup lang="ts">
import { Deferred, Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import BarList from '@/components/crm/BarList.vue';
import type { BarRow } from '@/components/crm/BarList.vue';
import LeadCard from '@/components/crm/LeadCard.vue';
import UserAvatar from '@/components/crm/UserAvatar.vue';
import { toneClasses, useCrm } from '@/composables/useCrm';
import { compactMoney, count, time } from '@/lib/format';
import { dashboard } from '@/routes';
import { index as appointmentsIndex } from '@/routes/appointments';
import { index as leadsIndex, show as showLead } from '@/routes/leads';
import type { Appointment, Lead } from '@/types/crm';

type PipelineRow = { stage: string; count: number; value: number };
type SourceRow = { source: string; count: number; won: number };
type RepRow = { id: number; name: string; won: number; open: number };

const props = defineProps<{
    today: { appointments: number; followUpsDue: number; stale: number };
    callFirst: Lead[];
    schedule: Appointment[];
    pipeline?: PipelineRow[];
    sources?: SourceRow[];
    leaderboard?: RepRow[] | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});

const page = usePage();
const { label, tone } = useCrm();

const firstName = computed(
    () => String(page.props.auth.user.name).split(' ')[0],
);

const greeting = computed(() => {
    const hour = new Date().getHours();

    return hour < 12
        ? 'Good morning'
        : hour < 17
          ? 'Good afternoon'
          : 'Good evening';
});

const pipelineRows = computed<BarRow[]>(() =>
    (props.pipeline ?? []).map((row) => ({
        key: row.stage,
        label: label('stages', row.stage),
        value: row.count,
        display: count(row.count),
        detail: `${compactMoney(row.value)} in stated budgets`,
    })),
);

const sourceRows = computed<BarRow[]>(() =>
    (props.sources ?? [])
        .filter((row) => row.count > 0)
        .map((row) => ({
            key: row.source,
            label: label('sources', row.source),
            value: row.count,
            display: count(row.count),
            detail: `${row.won} sold (${Math.round((row.won / row.count) * 100)}%)`,
        })),
);

const repRows = computed<BarRow[]>(() =>
    (props.leaderboard ?? []).map((rep) => ({
        key: String(rep.id),
        label: rep.name,
        value: rep.won,
        display: `${rep.won} won`,
        detail: `${rep.open} open leads`,
    })),
);
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex min-w-0 flex-1 flex-col gap-6 p-4 md:p-6">
        <!-- The day at a glance, written as a highway sign: where you're headed and how far. -->
        <header class="flex flex-col items-start">
            <div class="sign px-6 pt-4 pb-5">
                <p class="text-sm font-semibold text-white/85">
                    {{ greeting }}, {{ firstName }}
                </p>
                <h1
                    class="mt-1 text-[2rem] leading-none font-extrabold tracking-tight"
                >
                    Today
                </h1>
                <dl
                    class="mt-3 grid grid-cols-[1fr_auto] gap-x-12 gap-y-0.5 text-[15px] font-semibold"
                >
                    <dt>Appointments</dt>
                    <dd class="text-right tabular-nums">
                        {{ today.appointments }}
                    </dd>
                    <dt>Follow-ups due</dt>
                    <dd class="text-right tabular-nums">
                        {{ today.followUpsDue }}
                    </dd>
                </dl>
            </div>
            <Link
                v-if="today.stale"
                :href="leadsIndex({ query: { stale: '1' } })"
                class="-mt-px ml-5 sign-tab px-3 pt-1 pb-1.5 text-xs font-bold transition-transform hover:translate-y-0.5"
            >
                {{ today.stale }} leads going stale
            </Link>
        </header>

        <!-- minmax(0,1fr) on mobile too: a bare "grid" column grows to fit the longest truncated line. -->
        <div
            class="grid grid-cols-[minmax(0,1fr)] gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]"
        >
            <section aria-labelledby="call-first-heading">
                <div class="mb-3 flex items-baseline justify-between">
                    <h2 id="call-first-heading" class="text-base font-bold">
                        Call these first
                    </h2>
                    <Link
                        :href="leadsIndex({ query: { stale: '1' } })"
                        class="text-sm text-primary hover:underline"
                    >
                        All stale leads
                    </Link>
                </div>
                <div
                    v-if="callFirst.length"
                    class="grid gap-3 sm:grid-cols-2 2xl:grid-cols-3"
                >
                    <LeadCard
                        v-for="lead in callFirst"
                        :key="lead.id"
                        :lead="lead"
                    />
                </div>
                <p
                    v-else
                    class="rounded-xl border border-dashed px-4 py-8 text-center text-sm text-muted-foreground"
                >
                    Everyone's been contacted in the last three days. Nice.
                </p>
            </section>

            <section
                aria-labelledby="schedule-heading"
                class="h-fit rounded-xl border bg-card"
            >
                <div
                    class="flex items-baseline justify-between border-b px-4 py-3"
                >
                    <h2 id="schedule-heading" class="text-base font-bold">
                        On the floor today
                    </h2>
                    <Link
                        :href="appointmentsIndex()"
                        class="text-sm text-primary hover:underline"
                        >Week</Link
                    >
                </div>
                <ol v-if="schedule.length" class="divide-y">
                    <li
                        v-for="a in schedule"
                        :key="a.id"
                        class="flex items-center gap-3 px-4 py-2.5 text-sm"
                    >
                        <span
                            class="w-[4.75rem] shrink-0 font-bold whitespace-nowrap tabular-nums"
                            >{{ time(a.starts_at) }}</span
                        >
                        <span
                            :class="[
                                'h-8 w-0.5 shrink-0 rounded-full',
                                toneClasses[tone('appointmentTypes', a.type)]
                                    .bar,
                            ]"
                            aria-hidden="true"
                        />
                        <div class="min-w-0 flex-1">
                            <Link
                                :href="showLead(a.lead_id)"
                                class="block truncate font-semibold hover:underline"
                            >
                                {{ a.lead?.customer?.name }}
                            </Link>
                            <p class="truncate text-xs text-muted-foreground">
                                {{ label('appointmentTypes', a.type)
                                }}<template v-if="a.vehicle"
                                    >, {{ a.vehicle.title }}</template
                                >
                            </p>
                        </div>
                        <UserAvatar :name="a.user?.name" />
                    </li>
                </ol>
                <p
                    v-else
                    class="px-4 py-8 text-center text-sm text-muted-foreground"
                >
                    No appointments today.
                </p>
            </section>
        </div>

        <div
            :class="[
                'grid gap-6',
                leaderboard !== null ? 'lg:grid-cols-3' : 'lg:grid-cols-2',
            ]"
        >
            <section
                aria-labelledby="pipeline-heading"
                class="rounded-xl border bg-card p-5"
            >
                <h2 id="pipeline-heading" class="text-base font-bold">
                    Open pipeline
                </h2>
                <p class="mb-3 text-xs text-muted-foreground">
                    Leads in each stage. Hover for stated budgets.
                </p>
                <Deferred data="pipeline">
                    <template #fallback>
                        <div class="h-40 animate-pulse rounded-lg bg-muted" />
                    </template>
                    <BarList
                        :rows="pipelineRows"
                        caption="Open leads by pipeline stage"
                    />
                </Deferred>
            </section>

            <section
                aria-labelledby="sources-heading"
                class="rounded-xl border bg-card p-5"
            >
                <h2 id="sources-heading" class="text-base font-bold">
                    Where leads come from
                </h2>
                <p class="mb-3 text-xs text-muted-foreground">
                    Last 90 days. Hover to see how many sold.
                </p>
                <Deferred data="sources">
                    <template #fallback>
                        <div class="h-40 animate-pulse rounded-lg bg-muted" />
                    </template>
                    <BarList
                        :rows="sourceRows"
                        caption="Leads by source, last 90 days"
                    />
                </Deferred>
            </section>

            <section
                v-if="leaderboard !== null"
                aria-labelledby="reps-heading"
                class="rounded-xl border bg-card p-5"
            >
                <h2 id="reps-heading" class="text-base font-bold">
                    Deals won this month
                </h2>
                <p class="mb-3 text-xs text-muted-foreground">
                    Per rep. Hover for their open leads.
                </p>
                <Deferred data="leaderboard">
                    <template #fallback>
                        <div class="h-40 animate-pulse rounded-lg bg-muted" />
                    </template>
                    <BarList
                        :rows="repRows"
                        caption="Deals won this month by rep"
                    />
                </Deferred>
            </section>
        </div>
    </div>
</template>
