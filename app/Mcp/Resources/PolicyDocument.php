<?php

namespace App\Mcp\Resources;

use App\Models\Chunk;
use App\Models\Document;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

/**
 * One document, in full. Rebuilt from its indexed passages rather than read from object
 * storage: that's exactly the text the assistant searches, with contact details already
 * masked, and it works for PDFs too.
 */
#[Name('policy')]
#[MimeType('text/markdown')]
#[Description('The full text of one dealership policy or guide. Get the URIs from showroom://policies.')]
class PolicyDocument extends Resource implements HasUriTemplate
{
    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('showroom://policies/{id}');
    }

    public function handle(Request $request): Response
    {
        $document = Document::query()->where('status', 'ready')->find((int) $request->get('id'));

        if (! $document) {
            return Response::error('No such policy. See showroom://policies for the list.');
        }

        $body = $document->chunks()
            ->orderBy('position')
            ->get(['heading', 'content'])
            ->map(function (Chunk $chunk) {
                // Passage headings look like "Title: Section"; keep just the section.
                $section = str_contains($chunk->heading ?? '', ':')
                    ? trim(explode(':', (string) $chunk->heading, 2)[1])
                    : null;

                return ($section ? "## {$section}\n\n" : '').$chunk->content;
            })
            ->implode("\n\n");

        return Response::text("# {$document->title}\n\n{$body}");
    }
}
