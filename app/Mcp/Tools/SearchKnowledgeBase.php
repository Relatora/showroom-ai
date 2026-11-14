<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ActsForStaff;
use App\Services\Knowledge\HybridRetriever;
use App\Services\Knowledge\RetrievedChunk;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * The same hybrid retrieval Ask Showroom uses, exposed raw. The client gets the passages and
 * writes the answer itself, citing them by label.
 */
#[Name('search_knowledge_base')]
#[Description("Search the dealership's knowledge: policy documents (trade-ins, deposits, finance rates, warranties, test drives, pricing rules…), the vehicle inventory, and the notes on leads you can see. Returns the most relevant passages with their source. Answer from these and cite the source label; don't guess at policy details.")]
#[IsReadOnly]
class SearchKnowledgeBase extends Tool
{
    use ActsForStaff;

    private const TYPES = ['document', 'vehicle', 'lead', 'activity'];

    public function handle(Request $request, HybridRetriever $retriever): Response|ResponseFactory
    {
        $user = $this->staff($request);
        $args = $request->validate([
            'query' => ['required', 'string', 'min:2', 'max:500'],
            'only' => ['nullable', 'array'],
            'only.*' => ['string', 'in:'.implode(',', self::TYPES)],
            'limit' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        $results = $retriever->search($args['query'], $user, $args['limit'] ?? 5, $args['only'] ?? null);

        if ($results === []) {
            return Response::text('Nothing in the knowledge base matches that.');
        }

        return Response::structured([
            'passages' => array_map(fn (RetrievedChunk $r) => [
                'source' => $r->label(),
                'section' => $r->section(),
                'type' => $r->chunk->source_type,
                // Chunks were masked when they were indexed; nothing to do here.
                'text' => $r->chunk->content,
                'url' => $r->url(),
            ], $results),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->required()->description('A question or keywords. Policy codes (F-204), stock numbers (S1046) and VINs match exactly.'),
            'only' => $schema->array()->items($schema->string()->enum(self::TYPES))->description('Limit to some kinds of source, e.g. ["document"] for policies only.'),
            'limit' => $schema->integer()->min(1)->max(10)->description('How many passages (default 5).'),
        ];
    }
}
