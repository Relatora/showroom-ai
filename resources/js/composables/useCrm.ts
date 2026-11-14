import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { CrmOptions, Option, Tone } from '@/types/crm';

/**
 * Typed access to the enum options shared by HandleInertiaRequests, plus a couple of helpers
 * for turning a raw value like "test_drive" into its label and colour.
 */
export function useCrm() {
    const page = usePage();
    const options = computed(() => page.props.crm as CrmOptions);

    function find(list: keyof CrmOptions, value: string): Option | undefined {
        return options.value?.[list].find((option) => option.value === value);
    }

    return {
        options,
        label: (list: keyof CrmOptions, value: string): string =>
            find(list, value)?.label ?? value,
        tone: (list: keyof CrmOptions, value: string): Tone =>
            find(list, value)?.tone ?? 'muted',
    };
}

/**
 * Tailwind classes for each tone. Written out in full (not built from strings) so Tailwind's
 * scanner can see them and keep them in the CSS bundle.
 */
export const toneClasses: Record<
    Tone,
    { badge: string; dot: string; bar: string }
> = {
    primary: {
        badge: 'bg-primary/12 text-primary ring-primary/25',
        dot: 'bg-primary',
        bar: 'bg-primary',
    },
    info: {
        badge: 'bg-info/12 text-info ring-info/25',
        dot: 'bg-info',
        bar: 'bg-info',
    },
    success: {
        badge: 'bg-success/12 text-success ring-success/25',
        dot: 'bg-success',
        bar: 'bg-success',
    },
    warning: {
        badge: 'bg-warning/15 text-warning-foreground ring-warning/30',
        dot: 'bg-warning',
        bar: 'bg-warning',
    },
    destructive: {
        badge: 'bg-destructive/12 text-destructive-foreground ring-destructive/25',
        dot: 'bg-destructive-foreground',
        bar: 'bg-destructive-foreground',
    },
    muted: {
        badge: 'bg-muted text-muted-foreground ring-border',
        dot: 'bg-muted-foreground',
        bar: 'bg-muted-foreground',
    },
};
