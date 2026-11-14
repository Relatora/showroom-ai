<?php

namespace App\Services\Knowledge;

/**
 * Cuts a document into retrieval-sized pieces.
 *
 * The goal is chunks that each answer one question. So we split on headings first (a section
 * is usually about one thing), then pack paragraphs into chunks of about 450 tokens, carrying
 * the last paragraph over as overlap so an answer that straddles a boundary isn't lost.
 * Every chunk remembers its section heading, which is shown in citations and boosts keyword search.
 */
class Chunker
{
    public function __construct(
        private readonly int $maxChars = 1800,
        private readonly int $overlapChars = 300,
    ) {}

    /**
     * @return list<ChunkDraft>
     */
    public function split(string $text, ?string $title = null): array
    {
        $drafts = [];

        foreach ($this->sections($this->normalise($text)) as [$heading, $body]) {
            $heading = $this->qualify($heading, $title);

            foreach ($this->pack($this->paragraphs($body)) as $content) {
                $drafts[] = new ChunkDraft($content, $heading);
            }
        }

        return $drafts;
    }

    private function normalise(string $text): string
    {
        $text = str_replace(["\r\n", "\r", "\f"], "\n", $text);
        // PDF extraction often leaves runs of spaces and blank lines.
        $text = (string) preg_replace('/[ \t]+/', ' ', $text);

        return trim((string) preg_replace("/\n{3,}/", "\n\n", $text));
    }

    /**
     * Split on markdown headings, and on the numbered or all-caps headings that PDFs tend to use.
     *
     * @return list<array{0: string|null, 1: string}>
     */
    private function sections(string $text): array
    {
        $sections = [];
        $heading = null;
        $buffer = [];

        foreach (explode("\n", $text) as $line) {
            $candidate = $this->headingFrom($line);

            if ($candidate !== null) {
                if (trim(implode("\n", $buffer)) !== '') {
                    $sections[] = [$heading, implode("\n", $buffer)];
                }

                $heading = $candidate;
                $buffer = [];

                continue;
            }

            $buffer[] = $line;
        }

        if (trim(implode("\n", $buffer)) !== '') {
            $sections[] = [$heading, implode("\n", $buffer)];
        }

        return $sections;
    }

    private function headingFrom(string $line): ?string
    {
        $line = trim($line);

        if (preg_match('/^#{1,4}\s+(.+)$/', $line, $m)) {
            return trim($m[1]);
        }

        // "3. Trade-in appraisals" or "TRADE-IN APPRAISALS", short and without a full stop.
        $short = mb_strlen($line) > 2 && mb_strlen($line) <= 70 && ! str_ends_with($line, '.');

        if ($short && preg_match('/^\d+(\.\d+)*\.?\s+[A-Z]/', $line)) {
            return $line;
        }

        if ($short && preg_match('/[A-Z]/', $line) && $line === mb_strtoupper($line) && preg_match('/^[A-Z0-9 &\-\/\']+$/', $line)) {
            return ucfirst(mb_strtolower($line));
        }

        return null;
    }

    /**
     * Sub-headings read better with their document: "Finance rates" alone is vague,
     * "Northgate finance policy: Finance rates" isn't.
     */
    private function qualify(?string $heading, ?string $title): ?string
    {
        if ($title === null || $heading === null || str_contains(mb_strtolower($heading), mb_strtolower($title))) {
            return $heading ?? $title;
        }

        return "{$title}: {$heading}";
    }

    /**
     * @return list<string>
     */
    private function paragraphs(string $body): array
    {
        $paragraphs = array_map('trim', preg_split("/\n\s*\n/", $body) ?: []);

        return array_values(array_filter($paragraphs, fn (string $p) => $p !== ''));
    }

    /**
     * Greedily pack paragraphs up to the size limit, splitting any single paragraph that's too
     * long on sentence boundaries.
     *
     * @param  list<string>  $paragraphs
     * @return list<string>
     */
    private function pack(array $paragraphs): array
    {
        $pieces = [];

        foreach ($paragraphs as $paragraph) {
            array_push($pieces, ...$this->splitLong($paragraph));
        }

        $chunks = [];
        $current = '';

        foreach ($pieces as $piece) {
            if ($current !== '' && mb_strlen($current) + mb_strlen($piece) + 2 > $this->maxChars) {
                $chunks[] = $current;
                // Carry the tail of the previous chunk forward as overlap.
                $current = $this->tail($current)."\n\n".$piece;

                continue;
            }

            $current = $current === '' ? $piece : $current."\n\n".$piece;
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    /**
     * @return list<string>
     */
    private function splitLong(string $paragraph): array
    {
        if (mb_strlen($paragraph) <= $this->maxChars) {
            return [$paragraph];
        }

        $sentences = preg_split('/(?<=[.!?])\s+/', $paragraph) ?: [$paragraph];
        $out = [];
        $current = '';

        foreach ($sentences as $sentence) {
            if ($current !== '' && mb_strlen($current) + mb_strlen($sentence) + 1 > $this->maxChars) {
                $out[] = $current;
                $current = '';
            }

            $current = trim($current.' '.$sentence);
        }

        if ($current !== '') {
            $out[] = $current;
        }

        return $out;
    }

    private function tail(string $text): string
    {
        if (mb_strlen($text) <= $this->overlapChars) {
            return $text;
        }

        $tail = mb_substr($text, -$this->overlapChars);
        // Start the overlap at a sentence or word boundary, never mid-word.
        $start = preg_match('/[.!?]\s+/', $tail, $m, PREG_OFFSET_CAPTURE) ? $m[0][1] + mb_strlen($m[0][0]) : (int) mb_strpos($tail, ' ') + 1;

        return trim(mb_substr($tail, $start));
    }
}
