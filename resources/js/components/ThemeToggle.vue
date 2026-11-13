<script setup lang="ts">
import { Moon, Sun } from '@lucide/vue';
import { computed } from 'vue';
import { useThemeTransition } from '@/composables/useThemeTransition';
import { cn } from '@/lib/utils';

const { class: className } = defineProps<{ class?: string }>();

const { resolvedAppearance, toggle } = useThemeTransition();

const isDark = computed(() => resolvedAppearance.value === 'dark');
const label = computed(() =>
    isDark.value ? 'Switch to light mode' : 'Switch to dark mode',
);
</script>

<template>
    <button
        type="button"
        :aria-label="label"
        :title="label"
        :class="
            cn(
                'relative inline-flex size-9 items-center justify-center overflow-hidden rounded-full text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                className,
            )
        "
        @click="toggle($event)"
    >
        <!--
            The two icons swap with a little "sunrise" rotation. mode="out-in" makes sure we never
            see both at once, which looked muddy mid-transition.
        -->
        <Transition
            mode="out-in"
            enter-active-class="transition duration-300 ease-[var(--ease-spring)]"
            enter-from-class="-rotate-90 scale-50 opacity-0"
            leave-active-class="transition duration-150 ease-in"
            leave-to-class="rotate-90 scale-50 opacity-0"
        >
            <Moon v-if="isDark" key="moon" class="size-[18px]" />
            <Sun v-else key="sun" class="size-[18px]" />
        </Transition>
    </button>
</template>
