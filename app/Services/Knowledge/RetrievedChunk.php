<?php

namespace App\Services\Knowledge;

use App\Models\Chunk;

/**
 * One search result: the chunk, its fused score, and where it ranked in each half of the
 * search. Keeping the per-half ranks makes it easy to see *why* something came back, which
 * matters when you're tuning retrieval or explaining an answer.
 */
final readonly class RetrievedChunk
{
    public function __construct(
        public Chunk $chunk,
        public float $score,
        public ?int $vectorRank,
        public ?int $keywordRank,
    ) {}

    /**
     * A short label for a citation chip: the document title, vehicle, or customer.
     */
    public function label(): string
    {
        $heading = $this->chunk->heading ?? '';

        return match ($this->chunk->source_type) {
            // "Trade-in Appraisal Policy (F-204): How long an offer is valid" -> the part before the colon.
            'document' => trim(explode(':', $heading, 2)[0]) ?: 'Document',
            'vehicle' => trim(preg_replace('/^Inventory:\s*/', '', $heading) ?? $heading),
            'lead' => trim(preg_replace('/^Lead:\s*/', '', $heading) ?? $heading),
            'activity' => $heading !== '' ? $heading : 'Timeline note',
            default => $heading,
        };
    }

    /**
     * For documents, the section within it ("How long an offer is valid"). Five passages from
     * the same policy all share a label, so this is what tells them apart.
     */
    public function section(): ?string
    {
        if ($this->chunk->source_type !== 'document' || ! str_contains($this->chunk->heading ?? '', ':')) {
            return null;
        }

        return trim(explode(':', (string) $this->chunk->heading, 2)[1]);
    }

    /**
     * Where a citation should take you. Timeline notes open the lead they belong to.
     */
    public function url(): ?string
    {
        $chunk = $this->chunk;

        return match ($chunk->source_type) {
            'document' => route('knowledge.show', $chunk->source_id).'#passage-'.$chunk->position,
            'vehicle' => route('inventory.show', $chunk->source_id),
            'lead' => route('leads.show', $chunk->source_id),
            'activity' => $chunk->lead_id ? route('leads.show', $chunk->lead_id) : null,
            default => null,
        };
    }

    /**
     * @return array{id: int, type: string, label: string, section: string|null, excerpt: string, url: string|null, score: float}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->chunk->id,
            'type' => $this->chunk->source_type,
            'label' => $this->label(),
            'section' => $this->section(),
            'excerpt' => mb_strimwidth($this->chunk->content, 0, 220, '…'),
            'url' => $this->url(),
            'score' => round($this->score, 4),
        ];
    }
}
