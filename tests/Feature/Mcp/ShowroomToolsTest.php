<?php

use App\Enums\ActivityType;
use App\Enums\LeadStage;
use App\Enums\LeadTemperature;
use App\Enums\VehicleStatus;
use App\Mcp\Prompts\DailyLeadReview;
use App\Mcp\Resources\PolicyDocument;
use App\Mcp\Resources\PolicyIndex;
use App\Mcp\Servers\ShowroomServer;
use App\Mcp\Tools\FindMatchingVehicles;
use App\Mcp\Tools\GetCustomerTimeline;
use App\Mcp\Tools\LogActivity;
use App\Mcp\Tools\PipelineSummary;
use App\Mcp\Tools\SearchKnowledgeBase;
use App\Mcp\Tools\SearchLeads;
use App\Models\Activity;
use App\Models\Chunk;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Lead;
use App\Models\User;
use App\Models\Vehicle;

beforeEach(function () {
    $this->rep = User::factory()->create(['name' => 'Marcus Chen']);
    $this->manager = User::factory()->manager()->create();

    $this->mine = Lead::factory()->assignedTo($this->rep)->create([
        'customer_id' => Customer::factory()->create(['first_name' => 'Amara', 'last_name' => 'Singh']),
        'interest' => 'A pickup that tows a 6,000 lb boat',
        'budget_max' => 60000,
        'stage' => LeadStage::Negotiation,
        'temperature' => LeadTemperature::Hot,
        'last_contacted_at' => now()->subDays(6),
    ]);
    $this->theirs = Lead::factory()->create([
        'customer_id' => Customer::factory()->create(['first_name' => 'Zed', 'last_name' => 'Other']),
        'interest' => 'Another pickup for towing',
    ]);
});

describe('search_leads', function () {
    it("finds the rep's own leads, stalest first, and never anyone else's", function () {
        ShowroomServer::actingAs($this->rep)
            ->tool(SearchLeads::class, ['query' => 'pickup'])
            ->assertOk()
            ->assertStructuredContentPath('count', 1)
            ->assertStructuredContentPath('leads.0.customer', 'Amara Singh')
            ->assertStructuredContentPath('leads.0.is_stale', true)
            ->assertDontSee('Zed Other');
    });

    it('lets a manager see the whole floor and filter it', function () {
        ShowroomServer::actingAs($this->manager)
            ->tool(SearchLeads::class, ['query' => 'pickup'])
            ->assertStructuredContentPath('count', 2);

        ShowroomServer::actingAs($this->manager)
            ->tool(SearchLeads::class, ['stage' => 'negotiation', 'temperature' => 'hot', 'stale_only' => true])
            ->assertSee('Amara Singh')
            ->assertDontSee('Zed Other');
    });

    it('finds customers by name', function () {
        ShowroomServer::actingAs($this->rep)
            ->tool(SearchLeads::class, ['query' => 'singh'])
            ->assertStructuredContentPath('count', 1);
    });

    it('rejects a stage that does not exist', function () {
        ShowroomServer::actingAs($this->rep)
            ->tool(SearchLeads::class, ['stage' => 'maybe'])
            ->assertHasErrors();
    });
});

describe('get_customer_timeline', function () {
    it('returns the timeline with contact details masked', function () {
        Activity::factory()->for($this->mine)->create([
            'type' => ActivityType::Call,
            'body' => 'Call back on 416-555-0199 about the payout letter.',
        ]);

        ShowroomServer::actingAs($this->rep)
            ->tool(GetCustomerTimeline::class, ['lead_id' => $this->mine->id])
            ->assertOk()
            ->assertSee(['Amara Singh', 'payout letter', '[phone]'])
            ->assertDontSee('416-555-0199');
    });

    it("treats another rep's lead exactly like one that doesn't exist", function () {
        ShowroomServer::actingAs($this->rep)
            ->tool(GetCustomerTimeline::class, ['lead_id' => $this->theirs->id])
            ->assertHasErrors(["There's no lead #{$this->theirs->id} that you can see."]);

        ShowroomServer::actingAs($this->rep)
            ->tool(GetCustomerTimeline::class, ['lead_id' => 999999])
            ->assertHasErrors(["There's no lead #999999 that you can see."]);
    });
});

describe('find_matching_vehicles', function () {
    it('returns available stock within budget, with the facts that matter', function () {
        Vehicle::factory()->create(['stock_number' => 'S3001', 'price' => 55000, 'towing_lb' => 9000, 'status' => VehicleStatus::Available]);
        Vehicle::factory()->create(['stock_number' => 'S3002', 'price' => 95000, 'status' => VehicleStatus::Available]);
        Vehicle::factory()->create(['stock_number' => 'S3003', 'price' => 40000, 'status' => VehicleStatus::Sold]);

        ShowroomServer::actingAs($this->rep)
            ->tool(FindMatchingVehicles::class, ['lead_id' => $this->mine->id])
            ->assertOk()
            ->assertSee(['S3001', 'tows 9,000 lb'])
            ->assertDontSee(['S3002', 'S3003']);
    });

    it('says so when nothing fits', function () {
        ShowroomServer::actingAs($this->rep)
            ->tool(FindMatchingVehicles::class, ['lead_id' => $this->mine->id])
            ->assertSee('Nothing available in stock fits Amara Singh');
    });
});

describe('search_knowledge_base', function () {
    it('returns cited passages from the policies', function () {
        Chunk::create([
            'source_type' => 'document', 'source_id' => 1, 'position' => 1,
            'heading' => 'Trade-in Appraisal Policy (F-204): How long an offer is valid',
            'content' => 'A written trade-in offer is valid for 7 days or 500 additional kilometres.',
            'token_estimate' => 20, 'content_hash' => 'x', 'embedding' => array_fill(0, 768, 0.01),
        ]);

        ShowroomServer::actingAs($this->rep)
            ->tool(SearchKnowledgeBase::class, ['query' => 'F-204 offer valid', 'only' => ['document']])
            ->assertOk()
            ->assertStructuredContentPath('passages.0.source', 'Trade-in Appraisal Policy (F-204)')
            ->assertStructuredContentPath('passages.0.section', 'How long an offer is valid')
            ->assertSee('7 days');
    });

    it("doesn't surface another rep's customer notes", function () {
        ShowroomServer::actingAs($this->rep)
            ->tool(SearchKnowledgeBase::class, ['query' => 'Another pickup for towing', 'limit' => 10])
            ->assertDontSee('Zed Other');
    });
});

describe('log_activity', function () {
    it('writes to the timeline as the user and resets the stale clock for real contact', function () {
        ShowroomServer::actingAs($this->rep)
            ->tool(LogActivity::class, ['lead_id' => $this->mine->id, 'type' => 'call', 'body' => 'Confirmed Saturday test drive.'])
            ->assertOk()
            ->assertStructuredContentPath('logged', true)
            ->assertStructuredContentPath('resets_stale_clock', true);

        $activity = $this->mine->activities()->first();

        expect($activity->body)->toBe('Confirmed Saturday test drive.')
            ->and($activity->user_id)->toBe($this->rep->id)
            ->and($activity->meta)->toBe(['via' => 'mcp'])
            ->and($this->mine->fresh()->isStale())->toBeFalse();
    });

    it("doesn't reset the clock for a private note", function () {
        ShowroomServer::actingAs($this->rep)
            ->tool(LogActivity::class, ['lead_id' => $this->mine->id, 'type' => 'note', 'body' => 'Spouse prefers grey.'])
            ->assertStructuredContentPath('resets_stale_clock', false);

        expect($this->mine->fresh()->isStale())->toBeTrue();
    });

    it("refuses stage changes and other reps' leads", function () {
        ShowroomServer::actingAs($this->rep)
            ->tool(LogActivity::class, ['lead_id' => $this->mine->id, 'type' => 'stage_change', 'body' => 'Moved to Won.'])
            ->assertHasErrors();

        ShowroomServer::actingAs($this->rep)
            ->tool(LogActivity::class, ['lead_id' => $this->theirs->id, 'type' => 'call', 'body' => 'Sneaky.'])
            ->assertHasErrors();

        expect(Activity::where('body', 'Sneaky.')->exists())->toBeFalse();
    });
});

describe('pipeline_summary', function () {
    it("summarises only the rep's own pipeline", function () {
        ShowroomServer::actingAs($this->rep)
            ->tool(PipelineSummary::class)
            ->assertOk()
            ->assertStructuredContentPath('scope', "Marcus Chen's leads")
            ->assertStructuredContentPath('stale_leads', 1)
            ->assertSee('"stage":"negotiation","leads":1,"budget_total":60000');
    });

    it('gives a manager the whole dealership', function () {
        ShowroomServer::actingAs($this->manager)
            ->tool(PipelineSummary::class)
            ->assertStructuredContentPath('scope', 'whole dealership');
    });
});

it('refuses every tool without a signed-in user', function () {
    ShowroomServer::tool(PipelineSummary::class)->assertHasErrors();
});

it('offers a daily review prompt that walks the tools', function () {
    ShowroomServer::actingAs($this->rep)
        ->prompt(DailyLeadReview::class, ['how_many' => 2, 'focus' => 'EV buyers'])
        ->assertOk()
        ->assertSee(['pipeline_summary', 'pick the 2 leads', 'Focus especially on: EV buyers']);
});

it('exposes the policies as readable resources', function () {
    $document = Document::factory()->create(['title' => 'Trade-in Appraisal Policy (F-204)', 'status' => 'ready']);
    Chunk::create([
        'source_type' => 'document', 'source_id' => $document->id, 'position' => 0,
        'heading' => 'Trade-in Appraisal Policy (F-204): How long an offer is valid',
        'content' => 'Valid for 7 days.', 'token_estimate' => 5, 'content_hash' => 'y',
        'embedding' => array_fill(0, 768, 0.01),
    ]);

    ShowroomServer::actingAs($this->rep)
        ->resource(PolicyIndex::class)
        ->assertSee("showroom://policies/{$document->id}");

    ShowroomServer::actingAs($this->rep)
        ->resource(PolicyDocument::class, ['id' => $document->id])
        ->assertSee(['# Trade-in Appraisal Policy (F-204)', '## How long an offer is valid', 'Valid for 7 days.']);
});
