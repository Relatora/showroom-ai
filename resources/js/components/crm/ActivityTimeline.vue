<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';
import {
    ArrowRightLeft,
    Car,
    Mail,
    MessageSquareText,
    NotebookPen,
    Phone,
    Users,
} from '@lucide/vue';
import { useId } from 'vue';
import { Button } from '@/components/ui/button';
import { useCrm } from '@/composables/useCrm';
import { dateTime, timeAgo } from '@/lib/format';
import { store as storeActivity } from '@/routes/leads/activities';
import type { Activity, ActivityType, Lead } from '@/types/crm';

const { lead } = defineProps<{ lead: Lead }>();

const { label } = useCrm();
const fieldId = useId();

const icons: Record<ActivityType, LucideIcon> = {
    note: NotebookPen,
    call: Phone,
    email: Mail,
    sms: MessageSquareText,
    meeting: Users,
    stage_change: ArrowRightLeft,
    test_drive: Car,
};

// The types a person can log by hand. Stage changes are written by the system.
const loggable: ActivityType[] = ['call', 'email', 'sms', 'meeting', 'note'];

const form = useForm<{ type: ActivityType; body: string }>({
    type: 'call',
    body: '',
});

function submit(): void {
    form.submit(storeActivity(lead.id), {
        preserveScroll: true,
        viewTransition: false,
        onSuccess: () => form.reset('body'),
    });
}

const isSystem = (activity: Activity) => activity.type === 'stage_change';
</script>

<template>
    <section
        aria-labelledby="timeline-heading"
        class="rounded-xl border bg-card"
    >
        <h2 id="timeline-heading" class="border-b px-5 py-3 text-sm font-bold">
            Timeline
        </h2>

        <form
            v-if="lead.can.update"
            class="border-b bg-muted/40 px-5 py-4"
            @submit.prevent="submit"
        >
            <div
                role="radiogroup"
                aria-label="What happened"
                class="mb-2 flex flex-wrap gap-1.5"
            >
                <button
                    v-for="type in loggable"
                    :key="type"
                    type="button"
                    role="radio"
                    :aria-checked="form.type === type"
                    class="flex h-7 items-center gap-1.5 rounded-full border bg-background px-2.5 text-xs font-medium text-muted-foreground transition-colors hover:text-foreground aria-checked:border-primary aria-checked:bg-primary/10 aria-checked:text-primary"
                    @click="form.type = type"
                >
                    <component :is="icons[type]" class="size-3.5" />
                    {{ label('activityTypes', type) }}
                </button>
            </div>
            <label :for="fieldId" class="sr-only">Details</label>
            <textarea
                :id="fieldId"
                v-model="form.body"
                rows="2"
                placeholder="What was said? Calls, emails and meetings reset the stale clock. Notes don't."
                class="w-full resize-y rounded-lg border bg-background px-3 py-2 text-sm outline-none placeholder:text-muted-foreground focus:border-primary/50 focus:ring-3 focus:ring-primary/15"
                @keydown.ctrl.enter="submit"
                @keydown.meta.enter="submit"
            />
            <p
                v-if="form.errors.body"
                class="mt-1 text-xs text-destructive-foreground"
            >
                {{ form.errors.body }}
            </p>
            <div class="mt-2 flex items-center justify-between">
                <span class="text-[11px] text-muted-foreground"
                    >Ctrl + Enter to save</span
                >
                <Button
                    size="sm"
                    :disabled="form.processing || form.body.trim() === ''"
                >
                    Log {{ label('activityTypes', form.type).toLowerCase() }}
                </Button>
            </div>
        </form>

        <ol class="relative px-5 py-4">
            <!-- The road line the entries hang off. -->
            <span
                aria-hidden="true"
                class="absolute top-6 bottom-6 left-[2.15rem] w-px bg-border"
            />

            <TransitionGroup
                enter-active-class="transition duration-300 ease-[var(--ease-out-expo)]"
                enter-from-class="opacity-0 -translate-y-1"
            >
                <li
                    v-for="activity in lead.activities"
                    :key="activity.id"
                    class="relative flex gap-3 py-2.5"
                >
                    <span
                        :class="[
                            'relative z-10 flex size-7 shrink-0 items-center justify-center rounded-full border bg-card',
                            isSystem(activity)
                                ? 'text-muted-foreground'
                                : 'text-primary',
                        ]"
                    >
                        <component
                            :is="icons[activity.type]"
                            class="size-3.5"
                        />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p
                            class="flex flex-wrap items-baseline gap-x-2 text-xs text-muted-foreground"
                        >
                            <span class="font-semibold text-foreground">
                                {{ label('activityTypes', activity.type) }}
                            </span>
                            <span v-if="activity.user"
                                >by {{ activity.user.name }}</span
                            >
                            <time
                                :datetime="activity.occurred_at"
                                :title="dateTime(activity.occurred_at)"
                            >
                                {{ timeAgo(activity.occurred_at) }}
                            </time>
                        </p>
                        <p
                            :class="[
                                'mt-0.5 text-sm whitespace-pre-line',
                                isSystem(activity) && 'text-muted-foreground',
                            ]"
                        >
                            {{ activity.body }}
                        </p>
                    </div>
                </li>
            </TransitionGroup>

            <li
                v-if="!lead.activities?.length"
                class="py-6 text-center text-sm text-muted-foreground"
            >
                Nothing logged yet. Start with the first call.
            </li>
        </ol>
    </section>
</template>
