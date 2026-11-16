<script setup lang="ts">
import { onBeforeUnmount, onMounted, useTemplateRef } from 'vue';

/**
 * The hero backdrop: a four-lane highway running into the city, seen from an overpass.
 *
 * It's drawn on a canvas with a real pinhole-camera projection rather than CSS 3D, because
 * that's what makes the road narrow to a true vanishing point at the skyline. Everything is
 * placed in metres (lanes are 3.6 m, cars 1.8 m wide) and projected with
 *
 *     screenX = centre + f · x / z        screenY = horizon + f · cameraHeight / z
 *
 * so sizes, spacing and speeds shrink with distance exactly as they would in a photo.
 *
 * The theme decides the time of day. Light mode is daytime: blue sky, hazy towers and real
 * cars. Dark mode is night, shot like a long exposure: headlights coming toward you,
 * taillights pulling away, streetlights and lit windows.
 *
 * Costs: it pauses when scrolled out of view or when the tab is hidden, and with reduced
 * motion it draws a single still frame.
 */

const canvas = useTemplateRef<HTMLCanvasElement>('canvas');

// --- The world, in metres ---------------------------------------------------------------

const LANE = 3.6;
const ROAD_HALF = LANE * 2 + 1.8; // two lanes each way plus a shoulder
const Z_FAR = 380; // cars beyond this are sub-pixel anyway, and a shorter loop keeps the foreground busy
const LAMP_SPACING = 45;

// Seeded, so every load (and every screenshot) has the same traffic and skyline.
let seed = 11;
const random = () => ((seed = (seed * 16807) % 2147483647) - 1) / 2147483646;

type Car = { x: number; z0: number; colour: string };
type Lane = { x: number; speed: number; dir: 1 | -1; cars: Car[] };

const PAINT = [
    '#f4f5f6',
    '#c9ccd0',
    '#2b2f33',
    '#6b7178',
    '#9e1f1f',
    '#1f4e8c',
    '#d8d2c4',
    '#3d5a40',
];

// Driving on the right: oncoming traffic on the left (coming toward the camera), ours on the right.
const lanes: Lane[] = [
    { x: -LANE * 1.5, speed: 31, dir: -1, cars: [] },
    { x: -LANE * 0.5, speed: 26, dir: -1, cars: [] },
    { x: LANE * 0.5, speed: 24, dir: 1, cars: [] },
    { x: LANE * 1.5, speed: 29, dir: 1, cars: [] },
];

for (const lane of lanes) {
    let z = random() * 40;

    while (z < Z_FAR) {
        lane.cars.push({
            x: lane.x + (random() - 0.5) * 0.5,
            z0: z,
            colour: PAINT[Math.floor(random() * PAINT.length)],
        });
        // Real gaps vary a lot: a pair of cars close together, then nothing for a while.
        z += 16 + random() * (random() > 0.7 ? 110 : 40);
    }
}

// Skyline: two layers, the far one paler. Heights are fractions of the sky's height.
type Tower = { x: number; w: number; h: number; lit: number[] };

function skyline(count: number, minH: number, maxH: number): Tower[] {
    const towers: Tower[] = [];
    let x = -0.02;

    while (x < 1.02) {
        const w = 0.012 + random() * 0.035;
        // Taller towers cluster toward the middle, where the road is headed.
        const centre = 1 - Math.min(1, Math.abs(x - 0.5) * 2);
        const h = minH + random() * (maxH - minH) * (0.35 + centre * 0.9);
        const lit = Array.from({ length: count }, () => random());
        towers.push({ x, w, h, lit });
        x += w + random() * 0.006;
    }

    return towers;
}

const farTowers = skyline(0, 0.12, 0.45);
const nearTowers = skyline(24, 0.08, 0.7);

// --- Drawing ----------------------------------------------------------------------------

type View = {
    ctx: CanvasRenderingContext2D;
    w: number;
    h: number;
    horizon: number;
    f: number;
    camH: number;
    zNear: number;
    night: boolean;
};

function project(v: View, x: number, z: number, height = 0): [number, number] {
    return [v.w / 2 + (v.f * x) / z, v.horizon + (v.f * (v.camH - height)) / z];
}

// A flat strip on the road between two depths, e.g. a lane line or a dash.
function strip(
    v: View,
    x1: number,
    x2: number,
    za: number,
    zb: number,
    fill: string | CanvasGradient,
) {
    const [ax1, ay] = project(v, x1, za);
    const [ax2] = project(v, x2, za);
    const [bx1, by] = project(v, x1, zb);
    const [bx2] = project(v, x2, zb);
    v.ctx.fillStyle = fill;
    v.ctx.beginPath();
    v.ctx.moveTo(ax1, ay);
    v.ctx.lineTo(ax2, ay);
    v.ctx.lineTo(bx2, by);
    v.ctx.lineTo(bx1, by);
    v.ctx.fill();
}

function drawSky(v: View) {
    const { ctx, w, horizon, night } = v;
    const sky = ctx.createLinearGradient(0, 0, 0, horizon);

    if (night) {
        sky.addColorStop(0, '#0d1418');
        sky.addColorStop(1, '#15302b');
    } else {
        sky.addColorStop(0, '#9fc3e0');
        sky.addColorStop(1, '#e3edf2');
    }

    ctx.fillStyle = sky;
    ctx.fillRect(0, 0, w, horizon + 1);

    // The sun (day) or the city's own glow (night), low over the road.
    const glow = ctx.createRadialGradient(
        w * 0.5,
        horizon,
        0,
        w * 0.5,
        horizon,
        w * 0.45,
    );
    glow.addColorStop(
        0,
        night ? 'rgba(80,200,150,0.30)' : 'rgba(255,244,214,0.75)',
    );
    glow.addColorStop(1, 'rgba(0,0,0,0)');
    ctx.fillStyle = glow;
    ctx.fillRect(0, 0, w, horizon);
}

function drawTowers(v: View, towers: Tower[], far: boolean, t: number) {
    const { ctx, w, horizon, night } = v;
    const skyH = horizon;

    for (const tower of towers) {
        const x = tower.x * w;
        const tw = tower.w * w;
        const th = tower.h * skyH;
        const top = horizon - th;

        if (night) {
            ctx.fillStyle = far ? '#14211f' : '#0b1112';
        } else {
            // Haze: far towers almost melt into the sky.
            ctx.fillStyle = far ? '#b7c9d6' : '#8ea4b5';
        }
        ctx.fillRect(x, top, tw, th);

        if (far) {
            continue;
        }

        if (night) {
            // A grid of windows, a few of them on, one or two slowly switching.
            const cols = Math.max(1, Math.floor(tw / 4));
            const rows = Math.max(1, Math.floor(th / 5));
            ctx.fillStyle = '#f2d58a';

            for (let i = 0; i < tower.lit.length; i++) {
                const on =
                    (tower.lit[i] + Math.floor(t / 4 + i) * 0.137) % 1 > 0.55;

                if (on) {
                    const cx = x + 1.5 + ((i * 7) % cols) * 4;
                    const cy = top + 3 + ((i * 3) % rows) * 5;

                    if (cx < x + tw - 2 && cy < horizon - 2) {
                        ctx.fillRect(cx, cy, 1.6, 1.6);
                    }
                }
            }
        } else {
            // Glass: a lighter band down the sunlit side.
            ctx.fillStyle = 'rgba(255,255,255,0.18)';
            ctx.fillRect(x + tw * 0.62, top, tw * 0.18, th);
        }
    }
}

function drawGround(v: View) {
    const { ctx, w, h, horizon, night } = v;
    const ground = ctx.createLinearGradient(0, horizon, 0, h);

    if (night) {
        ground.addColorStop(0, '#0d1312');
        ground.addColorStop(1, '#070a0b');
    } else {
        ground.addColorStop(0, '#a9b9a3');
        ground.addColorStop(1, '#6f8467');
    }

    ctx.fillStyle = ground;
    ctx.fillRect(0, horizon, w, h - horizon);

    // Asphalt, a shade lighter in the distance the way haze makes it.
    const asphalt = ctx.createLinearGradient(0, horizon, 0, h);
    asphalt.addColorStop(0, night ? '#1a1f22' : '#9aa1a6');
    asphalt.addColorStop(1, night ? '#0f1214' : '#4d5459');
    strip(v, -ROAD_HALF, ROAD_HALF, v.zNear * 0.5, Z_FAR, asphalt);

    const white = night ? 'rgba(220,225,220,0.55)' : 'rgba(250,250,245,0.95)';
    const yellow = night ? 'rgba(230,190,80,0.75)' : '#e2b23a';

    // Edge lines and the double yellow centre line.
    strip(v, -LANE * 2 - 0.15, -LANE * 2, v.zNear * 0.5, Z_FAR, white);
    strip(v, LANE * 2, LANE * 2 + 0.15, v.zNear * 0.5, Z_FAR, white);
    strip(v, -0.22, -0.1, v.zNear * 0.5, Z_FAR, yellow);
    strip(v, 0.1, 0.22, v.zNear * 0.5, Z_FAR, yellow);

    // Lane dashes: 3 m painted, 9 m gap, the North American pattern.
    for (const x of [-LANE, LANE]) {
        for (let z = v.zNear * 0.5; z < 320; z += 12) {
            strip(v, x - 0.07, x + 0.07, z, z + 3, white);
        }
    }
}

function drawLamps(v: View) {
    const { ctx, night } = v;

    for (const side of [-1, 1]) {
        for (let z = LAMP_SPACING / 2; z < 600; z += LAMP_SPACING) {
            const x = side * (ROAD_HALF + 1);
            const [gx, gy] = project(v, x, z);
            const [tx, ty] = project(v, x, z, 10);
            const [ax] = project(v, x - side * 2.2, z, 10);

            ctx.strokeStyle = night
                ? 'rgba(90,100,105,0.6)'
                : 'rgba(80,88,95,0.75)';
            ctx.lineWidth = Math.max(0.6, (v.f * 0.22) / z);
            ctx.beginPath();
            ctx.moveTo(gx, gy);
            ctx.lineTo(tx, ty);
            ctx.lineTo(ax, ty);
            ctx.stroke();

            if (night) {
                const r = Math.max(2, (v.f * 3) / z);
                const glow = ctx.createRadialGradient(ax, ty, 0, ax, ty, r);
                glow.addColorStop(0, 'rgba(255,220,150,0.95)');
                glow.addColorStop(0.2, 'rgba(255,200,120,0.35)');
                glow.addColorStop(1, 'rgba(255,200,120,0)');
                ctx.fillStyle = glow;
                ctx.globalCompositeOperation = 'lighter';
                ctx.fillRect(ax - r, ty - r, r * 2, r * 2);
                ctx.globalCompositeOperation = 'source-over';
            }
        }
    }
}

type Placed = { car: Car; lane: Lane; z: number };

function placeCars(v: View, t: number): Placed[] {
    const zMin = v.zNear * 0.55;
    const span = Z_FAR - zMin;
    const placed: Placed[] = [];

    for (const lane of lanes) {
        for (const car of lane.cars) {
            const travelled = lane.dir * lane.speed * t;
            const z = zMin + ((((car.z0 + travelled) % span) + span) % span);
            placed.push({ car, lane, z });
        }
    }

    // Painter's algorithm: far things first.
    return placed.sort((a, b) => b.z - a.z);
}

// Daytime: a simple car seen from the front or back, sized by distance.
function drawCar(v: View, p: Placed) {
    const { ctx } = v;
    const [sx, sy] = project(v, p.car.x, p.z);
    const bw = (v.f * 1.8) / p.z;
    const bh = (v.f * 1.45) / p.z;

    if (bw < 1.2) {
        ctx.fillStyle = p.car.colour;
        ctx.fillRect(
            sx - bw / 2,
            sy - bh,
            Math.max(bw, 0.8),
            Math.max(bh, 0.8),
        );

        return;
    }

    ctx.fillStyle = 'rgba(0,0,0,0.28)';
    ctx.beginPath();
    ctx.ellipse(sx, sy, bw * 0.6, bh * 0.1, 0, 0, Math.PI * 2);
    ctx.fill();

    // Wheels, body, then the cabin with its glass.
    ctx.fillStyle = '#16191b';
    ctx.fillRect(sx - bw * 0.46, sy - bh * 0.18, bw * 0.16, bh * 0.18);
    ctx.fillRect(sx + bw * 0.3, sy - bh * 0.18, bw * 0.16, bh * 0.18);

    ctx.fillStyle = p.car.colour;
    ctx.beginPath();
    ctx.roundRect(sx - bw / 2, sy - bh * 0.62, bw, bh * 0.5, bw * 0.08);
    ctx.fill();
    ctx.beginPath();
    ctx.moveTo(sx - bw * 0.42, sy - bh * 0.6);
    ctx.lineTo(sx - bw * 0.32, sy - bh);
    ctx.lineTo(sx + bw * 0.32, sy - bh);
    ctx.lineTo(sx + bw * 0.42, sy - bh * 0.6);
    ctx.fill();

    ctx.fillStyle = 'rgba(40,52,62,0.85)';
    ctx.beginPath();
    ctx.moveTo(sx - bw * 0.36, sy - bh * 0.63);
    ctx.lineTo(sx - bw * 0.29, sy - bh * 0.93);
    ctx.lineTo(sx + bw * 0.29, sy - bh * 0.93);
    ctx.lineTo(sx + bw * 0.36, sy - bh * 0.63);
    ctx.fill();

    // Headlights toward us, taillights going away.
    ctx.fillStyle = p.lane.dir === -1 ? '#fff7d6' : '#c4252a';
    ctx.fillRect(sx - bw * 0.46, sy - bh * 0.5, bw * 0.16, bh * 0.09);
    ctx.fillRect(sx + bw * 0.3, sy - bh * 0.5, bw * 0.16, bh * 0.09);
}

// Night: only the lights, each dragging a long-exposure trail along its own path.
function drawTrail(v: View, p: Placed) {
    const { ctx } = v;
    const oncoming = p.lane.dir === -1;
    // Where the car was 0.9 s ago: the exposure.
    const zTail = p.z - p.lane.dir * p.lane.speed * 0.9;

    if (zTail <= v.zNear * 0.3) {
        return;
    }

    const colour = oncoming ? [255, 246, 220] : [235, 40, 40];

    for (const offset of [-0.7, 0.7]) {
        const x = p.car.x + offset;
        const [hx, hy] = project(v, x, p.z, 0.7);
        const [tx, ty] = project(v, x, zTail, 0.7);
        const hw = Math.max(0.5, (v.f * 0.1) / p.z);
        const tw = Math.max(0.3, (v.f * 0.1) / zTail);

        const g = ctx.createLinearGradient(hx, hy, tx, ty);
        g.addColorStop(0, `rgba(${colour},0.95)`);
        g.addColorStop(0.45, `rgba(${colour},0.3)`);
        g.addColorStop(1, `rgba(${colour},0)`);
        ctx.fillStyle = g;
        ctx.beginPath();
        ctx.moveTo(hx - hw, hy);
        ctx.lineTo(hx + hw, hy);
        ctx.lineTo(tx + tw, ty);
        ctx.lineTo(tx - tw, ty);
        ctx.fill();

        const r = Math.max(1.2, (v.f * 0.35) / p.z);
        const glow = ctx.createRadialGradient(hx, hy, 0, hx, hy, r);
        glow.addColorStop(0, `rgba(${colour},0.9)`);
        glow.addColorStop(1, `rgba(${colour},0)`);
        ctx.fillStyle = glow;
        ctx.fillRect(hx - r, hy - r, r * 2, r * 2);
    }
}

function drawHaze(v: View) {
    const { ctx, w, h, horizon, night } = v;
    const band = (h - horizon) * 0.35;
    const haze = ctx.createLinearGradient(0, horizon - 6, 0, horizon + band);
    haze.addColorStop(
        0,
        night ? 'rgba(40,90,75,0.55)' : 'rgba(228,236,240,0.6)',
    );
    haze.addColorStop(1, 'rgba(0,0,0,0)');
    ctx.fillStyle = haze;
    ctx.fillRect(0, horizon - 6, w, band + 6);
}

function render(
    ctx: CanvasRenderingContext2D,
    w: number,
    h: number,
    t: number,
    night: boolean,
) {
    const horizon = h * 0.46;
    const ground = h - horizon;
    const f = h;
    // Pick the camera height so the road fills about three quarters of the width at the bottom.
    const bottomWidth = Math.min(Math.max(w * 0.78, 340), 1300);
    const camH = (ROAD_HALF * 2 * ground) / bottomWidth;
    const v: View = {
        ctx,
        w,
        h,
        horizon,
        f,
        camH,
        zNear: (f * camH) / ground,
        night,
    };

    ctx.clearRect(0, 0, w, h);
    drawSky(v);
    drawTowers(v, farTowers, true, t);
    drawTowers(v, nearTowers, false, t);
    drawGround(v);
    drawHaze(v);
    drawLamps(v);

    const cars = placeCars(v, t);

    if (night) {
        ctx.globalCompositeOperation = 'lighter';
        cars.forEach((p) => drawTrail(v, p));
        ctx.globalCompositeOperation = 'source-over';
    } else {
        cars.forEach((p) => drawCar(v, p));
    }
}

// --- Lifecycle --------------------------------------------------------------------------

let frame = 0;
let visible = true;
let cleanup: (() => void) | undefined;

onMounted(() => {
    const el = canvas.value;
    const ctx = el?.getContext('2d');

    if (!el || !ctx) {
        return;
    }

    const still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const start = performance.now();
    let night = document.documentElement.classList.contains('dark');

    const draw = (now: number) => {
        const dpr = Math.min(window.devicePixelRatio || 1, 2);
        const w = el.clientWidth;
        const h = el.clientHeight;

        if (
            el.width !== Math.round(w * dpr) ||
            el.height !== Math.round(h * dpr)
        ) {
            el.width = Math.round(w * dpr);
            el.height = Math.round(h * dpr);
        }

        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        // A still frame is taken a few seconds in, so the road is already busy.
        render(ctx, w, h, still ? 6 : 6 + (now - start) / 1000, night);
    };

    const loop = (now: number) => {
        draw(now);
        frame = visible && !still ? requestAnimationFrame(loop) : 0;
    };

    const resume = () => {
        if (!frame) {
            frame = requestAnimationFrame(loop);
        }
    };

    // Follow the theme switch: the time of day changes with it.
    const themeWatch = new MutationObserver(() => {
        night = document.documentElement.classList.contains('dark');
        draw(performance.now());
    });
    themeWatch.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class'],
    });

    const onScreen = new IntersectionObserver(([entry]) => {
        visible = entry.isIntersecting;

        if (visible) {
            resume();
        }
    });
    onScreen.observe(el);

    const resized = new ResizeObserver(() => draw(performance.now()));
    resized.observe(el);

    resume();

    cleanup = () => {
        cancelAnimationFrame(frame);
        themeWatch.disconnect();
        onScreen.disconnect();
        resized.disconnect();
    };
});

onBeforeUnmount(() => cleanup?.());
</script>

<template>
    <div
        class="scene pointer-events-none absolute inset-x-0 bottom-0 h-56 overflow-hidden sm:h-80"
        aria-hidden="true"
    >
        <canvas ref="canvas" class="block size-full" />
    </div>
</template>

<style scoped>
/* Fades the scene in from the page above, in either theme. */
.scene {
    mask-image: linear-gradient(to bottom, transparent 0%, black 28%);
}
</style>
