<script setup lang="ts">
import { computed, useId } from 'vue';

/**
 * A stylised side-on silhouette of the vehicle, painted in its actual exterior colour.
 *
 * We don't have real photos (and stock photos of real cars would be a licensing headache), so
 * each body style gets a simple hand-drawn profile. It's enough to make the inventory grid
 * scannable at a glance: you spot the red pickup before you read the word "pickup".
 */
const { bodyStyle, color = '#9a9ea3' } = defineProps<{
    bodyStyle: string;
    color?: string;
}>();

const id = useId();

// Each body: the outer shell path, plus where the two wheels sit along the x axis.
const shapes: Record<
    string,
    { body: string; glass: string; wheels: [number, number] }
> = {
    sedan: {
        body: 'M14 62 Q14 52 26 50 L58 46 Q72 30 96 28 L132 28 Q150 30 164 44 L190 48 Q206 51 206 62 L206 68 L14 68 Z',
        glass: 'M66 46 Q78 34 96 33 L128 33 Q142 34 154 46 Z',
        wheels: [52, 170],
    },
    hatchback: {
        body: 'M16 62 Q16 52 28 49 L60 45 Q76 28 100 27 L150 27 Q170 28 182 44 L196 50 Q204 54 204 62 L204 68 L16 68 Z',
        glass: 'M68 45 Q82 32 100 32 L148 32 Q164 33 174 45 Z',
        wheels: [54, 168],
    },
    suv: {
        body: 'M14 64 Q14 50 26 47 L52 42 Q64 22 88 20 L158 20 Q176 21 186 40 L198 44 Q208 48 208 60 L208 68 L14 68 Z',
        glass: 'M60 42 Q70 26 88 25 L154 25 Q168 26 178 42 Z',
        wheels: [52, 172],
    },
    truck: {
        body: 'M12 64 Q12 50 24 47 L50 44 Q60 24 82 22 L118 22 Q128 23 132 40 L132 44 L210 44 L210 68 L12 68 Z',
        glass: 'M58 42 Q66 28 82 27 L114 27 Q124 28 126 42 Z',
        wheels: [48, 180],
    },
    minivan: {
        body: 'M14 64 Q14 48 30 40 Q50 22 80 20 L170 20 Q190 21 198 40 L204 48 Q208 54 208 62 L208 68 L14 68 Z',
        glass: 'M42 40 Q58 26 80 25 L168 25 Q184 26 192 42 Z',
        wheels: [52, 172],
    },
    coupe: {
        body: 'M16 62 Q16 54 30 52 L70 47 Q90 32 112 31 L136 31 Q154 33 168 46 L194 50 Q204 53 204 62 L204 68 L16 68 Z',
        glass: 'M80 47 Q94 36 112 36 L132 36 Q146 37 158 47 Z',
        wheels: [56, 170],
    },
    wagon: {
        body: 'M14 62 Q14 51 26 48 L56 44 Q70 28 94 26 L176 26 Q188 27 196 44 L202 48 Q208 52 208 62 L208 68 L14 68 Z',
        glass: 'M64 44 Q76 31 94 31 L172 31 Q184 32 190 44 Z',
        wheels: [52, 172],
    },
};

const shape = computed(() => shapes[bodyStyle] ?? shapes.suv);
</script>

<template>
    <svg
        viewBox="0 0 220 84"
        role="img"
        :aria-label="`${bodyStyle} illustration`"
        class="h-auto w-full"
    >
        <defs>
            <!-- Paint: a vertical gradient so the body looks curved and catches light on top. -->
            <linearGradient :id="`${id}-paint`" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" :stop-color="color" stop-opacity="0.75" />
                <stop offset="0.45" :stop-color="color" />
                <stop offset="1" :stop-color="color" stop-opacity="0.92" />
            </linearGradient>
            <linearGradient :id="`${id}-shine`" x1="0" y1="0" x2="1" y2="0">
                <stop offset="0" stop-color="white" stop-opacity="0" />
                <stop offset="0.5" stop-color="white" stop-opacity="0.35" />
                <stop offset="1" stop-color="white" stop-opacity="0" />
            </linearGradient>
        </defs>

        <!-- Floor shadow -->
        <ellipse cx="110" cy="74" rx="96" ry="5" class="fill-foreground/15" />

        <path
            :d="shape.body"
            :fill="`url(#${id}-paint)`"
            class="stroke-foreground/25"
            stroke-width="1"
        />
        <path :d="shape.glass" class="fill-foreground/70 dark:fill-black/70" />
        <path :d="shape.glass" :fill="`url(#${id}-shine)`" />

        <g v-for="x in shape.wheels" :key="x">
            <circle :cx="x" cy="68" r="12" class="fill-neutral-900" />
            <circle :cx="x" cy="68" r="6.5" class="fill-neutral-400" />
            <circle :cx="x" cy="68" r="2" class="fill-neutral-700" />
        </g>
    </svg>
</template>
