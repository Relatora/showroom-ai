<script setup lang="ts">
import { computed, useId } from 'vue';

/**
 * A side-on silhouette of the vehicle, painted in its actual exterior colour.
 *
 * We don't have photos (and stock photos of real cars would be a licensing headache), so each
 * body style gets a simple drawn profile with roughly real proportions: an SUV is about a third
 * as tall as it is long, a coupe much less. The front of every vehicle faces right.
 */
const { bodyStyle, color = '#9a9ea3' } = defineProps<{
    bodyStyle: string;
    color?: string;
}>();

const id = useId();

type Shape = {
    body: string;
    windows: string[];
    wheels: [number, number];
    wheelY: number;
    radius: number;
};

const shapes: Record<string, Shape> = {
    sedan: {
        body: 'M10 80 L12 66 Q14 60 24 58 L62 54 L84 38 Q90 34 100 34 L150 34 Q160 34 168 40 L190 56 L216 60 Q228 62 230 72 L230 82 Q230 86 226 86 L14 86 Q10 86 10 80 Z',
        windows: [
            'M72 54 L88 41 Q92 38 100 38 L126 38 L126 54 Z',
            'M132 54 L132 38 L150 38 Q158 38 164 43 L180 54 Z',
        ],
        wheels: [50, 192],
        wheelY: 86,
        radius: 15,
    },
    hatchback: {
        body: 'M10 80 L12 48 Q14 40 24 38 L40 36 L70 26 Q76 24 84 24 L132 24 Q142 24 150 32 L172 52 L208 58 Q226 60 230 70 L230 82 Q230 86 226 86 L14 86 Q10 86 10 80 Z',
        windows: [
            'M30 52 L44 40 L74 30 L96 29 L96 52 Z',
            'M102 52 L102 29 L130 29 Q138 29 144 35 L160 52 Z',
        ],
        wheels: [48, 190],
        wheelY: 86,
        radius: 15,
    },
    suv: {
        body: 'M10 82 L10 46 Q10 38 18 36 L40 34 L62 22 Q68 18 78 18 L166 18 Q176 18 182 24 L202 46 L222 52 Q230 54 230 64 L230 82 Q230 88 224 88 L16 88 Q10 88 10 82 Z',
        windows: [
            'M22 48 L22 41 Q22 38 28 37 L44 36 L44 48 Z',
            'M50 48 L50 34 L68 25 L110 24 L110 48 Z',
            'M116 48 L116 24 L164 24 Q172 24 177 30 L192 48 Z',
        ],
        wheels: [52, 190],
        wheelY: 88,
        radius: 17,
    },
    truck: {
        body: 'M10 84 L10 52 Q10 48 14 48 L104 48 L104 24 Q104 20 110 20 L160 20 Q170 20 176 28 L192 48 L222 54 Q230 56 230 66 L230 84 Q230 88 226 88 L14 88 Q10 88 10 84 Z',
        windows: [
            'M112 46 L112 26 L134 26 L134 46 Z',
            'M140 46 L140 26 L160 26 Q166 26 170 32 L182 46 Z',
        ],
        wheels: [50, 192],
        wheelY: 88,
        radius: 17,
    },
    minivan: {
        body: 'M10 84 L10 40 Q12 24 28 22 L170 20 Q180 20 188 28 L208 48 L224 54 Q230 56 230 66 L230 84 Q230 88 226 88 L14 88 Q10 88 10 84 Z',
        windows: [
            'M22 46 L24 32 Q26 28 32 28 L70 27 L70 46 Z',
            'M76 46 L76 27 L126 26 L126 46 Z',
            'M132 46 L132 26 L172 25 Q178 25 182 30 L196 46 Z',
        ],
        wheels: [50, 192],
        wheelY: 88,
        radius: 16,
    },
    coupe: {
        body: 'M10 82 L12 70 Q14 64 26 62 L70 58 L96 44 Q104 40 114 40 L142 40 Q152 40 160 46 L180 60 L214 64 Q228 66 230 74 L230 82 Q230 86 226 86 L14 86 Q10 86 10 82 Z',
        windows: [
            'M86 58 L102 47 Q108 44 116 44 L136 44 L136 58 Z',
            'M142 58 L142 44 Q152 44 158 50 L168 58 Z',
        ],
        wheels: [52, 190],
        wheelY: 86,
        radius: 15,
    },
    wagon: {
        body: 'M10 80 L10 50 Q10 44 18 42 L30 40 L54 30 Q60 28 68 28 L150 28 Q160 28 168 36 L186 54 L214 58 Q228 60 230 70 L230 82 Q230 86 226 86 L14 86 Q10 86 10 80 Z',
        windows: [
            'M22 54 L22 46 L34 44 L56 34 L84 33 L84 54 Z',
            'M90 54 L90 33 L124 33 L124 54 Z',
            'M130 54 L130 33 L150 33 Q158 33 164 40 L176 54 Z',
        ],
        wheels: [50, 192],
        wheelY: 86,
        radius: 15,
    },
};

const shape = computed(() => shapes[bodyStyle] ?? shapes.suv);
</script>

<template>
    <svg
        viewBox="0 0 240 106"
        role="img"
        :aria-label="`${bodyStyle} illustration`"
        class="h-auto w-full"
    >
        <defs>
            <!-- Paint: lighter along the shoulder line, darker toward the sills, so it reads as curved. -->
            <linearGradient :id="`${id}-paint`" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" :stop-color="color" />
                <stop offset="0.55" :stop-color="color" />
                <stop offset="1" :stop-color="color" stop-opacity="0.7" />
            </linearGradient>
            <linearGradient :id="`${id}-glass`" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0" stop-color="#1c2328" />
                <stop offset="0.6" stop-color="#2f3a42" />
                <stop offset="1" stop-color="#1c2328" />
            </linearGradient>
        </defs>

        <ellipse
            cx="120"
            :cy="shape.wheelY + shape.radius - 1"
            rx="108"
            ry="4"
            class="fill-foreground/15"
        />

        <path
            :d="shape.body"
            :fill="`url(#${id}-paint)`"
            class="stroke-black/25"
            stroke-width="1"
        />
        <!-- Sill: a darker strip along the bottom, the way the lower body falls into shadow. -->
        <path
            :d="shape.body"
            fill="black"
            fill-opacity="0.12"
            :clip-path="`inset(${shape.wheelY - 12}px 0 0 0)`"
        />
        <path
            v-for="(w, i) in shape.windows"
            :key="i"
            :d="w"
            :fill="`url(#${id}-glass)`"
        />
        <!-- Head and tail lights. -->
        <rect
            x="224"
            :y="shape.wheelY - 26"
            width="6"
            height="4"
            rx="2"
            fill="#fff6d6"
        />
        <rect
            x="10"
            :y="shape.wheelY - 30"
            width="4"
            height="5"
            rx="1.5"
            fill="#c8312f"
        />

        <g v-for="x in shape.wheels" :key="x">
            <circle
                :cx="x"
                :cy="shape.wheelY"
                :r="shape.radius"
                fill="#15191c"
            />
            <circle
                :cx="x"
                :cy="shape.wheelY"
                :r="shape.radius * 0.55"
                fill="#9aa3a9"
            />
            <circle
                :cx="x"
                :cy="shape.wheelY"
                :r="shape.radius * 0.16"
                fill="#4b545a"
            />
        </g>
    </svg>
</template>
