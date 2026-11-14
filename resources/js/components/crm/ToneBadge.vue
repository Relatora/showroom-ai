<script setup lang="ts">
import { toneClasses } from '@/composables/useCrm';
import { cn } from '@/lib/utils';
import type { Tone } from '@/types/crm';

const {
    tone = 'muted',
    dot = false,
    class: className,
} = defineProps<{
    tone?: Tone;
    dot?: boolean;
    class?: string;
}>();
</script>

<template>
    <span
        :class="
            cn(
                'inline-flex shrink-0 items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] leading-4 font-medium whitespace-nowrap ring-1 ring-inset',
                toneClasses[tone].badge,
                className,
            )
        "
    >
        <span
            v-if="dot"
            :class="['size-1.5 rounded-full', toneClasses[tone].dot]"
        />
        <slot />
    </span>
</template>
