<?php

namespace App\Models;

use App\Contracts\Indexable;
use App\Services\Knowledge\Chunker;
use App\Services\Knowledge\TextExtractor;
use Carbon\CarbonImmutable;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * An uploaded dealership document: a policy, a warranty booklet, a finance rate sheet.
 *
 * @property int $id
 * @property string $title
 * @property string $category
 * @property string $disk_path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property string $status
 * @property string|null $error
 * @property int $chunks_count
 * @property int|null $uploaded_by
 * @property CarbonImmutable|null $indexed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User|null $uploader
 */
#[Fillable(['title', 'category', 'disk_path', 'original_name', 'mime_type', 'size_bytes', 'status', 'error', 'chunks_count', 'uploaded_by', 'indexed_at'])]
class Document extends Model implements Indexable
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    public const CATEGORIES = [
        'policy' => 'Dealership policy',
        'warranty' => 'Warranty',
        'finance' => 'Finance and leasing',
        'spec_sheet' => 'Spec sheet',
        'process' => 'Sales process',
        'other' => 'Other',
    ];

    protected function casts(): array
    {
        return ['indexed_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @return MorphMany<Chunk, $this>
     */
    public function chunks(): MorphMany
    {
        return $this->morphMany(Chunk::class, 'source')->orderBy('position');
    }

    public function markAs(string $status, ?string $error = null): void
    {
        $this->forceFill(['status' => $status, 'error' => $error])->save();
    }

    public function toChunks(): array
    {
        $text = app(TextExtractor::class)->extract($this);

        return app(Chunker::class)->split($text, $this->title);
    }

    public function indexLeadId(): ?int
    {
        return null;
    }
}
