<?php

namespace App\Services\Knowledge;

/**
 * A chunk before it's been embedded and saved.
 */
final readonly class ChunkDraft
{
    public function __construct(
        public string $content,
        public ?string $heading = null,
    ) {}

    public function hash(): string
    {
        return hash('sha256', ($this->heading ?? '')."\n".$this->content);
    }

    /**
     * Roughly four characters per token for English. Close enough for sizing chunks, and it
     * saves shipping a tokenizer for a model we don't control.
     */
    public function tokenEstimate(): int
    {
        return (int) ceil(mb_strlen(($this->heading ?? '').' '.$this->content) / 4);
    }

    /**
     * What actually gets embedded. Including the heading gives a short paragraph its context:
     * "Offers are valid for 7 days" means a lot more under "Trade-in appraisals".
     */
    public function textForEmbedding(): string
    {
        return $this->heading ? "{$this->heading}\n\n{$this->content}" : $this->content;
    }
}
