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
        class="relative flex min-h-svh flex-col items-center justify-center bg-background p-6 md:p-10"
    >
        <ThemeToggle class="absolute top-4 right-4" />

        <div class="w-full max-w-sm">
            <!-- Same highway sign as the app's page titles, so the brand starts at the front door. -->
            <div class="mb-6 sign px-6 pt-5 pb-6">
                <Link
                    :href="home()"
                    class="mb-4 flex items-center gap-2 text-sm font-bold"
                >
                    <span
                        class="flex size-7 items-center justify-center rounded-md bg-white text-neutral-900"
                    >
                        <AppLogoIcon class="size-5" />
                    </span>
                    Showroom AI
                </Link>
                <h1
                    class="text-2xl leading-tight font-extrabold tracking-tight"
                >
                    {{ title }}
                </h1>
                <p class="mt-1 text-sm font-medium text-white/85">
                    {{ description }}
                </p>
            </div>

            <div class="rounded-xl border bg-card p-6 sm:p-8">
                <slot />
            </div>
        </div>
    </div>
</template>
