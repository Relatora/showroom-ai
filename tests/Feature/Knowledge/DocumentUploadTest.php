<?php

use App\Models\Document;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Embeddings;

beforeEach(function () {
    Storage::fake('documents');
    Embeddings::fake();
});

it('lets a manager upload a policy and indexes it into passages', function () {
    $markdown = "# Holds\n\nA deposit of 500 dollars holds a vehicle for 72 hours.\n\n## Refunds\n\nRefunds take 5 business days.";

    $this->actingAs(User::factory()->manager()->create())
        ->post(route('knowledge.store'), [
            'title' => 'Deposit policy',
            'category' => 'policy',
            'file' => UploadedFile::fake()->createWithContent('deposit.md', $markdown),
        ])
        ->assertRedirect(route('knowledge.index'));

    $document = Document::sole();

    expect($document->status)->toBe('ready')
        ->and($document->chunks_count)->toBe(2)
        ->and($document->chunks()->pluck('heading')->all())->toBe(['Deposit policy: Holds', 'Deposit policy: Refunds']);
    Storage::disk('documents')->assertExists($document->disk_path);
});

it('marks a document failed with advice when there is no readable text', function () {
    $this->actingAs(User::factory()->manager()->create())
        ->post(route('knowledge.store'), [
            'title' => 'Empty',
            'category' => 'other',
            'file' => UploadedFile::fake()->createWithContent('empty.txt', 'hi'),
        ]);

    expect(Document::sole())->status->toBe('failed')->error->toContain('No readable text');
});

it('only lets managers change the knowledge base', function () {
    $rep = User::factory()->create();

    $this->actingAs($rep)->get(route('knowledge.index'))->assertOk();
    $this->actingAs($rep)
        ->post(route('knowledge.store'), [
            'title' => 'x',
            'category' => 'other',
            'file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
        ])
        ->assertForbidden();
});
