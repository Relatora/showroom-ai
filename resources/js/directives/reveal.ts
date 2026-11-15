import type { Directive } from 'vue';

/**
 * v-reveal: fades and lifts an element in the first time it scrolls into view.
 *
 *     <section v-reveal>…</section>
 *     <li v-reveal="120">…</li>      (a delay in ms, for staggering a list)
 *
 * The styles live in app.css under [data-reveal]. The element starts hidden only once this has
 * run, so with JavaScript off (or before hydration) everything is simply visible. A single
 * shared IntersectionObserver watches every element, and each one is unobserved after it
 * appears: there's no reason to keep paying for it.
 */
let observer: IntersectionObserver | null = null;

function shared(): IntersectionObserver {
    observer ??= new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting) {
                    entry.target.setAttribute('data-reveal', 'shown');
                    observer?.unobserve(entry.target);
                }
            }
        },
        // Trigger a little before the element is fully on screen, so it's already moving when
        // the eye gets there.
        { rootMargin: '0px 0px -10% 0px', threshold: 0.1 },
    );

    return observer;
}

export const vReveal: Directive<HTMLElement, number | undefined> = {
    mounted(el, binding) {
        if (!('IntersectionObserver' in window)) {
            return;
        }

        el.setAttribute('data-reveal', 'hidden');
        el.style.setProperty('--reveal-delay', `${binding.value ?? 0}ms`);
        shared().observe(el);
    },
    beforeUnmount(el) {
        observer?.unobserve(el);
    },
};
