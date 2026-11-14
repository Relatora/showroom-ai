<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { computed } from 'vue';
import { dateTime } from '@/lib/format';
import { index as knowledgeIndex } from '@/routes/knowledge';

type Chunk = {
    id: number;
    position: number;
    heading: string | null;
    content: string;
    token_estimate: number;
};

const { document, chunks } = defineProps<{
    document: {
        id: number;
        title: string;
        category_label: string;
        original_name: string;
        status: string;
        error: string | null;
        chunks_count: number;
        indexed_at: string | null;
    };
    chunks: Chunk[];
}>();

// Built as one string so template line breaks can't sneak a space in before the commas.
const meta = computed(() =>
    [
        document.category_label,
        document.original_name,
        document.indexed_at ? `indexed ${dateTime(document.indexed_at)}` : null,
    ]
        .filter(Boolean)
        .join(', '),
);

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Knowledge base', href: knowledgeIndex() }],
    },
});
</script>

<template>
    <Head :title="document.title" />

    <div class="flex min-w-0 flex-1 flex-col gap-6 p-4 md:p-6">
        <Link
            :href="knowledgeIndex()"
            class="flex w-fit items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" /> Knowledge base
        </Link>

        <header>
            <h1 class="text-3xl font-extrabold tracking-tight">
                {{ document.title }}
            </h1>
            <p class="mt-1 text-sm text-muted-foreground">{{ meta }}</p>
            <p
                v-if="document.error"
                class="mt-2 text-sm text-destructive-foreground"
            >
                {{ document.error }}
            </p>
        </header>

        <section aria-labelledby="passages-heading" class="max-w-3xl">
            <h2 id="passages-heading" class="text-base font-bold">
                How the assistant reads this
            </h2>
            <p class="mt-1 mb-4 text-sm text-muted-foreground">
                The document is split into {{ chunks.length }} passages. When
                someone asks a question, the assistant finds the most relevant
                passages and cites them by heading.
            </p>

            <ol class="flex flex-col gap-3">
                <li
                    v-for="chunk in chunks"
                    :id="`passage-${chunk.position}`"
                    :key="chunk.id"
                    class="scroll-mt-20 rounded-xl border bg-card p-4 transition-shadow target:ring-2 target:ring-primary"
                >
                    <div class="mb-2 flex items-baseline justify-between gap-3">
                        <h3 class="text-sm font-bold">
                            {{ chunk.heading ?? document.title }}
                        </h3>
                        <span
                            class="shrink-0 text-xs text-muted-foreground tabular-nums"
                        >
                            Passage {{ chunk.position + 1 }}, about
                            {{ chunk.token_estimate }} tokens
                        </span>
                    </div>
                    <p
                        class="text-sm leading-relaxed whitespace-pre-line text-foreground/90"
                    >
                        {{ chunk.content }}
                    </p>
                </li>
            </ol>
        </section>
    </div>
</template>
