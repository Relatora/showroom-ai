<?php

namespace App\Services\Knowledge;

use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Smalot\PdfParser\Parser;

/**
 * Turns an uploaded file into plain text.
 */
class TextExtractor
{
    public function __construct(private readonly Parser $pdf) {}

    public function extract(Document $document): string
    {
        $bytes = Storage::disk('documents')->get($document->disk_path)
            ?? throw new RuntimeException("File {$document->disk_path} is missing from storage.");

        $text = match (true) {
            $document->mime_type === 'application/pdf' => $this->pdf->parseContent($bytes)->getText(),
            str_starts_with($document->mime_type, 'text/') => $bytes,
            default => throw new RuntimeException("Can't read {$document->mime_type} files yet. Upload a PDF, Markdown or text file."),
        };

        $text = trim($text);

        // A scanned PDF is just pictures of text. Better to say so than index nothing silently.
        if (mb_strlen($text) < 40) {
            throw new RuntimeException('No readable text found. If this is a scanned PDF, it needs OCR first.');
        }

        return $text;
    }
}
