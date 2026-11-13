<script setup lang="ts">
import { Search } from '@lucide/vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import ThemeToggle from '@/components/ThemeToggle.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const emit = defineEmits<{
    openPalette: [];
}>();

// Show the right shortcut for the platform. It's a small thing, but ⌘ on Windows looks broken.
const isMac =
    typeof navigator !== 'undefined' &&
    /Mac|iPhone|iPad/.test(navigator.userAgent);
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-4 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex min-w-0 flex-1 items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>

        <button
            type="button"
            class="group flex h-9 items-center gap-2 rounded-full border bg-background/60 pr-2 pl-3 text-sm text-muted-foreground transition-colors hover:border-primary/40 hover:text-foreground sm:w-64"
            @click="emit('openPalette')"
        >
            <Search class="size-4" />
            <span class="hidden flex-1 text-left sm:inline"
                >Search or jump to…</span
            >
            <kbd
                class="hidden rounded-md border bg-muted px-1.5 py-0.5 font-mono text-[10px] transition-colors group-hover:border-primary/30 sm:inline"
            >
                {{ isMac ? '⌘' : 'Ctrl' }} K
            </kbd>
        </button>

        <ThemeToggle />
    </header>
</template>
