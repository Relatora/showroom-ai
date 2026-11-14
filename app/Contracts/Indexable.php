<?php

namespace App\Contracts;

use App\Services\Knowledge\ChunkDraft;

/**
 * Anything the assistant can search: documents, vehicles, leads and timeline entries.
 */
interface Indexable
{
    /**
     * The text to make searchable. An empty list means "remove this from the index".
     *
     * @return list<ChunkDraft>
     */
    public function toChunks(): array;

    /**
     * The lead this knowledge belongs to, if any. Retrieval uses it to apply lead visibility,
     * so a rep's assistant can't quote another rep's customer notes.
     */
    public function indexLeadId(): ?int;
}
