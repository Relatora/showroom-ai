<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowUp,
    CarFront,
    FileText,
    MessageSquareText,
    Square,
    UserRound,
} from '@lucide/vue';
import { useIntervalFn } from '@vueuse/core';
import { computed, nextTick, ref, useId, useTemplateRef } from 'vue';
import AnswerText from '@/components/assistant/AnswerText.vue';
import UserAvatar from '@/components/crm/UserAvatar.vue';
import { Button } from '@/components/ui/button';
import { useEventStream } from '@/composables/useEventStream';
import { count } from '@/lib/format';
import { ask as askRoute, index as assistantIndex } from '@/routes/assistant';
import type { AskEvents, Source, Timing } from '@/types/assistant';

const { coverage, suggestions } = defineProps<{
    coverage: { documents: number; vehicles: number; leads: number };
    suggestions: string[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Ask Showroom', href: assistantIndex() }],
    },
});

/**
 * One question and its answer. The status walks forward as events arrive:
 * searching -> reading (sources are in, waiting for the model) -> answering -> done.
 */
type Turn = {
    id: number;
    question: string;
    answer: string;
    sources: Source[];
    status:
        | 'searching'
        | 'reading'
        | 'answering'
        | 'done'
        | 'stopped'
        | 'error';
    error?: string;
    timing?: Timing;
};

const page = usePage();
const turns = ref<Turn[]>([]);
const question = ref('');
const inputId = useId();
const input = useTemplateRef<HTMLTextAreaElement>('input');
const lastTurn = useTemplateRef<HTMLElement[]>('turn');
const { streaming, post, cancel } = useEventStream<AskEvents>();

// A local model on a CPU can take half a minute before the first word, so show that time
// passing. A counter that moves feels very different from a spinner that doesn't.
const waited = ref(0);
const { pause, resume } = useIntervalFn(() => waited.value++, 1000, {
    immediate: false,
});

async function ask(text?: string): Promise<void> {
    const q = (text ?? question.value).trim();

    if (q === '' || streaming.value) {
        return;
    }

    // Send the last few finished turns along, so "what about for a CPO?" has context.
    const history = turns.value
        .filter((t) => t.status === 'done')
        .slice(-4)
        .flatMap((t) => [
            { role: 'user', content: t.question },
            { role: 'assistant', content: t.answer },
        ]);

    turns.value.push({
        id: Date.now(),
        question: q,
        answer: '',
        sources: [],
        status: 'searching',
    });
    // Read it back through the ref so we're mutating the reactive copy, not the plain object.
    const turn = turns.value[turns.value.length - 1];

    question.value = '';
    waited.value = 0;
    resume();

    await nextTick();
    lastTurn.value?.at(-1)?.scrollIntoView({
        behavior: 'smooth',
        block: 'start',
    });

    try {
        await post(
            askRoute.url(),
            { question: q, history },
            {
                sources: (data) => {
                    turn.sources = data.sources;
                    turn.status = 'reading';
                },
                delta: (data) => {
                    turn.status = 'answering';
                    turn.answer += data.text;
                },
                done: (data) => (turn.timing = data),
                error: (data) => {
                    turn.status = 'error';
                    turn.error = data.message;
                },
            },
        );

        if (turn.status === 'answering') {
            turn.status = 'done';
        } else if (turn.status !== 'error') {
            turn.status = 'stopped';
        }
    } catch (error) {
        turn.status = 'error';
        turn.error = (error as Error).message;
    } finally {
        pause();
        input.value?.focus();
    }
}

function onKeydown(event: KeyboardEvent): void {
    // Enter sends, Shift+Enter adds a line, like every chat app people already know.
    if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
        event.preventDefault();
        ask();
    }
}

/** Which source numbers the answer actually cited, so the rest can be shown as "also read". */
function cited(turn: Turn): Set<number> {
    return new Set(
        [...turn.answer.matchAll(/\[(\d+)\]/g)].map((m) => Number(m[1])),
    );
}

const sourceIcon = {
    document: FileText,
    vehicle: CarFront,
    lead: UserRound,
    activity: MessageSquareText,
} as const;

const seconds = (ms: number) =>
    ms < 1000 ? `${ms} ms` : `${(ms / 1000).toFixed(1)} s`;

const leadsLabel = computed(() =>
    page.props.auth.user.role === 'rep' ? 'Your leads' : 'Leads',
);
</script>

<template>
    <Head title="Ask Showroom" />

    <div class="flex min-w-0 flex-1 flex-col">
        <div
            class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-8 p-4 md:p-6"
        >
            <header class="flex flex-col items-start">
                <div class="sign px-6 pt-4 pb-5">
                    <h1
                        class="text-[2rem] leading-none font-extrabold tracking-tight"
                    >
                        Ask Showroom
                    </h1>
                    <dl
                        class="mt-3 grid grid-cols-[1fr_auto] gap-x-12 gap-y-0.5 text-[15px] font-semibold"
                    >
                        <dt>Policy documents</dt>
                        <dd class="text-right tabular-nums">
                            {{ coverage.documents }}
                        </dd>
                        <dt>Vehicles in stock</dt>
                        <dd class="text-right tabular-nums">
                            {{ count(coverage.vehicles) }}
                        </dd>
                        <dt>{{ leadsLabel }}</dt>
                        <dd class="text-right tabular-nums">
                            {{ count(coverage.leads) }}
                        </dd>
                    </dl>
                </div>
            </header>

            <section v-if="turns.length === 0" aria-labelledby="try-heading">
                <h2 id="try-heading" class="mb-1 text-lg font-bold">
                    What do you need to know?
                </h2>
                <p class="mb-4 max-w-prose text-sm text-muted-foreground">
                    Answers come from the dealership's policies, the stock list
                    and your customers' notes, with a numbered link to every
                    source. Try one of these:
                </p>
                <ul class="flex flex-col gap-2">
                    <li v-for="suggestion in suggestions" :key="suggestion">
                        <button
                            type="button"
                            class="group flex w-full items-center justify-between gap-3 rounded-lg border bg-card px-4 py-3 text-left text-[15px] font-semibold transition-colors hover:border-primary/50 hover:bg-primary/5 focus-visible:shadow-glow focus-visible:outline-none"
                            @click="ask(suggestion)"
                        >
                            {{ suggestion }}
                            <ArrowUp
                                class="size-4 shrink-0 rotate-45 text-muted-foreground transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:text-primary"
                            />
                        </button>
                    </li>
                </ul>
            </section>

            <ol v-else class="flex flex-col gap-10" aria-live="polite">
                <li
                    v-for="turn in turns"
                    ref="turn"
                    :key="turn.id"
                    class="flex scroll-mt-6 flex-col gap-4"
                >
                    <div class="flex items-start gap-3">
                        <UserAvatar
                            :name="page.props.auth.user.name"
                            class="mt-0.5 size-7 text-[11px]"
                        />
                        <p class="text-lg leading-snug font-bold">
                            {{ turn.question }}
                        </p>
                    </div>

                    <div class="rounded-xl border bg-card p-5">
                        <p
                            v-if="turn.status === 'searching'"
                            class="text-sm text-muted-foreground"
                        >
                            Searching the dealership's records…
                        </p>
                        <p
                            v-else-if="turn.status === 'reading'"
                            class="flex items-center justify-between gap-3 text-sm text-muted-foreground"
                        >
                            <span>
                                Reading {{ turn.sources.length }}
                                {{
                                    turn.sources.length === 1
                                        ? 'passage'
                                        : 'passages'
                                }}
                                and writing an answer…
                            </span>
                            <span class="tabular-nums">{{ waited }} s</span>
                        </p>
                        <p
                            v-else-if="turn.status === 'error'"
                            class="text-sm font-semibold text-destructive"
                        >
                            {{ turn.error }}
                        </p>

                        <AnswerText
                            v-if="turn.answer"
                            :text="turn.answer"
                            :sources="turn.sources"
                            :streaming="turn.status === 'answering'"
                        />
                        <p
                            v-if="turn.status === 'stopped'"
                            class="mt-3 text-xs text-muted-foreground"
                        >
                            Stopped.
                        </p>
                    </div>

                    <div v-if="turn.sources.length" class="flex flex-col gap-2">
                        <h3 class="text-xs font-semibold text-muted-foreground">
                            Sources
                        </h3>
                        <ol class="grid gap-2 sm:grid-cols-2">
                            <li
                                v-for="(source, i) in turn.sources"
                                :key="source.id"
                                :class="[
                                    'transition-opacity',
                                    turn.status === 'done' &&
                                    !cited(turn).has(i + 1)
                                        ? 'opacity-60 hover:opacity-100'
                                        : '',
                                ]"
                            >
                                <component
                                    :is="source.url ? Link : 'div'"
                                    :href="source.url ?? undefined"
                                    class="flex h-full gap-3 rounded-lg border bg-card p-3 text-sm transition-colors hover:border-primary/50"
                                >
                                    <span
                                        class="flex h-5 min-w-5 shrink-0 items-center justify-center rounded bg-sign px-1 text-[11px] font-extrabold text-sign-foreground"
                                        >{{ i + 1 }}</span
                                    >
                                    <span class="flex min-w-0 flex-col gap-0.5">
                                        <span
                                            class="flex items-center gap-1.5 font-semibold"
                                        >
                                            <component
                                                :is="sourceIcon[source.type]"
                                                class="size-3.5 shrink-0 text-muted-foreground"
                                            />
                                            <span class="truncate">{{
                                                source.section ?? source.label
                                            }}</span>
                                        </span>
                                        <span
                                            v-if="source.section"
                                            class="truncate text-xs font-semibold text-muted-foreground"
                                            >{{ source.label }}</span
                                        >
                                        <span
                                            v-else
                                            class="line-clamp-2 text-xs text-muted-foreground"
                                            >{{ source.excerpt }}</span
                                        >
                                    </span>
                                </component>
                            </li>
                        </ol>
                    </div>

                    <p
                        v-if="turn.timing"
                        class="text-xs text-muted-foreground tabular-nums"
                    >
                        Found sources in
                        {{ seconds(turn.timing.retrieval_ms) }}, first words
                        after {{ seconds(turn.timing.first_token_ms ?? 0) }},
                        done in {{ seconds(turn.timing.total_ms) }}.
                    </p>
                </li>
            </ol>
        </div>

        <!-- The composer sticks to the bottom of the page, so it's always in reach. -->
        <form
            class="sticky bottom-0 border-t bg-background/85 backdrop-blur supports-[backdrop-filter]:bg-background/70"
            @submit.prevent="ask()"
        >
            <div class="mx-auto flex max-w-3xl flex-col gap-1.5 p-4 md:px-6">
                <label :for="inputId" class="sr-only">Your question</label>
                <div
                    class="flex items-end gap-2 rounded-xl border bg-card p-2 transition-shadow focus-within:shadow-glow"
                >
                    <textarea
                        :id="inputId"
                        ref="input"
                        v-model="question"
                        rows="1"
                        maxlength="500"
                        placeholder="Ask about a policy, a vehicle or a customer…"
                        class="[field-sizing:content] max-h-40 min-h-9 flex-1 resize-none bg-transparent px-2 py-1.5 text-[15px] placeholder:text-muted-foreground focus:outline-none"
                        @keydown="onKeydown"
                    />
                    <Button
                        v-if="streaming"
                        type="button"
                        size="icon"
                        variant="secondary"
                        aria-label="Stop answering"
                        @click="cancel"
                    >
                        <Square class="size-3.5 fill-current" />
                    </Button>
                    <Button
                        v-else
                        type="submit"
                        size="icon"
                        :disabled="question.trim() === ''"
                        aria-label="Ask"
                    >
                        <ArrowUp class="size-4" />
                    </Button>
                </div>
                <p class="px-1 text-[11px] text-muted-foreground">
                    Answers can be wrong. Check the cited source before you
                    quote anything to a customer.
                </p>
            </div>
        </form>
    </div>
</template>
