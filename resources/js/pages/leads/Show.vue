<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, CalendarClock, Mail, MapPin, Phone } from '@lucide/vue';
import LeadAssistant from '@/components/assistant/LeadAssistant.vue';
import ActivityTimeline from '@/components/crm/ActivityTimeline.vue';
import StageStepper from '@/components/crm/StageStepper.vue';
import ToneBadge from '@/components/crm/ToneBadge.vue';
import UserAvatar from '@/components/crm/UserAvatar.vue';
import VehicleArt from '@/components/crm/VehicleArt.vue';
import { useCrm } from '@/composables/useCrm';
import { dateTime, km, money, timeAgo } from '@/lib/format';
import { index as leadsIndex, update as updateLead } from '@/routes/leads';
import type { Lead, UserSummary } from '@/types/crm';

const { lead, team } = defineProps<{
    lead: Lead;
    team: UserSummary[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Leads', href: leadsIndex() }],
    },
});

const { label, tone } = useCrm();

function setTemperature(temperature: string): void {
    router.patch(
        updateLead(lead.id).url,
        { temperature },
        { preserveScroll: true, viewTransition: false },
    );
}

function reassign(event: Event): void {
    const value = (event.target as HTMLSelectElement).value;

    router.patch(
        updateLead(lead.id).url,
        { assigned_user_id: value === '' ? null : Number(value) },
        { preserveScroll: true, viewTransition: false },
    );
}
</script>

<template>
    <Head :title="lead.customer?.name ?? 'Lead'" />

    <div class="flex min-w-0 flex-1 flex-col gap-5 p-4 md:p-6">
        <Link
            :href="leadsIndex()"
            class="flex w-fit items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" /> All leads
        </Link>

        <header class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex flex-col items-start">
                <div class="sign px-6 pt-4 pb-5">
                    <h1
                        class="text-[2rem] leading-none font-extrabold tracking-tight"
                    >
                        {{ lead.customer?.name }}
                    </h1>
                    <p class="mt-2 text-[15px] font-semibold text-white/90">
                        {{ label('sources', lead.source) }} lead,
                        {{ timeAgo(lead.created_at) }}
                    </p>
                </div>
                <p
                    v-if="lead.is_stale"
                    class="-mt-px ml-5 sign-tab px-3 pt-1 pb-1.5 text-xs font-bold"
                >
                    No contact since {{ timeAgo(lead.last_contacted_at) }}
                </p>
            </div>

            <div
                class="flex items-center gap-2"
                role="group"
                aria-label="Temperature"
            >
                <button
                    v-for="option in ['hot', 'warm', 'cold']"
                    :key="option"
                    type="button"
                    :disabled="!lead.can.update"
                    :aria-pressed="lead.temperature === option"
                    class="rounded-full opacity-50 transition-opacity hover:opacity-100 disabled:cursor-default aria-pressed:opacity-100"
                    @click="setTemperature(option)"
                >
                    <ToneBadge
                        :tone="tone('temperatures', option)"
                        class="px-3 py-1 text-xs"
                    >
                        {{ label('temperatures', option) }}
                    </ToneBadge>
                </button>
            </div>
        </header>

        <StageStepper :lead="lead" />

        <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <div class="flex min-w-0 flex-col gap-5">
                <section
                    class="rounded-xl border bg-card p-5"
                    aria-labelledby="needs-heading"
                >
                    <h2 id="needs-heading" class="text-sm font-bold">
                        What they're looking for
                    </h2>
                    <p class="mt-2 max-w-prose text-[15px] leading-relaxed">
                        {{ lead.interest }}
                    </p>
                    <dl
                        class="mt-4 grid grid-cols-2 gap-4 text-sm sm:grid-cols-3"
                    >
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Budget
                            </dt>
                            <dd class="mt-0.5 font-semibold tabular-nums">
                                Up to {{ money(lead.budget_max) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Trade-in
                            </dt>
                            <dd class="mt-0.5 font-semibold">
                                {{ lead.trade_in ?? 'None' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Next follow-up
                            </dt>
                            <dd class="mt-0.5 font-semibold">
                                {{
                                    lead.next_follow_up_at
                                        ? dateTime(lead.next_follow_up_at)
                                        : 'Not set'
                                }}
                            </dd>
                        </div>
                    </dl>
                </section>

                <LeadAssistant :lead="lead" />

                <ActivityTimeline :lead="lead" />
            </div>

            <aside class="flex flex-col gap-5">
                <section
                    class="rounded-xl border bg-card p-5"
                    aria-labelledby="contact-heading"
                >
                    <h2 id="contact-heading" class="text-sm font-bold">
                        Contact
                    </h2>
                    <ul class="mt-3 space-y-2 text-sm">
                        <li
                            v-if="lead.customer?.phone"
                            class="flex items-center gap-2"
                        >
                            <Phone class="size-4 text-muted-foreground" />
                            <a
                                :href="`tel:${lead.customer.phone}`"
                                class="hover:underline"
                                >{{ lead.customer.phone }}</a
                            >
                        </li>
                        <li
                            v-if="lead.customer?.email"
                            class="flex items-center gap-2"
                        >
                            <Mail class="size-4 text-muted-foreground" />
                            <a
                                :href="`mailto:${lead.customer.email}`"
                                class="truncate hover:underline"
                            >
                                {{ lead.customer.email }}
                            </a>
                        </li>
                        <li
                            v-if="lead.customer?.city"
                            class="flex items-center gap-2"
                        >
                            <MapPin class="size-4 text-muted-foreground" />
                            {{ lead.customer.city }},
                            {{ lead.customer.province }}
                        </li>
                    </ul>
                    <p class="mt-3 text-xs text-muted-foreground">
                        Prefers
                        {{
                            lead.customer?.preferred_contact === 'sms'
                                ? 'text messages'
                                : lead.customer?.preferred_contact
                        }}
                    </p>
                </section>

                <section
                    class="rounded-xl border bg-card p-5"
                    aria-labelledby="owner-heading"
                >
                    <h2 id="owner-heading" class="text-sm font-bold">
                        Sales rep
                    </h2>
                    <div class="mt-3 flex items-center gap-2 text-sm">
                        <UserAvatar
                            :name="lead.assignee?.name"
                            class="size-8 text-xs"
                        />
                        <div v-if="!lead.can.reassign">
                            <p class="font-semibold">
                                {{ lead.assignee?.name ?? 'Unassigned' }}
                            </p>
                            <p
                                v-if="lead.assignee?.title"
                                class="text-xs text-muted-foreground"
                            >
                                {{ lead.assignee.title }}
                            </p>
                        </div>
                        <select
                            v-else
                            :value="lead.assignee?.id ?? ''"
                            aria-label="Assign to"
                            class="h-9 flex-1 rounded-lg border bg-background px-2 text-sm outline-none focus:border-primary/50"
                            @change="reassign"
                        >
                            <option value="">Unassigned</option>
                            <option
                                v-for="rep in team"
                                :key="rep.id"
                                :value="rep.id"
                            >
                                {{ rep.name }}
                            </option>
                        </select>
                    </div>
                </section>

                <section
                    v-if="lead.vehicle"
                    class="overflow-hidden rounded-xl border bg-card"
                    aria-labelledby="vehicle-heading"
                >
                    <div class="bg-muted/50 px-6 pt-4 pb-2">
                        <VehicleArt
                            :body-style="lead.vehicle.body_style"
                            :color="lead.vehicle.exterior_hex"
                        />
                    </div>
                    <div class="p-5">
                        <h2
                            id="vehicle-heading"
                            class="text-xs text-muted-foreground"
                        >
                            Interested in
                        </h2>
                        <p class="mt-0.5 font-bold">{{ lead.vehicle.title }}</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ lead.vehicle.exterior_color }},
                            {{ km(lead.vehicle.km) }}
                        </p>
                        <p class="mt-2 text-lg font-bold tabular-nums">
                            {{ money(lead.vehicle.price) }}
                        </p>
                        <p
                            class="mt-1 font-mono text-[11px] text-muted-foreground"
                        >
                            Stock {{ lead.vehicle.stock_number }}, VIN
                            {{ lead.vehicle.vin }}
                        </p>
                    </div>
                </section>

                <section
                    v-if="lead.appointments?.length"
                    class="rounded-xl border bg-card p-5"
                    aria-labelledby="appointments-heading"
                >
                    <h2 id="appointments-heading" class="text-sm font-bold">
                        Appointments
                    </h2>
                    <ul class="mt-3 space-y-3">
                        <li
                            v-for="appointment in lead.appointments"
                            :key="appointment.id"
                            class="flex gap-3 text-sm"
                        >
                            <CalendarClock
                                class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                            />
                            <div>
                                <p class="font-semibold">
                                    {{
                                        label(
                                            'appointmentTypes',
                                            appointment.type,
                                        )
                                    }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ dateTime(appointment.starts_at) }},
                                    {{
                                        label(
                                            'appointmentStatuses',
                                            appointment.status,
                                        ).toLowerCase()
                                    }}
                                </p>
                            </div>
                        </li>
                    </ul>
                </section>
            </aside>
        </div>
    </div>
</template>
