<script setup lang="ts">
import { Head, Link, router, useForm, usePoll } from '@inertiajs/vue3';
import {
    FileText,
    LoaderCircle,
    RotateCw,
    Trash2,
    UploadCloud,
} from '@lucide/vue';
import { computed, ref, useId, watch } from 'vue';
import ToneBadge from '@/components/crm/ToneBadge.vue';
import { Button } from '@/components/ui/button';
import { count, timeAgo } from '@/lib/format';
import {
    destroy,
    index as knowledgeIndex,
    reindex,
    show,
    store,
} from '@/routes/knowledge';
import type { Tone } from '@/types/crm';

type KnowledgeDocument = {
    id: number;
    title: string;
    category: string;
    category_label: string;
    original_name: string;
    size_bytes: number;
    status: 'pending' | 'indexing' | 'ready' | 'failed';
    error: string | null;
    chunks_count: number;
    uploaded_by: string | null;
    indexed_at: string | null;
    created_at: string | null;
};

const { documents, categories, canManage } = defineProps<{
    documents: KnowledgeDocument[];
    categories: Record<string, string>;
    canManage: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Knowledge base', href: knowledgeIndex() }],
    },
});

const statusTone: Record<KnowledgeDocument['status'], Tone> = {
    pending: 'muted',
    indexing: 'info',
    ready: 'success',
    failed: 'destructive',
};

const statusLabel: Record<KnowledgeDocument['status'], string> = {
    pending: 'Queued',
    indexing: 'Indexing',
    ready: 'Ready',
    failed: 'Failed',
};

const totalChunks = computed(() =>
    documents.reduce((sum, d) => sum + d.chunks_count, 0),
);

// While anything is still being indexed, check back every two seconds so the status updates
// on its own. Polling stops as soon as everything settles.
const busy = computed(() =>
    documents.some((d) => d.status === 'pending' || d.status === 'indexing'),
);
const { start, stop } = usePoll(
    2000,
    { only: ['documents'] },
    { autoStart: false },
);
watch(busy, (isBusy) => (isBusy ? start() : stop()), { immediate: true });

// Upload
const form = useForm<{ title: string; category: string; file: File | null }>({
    title: '',
    category: 'policy',
    file: null,
});
const dragging = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);
const titleId = useId();
const categoryId = useId();

function pick(file: File | undefined): void {
    if (!file) {
        return;
    }

    form.file = file;

    // Suggest a title from the file name: "trade-in_policy-2026.pdf" becomes "Trade in policy 2026".
    if (!form.title) {
        const base = file.name
            .replace(/\.[^.]+$/, '')
            .replace(/[-_]+/g, ' ')
            .trim();
        form.title = base.charAt(0).toUpperCase() + base.slice(1);
    }
}

function onDrop(event: DragEvent): void {
    dragging.value = false;
    pick(event.dataTransfer?.files[0]);
}

function upload(): void {
    form.submit(store(), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset();

            if (fileInput.value) {
                fileInput.value.value = '';
            }
        },
    });
}

function remove(document: KnowledgeDocument): void {
    if (
        confirm(
            `Remove "${document.title}" from the knowledge base? The assistant will stop using it straight away.`,
        )
    ) {
        router.visit(destroy(document.id), { preserveScroll: true });
    }
}

const size = (bytes: number) =>
    bytes > 1024 * 1024
        ? `${(bytes / 1024 / 1024).toFixed(1)} MB`
        : `${Math.ceil(bytes / 1024)} KB`;
</script>

<template>
    <Head title="Knowledge base" />

    <div class="flex min-w-0 flex-1 flex-col gap-6 p-4 md:p-6">
        <header class="flex flex-col items-start">
            <div class="sign px-6 pt-4 pb-5">
                <h1
                    class="text-[2rem] leading-none font-extrabold tracking-tight"
                >
                    Knowledge base
                </h1>
                <dl
                    class="mt-3 grid grid-cols-[1fr_auto] gap-x-12 gap-y-0.5 text-[15px] font-semibold"
                >
                    <dt>Documents</dt>
                    <dd class="text-right tabular-nums">
                        {{ documents.length }}
                    </dd>
                    <dt>Searchable passages</dt>
                    <dd class="text-right tabular-nums">
                        {{ count(totalChunks) }}
                    </dd>
                </dl>
            </div>
            <p
                v-if="busy"
                class="-mt-px ml-5 flex items-center gap-1.5 sign-tab px-3 pt-1 pb-1.5 text-xs font-bold"
            >
                <LoaderCircle class="size-3.5 animate-spin" /> Indexing in
                progress
            </p>
        </header>

        <p class="max-w-prose text-sm text-muted-foreground">
            The assistant answers from these documents and cites them. Each one
            is split into passages, and each passage is made searchable by
            meaning and by keyword. Open a document to see exactly how the
            assistant reads it.
        </p>

        <form
            v-if="canManage"
            class="grid gap-4 rounded-xl border bg-card p-5 md:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]"
            @submit.prevent="upload"
        >
            <label
                :class="[
                    'flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed px-4 py-8 text-center transition-colors',
                    dragging
                        ? 'border-primary bg-primary/5'
                        : 'hover:border-primary/40',
                ]"
                @dragover.prevent="dragging = true"
                @dragleave="dragging = false"
                @drop.prevent="onDrop"
            >
                <UploadCloud class="size-7 text-muted-foreground" />
                <span v-if="form.file" class="text-sm font-semibold">{{
                    form.file.name
                }}</span>
                <span v-else class="text-sm font-semibold"
                    >Drop a PDF, Markdown or text file</span
                >
                <span class="text-xs text-muted-foreground"
                    >or click to choose one. Up to 10 MB.</span
                >
                <input
                    ref="fileInput"
                    type="file"
                    accept=".pdf,.md,.markdown,.txt"
                    class="sr-only"
                    @change="
                        pick(($event.target as HTMLInputElement).files?.[0])
                    "
                />
                <span
                    v-if="form.errors.file"
                    class="text-xs text-destructive-foreground"
                    >{{ form.errors.file }}</span
                >
            </label>

            <div class="flex flex-col gap-3">
                <div class="grid gap-1.5">
                    <label :for="titleId" class="text-sm font-semibold"
                        >Title</label
                    >
                    <input
                        :id="titleId"
                        v-model="form.title"
                        required
                        placeholder="Trade-in appraisal policy"
                        class="h-9 rounded-lg border bg-background px-3 text-sm outline-none focus:border-primary/50 focus:ring-3 focus:ring-primary/15"
                    />
                    <span
                        v-if="form.errors.title"
                        class="text-xs text-destructive-foreground"
                        >{{ form.errors.title }}</span
                    >
                </div>
                <div class="grid gap-1.5">
                    <label :for="categoryId" class="text-sm font-semibold"
                        >Category</label
                    >
                    <select
                        :id="categoryId"
                        v-model="form.category"
                        class="h-9 rounded-lg border bg-background px-2.5 text-sm outline-none focus:border-primary/50"
                    >
                        <option
                            v-for="(name, key) in categories"
                            :key="key"
                            :value="key"
                        >
                            {{ name }}
                        </option>
                    </select>
                </div>
                <Button
                    class="mt-auto self-start"
                    :disabled="!form.file || form.processing"
                >
                    <LoaderCircle v-if="form.processing" class="animate-spin" />
                    Add to knowledge base
                </Button>
            </div>
        </form>

        <section
            aria-labelledby="documents-heading"
            class="overflow-hidden rounded-xl border bg-card"
        >
            <h2 id="documents-heading" class="sr-only">Documents</h2>
            <table class="w-full text-sm">
                <thead
                    class="border-b bg-muted/40 text-left text-xs text-muted-foreground"
                >
                    <tr>
                        <th scope="col" class="px-4 py-2.5 font-semibold">
                            Document
                        </th>
                        <th
                            scope="col"
                            class="hidden px-4 py-2.5 font-semibold md:table-cell"
                        >
                            Category
                        </th>
                        <th scope="col" class="px-4 py-2.5 font-semibold">
                            Status
                        </th>
                        <th
                            scope="col"
                            class="hidden px-4 py-2.5 text-right font-semibold sm:table-cell"
                        >
                            Passages
                        </th>
                        <th
                            scope="col"
                            class="hidden px-4 py-2.5 font-semibold lg:table-cell"
                        >
                            Added
                        </th>
                        <th scope="col" class="px-4 py-2.5">
                            <span class="sr-only">Actions</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr
                        v-for="doc in documents"
                        :key="doc.id"
                        class="hover:bg-muted/30"
                    >
                        <td class="px-4 py-3">
                            <Link
                                :href="show(doc.id)"
                                class="flex items-center gap-2 font-semibold hover:underline"
                            >
                                <FileText
                                    class="size-4 shrink-0 text-muted-foreground"
                                />
                                {{ doc.title }}
                            </Link>
                            <p
                                class="mt-0.5 pl-6 text-xs text-muted-foreground"
                            >
                                {{ doc.original_name }},
                                {{ size(doc.size_bytes) }}
                            </p>
                            <p
                                v-if="doc.error"
                                class="mt-1 pl-6 text-xs text-destructive-foreground"
                            >
                                {{ doc.error }}
                            </p>
                        </td>
                        <td
                            class="hidden px-4 py-3 text-muted-foreground md:table-cell"
                        >
                            {{ doc.category_label }}
                        </td>
                        <td class="px-4 py-3">
                            <ToneBadge :tone="statusTone[doc.status]" dot>
                                <LoaderCircle
                                    v-if="doc.status === 'indexing'"
                                    class="size-3 animate-spin"
                                />
                                {{ statusLabel[doc.status] }}
                            </ToneBadge>
                        </td>
                        <td
                            class="hidden px-4 py-3 text-right tabular-nums sm:table-cell"
                        >
                            {{ doc.chunks_count }}
                        </td>
                        <td
                            class="hidden px-4 py-3 text-muted-foreground lg:table-cell"
                        >
                            {{ timeAgo(doc.created_at)
                            }}<template v-if="doc.uploaded_by">
                                by {{ doc.uploaded_by }}</template
                            >
                        </td>
                        <td class="px-4 py-3">
                            <div
                                v-if="canManage"
                                class="flex justify-end gap-1"
                            >
                                <button
                                    type="button"
                                    class="flex size-8 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground"
                                    :aria-label="`Re-index ${doc.title}`"
                                    title="Re-index"
                                    @click="
                                        router.visit(reindex(doc.id), {
                                            preserveScroll: true,
                                        })
                                    "
                                >
                                    <RotateCw class="size-4" />
                                </button>
                                <button
                                    type="button"
                                    class="flex size-8 items-center justify-center rounded-md text-muted-foreground hover:bg-destructive/10 hover:text-destructive-foreground"
                                    :aria-label="`Remove ${doc.title}`"
                                    title="Remove"
                                    @click="remove(doc)"
                                >
                                    <Trash2 class="size-4" />
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="documents.length === 0">
                        <td
                            colspan="6"
                            class="px-4 py-12 text-center text-muted-foreground"
                        >
                            No documents yet. Upload your trade-in, finance or
                            warranty policies to get started.
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>
    </div>
</template>
