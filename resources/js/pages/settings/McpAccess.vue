<script setup lang="ts">
import { Form, Head, router, usePage } from '@inertiajs/vue3';
import { Check, Copy, KeyRound, Trash2 } from '@lucide/vue';
import { computed, ref, useId } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { timeAgo } from '@/lib/format';
import { destroy, index as mcpIndex, store } from '@/routes/mcp-tokens';

type Token = {
    id: number;
    name: string;
    last_used_at: string | null;
    created_at: string | null;
};

const { tokens, endpoint } = defineProps<{
    tokens: Token[];
    endpoint: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'MCP access', href: mcpIndex() }],
    },
});

// The plain-text token arrives once, as flash data on the response that created it.
const page = usePage();
const newToken = computed(
    () => (page.flash as { newToken?: string }).newToken ?? null,
);

const nameId = useId();
const copied = ref<string | null>(null);

async function copy(text: string): Promise<void> {
    await navigator.clipboard.writeText(text);
    copied.value = text;
    setTimeout(() => (copied.value = null), 2000);
}

// One string, so template line breaks can't eat the space around the dot.
const usage = (token: Token) =>
    `Created ${timeAgo(token.created_at)} · ${
        token.last_used_at
            ? `last used ${timeAgo(token.last_used_at)}`
            : 'never used'
    }`;

function revoke(token: Token): void {
    if (
        confirm(
            `Revoke "${token.name}"? Anything connected with it stops working straight away.`,
        )
    ) {
        router.delete(destroy(token.id).url, { preserveScroll: true });
    }
}

// What to paste into Claude Desktop's config. mcp-remote bridges its stdio to our HTTP endpoint.
const desktopConfig = computed(() =>
    JSON.stringify(
        {
            mcpServers: {
                showroom: {
                    command: 'npx',
                    args: [
                        'mcp-remote',
                        endpoint,
                        '--header',
                        'Authorization:Bearer ${SHOWROOM_TOKEN}',
                    ],
                    env: {
                        SHOWROOM_TOKEN: newToken.value ?? 'paste-your-token',
                    },
                },
            },
        },
        null,
        2,
    ),
);
</script>

<template>
    <Head title="MCP access" />

    <h1 class="sr-only">MCP access</h1>

    <div class="flex flex-col gap-8">
        <div class="flex flex-col gap-4">
            <Heading
                variant="small"
                title="MCP access"
                description="Use Showroom from Claude Desktop, an IDE or any other MCP client. A token acts as you: it sees exactly the leads you can see."
            />

            <Form
                v-bind="store.form()"
                reset-on-success
                class="flex flex-col gap-2"
                #default="{ errors, processing }"
            >
                <Label :for="nameId">Token name</Label>
                <div class="flex gap-2">
                    <Input
                        :id="nameId"
                        name="name"
                        placeholder="e.g. Claude Desktop on my laptop"
                        required
                        maxlength="60"
                    />
                    <Button :disabled="processing">
                        <KeyRound class="size-4" /> Create token
                    </Button>
                </div>
                <InputError :message="errors.name" />
            </Form>
        </div>

        <section
            v-if="newToken"
            class="flex flex-col gap-3 rounded-xl border border-primary/50 bg-primary/5 p-4"
            aria-labelledby="new-token-heading"
        >
            <h2 id="new-token-heading" class="text-sm font-bold">
                Your new token
            </h2>
            <p class="text-sm text-muted-foreground">
                Copy it now. For your security it's stored only as a hash, so it
                can't be shown again.
            </p>
            <div class="flex items-center gap-2">
                <code
                    class="min-w-0 flex-1 truncate rounded-md border bg-background px-3 py-2 font-mono text-xs"
                    >{{ newToken }}</code
                >
                <Button size="sm" variant="secondary" @click="copy(newToken)">
                    <Check v-if="copied === newToken" class="size-3.5" />
                    <Copy v-else class="size-3.5" />
                    {{ copied === newToken ? 'Copied' : 'Copy' }}
                </Button>
            </div>
        </section>

        <section class="flex flex-col gap-3" aria-labelledby="tokens-heading">
            <h2 id="tokens-heading" class="text-sm font-bold">Your tokens</h2>
            <p v-if="tokens.length === 0" class="text-sm text-muted-foreground">
                No tokens yet.
            </p>
            <ul v-else class="divide-y rounded-xl border">
                <li
                    v-for="token in tokens"
                    :key="token.id"
                    class="flex items-center justify-between gap-3 px-4 py-3"
                >
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold">
                            {{ token.name }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ usage(token) }}
                        </p>
                    </div>
                    <Button
                        size="sm"
                        variant="ghost"
                        :aria-label="`Revoke ${token.name}`"
                        @click="revoke(token)"
                    >
                        <Trash2 class="size-4" /> Revoke
                    </Button>
                </li>
            </ul>
        </section>

        <section class="flex flex-col gap-3" aria-labelledby="connect-heading">
            <h2 id="connect-heading" class="text-sm font-bold">
                Connect Claude Desktop
            </h2>
            <p class="text-sm text-muted-foreground">
                Add this to <code>claude_desktop_config.json</code> and restart
                Claude. The full guide, including running it without a token
                over stdio, is in <code>docs/MCP.md</code>.
            </p>
            <div class="relative">
                <pre
                    class="overflow-x-auto rounded-xl border bg-muted/40 p-4 font-mono text-xs leading-relaxed"
                    >{{ desktopConfig }}</pre>
                <Button
                    size="sm"
                    variant="secondary"
                    class="absolute top-2 right-2"
                    @click="copy(desktopConfig)"
                >
                    <Check v-if="copied === desktopConfig" class="size-3.5" />
                    <Copy v-else class="size-3.5" />
                </Button>
            </div>
        </section>
    </div>
</template>
