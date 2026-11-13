<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
</script>

<template>
    <div
        class="flex aspect-square size-8 items-center justify-center rounded-lg bg-sidebar-accent text-sidebar-foreground ring-1 ring-sidebar-border"
    >
        <AppLogoIcon class="size-5" />
    </div>
    <div class="ml-1 grid flex-1 text-left leading-tight">
        <span
            class="truncate font-display text-[13px] font-semibold tracking-tight"
        >
            Showroom
            <span
                class="ml-0.5 rounded bg-primary/15 px-1 py-px text-[10px] font-semibold text-primary"
            >
                AI
            </span>
        </span>
        <span class="truncate text-[11px] text-muted-foreground"
            >Dealership CRM</span
        >
    </div>
</template>
