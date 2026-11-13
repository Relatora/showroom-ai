<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';
import {
    CornerDownLeft,
    LogOut,
    Moon,
    Palette,
    Search,
    ShieldCheck,
    Sun,
    UserRound,
} from '@lucide/vue';
import { useEventListener } from '@vueuse/core';
import {
    DialogContent,
    DialogOverlay,
    DialogPortal,
    DialogRoot,
    DialogTitle,
} from 'reka-ui';
import { computed, nextTick, ref, useId, useTemplateRef, watch } from 'vue';
import { useThemeTransition } from '@/composables/useThemeTransition';
import { mainNavigation } from '@/lib/navigation';
import { toUrl } from '@/lib/utils';
import { logout } from '@/routes';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';

/**
 * ⌘K / Ctrl+K command palette.
 *
 * Power users in a CRM live on the keyboard: jump to a section, flip the theme, and (as later
 * features land) open a lead or ask the assistant without touching the mouse. It's built on
 * Reka's Dialog for focus trapping and Escape handling, with the list and keyboard
 * navigation done by hand so we control exactly how it behaves.
 */

type Command = {
    id: string;
    group: 'Go to' | 'Settings' | 'Actions';
    label: string;
    hint?: string;
    icon: LucideIcon;
    keywords?: string;
    run: () => void;
};

const open = defineModel<boolean>('open', { default: false });

const query = ref('');
const activeIndex = ref(0);
const input = useTemplateRef<HTMLInputElement>('input');
const listId = useId();

const { resolvedAppearance, switchTo } = useThemeTransition();

const visit = (href: Parameters<typeof router.visit>[0]) => () =>
    router.visit(href);

const commands = computed<Command[]>(() => [
    ...mainNavigation.map((item) => ({
        id: `nav:${toUrl(item.href)}`,
        group: 'Go to' as const,
        label: item.title,
        icon: item.icon ?? Search,
        run: visit(item.href),
    })),
    {
        id: 'settings:profile',
        group: 'Settings',
        label: 'Profile',
        icon: UserRound,
        run: visit(editProfile()),
    },
    {
        id: 'settings:security',
        group: 'Settings',
        label: 'Security',
        keywords: 'password two factor 2fa',
        icon: ShieldCheck,
        run: visit(editSecurity()),
    },
    {
        id: 'settings:appearance',
        group: 'Settings',
        label: 'Appearance',
        keywords: 'theme colours',
        icon: Palette,
        run: visit(editAppearance()),
    },
    resolvedAppearance.value === 'dark'
        ? {
              id: 'theme:light',
              group: 'Actions',
              label: 'Switch to light mode',
              keywords: 'theme',
              icon: Sun,
              run: () => switchTo('light'),
          }
        : {
              id: 'theme:dark',
              group: 'Actions',
              label: 'Switch to dark mode',
              keywords: 'theme',
              icon: Moon,
              run: () => switchTo('dark'),
          },
    {
        id: 'auth:logout',
        group: 'Actions',
        label: 'Log out',
        keywords: 'sign out',
        icon: LogOut,
        run: () => {
            router.flushAll();
            router.post(toUrl(logout()));
        },
    },
]);

/**
 * Loose "every word appears somewhere" matching. It's forgiving enough for "sec 2fa" to find
 * Security without pulling in a fuzzy-search library for a list this short.
 */
const results = computed(() => {
    const words = query.value.toLowerCase().split(/\s+/).filter(Boolean);

    if (words.length === 0) {
        return commands.value;
    }

    return commands.value.filter((command) => {
        const haystack =
            `${command.label} ${command.group} ${command.keywords ?? ''}`.toLowerCase();

        return words.every((word) => haystack.includes(word));
    });
});

const groups = computed(() => {
    const byGroup = new Map<
        Command['group'],
        { command: Command; index: number }[]
    >();

    results.value.forEach((command, index) => {
        byGroup.set(command.group, [
            ...(byGroup.get(command.group) ?? []),
            { command, index },
        ]);
    });

    return [...byGroup.entries()];
});

watch(query, () => (activeIndex.value = 0));

watch(open, (isOpen) => {
    if (isOpen) {
        query.value = '';
        activeIndex.value = 0;
    }
});

// Reka would focus the first focusable thing in the dialog. We want the search box, and doing it
// from this event (rather than after a nextTick) means typing straight after ⌘K never gets lost.
function focusSearch(event: Event): void {
    event.preventDefault();
    input.value?.focus();
}

// The global shortcut. Ctrl+K on Windows/Linux, ⌘K on a Mac.
useEventListener(window, 'keydown', (event: KeyboardEvent) => {
    if (event.key.toLowerCase() === 'k' && (event.metaKey || event.ctrlKey)) {
        event.preventDefault();
        open.value = !open.value;
    }
});

function move(step: number): void {
    const count = results.value.length;

    if (count === 0) {
        return;
    }

    activeIndex.value = (activeIndex.value + step + count) % count;

    void nextTick(() => {
        document
            .getElementById(optionId(activeIndex.value))
            ?.scrollIntoView({ block: 'nearest' });
    });
}

function choose(command: Command | undefined): void {
    if (!command) {
        return;
    }

    open.value = false;
    // Let the dialog start closing before navigating, so the exit animation isn't cut off.
    requestAnimationFrame(() => command.run());
}

const optionId = (index: number) => `${listId}-option-${index}`;
</script>

<template>
    <DialogRoot v-model:open="open">
        <DialogPortal>
            <DialogOverlay
                class="fixed inset-0 z-50 bg-background/60 backdrop-blur-sm data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:animate-in data-[state=open]:fade-in-0"
            />
            <DialogContent
                class="fixed top-[12vh] left-1/2 z-50 w-[calc(100%-2rem)] max-w-xl -translate-x-1/2 overflow-hidden rounded-2xl border bg-popover text-popover-foreground shadow-2xl ring-1 ring-black/5 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95 data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95 data-[state=open]:slide-in-from-top-2"
                @open-auto-focus="focusSearch"
            >
                <DialogTitle class="sr-only">Command palette</DialogTitle>

                <div class="flex items-center gap-3 border-b px-4">
                    <Search class="size-4 shrink-0 text-muted-foreground" />
                    <input
                        ref="input"
                        v-model="query"
                        type="text"
                        role="combobox"
                        aria-expanded="true"
                        :aria-controls="listId"
                        :aria-activedescendant="
                            results.length ? optionId(activeIndex) : undefined
                        "
                        placeholder="Search or jump to…"
                        class="h-14 w-full bg-transparent text-[15px] outline-none placeholder:text-muted-foreground"
                        @keydown.down.prevent="move(1)"
                        @keydown.up.prevent="move(-1)"
                        @keydown.enter.prevent="choose(results[activeIndex])"
                    />
                    <kbd
                        class="hidden rounded border bg-muted px-1.5 py-0.5 font-mono text-[10px] text-muted-foreground sm:inline"
                    >
                        ESC
                    </kbd>
                </div>

                <div
                    :id="listId"
                    role="listbox"
                    class="max-h-[min(60vh,420px)] overflow-y-auto p-2"
                >
                    <p
                        v-if="results.length === 0"
                        class="px-3 py-10 text-center text-sm text-muted-foreground"
                    >
                        Nothing matches “{{ query }}”.
                    </p>

                    <div
                        v-for="[group, items] in groups"
                        :key="group"
                        class="mb-1 last:mb-0"
                    >
                        <p
                            class="px-3 pt-2 pb-1 text-[11px] font-medium tracking-wider text-muted-foreground uppercase"
                        >
                            {{ group }}
                        </p>
                        <button
                            v-for="{ command, index } in items"
                            :id="optionId(index)"
                            :key="command.id"
                            type="button"
                            role="option"
                            :aria-selected="index === activeIndex"
                            class="group relative flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm transition-colors aria-selected:bg-accent aria-selected:text-accent-foreground"
                            @mousemove="activeIndex = index"
                            @click="choose(command)"
                        >
                            <span
                                class="flex size-7 items-center justify-center rounded-md border bg-background text-muted-foreground transition-colors group-aria-selected:border-primary/40 group-aria-selected:text-primary"
                            >
                                <component :is="command.icon" class="size-4" />
                            </span>
                            <span class="flex-1">{{ command.label }}</span>
                            <CornerDownLeft
                                class="size-3.5 opacity-0 transition-opacity group-aria-selected:opacity-60"
                            />
                        </button>
                    </div>
                </div>

                <div
                    class="flex items-center gap-4 border-t bg-muted/40 px-4 py-2 text-[11px] text-muted-foreground"
                >
                    <span><kbd class="font-mono">↑↓</kbd> to move</span>
                    <span><kbd class="font-mono">↵</kbd> to select</span>
                    <span class="ml-auto">Showroom AI</span>
                </div>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
