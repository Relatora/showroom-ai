<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<!--
    The Showroom mark: an "S" drawn as a road sweeping through two bends, with the top bend
    finished in the ignition accent. It uses currentColor so it works on any background, and
    the accent stroke picks up the theme's primary colour automatically.
-->
<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 32 32"
        fill="none"
        :class="className"
        v-bind="$attrs"
        aria-hidden="true"
    >
        <path
            d="M24 8.5c-1.8-1.6-4.4-2.5-7.4-2.5C12 6 9 8.2 9 11.2c0 3 2.6 4.3 7 5.3"
            stroke="var(--primary)"
            stroke-width="3.2"
            stroke-linecap="round"
        />
        <path
            d="M16 16.5c4.4 1 7 2.4 7 5.3 0 3-3 5.2-7.6 5.2-3 0-5.6-.9-7.4-2.5"
            stroke="currentColor"
            stroke-width="3.2"
            stroke-linecap="round"
        />
        <circle cx="24.6" cy="8.9" r="1.6" fill="var(--primary)" />
    </svg>
</template>
