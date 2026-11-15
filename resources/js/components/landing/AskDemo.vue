<script setup lang="ts">
import { FileText, RotateCcw, Sparkles } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, useTemplateRef } from 'vue';

/**
 * A replay of a real Ask Showroom answer, for the landing page.
 *
 * It's a recording, not a live call: the page is public and the model runs on one CPU, so
 * letting visitors fire questions at it would be both slow and an easy way to knock it over.
 * The question, answer and sources are what the assistant gave on the demo data (the
 * screenshot in the README is the same answer). Only two of the five sources are shown. The text
 * "streams" in once the card scrolls into view, the same way the real one does.
 */
const question = 'Can a customer take a car home overnight?';

// The answer, split so the citation markers can be drawn as chips rather than plain "[1]".
const parts: Array<string | number> = [
    'A customer can take a car home overnight if they are in the Negotiation stage, the vehicle is valued at $25,000 or more, and it’s not a new model in its first month of release ',
    1,
    '. The customer must sign an overnight agreement, provide insurance details, and return the vehicle by 10 a.m. the next day. Overnight test drives require sales manager approval.',
];

const sources = [
    {
        n: 1,
        title: 'Test Drive Process (S-105)',
        section: 'Overnight test drives',
    },
    {
        n: 2,
        title: 'Vehicle Delivery Checklist (S-130)',
        section: 'After delivery',
    },
];

const total = parts.reduce<number>(
    (sum, p) => sum + (typeof p === 'string' ? p.length : 1),
    0,
);

const shown = ref(0);
const done = computed(() => shown.value >= total);

// What's visible so far: whole parts, then a slice of the part currently being "typed".
const visible = computed(() => {
    let left = shown.value;
    const out: Array<string | number> = [];

    for (const part of parts) {
        if (left <= 0) {
            break;
        }

        if (typeof part === 'number') {
            out.push(part);
            left -= 1;
        } else {
            out.push(part.slice(0, left));
            left -= part.length;
        }
    }

    return out;
});

const card = useTemplateRef<HTMLElement>('card');
let timer: number | undefined;
let observer: IntersectionObserver | undefined;

function play(): void {
    window.clearInterval(timer);

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        shown.value = total;

        return;
    }

    shown.value = 0;
    // A few characters per tick reads like token-by-token output rather than a typewriter.
    timer = window.setInterval(() => {
        shown.value = Math.min(total, shown.value + 3);

        if (done.value) {
            window.clearInterval(timer);
        }
    }, 28);
}

onMounted(() => {
    if (!card.value || !('IntersectionObserver' in window)) {
        shown.value = total;

        return;
    }

    observer = new IntersectionObserver(
        ([entry]) => {
            if (entry.isIntersecting) {
                play();
                observer?.disconnect();
            }
        },
        { threshold: 0.4 },
    );
    observer.observe(card.value);
});

onBeforeUnmount(() => {
    window.clearInterval(timer);
    observer?.disconnect();
});
</script>

<template>
    <div
        ref="card"
        class="relative overflow-hidden rounded-2xl border bg-card shadow-xl shadow-black/5 dark:shadow-black/30"
    >
        <div
            class="flex items-center gap-2 border-b bg-muted/50 px-4 py-2.5 text-xs font-semibold text-muted-foreground"
        >
            <Sparkles class="size-3.5 text-primary" />
            Ask Showroom
            <span class="ml-auto font-normal"
                >qwen2.5:7b · on this machine</span
            >
        </div>

        <div class="space-y-4 p-4 sm:p-5">
            <p
                class="ml-auto w-fit max-w-[85%] rounded-2xl rounded-br-sm bg-primary px-4 py-2 text-sm text-primary-foreground"
            >
                {{ question }}
            </p>

            <!-- Screen readers get the whole answer at once instead of a stream of fragments. -->
            <p class="sr-only">
                {{
                    parts
                        .map((p) => (typeof p === 'number' ? `[${p}]` : p))
                        .join('')
                }}
            </p>
            <p
                class="min-h-36 text-sm leading-relaxed sm:min-h-28"
                aria-hidden="true"
            >
                <template v-for="(part, i) in visible" :key="i">
                    <span
                        v-if="typeof part === 'number'"
                        class="mx-0.5 inline-flex size-4.5 -translate-y-px items-center justify-center rounded bg-accent align-middle text-[10px] font-bold text-accent-foreground"
                        >{{ part }}</span
                    >
                    <template v-else>{{ part }}</template>
                </template>
                <span
                    v-if="!done"
                    class="ml-0.5 inline-block h-4 w-1.5 animate-pulse rounded-sm bg-primary align-middle"
                />
            </p>

            <Transition
                enter-active-class="transition duration-500 ease-[var(--ease-out-expo)]"
                enter-from-class="translate-y-2 opacity-0"
            >
                <div v-if="done" class="space-y-3">
                    <ul class="grid gap-2 sm:grid-cols-2">
                        <li
                            v-for="s in sources"
                            :key="s.n"
                            class="flex items-start gap-2 rounded-lg border bg-background p-2.5 text-xs"
                        >
                            <span
                                class="flex size-4.5 shrink-0 items-center justify-center rounded bg-accent text-[10px] font-bold text-accent-foreground"
                                >{{ s.n }}</span
                            >
                            <span class="min-w-0">
                                <span
                                    class="flex items-center gap-1 font-semibold"
                                >
                                    <FileText class="size-3 shrink-0" />
                                    <span class="truncate">{{ s.title }}</span>
                                </span>
                                <span class="text-muted-foreground">{{
                                    s.section
                                }}</span>
                            </span>
                        </li>
                    </ul>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-md text-xs font-medium text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        @click="play"
                    >
                        <RotateCcw class="size-3" />
                        Replay
                    </button>
                </div>
            </Transition>
        </div>
    </div>
</template>
