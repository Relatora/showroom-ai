<script setup lang="ts">
/**
 * The hero backdrop: a highway at night, shot like a long exposure.
 *
 * The road is a flat plane tipped back in 3D (CSS perspective), so lane markings and traffic
 * shrink toward the horizon on their own. Traffic is just light: white headlight streaks
 * coming toward you on the left, red taillights pulling away on the right. On the horizon,
 * a skyline with a few lit windows and a green glow, the brand colour.
 *
 * Everything moves with CSS animations, so the browser runs it off the main thread. With
 * reduced motion the streaks stop where they are, which still reads as a photo of traffic.
 */

type Streak = {
    lane: number; // centre of the lane, as a % of the plane's width
    duration: number; // seconds to cover the whole plane
    offset: number; // 0..1, how far along the trip it starts (and where it parks)
    length: number; // trail length in px, before perspective
};

// A tiny seeded generator keeps the traffic and skyline the same on every load, so
// screenshots are repeatable and nothing jumps between renders.
let seed = 7;
const random = () => ((seed = (seed * 16807) % 2147483647) - 1) / 2147483646;

function traffic(
    lanes: number[],
    count: number,
    speed: [number, number],
): Streak[] {
    return Array.from({ length: count }, (_, i) => ({
        lane: lanes[i % lanes.length] + (random() - 0.5) * 0.8,
        duration: speed[0] + random() * (speed[1] - speed[0]),
        offset: random(),
        length: 200 + random() * 260,
    }));
}

// Two lanes each way, driving on the right.
const oncoming = traffic([42.5, 46.5], 14, [2.4, 4.2]);
const outgoing = traffic([53.5, 57.5], 14, [3.2, 5.6]);

// Skyline: a run of buildings of varying width and height along the horizon.
const buildings = (() => {
    const out: { x: number; w: number; h: number }[] = [];
    let x = 0;

    while (x < 1600) {
        const w = 18 + random() * 46;
        const h = 14 + random() * (random() > 0.85 ? 70 : 34);
        out.push({ x, w, h });
        x += w + random() * 6;
    }

    return out;
})();

// A sprinkle of lit windows, only on the taller buildings.
const windows = buildings.flatMap((b) =>
    b.h < 30
        ? []
        : Array.from({ length: Math.floor(b.h / 9) }, () => ({
              x: b.x + 3 + random() * (b.w - 6),
              y: 100 - b.h + 4 + random() * (b.h - 8),
              on: random() > 0.55,
          })).filter((w) => w.on),
);
</script>

<template>
    <div
        class="scene pointer-events-none absolute inset-x-0 bottom-0 h-56 overflow-hidden sm:h-72"
        aria-hidden="true"
    >
        <!-- Night sky, fading up into the page. -->
        <div class="sky absolute inset-0" />

        <!-- Skyline on the horizon. Stretches with the width; the height stays put. -->
        <svg
            class="absolute inset-x-0 top-[22%] h-[28%] w-full"
            viewBox="0 0 1600 100"
            preserveAspectRatio="none"
        >
            <rect
                v-for="(b, i) in buildings"
                :key="i"
                :x="b.x"
                :y="100 - b.h"
                :width="b.w"
                :height="b.h"
                class="fill-[oklch(0.17_0.02_230)]"
            />
            <rect
                v-for="(w, i) in windows"
                :key="`w${i}`"
                :x="w.x"
                :y="w.y"
                width="2.5"
                height="2"
                class="window fill-[oklch(0.88_0.12_85)]"
                :style="{ animationDelay: `${(i % 9) * 0.7}s` }"
            />
        </svg>

        <!-- The road: a plane tipped back toward the horizon. -->
        <div class="road-wrap absolute inset-x-0 top-[50%] bottom-0">
            <div class="road">
                <div class="edge left-[38%]" />
                <div class="edge left-[62%]" />
                <div class="median left-[50%]" />
                <div class="dashes left-[44.5%]" />
                <div class="dashes left-[55.5%]" />
                <div class="lamps left-[35%]" />
                <div class="lamps left-[65%]" />

                <span
                    v-for="(s, i) in oncoming"
                    :key="`in${i}`"
                    class="streak oncoming"
                    :style="{
                        left: `${s.lane}%`,
                        height: `${s.length}px`,
                        '--duration': `${s.duration}s`,
                        '--offset': s.offset,
                    }"
                />
                <span
                    v-for="(s, i) in outgoing"
                    :key="`out${i}`"
                    class="streak outgoing"
                    :style="{
                        left: `${s.lane}%`,
                        height: `${s.length}px`,
                        '--duration': `${s.duration}s`,
                        '--offset': s.offset,
                    }"
                />
            </div>
        </div>

        <!-- The glow where the road meets the city. -->
        <div class="horizon-glow absolute inset-x-0 top-[38%] h-[26%]" />
    </div>
</template>

<style scoped>
.scene {
    /* Fades the whole scene in from the page above, in either theme. */
    mask-image: linear-gradient(to bottom, transparent 0%, black 30%);
}

.sky {
    background:
        radial-gradient(
            ellipse 60% 45% at 50% 50%,
            oklch(0.45 0.09 163 / 0.35),
            transparent 70%
        ),
        linear-gradient(
            to bottom,
            oklch(0.2 0.03 240),
            oklch(0.16 0.025 235) 50%,
            oklch(0.12 0.01 240)
        );
}

.window {
    animation: flicker 7s steps(1) infinite;
}

.road-wrap {
    perspective: 700px;
    perspective-origin: 50% 0%;
}

/* --plane is the road's length before it's tipped back. The streaks travel all of it. */
.road {
    --plane: 1800px;
    position: absolute;
    left: 50%;
    top: 0;
    width: 2600px;
    height: var(--plane);
    margin-left: -1300px;
    transform-origin: top center;
    transform: rotateX(80deg);
    background: linear-gradient(
        to bottom,
        oklch(0.13 0.01 240),
        oklch(0.17 0.012 240) 60%,
        oklch(0.2 0.012 240)
    );
}

.edge,
.median,
.dashes {
    position: absolute;
    top: 0;
    bottom: 0;
    width: 6px;
    margin-left: -3px;
}

.edge {
    background: oklch(0.85 0.01 90 / 0.55);
    box-shadow: 0 0 18px oklch(0.74 0.12 163 / 0.6);
}

.median {
    width: 8px;
    margin-left: -4px;
    background: repeating-linear-gradient(
        to right,
        oklch(0.85 0.15 85) 0 2px,
        transparent 2px 6px,
        oklch(0.85 0.15 85) 6px 8px
    );
    opacity: 0.8;
}

.dashes {
    background: repeating-linear-gradient(
        to bottom,
        oklch(0.92 0.01 90 / 0.7) 0 60px,
        transparent 60px 150px
    );
    background-size: 100% 150px;
    animation: dashes 0.5s linear infinite;
}

/* Streetlights just off the shoulder. They scroll with the lane dashes, which sells the speed. */
.lamps {
    position: absolute;
    top: 0;
    bottom: 0;
    width: 40px;
    margin-left: -20px;
    background: radial-gradient(
        circle at 50% 50%,
        oklch(0.95 0.08 85) 0 5px,
        oklch(0.85 0.12 80 / 0.35) 9px,
        transparent 20px
    );
    background-size: 40px 300px;
    animation: lamps 1s linear infinite;
}

.streak {
    position: absolute;
    top: 0;
    width: 7px;
    margin-left: -3.5px;
    border-radius: 999px;
    mix-blend-mode: screen;
    animation: var(--travel) var(--duration) linear infinite;
    /* A negative delay starts each one partway along, so the road is busy from the first frame. */
    animation-delay: calc(var(--duration) * var(--offset) * -1);
}

/* Coming toward you: bright headlight at the front (bottom), trail fading behind it. */
.oncoming {
    --travel: toward;
    background: linear-gradient(
        to top,
        oklch(0.99 0.02 90),
        oklch(0.95 0.05 90 / 0.6) 30%,
        transparent
    );
    box-shadow:
        0 0 12px oklch(0.95 0.05 90 / 0.8),
        0 0 30px oklch(0.9 0.08 90 / 0.4);
}

/* Pulling away: red taillight at the front (top), trail fading back toward you. */
.outgoing {
    --travel: away;
    background: linear-gradient(
        to bottom,
        oklch(0.7 0.24 27),
        oklch(0.6 0.22 27 / 0.6) 30%,
        transparent
    );
    box-shadow:
        0 0 12px oklch(0.65 0.24 27 / 0.9),
        0 0 30px oklch(0.6 0.22 27 / 0.45);
}

.horizon-glow {
    background: radial-gradient(
        ellipse 40% 50% at 50% 60%,
        oklch(0.8 0.12 163 / 0.25),
        transparent 70%
    );
    mix-blend-mode: screen;
}

/* On a phone the same road would fill the screen with two lanes, so shrink it to fit. */
@media (max-width: 640px) {
    .road {
        transform: rotateX(80deg) scale(0.5);
    }
}

@keyframes toward {
    from {
        transform: translateY(-20%);
    }
    to {
        transform: translateY(var(--plane));
    }
}

@keyframes away {
    from {
        transform: translateY(var(--plane));
    }
    to {
        transform: translateY(-20%);
    }
}

@keyframes dashes {
    to {
        background-position: 0 150px;
    }
}

@keyframes lamps {
    to {
        background-position: 0 300px;
    }
}

@keyframes flicker {
    0%,
    100% {
        opacity: 1;
    }
    47% {
        opacity: 0.35;
    }
    53% {
        opacity: 1;
    }
}

/*
 * Reduced motion: freeze the traffic mid-trip instead of letting the global rule fast-forward
 * it, which would bunch every streak at the end of its run.
 */
@media (prefers-reduced-motion: reduce) {
    .streak,
    .dashes,
    .lamps,
    .window {
        animation: none !important;
    }

    .oncoming {
        transform: translateY(calc(var(--plane) * var(--offset)));
    }

    .outgoing {
        transform: translateY(calc(var(--plane) * (1 - var(--offset))));
    }
}
</style>
