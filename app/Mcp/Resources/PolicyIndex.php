<?php

namespace App\Mcp\Resources;

use App\Models\Document;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Resource;

/**
 * The list of dealership documents, so a client can show them and open one in full.
 * Resources are for reading whole things; search_knowledge_base is for finding passages.
 */
#[Name('policies')]
#[Uri('showroom://policies')]
#[MimeType('text/markdown')]
#[Description('Every dealership policy and guide in the knowledge base, with the URI to read each one in full.')]
class PolicyIndex extends Resource
{
    public function handle(Request $request): Response
    {
        $lines = Document::query()
            ->where('status', 'ready')
            ->orderBy('title')
            ->get()
            ->map(fn (Document $d) => "- [{$d->title}](showroom://policies/{$d->id}): {$d->category}, {$d->chunks_count} passages")
            ->implode("\n");

        return Response::text("# Dealership policies\n\n".($lines !== '' ? $lines : 'No documents have been indexed yet.'));
    }
}
