<?php

use App\Ai\Agents\FollowUpWriter;
use App\Ai\Agents\InventoryMatcher;
use App\Enums\ActivityType;
use App\Enums\VehicleStatus;
use App\Models\Activity;
use App\Models\Lead;
use App\Models\User;
use App\Models\Vehicle;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Prompts\AgentPrompt;

beforeEach(function () {
    Embeddings::fake();

    $this->rep = User::factory()->create(['name' => 'Marcus Chen']);
    $this->lead = Lead::factory()->assignedTo($this->rep)->create([
        'interest' => 'A pickup that can tow a 6,000 lb boat',
        'budget_max' => 60000,
    ]);
});

function truck(array $attributes = []): Vehicle
{
    return Vehicle::factory()->create([
        'body_style' => 'truck',
        'towing_lb' => 8000,
        'price' => 55000,
        'status' => VehicleStatus::Available,
        ...$attributes,
    ]);
}

it('shortlists only available stock within budget, then lets the model rank it', function () {
    $fits = truck(['stock_number' => 'S2001']);
    truck(['stock_number' => 'S2002', 'price' => 90000]);              // way over budget
    truck(['stock_number' => 'S2003', 'status' => VehicleStatus::Sold]); // gone

    InventoryMatcher::fake([[
        'matches' => [
            ['stock_number' => 'S2001', 'fit' => 'strong', 'reason' => 'Tows 8,000 lb, well over the boat.'],
            ['stock_number' => 'S9999', 'fit' => 'good', 'reason' => 'A truck we do not have.'],
        ],
    ]]);

    $response = $this->actingAs($this->rep)
        ->postJson(route('leads.matches', $this->lead))
        ->assertOk();

    // The made-up stock number was dropped; only the real, eligible truck came back.
    expect($response->json('matches'))->toHaveCount(1)
        ->and($response->json('matches.0.vehicle.id'))->toBe($fits->id)
        ->and($response->json('matches.0.fit'))->toBe('strong');

    // The model was only ever shown the eligible truck.
    InventoryMatcher::assertPrompted(fn (AgentPrompt $p) => str_contains($p->prompt, 'S2001')
        && ! str_contains($p->prompt, 'S2002')
        && ! str_contains($p->prompt, 'S2003'));
});

it("doesn't call the model when nothing in stock fits", function () {
    InventoryMatcher::fake();

    $this->actingAs($this->rep)
        ->postJson(route('leads.matches', $this->lead))
        ->assertOk()
        ->assertJsonPath('matches', []);

    InventoryMatcher::assertNeverPrompted();
});

it('streams a follow-up draft built from the timeline, with contact details masked', function () {
    Activity::factory()->for($this->lead)->create([
        'type' => ActivityType::Call,
        'body' => 'Wants to see the F-150 on Saturday. Cell is 416-555-0199.',
    ]);
    $truck = truck(['stock_number' => 'S2001']);

    FollowUpWriter::fake(["Hi there,\n\nGreat chatting about the F-150."]);

    $body = $this->actingAs($this->rep)
        ->post(route('leads.follow-up', $this->lead), ['vehicle_ids' => [$truck->id]])
        ->assertOk()
        ->streamedContent();

    expect(sseText($body))->toBe("Hi there,\n\nGreat chatting about the F-150.")
        ->and(array_column(sseEvents($body), 0))->toContain('done');

    FollowUpWriter::assertPrompted(fn (AgentPrompt $p) => str_contains($p->prompt, 'Wants to see the F-150 on Saturday')
        && str_contains($p->prompt, '[phone]')
        && ! str_contains($p->prompt, '416-555-0199')
        && str_contains($p->prompt, $truck->title));
});

it("keeps reps out of other reps' leads", function () {
    InventoryMatcher::fake();
    FollowUpWriter::fake();
    $otherRep = User::factory()->create();

    $this->actingAs($otherRep)->postJson(route('leads.matches', $this->lead))->assertForbidden();
    $this->actingAs($otherRep)->post(route('leads.follow-up', $this->lead))->assertForbidden();
});

it('returns a friendly 503 when the model is down', function () {
    truck();
    InventoryMatcher::fake(fn () => throw new RuntimeException('Connection refused'));

    $this->actingAs($this->rep)
        ->postJson(route('leads.matches', $this->lead))
        ->assertStatus(503)
        ->assertJsonPath('message', fn (string $m) => str_contains($m, 'showroom:ai-check'));
});
