<?php

namespace Database\Seeders\Demo;

use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Uploads Northgate's policy documents to object storage, the same way a manager would through
 * the knowledge base page.
 *
 * They're written for this project (no real dealer's material), with specific facts that the
 * assistant's evaluation questions check against: policy numbers, limits, time windows.
 * Indexing happens afterwards with `php artisan showroom:index`.
 */
class DemoDocumentsSeeder extends Seeder
{
    private const CATEGORIES = [
        'trade-in-policy' => 'policy',
        'deposit-and-hold-policy' => 'policy',
        'finance-rates' => 'finance',
        'extended-warranty' => 'warranty',
        'certified-pre-owned' => 'warranty',
        'ev-buyer-guide' => 'spec_sheet',
        'test-drive-process' => 'process',
        'lead-response-standards' => 'process',
        'pricing-and-negotiation' => 'policy',
        'delivery-checklist' => 'process',
    ];

    public function run(): void
    {
        Artisan::call('showroom:storage-init');

        $manager = User::where('email', 'dana@northgate.test')->first();

        foreach (glob(__DIR__.'/documents/*.md') ?: [] as $file) {
            $slug = basename($file, '.md');
            $markdown = (string) file_get_contents($file);
            $title = Str::of($markdown)->match('/^#\s+(.+)$/m')->trim()->toString() ?: Str::headline($slug);
            $path = "documents/{$slug}.md";

            Storage::disk('documents')->put($path, $markdown);

            Document::updateOrCreate(['disk_path' => $path], [
                'title' => $title,
                'category' => self::CATEGORIES[$slug] ?? 'other',
                'original_name' => "{$slug}.md",
                'mime_type' => 'text/markdown',
                'size_bytes' => strlen($markdown),
                'status' => 'pending',
                'uploaded_by' => $manager?->id,
            ]);
        }
    }
}
