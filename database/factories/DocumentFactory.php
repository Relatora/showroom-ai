<?php

namespace Database\Factories;

use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => 'Trade-in policy',
            'category' => 'policy',
            'disk_path' => 'documents/'.fake()->uuid().'.md',
            'original_name' => 'trade-in.md',
            'mime_type' => 'text/markdown',
            'size_bytes' => 1200,
            'status' => 'pending',
        ];
    }
}
