<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed } from 'vue';
import ToneBadge from '@/components/crm/ToneBadge.vue';
import UserAvatar from '@/components/crm/UserAvatar.vue';
import { toneClasses, useCrm } from '@/composables/useCrm';
import { time } from '@/lib/format';
import {
    index as appointmentsIndex,
    update as updateAppointment,
} from '@/routes/appointments';
import { show as showLead } from '@/routes/leads';
import type { Appointment } from '@/types/crm';

const { week, appointments } = defineProps<{
    week: { start: string; previous: string; next: string; isCurrent: boolean };
    appointments: Appointment[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Appointments', href: appointmentsIndex() }],
    },
});

const { label, tone } = useCrm();

/**
 * Seven day columns. Dates are built at noon so a daylight-saving switch can never push a day
 * onto the wrong side of midnight.
 */
const days = computed(() => {
    const start = new Date(`${week.start}T12:00:00`);
    const today = new Date().toDateString();

    return Array.from({ length: 7 }, (_, i) => {
        const date = new Date(start);
        date.setDate(start.getDate() + i);

        return {
            key: date.toDateString(),
            weekday: date.toLocaleDateString('en-CA', { weekday: 'short' }),
            day: date.getDate(),
            isToday: date.toDateString() === today,
            items: appointments.filter(
                (a) =>
                    new Date(a.starts_at).toDateString() ===
                    date.toDateString(),
            ),
        };
    });
});

const range = computed(() => {
    const start = new Date(`${week.start}T12:00:00`);
    const end = new Date(start);
    end.setDate(start.getDate() + 6);
    const fmt = (d: Date) =>
        d.toLocaleDateString('en-CA', { month: 'short', day: 'numeric' });

    return `${fmt(start)} to ${fmt(end)}`;
});

const scheduled = computed(
    () => appointments.filter((a) => a.status === 'scheduled').length,
);

function setStatus(appointment: Appointment, status: string): void {
    router.patch(
        updateAppointment(appointment.id).url,
        { status },
        { preserveScroll: true, viewTransition: false },
    );
}
</script>

<template>
    <Head title="Appointments" />

    <div class="flex min-w-0 flex-1 flex-col gap-5 p-4 md:p-6">
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div class="sign px-6 pt-4 pb-5">
                <h1
                    class="text-[2rem] leading-none font-extrabold tracking-tight"
                >
                    Appointments
                </h1>
                <dl
                    class="mt-3 grid grid-cols-[1fr_auto] gap-x-10 gap-y-0.5 text-[15px] font-semibold"
                >
                    <dt>{{ range }}</dt>
                    <dd class="text-right tabular-nums">
                        {{ appointments.length }}
                    </dd>
                    <dt>Still to happen</dt>
                    <dd class="text-right tabular-nums">{{ scheduled }}</dd>
                </dl>
            </div>

            <nav class="flex items-center gap-1" aria-label="Week">
                <Link
                    :href="
                        appointmentsIndex({ query: { week: week.previous } })
                    "
                    preserve-scroll
                    class="flex size-9 items-center justify-center rounded-lg border hover:border-primary/40"
                    aria-label="Previous week"
                >
                    <ChevronLeft class="size-4" />
                </Link>
                <Link
                    v-if="!week.isCurrent"
                    :href="appointmentsIndex()"
                    class="h-9 rounded-lg border px-3 text-sm leading-9 hover:border-primary/40"
                >
                    This week
                </Link>
                <Link
                    :href="appointmentsIndex({ query: { week: week.next } })"
                    preserve-scroll
                    class="flex size-9 items-center justify-center rounded-lg border hover:border-primary/40"
                    aria-label="Next week"
                >
                    <ChevronRight class="size-4" />
                </Link>
            </nav>
        </header>

        <!-- One column per day on wide screens, a stacked agenda on phones. -->
        <div class="grid gap-3 lg:grid-cols-7">
            <section
                v-for="day in days"
                :key="day.key"
                :aria-label="`${day.weekday} ${day.day}`"
                :class="[
                    'flex flex-col rounded-xl border bg-card lg:min-h-80',
                    day.isToday && 'border-primary/60 ring-1 ring-primary/30',
                ]"
            >
                <h2 class="flex items-baseline gap-2 border-b px-3 py-2">
                    <span class="text-xs font-semibold text-muted-foreground">{{
                        day.weekday
                    }}</span>
                    <span
                        :class="[
                            'text-lg font-extrabold tabular-nums',
                            day.isToday && 'text-primary',
                        ]"
                        >{{ day.day }}</span
                    >
                    <span
                        v-if="day.isToday"
                        class="ml-auto text-[11px] font-semibold text-primary"
                        >Today</span
                    >
                </h2>

                <ul class="flex flex-1 flex-col gap-2 p-2">
                    <li
                        v-for="a in day.items"
                        :key="a.id"
                        :class="[
                            'relative rounded-lg border bg-background p-2.5 text-xs',
                            a.status === 'cancelled' && 'opacity-50',
                        ]"
                    >
                        <span
                            :class="[
                                'absolute inset-y-2 left-0 w-0.5 rounded-full',
                                toneClasses[tone('appointmentTypes', a.type)]
                                    .bar,
                            ]"
                            aria-hidden="true"
                        />
                        <p class="flex items-center justify-between gap-1">
                            <span class="font-bold tabular-nums">{{
                                time(a.starts_at)
                            }}</span>
                            <UserAvatar
                                :name="a.user?.name"
                                class="size-5 text-[9px]"
                            />
                        </p>
                        <Link
                            :href="showLead(a.lead_id)"
                            class="mt-1 block font-semibold hover:underline"
                        >
                            {{ a.lead?.customer?.name }}
                        </Link>
                        <p class="text-muted-foreground">
                            {{ label('appointmentTypes', a.type) }}
                        </p>
                        <p
                            v-if="a.vehicle"
                            class="mt-1 truncate text-muted-foreground"
                            :title="a.vehicle.title"
                        >
                            {{ a.vehicle.title }}
                        </p>

                        <div
                            v-if="
                                a.status === 'scheduled' && a.lead?.can.update
                            "
                            class="mt-2 flex gap-1"
                        >
                            <button
                                type="button"
                                class="rounded-md border px-1.5 py-0.5 text-[11px] hover:border-primary/40 hover:text-primary"
                                @click="setStatus(a, 'completed')"
                            >
                                Done
                            </button>
                            <button
                                type="button"
                                class="rounded-md border px-1.5 py-0.5 text-[11px] hover:border-destructive/40 hover:text-destructive-foreground"
                                @click="setStatus(a, 'no_show')"
                            >
                                No-show
                            </button>
                        </div>
                        <ToneBadge
                            v-else-if="a.status !== 'scheduled'"
                            :tone="tone('appointmentStatuses', a.status)"
                            class="mt-2"
                        >
                            {{ label('appointmentStatuses', a.status) }}
                        </ToneBadge>
                    </li>
                    <li
                        v-if="day.items.length === 0"
                        class="m-auto py-4 text-[11px] text-muted-foreground"
                    >
                        Open
                    </li>
                </ul>
            </section>
        </div>
    </div>
</template>
