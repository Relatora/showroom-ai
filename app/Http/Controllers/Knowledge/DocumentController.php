<?php

namespace App\Http\Controllers\Knowledge;

use App\Http\Controllers\Controller;
use App\Jobs\Knowledge\IndexDocument;
use App\Models\Document;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The knowledge base: the dealership documents the assistant answers from.
 */
class DocumentController extends Controller
{
    public function index(Request $request): Response
    {
        $documents = Document::query()
            ->with('uploader:id,name')
            ->orderBy('title')
            ->get()
            ->map(fn (Document $d) => $this->present($d));

        return Inertia::render('knowledge/Index', [
            'documents' => $documents,
            'categories' => Document::CATEGORIES,
            'canManage' => $request->user()->can('create', Document::class),
        ]);
    }

    public function show(Document $document): Response
    {
        return Inertia::render('knowledge/Show', [
            'document' => $this->present($document->load('uploader:id,name')),
            'chunks' => $document->chunks()->get(['id', 'position', 'heading', 'content', 'token_estimate']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Document::class);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'category' => ['required', Rule::in(array_keys(Document::CATEGORIES))],
            // 10 MB is plenty for a policy PDF, and stops someone indexing a 400-page manual by accident.
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,md,markdown,txt'],
        ]);

        $file = $request->file('file');
        $path = $file->storeAs('documents', Str::ulid().'.'.$file->getClientOriginalExtension(), 'documents');

        $document = Document::create([
            'title' => $data['title'],
            'category' => $data['category'],
            'disk_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            // Browsers report Markdown inconsistently, so trust the extension for text formats.
            'mime_type' => $file->getClientOriginalExtension() === 'pdf' ? 'application/pdf' : 'text/markdown',
            'size_bytes' => $file->getSize(),
            'status' => 'pending',
            'uploaded_by' => $request->user()->id,
        ]);

        IndexDocument::dispatch($document);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Uploaded. Indexing {$document->title} now."]);

        return to_route('knowledge.index');
    }

    public function reindex(Document $document): RedirectResponse
    {
        Gate::authorize('update', $document);

        $document->markAs('pending');
        IndexDocument::dispatch($document);

        return back();
    }

    public function destroy(Document $document): RedirectResponse
    {
        Gate::authorize('delete', $document);

        $document->chunks()->delete();
        Storage::disk('documents')->delete($document->disk_path);
        $document->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Removed from the knowledge base.']);

        return to_route('knowledge.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Document $document): array
    {
        return [
            'id' => $document->id,
            'title' => $document->title,
            'category' => $document->category,
            'category_label' => Document::CATEGORIES[$document->category] ?? $document->category,
            'original_name' => $document->original_name,
            'size_bytes' => $document->size_bytes,
            'status' => $document->status,
            'error' => $document->error,
            'chunks_count' => $document->chunks_count,
            'uploaded_by' => $document->uploader?->name,
            'indexed_at' => $document->indexed_at?->toIso8601String(),
            'created_at' => $document->created_at?->toIso8601String(),
        ];
    }
}
