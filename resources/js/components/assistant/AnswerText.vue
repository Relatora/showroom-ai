<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { Source } from '@/types/assistant';

/**
 * Renders an answer from the model: paragraphs, "- " bullets, **bold**, and [n] citations as
 * clickable chips.
 *
 * Deliberately not a Markdown library plus v-html. The text comes from a language model that
 * was shown customer notes and uploaded files, so it's untrusted; building real elements from a
 * tiny, known grammar means nothing in it can ever become HTML.
 */
const {
    text,
    sources,
    streaming = false,
} = defineProps<{
    text: string;
    sources: Source[];
    streaming?: boolean;
}>();

type Inline =
    | { kind: 'text'; value: string }
    | { kind: 'bold'; value: string }
    | { kind: 'cite'; n: number };

// A paragraph is a block with a single item; a list has one item per bullet. Same shape, so the
// template only needs one loop for the inline parts.
type Block = { kind: 'p' | 'ul'; items: Inline[][] };

const BULLET = /^\s*(?:[-*•]|\d+\.)\s+/;

function parseInline(line: string): Inline[] {
    return line
        .split(/(\*\*[^*]+\*\*|\[\d+\])/g)
        .filter((part) => part !== '')
        .map((part): Inline => {
            if (part.startsWith('**') && part.endsWith('**')) {
                return { kind: 'bold', value: part.slice(2, -2) };
            }

            const cite = part.match(/^\[(\d+)\]$/);

            return cite
                ? { kind: 'cite', n: Number(cite[1]) }
                : { kind: 'text', value: part };
        });
}

const blocks = computed<Block[]>(() => {
    const result: Block[] = [];

    for (const chunk of text.trim().split(/\n{2,}/)) {
        const lines = chunk.split('\n').filter((l) => l.trim() !== '');
        let paragraph: string[] = [];
        let list: Inline[][] = [];

        const flushParagraph = () => {
            if (paragraph.length) {
                result.push({
                    kind: 'p',
                    items: [parseInline(paragraph.join(' '))],
                });
                paragraph = [];
            }
        };
        const flushList = () => {
            if (list.length) {
                result.push({ kind: 'ul', items: list });
                list = [];
            }
        };

        for (const line of lines) {
            if (BULLET.test(line)) {
                flushParagraph();
                list.push(parseInline(line.replace(BULLET, '')));
            } else {
                flushList();
                paragraph.push(line.trim());
            }
        }

        flushParagraph();
        flushList();
    }

    return result;
});

const source = (n: number): Source | undefined => sources[n - 1];
</script>

<template>
    <div
        class="flex flex-col gap-3 text-[15px] leading-relaxed"
        :class="{ 'answer-streaming': streaming }"
    >
        <component
            :is="block.kind"
            v-for="(block, b) in blocks"
            :key="b"
            :class="{
                'flex list-disc flex-col gap-1.5 pl-5': block.kind === 'ul',
            }"
        >
            <component
                :is="block.kind === 'ul' ? 'li' : 'span'"
                v-for="(item, j) in block.items"
                :key="j"
            >
                <template v-for="(part, i) in item" :key="i">
                    <strong v-if="part.kind === 'bold'" class="font-bold">{{
                        part.value
                    }}</strong>
                    <template v-else-if="part.kind === 'cite'">
                        <component
                            :is="source(part.n)?.url ? Link : 'span'"
                            v-if="source(part.n)"
                            :href="source(part.n)?.url ?? undefined"
                            class="cite"
                            :title="source(part.n)?.label"
                            >{{ part.n }}</component
                        >
                        <!-- A number with no matching source: the model made it up. Show it, but don't link it. -->
                        <span v-else class="text-muted-foreground"
                            >[{{ part.n }}]</span
                        >
                    </template>
                    <template v-else>{{ part.value }}</template>
                </template>
            </component>
        </component>
    </div>
</template>

<style scoped>
/* A citation: a small green number plate, like the exit number on a highway sign. */
.cite {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.25rem;
    height: 1.125rem;
    margin-inline: 0.125rem;
    padding-inline: 0.25rem;
    border-radius: 0.25rem;
    background: var(--sign);
    color: var(--sign-foreground);
    font-size: 0.6875rem;
    font-weight: 800;
    vertical-align: 0.125em;
    box-shadow: inset 0 0 0 1px oklch(0.99 0 0 / 0.7);
    transition: transform 150ms ease;
}

a.cite:hover {
    transform: translateY(-1px);
}

/* The blinking caret at the end of an answer that's still arriving. */
.answer-streaming > :last-child::after {
    content: '';
    display: inline-block;
    width: 0.5em;
    height: 1.05em;
    margin-left: 0.15em;
    vertical-align: -0.15em;
    background: var(--primary);
    animation: caret 1s steps(1) infinite;
}

@keyframes caret {
    50% {
        opacity: 0;
    }
}

@media (prefers-reduced-motion: reduce) {
    .answer-streaming > :last-child::after {
        animation: none;
    }
}
</style>
