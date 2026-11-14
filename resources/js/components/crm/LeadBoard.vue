<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import LeadCard from '@/components/crm/LeadCard.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { toneClasses, useCrm } from '@/composables/useCrm';
import { compactMoney } from '@/lib/format';
import { update as moveStage } from '@/routes/leads/stage';
import type { Lead, LeadStage } from '@/types/crm';

/**
 * The pipeline as a kanban board.
 *
 * Drag and drop uses the browser's native HTML5 drag events rather than a library: it's a
 * dozen lines, works with the mouse, and keeps the bundle small. Moves are optimistic. The
 * card jumps immediately and the request happens in the background. If the server says no,
 * we put the card back where it was and show the error.
 */
const { leads } = defineProps<{ leads: Lead[] }>();

const { options } = useCrm();

// A local copy we can reorder instantly. Re-synced whenever the server sends fresh props.
const board = ref<Lead[]>([]);
watch(
    () => leads,
    (fresh) => (board.value = fresh.map((lead) => ({ ...lead }))),
    { immediate: true },
);

const columns = computed(() =>
    options.value.stages.map((stage) => {
        const cards = board.value
            .filter((lead) => lead.stage === stage.value)
            .sort((a, b) => a.position - b.position);

        return {
            ...stage,
            cards,
            pipelineValue: cards.reduce(
                (sum, lead) => sum + (lead.budget_max ?? 0),
                0,
            ),
        };
    }),
);

const draggingId = ref<number | null>(null);
const dropTarget = ref<{ stage: LeadStage; index: number } | null>(null);
const pendingLost = ref<{ lead: Lead; index: number } | null>(null);
const lostReason = ref('');

function onDragStart(event: DragEvent, lead: Lead): void {
    draggingId.value = lead.id;
    event.dataTransfer?.setData('text/plain', String(lead.id));

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
    }
}

/**
 * Work out where in the column the card would land, from the pointer's height relative to the
 * cards already there. Halfway down a card means "after it".
 */
function onDragOver(event: DragEvent, stage: LeadStage): void {
    const list = event.currentTarget as HTMLElement;
    const cards = [...list.querySelectorAll<HTMLElement>('[data-card]')].filter(
        (card) => Number(card.dataset.card) !== draggingId.value,
    );
    const index = cards.findIndex((card) => {
        const box = card.getBoundingClientRect();

        return event.clientY < box.top + box.height / 2;
    });

    dropTarget.value = { stage, index: index === -1 ? cards.length : index };
}

function onDrop(stage: LeadStage): void {
    const lead = board.value.find((l) => l.id === draggingId.value);
    const index = dropTarget.value?.index ?? 0;

    reset();

    if (!lead) {
        return;
    }

    // Losing a deal needs a reason. Managers live off that report.
    if (stage === 'lost' && lead.stage !== 'lost') {
        pendingLost.value = { lead, index };
        lostReason.value = '';

        return;
    }

    move(lead, stage, index);
}

function move(
    lead: Lead,
    stage: LeadStage,
    index: number,
    reason?: string,
): void {
    const before = board.value.map((l) => ({ ...l }));
    const siblings = columns.value
        .find((column) => column.value === stage)!
        .cards.filter((card) => card.id !== lead.id);
    const position =
        siblings[index]?.position ?? (siblings.at(-1)?.position ?? 0) + 1;

    // Optimistic: shift the cards below the drop point, then drop ours in.
    board.value = board.value.map((card) => {
        if (card.id === lead.id) {
            return { ...card, stage, position };
        }

        return card.stage === stage && card.position >= position
            ? { ...card, position: card.position + 1 }
            : card;
    });

    router.visit(moveStage(lead.id), {
        data: { stage, position, lost_reason: reason },
        preserveScroll: true,
        preserveState: true,
        only: ['leads'],
        // The board animates its own moves, so skip the page cross-fade here.
        viewTransition: false,
        onError: () => (board.value = before),
    });
}

function confirmLost(): void {
    if (!pendingLost.value || lostReason.value.trim() === '') {
        return;
    }

    move(
        pendingLost.value.lead,
        'lost',
        pendingLost.value.index,
        lostReason.value.trim(),
    );
    pendingLost.value = null;
}

function isDropSlot(stage: LeadStage, index: number): boolean {
    if (dropTarget.value?.stage !== stage || dropTarget.value.index !== index) {
        return false;
    }

    // Dropping a card right back where it started isn't a move, so don't promise one.
    const cards = columns.value.find((c) => c.value === stage)?.cards ?? [];
    const current = cards.findIndex((card) => card.id === draggingId.value);

    return current === -1 || (index !== current && index !== current + 1);
}

function reset(): void {
    draggingId.value = null;
    dropTarget.value = null;
}

const lostReasons = [
    'Bought elsewhere',
    'Price too high',
    'Financing declined',
    'Decided to keep current vehicle',
    'Stopped responding',
];
</script>

<template>
    <div
        class="flex snap-x snap-mandatory [scrollbar-width:thin] gap-3 overflow-x-auto pb-4 md:snap-none"
    >
        <section
            v-for="column in columns"
            :key="column.value"
            :aria-label="`${column.label} column`"
            class="flex w-[85vw] shrink-0 snap-start flex-col rounded-2xl border bg-muted/40 sm:w-72"
        >
            <header class="flex items-center gap-2 px-3 pt-3 pb-2">
                <span
                    :class="[
                        'size-2 rounded-full',
                        toneClasses[column.tone].dot,
                    ]"
                />
                <h2 class="text-sm font-semibold">{{ column.label }}</h2>
                <span
                    class="rounded-full bg-background px-1.5 text-[11px] text-muted-foreground tabular-nums ring-1 ring-border"
                >
                    {{ column.cards.length }}
                </span>
                <span
                    v-if="column.pipelineValue"
                    class="ml-auto text-[11px] text-muted-foreground tabular-nums"
                    title="Sum of stated budgets"
                >
                    {{ compactMoney(column.pipelineValue) }}
                </span>
            </header>

            <div
                :class="[
                    'flex min-h-32 flex-1 flex-col gap-2 rounded-b-2xl p-2 transition-colors',
                    dropTarget?.stage === column.value &&
                        'bg-primary/5 ring-2 ring-primary/30 ring-inset',
                ]"
                @dragover.prevent="onDragOver($event, column.value)"
                @dragleave.self="dropTarget = null"
                @drop.prevent="onDrop(column.value)"
            >
                <TransitionGroup
                    move-class="transition-transform duration-300 ease-[var(--ease-out-expo)]"
                    enter-active-class="transition duration-300 ease-[var(--ease-out-expo)]"
                    enter-from-class="opacity-0 -translate-y-2"
                >
                    <div
                        v-for="(lead, i) in column.cards"
                        :key="lead.id"
                        :data-card="lead.id"
                        draggable="true"
                        :class="[
                            'relative cursor-grab active:cursor-grabbing',
                            // Drop indicator: a glowing line just above where the card will land.
                            isDropSlot(column.value, i) &&
                                'before:absolute before:inset-x-1 before:-top-1.5 before:h-0.5 before:rounded-full before:bg-primary before:shadow-glow',
                        ]"
                        @dragstart="onDragStart($event, lead)"
                        @dragend="reset"
                    >
                        <LeadCard
                            :lead="lead"
                            :dragging="draggingId === lead.id"
                        />
                    </div>
                </TransitionGroup>

                <div
                    v-if="
                        isDropSlot(column.value, column.cards.length) &&
                        column.cards.length > 0
                    "
                    class="h-0.5 shrink-0 rounded-full bg-primary shadow-glow"
                />

                <p
                    v-if="column.cards.length === 0"
                    class="m-auto px-4 py-6 text-center text-xs text-muted-foreground"
                >
                    Nothing here yet. Drag a lead in.
                </p>
            </div>
        </section>
    </div>

    <Dialog
        :open="pendingLost !== null"
        @update:open="(open) => !open && (pendingLost = null)"
    >
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Why was this deal lost?</DialogTitle>
                <DialogDescription>
                    {{ pendingLost?.lead.customer?.name }}. This feeds the
                    lost-deal report, so pick the closest match.
                </DialogDescription>
            </DialogHeader>
            <div class="grid gap-2">
                <button
                    v-for="reason in lostReasons"
                    :key="reason"
                    type="button"
                    :class="[
                        'rounded-lg border px-3 py-2 text-left text-sm transition-colors hover:border-primary/40',
                        lostReason === reason && 'border-primary bg-primary/5',
                    ]"
                    @click="lostReason = reason"
                >
                    {{ reason }}
                </button>
            </div>
            <DialogFooter>
                <Button variant="ghost" @click="pendingLost = null"
                    >Cancel</Button
                >
                <Button :disabled="!lostReason" @click="confirmLost"
                    >Mark as lost</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
