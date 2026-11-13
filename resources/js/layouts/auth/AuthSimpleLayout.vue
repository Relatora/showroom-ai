<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import ThemeToggle from '@/components/ThemeToggle.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="relative flex min-h-svh flex-col items-center justify-center overflow-hidden bg-showroom p-6 md:p-10"
    >
        <!-- A soft accent glow drifting behind the card, like showroom lights on paintwork. -->
        <div
            aria-hidden="true"
            class="pointer-events-none absolute top-1/3 left-1/2 size-[36rem] -translate-x-1/2 -translate-y-1/2 animate-float rounded-full bg-primary/10 blur-3xl"
        />

        <ThemeToggle class="absolute top-4 right-4" />

        <div class="relative w-full max-w-sm stagger">
            <div style="--i: 0" class="mb-8 flex flex-col items-center gap-5">
                <Link
                    :href="home()"
                    class="group flex flex-col items-center gap-3"
                >
                    <span
                        class="flex size-14 items-center justify-center rounded-2xl border bg-card shadow-glow transition-transform duration-500 ease-[var(--ease-spring)] group-hover:scale-105 group-hover:-rotate-6"
                    >
                        <AppLogoIcon class="size-8" />
                    </span>
                    <span class="sr-only">Showroom AI home</span>
                </Link>
                <div class="space-y-2 text-center">
                    <h1
                        class="font-display text-2xl font-semibold tracking-tight"
                    >
                        {{ title }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{ description }}
                    </p>
                </div>
            </div>

            <div
                style="--i: 1"
                class="rounded-2xl border bg-card/80 p-6 shadow-xl backdrop-blur-md sm:p-8"
            >
                <slot />
            </div>
        </div>
    </div>
</template>
