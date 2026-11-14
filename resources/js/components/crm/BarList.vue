<script setup lang="ts">
import { computed, ref } from 'vue';

/**
 * A horizontal bar chart that is also a real <table>.
 *
 * Every chart on the dashboard is a single series, so one brand colour does the job and each bar
 * carries its value as a direct label. Because the markup is a table, screen readers and
 * forced-colours mode get the numbers without a separate "view as table" switch.
 *
 * Bars are anchored at the left with a rounded data end, sized against the largest row, and
 * each row highlights on hover with a short detail line.
 */
export type BarRow = {
    key: string;
    label: string;
    value: number;
    display: string;
    detail?: string;
};

const { rows, caption } = defineProps<{
    rows: BarRow[];
    caption: string;
}>();

const max = computed(() => Math.max(1, ...rows.map((row) => row.value)));
const hovered = ref<string | null>(null);
</script>

<template>
    <table class="w-full border-separate border-spacing-y-1.5 text-sm">
        <caption class="sr-only">
            {{
                caption
            }}
        </caption>
        <tbody>
            <tr
                v-for="row in rows"
                :key="row.key"
                class="group"
                @mouseenter="hovered = row.key"
                @mouseleave="hovered = null"
            >
                <th
                    scope="row"
                    class="w-32 pr-3 text-left align-middle font-medium whitespace-nowrap text-muted-foreground group-hover:text-foreground"
                >
                    {{ row.label }}
                </th>
                <td class="relative align-middle">
                    <div class="flex items-center gap-2">
                        <!-- The hit area is the whole row, much bigger than the bar itself. -->
                        <span
                            class="block h-2.5 rounded-r-[4px] bg-chart-1 transition-[width,opacity] duration-500 ease-[var(--ease-out-expo)]"
                            :class="
                                hovered && hovered !== row.key
                                    ? 'opacity-40'
                                    : ''
                            "
                            :style="{
                                width: `max(${(row.value / max) * 100}%, 2px)`,
                            }"
                            aria-hidden="true"
                        />
                        <span
                            class="shrink-0 text-xs font-semibold tabular-nums"
                            >{{ row.display }}</span
                        >
                    </div>
                    <span
                        v-if="row.detail && hovered === row.key"
                        role="tooltip"
                        class="absolute top-full left-0 z-10 mt-1 rounded-md border bg-popover px-2 py-1 text-xs whitespace-nowrap text-popover-foreground shadow-md"
                    >
                        {{ row.detail }}
                    </span>
                </td>
            </tr>
        </tbody>
    </table>
</template>
