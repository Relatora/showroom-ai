<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    Car,
    Cpu,
    Database,
    HardDrive,
    KeyRound,
    Layers,
    Mail,
    Plug,
    ShieldCheck,
    Zap,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import AskDemo from '@/components/landing/AskDemo.vue';
import HighwayScene from '@/components/landing/HighwayScene.vue';
import RetrievalScores from '@/components/landing/RetrievalScores.vue';
import ThemeToggle from '@/components/ThemeToggle.vue';
import { vReveal } from '@/directives/reveal';
import { dashboard, login } from '@/routes';

const { stats, repository } = defineProps<{
    stats: {
        vehicles: number;
        openLeads: number;
        documents: number;
        chunks: number;
    };
    repository: string;
}>();

const page = usePage();
const signedIn = computed(() => Boolean(page.props.auth.user));

const nav = [
    { label: 'Assistant', href: '#assistant' },
    { label: 'How it works', href: '#how' },
    { label: 'MCP', href: '#mcp' },
    { label: 'Stack', href: '#stack' },
];

const features = [
    {
        icon: Car,
        title: 'Match stock to a lead',
        body: 'Shortlists available vehicles within budget, then lets the model pick and explain the best three. Stock numbers it invents are thrown away.',
    },
    {
        icon: Mail,
        title: 'Draft the follow-up',
        body: 'Writes the next email from the lead’s real timeline and the facts of the car, with contact details masked before the model sees them.',
    },
    {
        icon: ShieldCheck,
        title: 'Sees what you see',
        body: 'A rep’s assistant only retrieves that rep’s leads. The access rules run inside the search queries, not as a filter afterwards.',
    },
];

// "Exits" along the way from a question to an answer.
const steps = [
    {
        title: 'Search three ways',
        body: 'By meaning (pgvector), by words (Postgres full text) and by exact codes like stock numbers and VINs.',
    },
    {
        title: 'Only what you may see',
        body: 'Every query is scoped to the person asking, the same rules as the rest of the CRM.',
    },
    {
        title: 'Fuse the rankings',
        body: 'Weighted reciprocal rank fusion merges the three lists. The weights were tuned against the eval.',
    },
    {
        title: 'Answer locally',
        body: 'A 7B model on Ollama writes the answer and streams it back over server-sent events.',
    },
    {
        title: 'Cite everything',
        body: 'Each claim links to the passage it came from, highlighted on the source page.',
    },
];

const tools = [
    'pipeline_summary',
    'search_leads',
    'get_customer_timeline',
    'find_matching_vehicles',
    'search_knowledge_base',
    'log_activity',
];

const stack = [
    {
        icon: Cpu,
        name: 'Ollama',
        role: 'qwen2.5 for chat, nomic-embed-text for embeddings',
    },
    {
        icon: Database,
        name: 'PostgreSQL + pgvector',
        role: 'CRM data, vectors and full-text search in one place',
    },
    {
        icon: Zap,
        name: 'Redis + Horizon',
        role: 'Queues for extracting, chunking and embedding',
    },
    {
        icon: HardDrive,
        name: 'RustFS',
        role: 'S3-compatible storage for uploaded documents',
    },
    {
        icon: Layers,
        name: 'Laravel 13 + Vue 3.5',
        role: 'Inertia, TypeScript, Tailwind 4, Laravel AI SDK and MCP',
    },
];

const numbers = computed(() => [
    { value: stats.vehicles, label: 'vehicles on the lot' },
    { value: stats.openLeads, label: 'open leads' },
    { value: stats.documents, label: 'policy documents' },
    { value: stats.chunks, label: 'searchable passages' },
]);

const format = new Intl.NumberFormat('en-CA');
</script>

<template>
    <Head title="The dealership CRM that cites its sources" />

    <div class="min-h-svh bg-background text-foreground">
        <a
            href="#main"
            class="sr-only z-50 rounded-md bg-primary px-4 py-2 text-primary-foreground focus:not-sr-only focus:fixed focus:top-3 focus:left-3"
            >Skip to content</a
        >

        <header
            class="sticky top-0 z-40 border-b border-transparent bg-background/75 backdrop-blur-lg supports-[backdrop-filter]:bg-background/60"
        >
            <div
                class="mx-auto flex h-16 max-w-6xl items-center gap-4 px-4 sm:px-6"
            >
                <a href="#main" class="flex items-center gap-2 font-bold">
                    <AppLogoIcon class="size-7" />
                    <span>Showroom</span>
                    <span
                        class="rounded bg-primary/15 px-1 py-px text-[10px] font-semibold text-primary"
                        >AI</span
                    >
                </a>

                <nav
                    aria-label="Sections"
                    class="ml-6 hidden items-center gap-1 md:flex"
                >
                    <a
                        v-for="item in nav"
                        :key="item.href"
                        :href="item.href"
                        class="rounded-md px-3 py-1.5 text-sm text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
                        >{{ item.label }}</a
                    >
                </nav>

                <div class="ml-auto flex items-center gap-1 sm:gap-2">
                    <a
                        :href="repository"
                        class="hidden rounded-md px-3 py-1.5 text-sm text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground sm:inline-flex"
                        >GitHub</a
                    >
                    <ThemeToggle />
                    <Link
                        :href="signedIn ? dashboard() : login()"
                        class="inline-flex h-9 items-center rounded-md bg-primary px-4 text-sm font-semibold text-primary-foreground transition hover:brightness-110 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none"
                    >
                        {{ signedIn ? 'Dashboard' : 'Log in' }}
                    </Link>
                </div>
            </div>
        </header>

        <main id="main">
            <!-- Hero -->
            <section
                class="relative overflow-hidden pt-12 pb-64 sm:pt-20 sm:pb-80"
                aria-labelledby="hero-title"
            >
                <!-- A faint lane grid, fading out, so the hero isn't a flat colour. -->
                <div
                    class="pointer-events-none absolute inset-0 bg-[linear-gradient(to_right,var(--border)_1px,transparent_1px),linear-gradient(to_bottom,var(--border)_1px,transparent_1px)] [mask-image:radial-gradient(ellipse_at_top,black_20%,transparent_70%)] bg-[size:3.5rem_3.5rem] opacity-60"
                    aria-hidden="true"
                />

                <div class="relative mx-auto max-w-6xl px-4 sm:px-6">
                    <div class="mx-auto max-w-3xl text-center">
                        <!-- The sign hangs over the road, and the amber tab hangs off the sign. -->
                        <div class="inline-block">
                            <div class="sign px-6 py-6 sm:px-10 sm:py-8">
                                <p
                                    class="mb-3 flex items-center justify-center gap-2 text-sm font-bold tracking-wide uppercase opacity-90"
                                >
                                    <span
                                        class="flex size-6 items-center justify-center rounded bg-white text-neutral-900"
                                    >
                                        <AppLogoIcon class="size-4" />
                                    </span>
                                    Showroom AI
                                </p>
                                <h1
                                    id="hero-title"
                                    class="text-4xl leading-[1.05] font-extrabold tracking-tight sm:text-6xl"
                                >
                                    The CRM that knows your lot.
                                </h1>
                            </div>
                            <div
                                class="mx-auto w-fit sign-tab px-4 pt-1 pb-1.5 text-xs font-extrabold tracking-wider uppercase sm:text-sm"
                            >
                                Runs free
                                <span
                                    class="mx-0.5 inline-block size-1 rounded-full bg-current align-middle"
                                ></span>
                                on your machine
                            </div>
                        </div>

                        <p
                            v-reveal="150"
                            class="mx-auto mt-8 max-w-2xl text-lg text-pretty text-muted-foreground"
                        >
                            A dealership CRM with an assistant built in. Ask
                            about inventory, policies or a customer and get an
                            answer that cites its sources. Match stock to a
                            lead, draft the follow-up, or plug the whole CRM
                            into Claude over MCP.
                        </p>

                        <div
                            v-reveal="250"
                            class="mt-8 flex flex-wrap items-center justify-center gap-3"
                        >
                            <Link
                                :href="signedIn ? dashboard() : login()"
                                class="group inline-flex h-11 items-center gap-2 rounded-lg bg-primary px-6 font-semibold text-primary-foreground shadow-lg shadow-primary/25 transition hover:brightness-110 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none"
                            >
                                {{
                                    signedIn
                                        ? 'Open the dashboard'
                                        : 'Try the demo'
                                }}
                                <ArrowRight
                                    class="size-4 transition-transform group-hover:translate-x-0.5"
                                />
                            </Link>
                            <a
                                :href="repository"
                                class="inline-flex h-11 items-center rounded-lg border bg-card px-6 font-semibold transition hover:bg-accent hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                Read the code
                            </a>
                        </div>

                        <p
                            v-if="!signedIn"
                            v-reveal="350"
                            class="mt-5 text-sm text-muted-foreground"
                        >
                            Demo logins:
                            <code class="font-mono text-foreground"
                                >dana@northgate.test</code
                            >
                            (manager) or
                            <code class="font-mono text-foreground"
                                >marcus@northgate.test</code
                            >
                            (rep), password
                            <code class="font-mono text-foreground"
                                >password</code
                            >
                        </p>
                    </div>
                </div>

                <HighwayScene />
            </section>

            <!-- Ask Showroom -->
            <section
                id="assistant"
                class="scroll-mt-20 py-20 sm:py-28"
                aria-labelledby="assistant-title"
            >
                <div
                    class="mx-auto grid max-w-6xl items-center gap-10 px-4 sm:px-6 lg:grid-cols-2 lg:gap-16"
                >
                    <div v-reveal>
                        <p
                            class="text-sm font-bold tracking-wide text-primary uppercase"
                        >
                            Ask Showroom
                        </p>
                        <h2
                            id="assistant-title"
                            class="mt-2 text-3xl font-extrabold tracking-tight sm:text-4xl"
                        >
                            Answers with receipts.
                        </h2>
                        <p class="mt-4 text-muted-foreground">
                            Sales floors run on policy questions. Can they take
                            it overnight? What’s the trade-in process? Is this
                            one CPO? The assistant answers from your own
                            documents, inventory and lead history, and every
                            claim links to the passage it came from. If the
                            sources don’t cover it, it says so instead of
                            guessing.
                        </p>
                        <ul class="mt-6 space-y-2 text-sm">
                            <li class="flex gap-2">
                                <span
                                    class="mt-1.5 size-1.5 shrink-0 rounded-full bg-primary"
                                />
                                Streams token by token, so the first words show
                                up fast
                            </li>
                            <li class="flex gap-2">
                                <span
                                    class="mt-1.5 size-1.5 shrink-0 rounded-full bg-primary"
                                />
                                Follow-up questions keep the thread of the
                                conversation
                            </li>
                            <li class="flex gap-2">
                                <span
                                    class="mt-1.5 size-1.5 shrink-0 rounded-full bg-primary"
                                />
                                Phone numbers and emails are masked before
                                indexing
                            </li>
                        </ul>
                    </div>
                    <div v-reveal="150">
                        <AskDemo />
                    </div>
                </div>

                <div
                    class="mx-auto mt-16 grid max-w-6xl gap-4 px-4 sm:px-6 md:grid-cols-3"
                >
                    <article
                        v-for="(f, i) in features"
                        :key="f.title"
                        v-reveal="i * 100"
                        class="group rounded-2xl border bg-card p-6 transition-shadow hover:shadow-lg hover:shadow-black/5 dark:hover:shadow-black/30"
                    >
                        <span
                            class="flex size-10 items-center justify-center rounded-lg bg-accent text-accent-foreground transition-transform duration-300 ease-[var(--ease-spring)] group-hover:scale-110"
                        >
                            <component :is="f.icon" class="size-5" />
                        </span>
                        <h3 class="mt-4 font-bold">{{ f.title }}</h3>
                        <p class="mt-2 text-sm text-muted-foreground">
                            {{ f.body }}
                        </p>
                    </article>
                </div>
            </section>

            <!-- How it works -->
            <section
                id="how"
                class="scroll-mt-20 border-y bg-muted/40 py-20 sm:py-28"
                aria-labelledby="how-title"
            >
                <div class="mx-auto max-w-6xl px-4 sm:px-6">
                    <div v-reveal class="max-w-2xl">
                        <p
                            class="text-sm font-bold tracking-wide text-primary uppercase"
                        >
                            How it works
                        </p>
                        <h2
                            id="how-title"
                            class="mt-2 text-3xl font-extrabold tracking-tight sm:text-4xl"
                        >
                            From question to cited answer in five exits.
                        </h2>
                    </div>

                    <ol
                        class="relative mt-12 grid gap-6 md:grid-cols-5 md:gap-4"
                    >
                        <!-- The road the exits sit along (desktop only). -->
                        <div
                            class="absolute top-5 right-[10%] left-[10%] hidden h-0.5 bg-[repeating-linear-gradient(90deg,var(--border)_0_12px,transparent_12px_20px)] md:block"
                            aria-hidden="true"
                        />
                        <li
                            v-for="(step, i) in steps"
                            :key="step.title"
                            v-reveal="i * 110"
                            class="relative flex gap-4 md:flex-col md:items-center md:text-center"
                        >
                            <span
                                class="relative flex size-10 shrink-0 sign [animation:none] items-center justify-center rounded-lg text-sm font-extrabold"
                                >{{ i + 1 }}</span
                            >
                            <div>
                                <h3 class="font-bold">{{ step.title }}</h3>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    {{ step.body }}
                                </p>
                            </div>
                        </li>
                    </ol>

                    <div class="mt-16 grid items-center gap-10 lg:grid-cols-2">
                        <div v-reveal>
                            <h3 class="text-xl font-extrabold tracking-tight">
                                Measured, not guessed.
                            </h3>
                            <p class="mt-3 text-muted-foreground">
                                <code class="font-mono text-sm text-foreground"
                                    >php artisan showroom:eval</code
                                >
                                asks 35 questions with known answers and scores
                                where the right passage lands. Vector search
                                alone missed questions about exact stock
                                numbers, so hybrid search adds a third ranking
                                just for codes. The weights you see in the code
                                are the ones the eval picked.
                            </p>
                        </div>
                        <div v-reveal="150">
                            <RetrievalScores />
                        </div>
                    </div>
                </div>
            </section>

            <!-- MCP -->
            <section
                id="mcp"
                class="scroll-mt-20 py-20 sm:py-28"
                aria-labelledby="mcp-title"
            >
                <div
                    class="mx-auto grid max-w-6xl items-center gap-10 px-4 sm:px-6 lg:grid-cols-2 lg:gap-16"
                >
                    <div v-reveal class="order-2 lg:order-1">
                        <div
                            class="overflow-hidden rounded-2xl border bg-[oklch(0.2_0.012_240)] text-[oklch(0.92_0.005_230)] shadow-xl shadow-black/10"
                        >
                            <div
                                class="flex items-center gap-1.5 border-b border-white/10 px-4 py-2.5"
                            >
                                <span
                                    class="size-2.5 rounded-full bg-white/20"
                                />
                                <span
                                    class="size-2.5 rounded-full bg-white/20"
                                />
                                <span
                                    class="size-2.5 rounded-full bg-white/20"
                                />
                                <span
                                    class="ml-2 font-mono text-xs text-white/60"
                                    >claude_desktop_config.json</span
                                >
                            </div>
                            <pre
                                class="overflow-x-auto p-4 font-mono text-xs leading-relaxed sm:text-[13px]"
                                tabindex="0"
                                aria-label="Example Claude Desktop configuration"
                            ><code>{
  <span class="text-[oklch(0.8_0.1_163)]">"mcpServers"</span>: {
    <span class="text-[oklch(0.8_0.1_163)]">"showroom"</span>: {
      <span class="text-[oklch(0.8_0.1_163)]">"url"</span>: <span class="text-[oklch(0.85_0.12_85)]">"http://localhost/mcp"</span>,
      <span class="text-[oklch(0.8_0.1_163)]">"headers"</span>: {
        <span class="text-[oklch(0.8_0.1_163)]">"Authorization"</span>: <span class="text-[oklch(0.85_0.12_85)]">"Bearer &lt;your token&gt;"</span>
      }
    }
  }
}</code></pre>
                        </div>
                        <ul
                            class="mt-4 flex flex-wrap gap-2"
                            aria-label="MCP tools"
                        >
                            <li
                                v-for="(tool, i) in tools"
                                :key="tool"
                                v-reveal="i * 60"
                                class="rounded-md border bg-card px-2.5 py-1 font-mono text-xs"
                            >
                                {{ tool }}
                            </li>
                        </ul>
                    </div>

                    <div v-reveal="100" class="order-1 lg:order-2">
                        <p
                            class="text-sm font-bold tracking-wide text-primary uppercase"
                        >
                            MCP server
                        </p>
                        <h2
                            id="mcp-title"
                            class="mt-2 text-3xl font-extrabold tracking-tight sm:text-4xl"
                        >
                            Bring your own AI.
                        </h2>
                        <p class="mt-4 text-muted-foreground">
                            Showroom is also a Model Context Protocol server.
                            Connect Claude Desktop, an IDE or your own agent and
                            ask “which hot leads have gone quiet, and what stock
                            fits them?” in plain language.
                        </p>
                        <dl class="mt-6 space-y-4 text-sm">
                            <div class="flex gap-3">
                                <dt class="sr-only">Same rules</dt>
                                <ShieldCheck
                                    class="mt-0.5 size-5 shrink-0 text-primary"
                                />
                                <dd>
                                    <strong>Same rules as the app.</strong>
                                    Every tool goes through the same policies,
                                    so an AI client can’t see more than the
                                    person it acts for.
                                </dd>
                            </div>
                            <div class="flex gap-3">
                                <dt class="sr-only">Scoped tokens</dt>
                                <KeyRound
                                    class="mt-0.5 size-5 shrink-0 text-primary"
                                />
                                <dd>
                                    <strong>Single-purpose tokens.</strong> They
                                    open the MCP endpoint and nothing else, are
                                    shown once and revoke in one click.
                                </dd>
                            </div>
                            <div class="flex gap-3">
                                <dt class="sr-only">One write</dt>
                                <Plug
                                    class="mt-0.5 size-5 shrink-0 text-primary"
                                />
                                <dd>
                                    <strong>Read-mostly.</strong> Five tools
                                    only read. The one that writes labels its
                                    entries, and contact details leave masked.
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </section>

            <!-- Stack and live numbers -->
            <section
                id="stack"
                class="scroll-mt-20 border-t bg-muted/40 py-20 sm:py-28"
                aria-labelledby="stack-title"
            >
                <div class="mx-auto max-w-6xl px-4 sm:px-6">
                    <div v-reveal class="max-w-2xl">
                        <p
                            class="text-sm font-bold tracking-wide text-primary uppercase"
                        >
                            Stack
                        </p>
                        <h2
                            id="stack-title"
                            class="mt-2 text-3xl font-extrabold tracking-tight sm:text-4xl"
                        >
                            No API bill. No data leaving the building.
                        </h2>
                        <p class="mt-4 text-muted-foreground">
                            Everything runs in Docker and Ollama on one machine.
                            Storage speaks S3, so moving to AWS later is a
                            change to
                            <code class="font-mono text-sm text-foreground"
                                >.env</code
                            >, not to the code.
                        </p>
                    </div>

                    <ul class="mt-10 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                        <li
                            v-for="(item, i) in stack"
                            :key="item.name"
                            v-reveal="i * 80"
                            class="rounded-xl border bg-card p-4"
                        >
                            <div class="flex items-center justify-between">
                                <component
                                    :is="item.icon"
                                    class="size-5 text-primary"
                                />
                                <span
                                    class="rounded bg-accent px-1.5 py-0.5 font-mono text-[11px] font-bold text-accent-foreground"
                                    >$0</span
                                >
                            </div>
                            <p class="mt-3 text-sm font-bold">
                                {{ item.name }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ item.role }}
                            </p>
                        </li>
                    </ul>

                    <div v-reveal class="mt-12">
                        <p class="text-sm font-semibold text-muted-foreground">
                            Right now, in this install
                        </p>
                        <dl
                            class="mt-3 grid grid-cols-2 gap-px overflow-hidden rounded-xl border bg-border lg:grid-cols-4"
                        >
                            <div
                                v-for="n in numbers"
                                :key="n.label"
                                class="bg-card p-5"
                            >
                                <dt class="text-xs text-muted-foreground">
                                    {{ n.label }}
                                </dt>
                                <dd
                                    class="mt-1 text-3xl font-extrabold tracking-tight tabular-nums"
                                >
                                    {{ format.format(n.value) }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </section>

            <!-- Last exit -->
            <section class="py-20 sm:py-28" aria-labelledby="cta-title">
                <div
                    v-reveal
                    class="mx-auto max-w-3xl px-4 text-center sm:px-6"
                >
                    <div class="inline-block">
                        <div class="sign [animation:none] px-8 py-7">
                            <h2
                                id="cta-title"
                                class="text-2xl font-extrabold tracking-tight sm:text-4xl"
                            >
                                Next exit: the showroom floor
                            </h2>
                        </div>
                        <div
                            class="mx-auto w-fit sign-tab px-4 pt-1 pb-1.5 text-xs font-extrabold tracking-wider uppercase"
                        >
                            Demo data
                            <span
                                class="mx-0.5 inline-block size-1 rounded-full bg-current align-middle"
                            ></span>
                            six staff
                            <span
                                class="mx-0.5 inline-block size-1 rounded-full bg-current align-middle"
                            ></span>
                            one lot
                        </div>
                    </div>
                    <div class="mt-8">
                        <Link
                            :href="signedIn ? dashboard() : login()"
                            class="group inline-flex h-11 items-center gap-2 rounded-lg bg-primary px-6 font-semibold text-primary-foreground shadow-lg shadow-primary/25 transition hover:brightness-110 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none"
                        >
                            {{
                                signedIn
                                    ? 'Open the dashboard'
                                    : 'Log in to the demo'
                            }}
                            <ArrowRight
                                class="size-4 transition-transform group-hover:translate-x-0.5"
                            />
                        </Link>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t py-8">
            <div
                class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-3 px-4 text-sm text-muted-foreground sm:flex-row sm:px-6"
            >
                <p class="flex items-center gap-2">
                    <AppLogoIcon class="size-5" />
                    Showroom AI, a portfolio project by Amr Abdallah
                </p>
                <a :href="repository" class="hover:text-foreground">
                    Source on GitHub
                </a>
            </div>
        </footer>
    </div>
</template>
