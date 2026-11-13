import { useAppearance } from '@/composables/useAppearance';
import type { ResolvedAppearance } from '@/types';

/**
 * Switches light/dark with a circular "spotlight" reveal that grows out of whatever the user
 * clicked, using the View Transitions API.
 *
 * Browsers without the API (or users who asked for reduced motion) just get an instant switch,
 * so this is pure progressive enhancement: the theme always changes, the flourish is optional.
 */
export function useThemeTransition() {
    const { resolvedAppearance, updateAppearance } = useAppearance();

    function switchTo(
        next: ResolvedAppearance,
        origin?: MouseEvent | KeyboardEvent,
    ): void {
        const reduceMotion = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        ).matches;

        if (!document.startViewTransition || reduceMotion) {
            updateAppearance(next);

            return;
        }

        // Keyboard activation has no pointer position, so grow from the element's centre instead.
        const { x, y } = originPoint(origin);
        const radius = Math.hypot(
            Math.max(x, window.innerWidth - x),
            Math.max(y, window.innerHeight - y),
        );

        // Page swaps use a cross-fade on the root (see app.css). This flag swaps that out for the
        // circular reveal for the duration of this one transition.
        document.documentElement.dataset.themeTransition = '';

        const transition = document.startViewTransition(() =>
            updateAppearance(next),
        );

        void transition.ready.then(() => {
            document.documentElement.animate(
                {
                    clipPath: [
                        `circle(0px at ${x}px ${y}px)`,
                        `circle(${radius}px at ${x}px ${y}px)`,
                    ],
                },
                {
                    duration: 550,
                    easing: 'cubic-bezier(0.16, 1, 0.3, 1)',
                    pseudoElement: '::view-transition-new(root)',
                },
            );
        });

        void transition.finished.finally(() => {
            delete document.documentElement.dataset.themeTransition;
        });
    }

    function toggle(origin?: MouseEvent | KeyboardEvent): void {
        switchTo(
            resolvedAppearance.value === 'dark' ? 'light' : 'dark',
            origin,
        );
    }

    return { resolvedAppearance, switchTo, toggle };
}

function originPoint(event?: MouseEvent | KeyboardEvent): {
    x: number;
    y: number;
} {
    if (event instanceof MouseEvent && (event.clientX || event.clientY)) {
        return { x: event.clientX, y: event.clientY };
    }

    const target = event?.currentTarget;

    if (target instanceof HTMLElement) {
        const rect = target.getBoundingClientRect();

        return { x: rect.left + rect.width / 2, y: rect.top + rect.height / 2 };
    }

    return { x: window.innerWidth / 2, y: 0 };
}
