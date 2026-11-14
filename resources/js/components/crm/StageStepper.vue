<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import { computed } from 'vue';
import { useCrm } from '@/composables/useCrm';
import { update as moveStage } from '@/routes/leads/stage';
import type { Lead, LeadStage } from '@/types/crm';

/**
 * The pipeline as a row of steps, like exits counting down along a highway. Clicking a step
 * moves the lead there. "Lost" lives on the board's dialog instead, because it needs a reason.
 */
const { lead } = defineProps<{ lead: Lead }>();

const { options } = useCrm();

const steps = computed(() =>
    options.value.stages.filter((stage) => stage.value !== 'lost'),
);
const currentIndex = computed(() =>
    steps.value.findIndex((stage) => stage.value === lead.stage),
);

function go(stage: LeadStage): void {
    if (stage === lead.stage || !lead.can.update) {
        return;
    }

    router.patch(
        moveStage(lead.id).url,
        { stage },
        { preserveScroll: true, viewTransition: false },
    );
}
</script>

<template>
    <ol class="flex flex-wrap gap-1.5" aria-label="Pipeline stage">
        <li v-for="(step, i) in steps" :key="step.value">
            <button
                type="button"
                :disabled="!lead.can.update"
                :aria-current="step.value === lead.stage ? 'step' : undefined"
                :class="[
                    'flex h-8 items-center gap-1.5 rounded-full border px-3 text-xs font-semibold transition-colors disabled:cursor-default',
                    i < currentIndex &&
                        'border-primary/30 bg-primary/10 text-primary',
                    step.value === lead.stage &&
                        'border-primary bg-primary text-primary-foreground',
                    i > currentIndex &&
                        'text-muted-foreground hover:border-primary/40 hover:text-foreground',
                ]"
                @click="go(step.value)"
            >
                <Check v-if="i < currentIndex" class="size-3.5" />
                {{ step.label }}
            </button>
        </li>
        <li
            v-if="lead.stage === 'lost'"
            class="flex h-8 items-center rounded-full bg-destructive/12 px-3 text-xs font-semibold text-destructive-foreground"
        >
            Lost{{ lead.lost_reason ? `: ${lead.lost_reason}` : '' }}
        </li>
    </ol>
</template>
