<script setup lang="ts">
import VehicleArt from '@/components/crm/VehicleArt.vue';

/**
 * The hero backdrop: a stretch of highway with traffic going by.
 *
 * Each car drives the full width on a loop, and a negative animation delay starts it somewhere
 * mid-trip, so the road is never empty on load. With reduced motion the cars don't drive at
 * all. They park at fixed spots instead (--park), which is why that isn't left to the global
 * "finish instantly" rule: finishing would leave them off the edge of the screen.
 */
// Everyone in a lane drives at the lane's speed, spaced evenly through the loop, so nobody
// drives through the car in front. The far lane is a little slower, which reads as depth.
const lanes = [
    {
        duration: 17,
        cars: [
            ['suv', '#2f6b55', '8%'],
            ['truck', '#8a2f2b', '52%'],
        ],
    },
    {
        duration: 12,
        cars: [
            ['sedan', '#b8bec4', '30%'],
            ['hatchback', '#2c5d8f', '68%'],
            ['coupe', '#d9a62e', '88%'],
        ],
    },
];

const traffic = lanes.flatMap((lane, index) =>
    lane.cars.map(([body, color, park], i) => ({
        body,
        color,
        park,
        lane: index,
        duration: lane.duration,
        delay: -(lane.duration / lane.cars.length) * i,
    })),
);
</script>

<template>
    <div
        class="pointer-events-none absolute inset-x-0 bottom-0 h-44 overflow-hidden sm:h-52"
        aria-hidden="true"
    >
        <!-- Asphalt with a soft fade into the page above it. -->
        <div
            class="absolute inset-x-0 bottom-0 h-28 bg-[oklch(0.3_0.01_240)] sm:h-32 dark:bg-[oklch(0.17_0.01_240)]"
        >
            <div
                class="absolute inset-x-0 top-0 h-1 bg-[oklch(0.92_0.01_90)] opacity-70"
            />
            <!-- Centre line: a dashed stripe that scrolls, so the road moves even between cars. -->
            <div class="road-dashes absolute inset-x-0 top-[52%] h-1" />
            <div
                class="absolute inset-x-0 bottom-0 h-1 bg-[oklch(0.92_0.01_90)] opacity-70"
            />
        </div>

        <div
            v-for="car in traffic"
            :key="car.body"
            class="car absolute w-28 sm:w-36"
            :class="
                car.lane === 0
                    ? 'bottom-[4.4rem] sm:bottom-[5.2rem]'
                    : 'bottom-3 z-10 sm:bottom-4'
            "
            :style="{
                '--duration': `${car.duration}s`,
                '--delay': `${car.delay}s`,
                '--park': car.park,
            }"
        >
            <VehicleArt :body-style="car.body" :color="car.color" />
        </div>
    </div>
</template>

<style scoped>
.road-dashes {
    background: repeating-linear-gradient(
        90deg,
        oklch(0.85 0.15 85) 0 2.5rem,
        transparent 2.5rem 5rem
    );
    background-size: 5rem 100%;
    animation: dashes 0.9s linear infinite;
}

.car {
    left: 0;
    animation: drive var(--duration) linear var(--delay) infinite;
}

@keyframes dashes {
    to {
        background-position: -5rem 0;
    }
}

@keyframes drive {
    from {
        transform: translateX(-12rem);
    }
    to {
        transform: translateX(calc(100vw + 2rem));
    }
}

@media (prefers-reduced-motion: reduce) {
    .road-dashes {
        animation: none;
    }

    .car {
        animation: none;
        left: var(--park);
    }
}
</style>
