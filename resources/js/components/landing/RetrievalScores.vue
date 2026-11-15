<script setup lang="ts">
import { vReveal } from '@/directives/reveal';

/**
 * The eval result as a chart: how often the right passage lands in the top 5.
 *
 * One measure, three methods, so it's a plain bar list with the value written on each bar,
 * not a grouped chart. The numbers are copied from evals/results.md (run `showroom:eval
 * --write` to regenerate that file). If they move, update them here too.
 */
const rows = [
    { method: 'Hybrid', note: 'what ships', score: 0.97, highlight: true },
    { method: 'Vector only', note: 'meaning', score: 0.94, highlight: false },
    {
        method: 'Keyword only',
        note: 'exact words',
        score: 0.74,
        highlight: false,
    },
];
</script>

<template>
    <figure class="rounded-2xl border bg-card p-5 sm:p-6">
        <figcaption class="mb-4">
            <p class="text-sm font-bold">Right passage in the top 5</p>
            <p class="text-xs text-muted-foreground">
                hit@5 on 35 eval questions, including 7 that ask by stock number
                or VIN
            </p>
        </figcaption>

        <ul class="space-y-3">
            <li v-for="(row, i) in rows" :key="row.method" v-reveal="i * 120">
                <div
                    class="mb-1 flex items-baseline justify-between gap-2 text-xs"
                >
                    <span>
                        <span class="font-semibold">{{ row.method }}</span>
                        <span class="text-muted-foreground">{{
                            ` · ${row.note}`
                        }}</span>
                    </span>
                    <span class="font-mono font-semibold tabular-nums">{{
                        row.score.toFixed(2)
                    }}</span>
                </div>
                <div class="h-2.5 overflow-hidden rounded-full bg-muted">
                    <div
                        class="bar h-full rounded-full"
                        :class="row.highlight ? 'bg-primary' : 'bg-chart-5'"
                        :style="{ '--score': row.score }"
                    />
                </div>
            </li>
        </ul>
    </figure>
</template>

<style scoped>
/* The bars grow to their value once the row has been revealed by v-reveal. */
.bar {
    width: calc(var(--score) * 100%);
    transform-origin: left;
    transition: transform 1s var(--ease-out-expo) 0.2s;
}

[data-reveal='hidden'] .bar {
    transform: scaleX(0);
}
</style>
