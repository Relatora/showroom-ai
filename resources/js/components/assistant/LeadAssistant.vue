<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Check, Copy, MailPlus, Search, Square } from '@lucide/vue';
import { useIntervalFn } from '@vueuse/core';
import { computed, ref, useId } from 'vue';
import ToneBadge from '@/components/crm/ToneBadge.vue';
import VehicleArt from '@/components/crm/VehicleArt.vue';
import { Button } from '@/components/ui/button';
import { useEventStream } from '@/composables/useEventStream';
import { km, money } from '@/lib/format';
import { postJson } from '@/lib/http';
import { show as showVehicle } from '@/routes/inventory';
import {
    followUp as followUpRoute,
    matches as matchesRoute,
} from '@/routes/leads';
import { store as storeActivity } from '@/routes/leads/activities';
import type { Lead, Tone, Vehicle } from '@/types/crm';

/**
 * The AI helpers for one lead: "what do we have for them?" and "write the follow-up".
 *
 * Nothing here acts on its own. Matches are suggestions, and the email is a draft in an
 * editable box; the rep decides what gets sent, and logging it is a separate, deliberate click.
 */
const { lead } = defineProps<{ lead: Lead }>();

type Match = {
    vehicle: Vehicle;
    fit: 'strong' | 'good' | 'stretch';
    reason: string;
};

const fitTone: Record<Match['fit'], Tone> = {
    strong: 'success',
    good: 'info',
    stretch: 'warning',
};

// A local model can take a while, so both helpers show the seconds ticking.
const waited = ref(0);
const timer = useIntervalFn(() => waited.value++, 1000, { immediate: false });

function startTimer(): void {
    waited.value = 0;
    timer.resume();
}

// Matching
const matches = ref<Match[] | null>(null);
const matching = ref(false);
const matchError = ref<string | null>(null);
const selected = ref<number[]>([]);

async function findMatches(): Promise<void> {
    matching.value = true;
    matchError.value = null;
    startTimer();

    try {
        const response = await postJson<{ matches: Match[] }>(
            matchesRoute.url(lead.id),
        );
        matches.value = response.matches;
        // Pre-select the strong fits for the email; the rep can change it.
        selected.value = response.matches
            .filter((m) => m.fit === 'strong')
            .map((m) => m.vehicle.id);
    } catch (error) {
        matchError.value = (error as Error).message;
    } finally {
        matching.value = false;
        timer.pause();
    }
}

function toggle(id: number): void {
    selected.value = selected.value.includes(id)
        ? selected.value.filter((v) => v !== id)
        : [...selected.value, id];
}

// Follow-up draft
const draft = ref('');
const draftError = ref<string | null>(null);
const draftId = useId();
const copied = ref(false);
const { streaming, post, cancel } = useEventStream<{
    delta: { text: string };
    done: { total_ms: number };
    error: { message: string };
}>();

async function writeFollowUp(): Promise<void> {
    draft.value = '';
    draftError.value = null;
    startTimer();

    try {
        await post(
            followUpRoute.url(lead.id),
            { vehicle_ids: selected.value },
            {
                delta: (data) => {
                    timer.pause();
                    draft.value += data.text;
                },
                error: (data) => (draftError.value = data.message),
            },
        );
    } catch (error) {
        draftError.value = (error as Error).message;
    } finally {
        timer.pause();
    }

    draft.value = draft.value.trim();
}

async function copy(): Promise<void> {
    await navigator.clipboard.writeText(draft.value);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
}

// Logging it as an email counts as contact, so the lead drops off the stale list.
function logAsSent(): void {
    router.post(
        storeActivity.url(lead.id),
        { type: 'email', body: draft.value },
        {
            preserveScroll: true,
            onSuccess: () => (draft.value = ''),
        },
    );
}

const selectedNames = computed(() =>
    (matches.value ?? [])
        .filter((m) => selected.value.includes(m.vehicle.id))
        .map((m) => m.vehicle.title),
);
</script>

<template>
    <section
        class="flex flex-col gap-5 rounded-xl border bg-card p-5"
        aria-labelledby="assistant-heading"
    >
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 id="assistant-heading" class="text-sm font-bold">Assistant</h2>
            <p class="text-xs text-muted-foreground">
                Suggestions only. Nothing is sent for you.
            </p>
        </div>

        <!-- Stock that fits -->
        <div class="flex flex-col gap-3">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-[15px] font-bold">Stock that fits</h3>
                <Button
                    size="sm"
                    variant="secondary"
                    :disabled="matching"
                    @click="findMatches"
                >
                    <Search class="size-3.5" />
                    {{ matches ? 'Match again' : 'Find matches' }}
                </Button>
            </div>

            <p
                v-if="matching"
                class="flex items-center justify-between gap-3 rounded-lg border border-dashed px-4 py-3 text-sm text-muted-foreground"
            >
                Shortlisting stock within budget and ranking it…
                <span class="tabular-nums">{{ waited }} s</span>
            </p>
            <p
                v-else-if="matchError"
                class="text-sm font-semibold text-destructive"
            >
                {{ matchError }}
            </p>
            <p
                v-else-if="matches && matches.length === 0"
                class="text-sm text-muted-foreground"
            >
                Nothing in stock fits this customer's needs and budget right
                now.
            </p>

            <ul v-if="matches?.length && !matching" class="flex flex-col gap-2">
                <li
                    v-for="match in matches"
                    :key="match.vehicle.id"
                    class="flex gap-4 rounded-lg border p-3"
                    :class="
                        selected.includes(match.vehicle.id)
                            ? 'border-primary/60 bg-primary/5'
                            : ''
                    "
                >
                    <div class="hidden w-24 shrink-0 self-center sm:block">
                        <VehicleArt
                            :body-style="match.vehicle.body_style"
                            :color="match.vehicle.exterior_hex"
                        />
                    </div>
                    <div class="flex min-w-0 flex-1 flex-col gap-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <Link
                                :href="showVehicle(match.vehicle.id)"
                                class="font-bold hover:underline"
                                >{{ match.vehicle.title }}</Link
                            >
                            <ToneBadge :tone="fitTone[match.fit]"
                                >{{ match.fit }} fit</ToneBadge
                            >
                        </div>
                        <p class="text-xs text-muted-foreground tabular-nums">
                            {{ money(match.vehicle.price) }} ·
                            {{ km(match.vehicle.km) }} ·
                            {{ match.vehicle.seats }} seats ·
                            {{ match.vehicle.stock_number }}
                        </p>
                        <p class="text-sm leading-relaxed">
                            {{ match.reason }}
                        </p>
                    </div>
                    <label
                        class="flex shrink-0 cursor-pointer items-start gap-1.5 pt-0.5 text-xs font-semibold text-muted-foreground"
                    >
                        <input
                            type="checkbox"
                            class="mt-0.5 accent-[var(--primary)]"
                            :checked="selected.includes(match.vehicle.id)"
                            @change="toggle(match.vehicle.id)"
                        />
                        In email
                    </label>
                </li>
            </ul>
        </div>

        <!-- Follow-up email -->
        <div class="flex flex-col gap-3 border-t pt-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-[15px] font-bold">Follow-up email</h3>
                    <p
                        v-if="selectedNames.length"
                        class="text-xs text-muted-foreground"
                    >
                        Mentions {{ selectedNames.join(' and ') }}
                    </p>
                </div>
                <Button
                    v-if="streaming"
                    size="sm"
                    variant="secondary"
                    @click="cancel"
                >
                    <Square class="size-3 fill-current" /> Stop
                </Button>
                <Button v-else size="sm" @click="writeFollowUp">
                    <MailPlus class="size-3.5" />
                    {{ draft ? 'Write another' : 'Draft follow-up' }}
                </Button>
            </div>

            <p
                v-if="streaming && draft === ''"
                class="flex items-center justify-between gap-3 rounded-lg border border-dashed px-4 py-3 text-sm text-muted-foreground"
            >
                Reading the timeline and writing…
                <span class="tabular-nums">{{ waited }} s</span>
            </p>
            <p v-if="draftError" class="text-sm font-semibold text-destructive">
                {{ draftError }}
            </p>

            <template v-if="draft">
                <label :for="draftId" class="sr-only">Draft email</label>
                <textarea
                    :id="draftId"
                    v-model="draft"
                    :readonly="streaming"
                    rows="9"
                    class="[field-sizing:content] w-full rounded-lg border bg-background px-3 py-2.5 text-sm leading-relaxed focus-visible:shadow-glow focus-visible:outline-none"
                />
                <div v-if="!streaming" class="flex flex-wrap gap-2">
                    <Button size="sm" variant="secondary" @click="copy">
                        <Check v-if="copied" class="size-3.5" />
                        <Copy v-else class="size-3.5" />
                        {{ copied ? 'Copied' : 'Copy' }}
                    </Button>
                    <Button size="sm" variant="outline" @click="logAsSent">
                        Log as sent email
                    </Button>
                </div>
            </template>
        </div>
    </section>
</template>
