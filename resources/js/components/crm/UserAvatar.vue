<script setup lang="ts">
import { computed } from 'vue';
import { getInitials } from '@/composables/useInitials';
import { cn } from '@/lib/utils';

const { name, class: className } = defineProps<{
    name: string | null | undefined;
    class?: string;
}>();

/**
 * Same person, same colour, every time: hash the name onto the hue wheel. Lightness and chroma
 * stay fixed so every avatar has the same contrast against its initials.
 */
const hue = computed(() => {
    let hash = 0;

    for (const char of name ?? '') {
        hash = (hash * 31 + char.charCodeAt(0)) % 360;
    }

    return hash;
});
</script>

<template>
    <span
        v-if="name"
        :title="name"
        :style="{
            background: `oklch(0.72 0.11 ${hue})`,
            color: `oklch(0.24 0.06 ${hue})`,
        }"
        :class="
            cn(
                'inline-flex size-6 shrink-0 items-center justify-center rounded-full text-[10px] font-semibold ring-2 ring-background',
                className,
            )
        "
    >
        {{ getInitials(name) }}
    </span>
    <span
        v-else
        title="Unassigned"
        :class="
            cn(
                'inline-flex size-6 shrink-0 items-center justify-center rounded-full border border-dashed text-[10px] text-muted-foreground',
                className,
            )
        "
    >
        ?
    </span>
</template>
