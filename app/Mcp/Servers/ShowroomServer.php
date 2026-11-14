<?php

namespace App\Mcp\Servers;

use App\Mcp\Prompts\DailyLeadReview;
use App\Mcp\Resources\PolicyDocument;
use App\Mcp\Resources\PolicyIndex;
use App\Mcp\Tools\FindMatchingVehicles;
use App\Mcp\Tools\GetCustomerTimeline;
use App\Mcp\Tools\LogActivity;
use App\Mcp\Tools\PipelineSummary;
use App\Mcp\Tools\SearchKnowledgeBase;
use App\Mcp\Tools\SearchLeads;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

/**
 * Showroom over the Model Context Protocol: the CRM as tools any MCP client can use (Claude
 * Desktop, an IDE, another agent). See docs/MCP.md for connecting one.
 *
 * Everything runs as the connected user and through the same scopes and policies as the web UI,
 * so this is a second front door, not a back door.
 */
#[Name('Showroom')]
#[Version('0.5.0')]
#[Instructions(<<<'TEXT'
    Showroom is the CRM for Northgate Motors, a car dealership in Ontario. You are acting as the signed-in staff member and see only the leads they can see.
    - Start broad with pipeline_summary or search_leads, then drill into one lead with get_customer_timeline.
    - Use find_matching_vehicles to suggest stock; it only returns available vehicles within budget.
    - Answer policy questions from search_knowledge_base and name the policy (e.g. "Trade-in policy F-204"). Never guess at rates, prices or rules.
    - log_activity writes to the customer's timeline under the user's name. Confirm the exact text with the user first.
    - Contact details are masked as [phone] and [email]; the user can see the real ones in the CRM via each lead's url.
    TEXT)]
class ShowroomServer extends Server
{
    protected array $tools = [
        PipelineSummary::class,
        SearchLeads::class,
        GetCustomerTimeline::class,
        FindMatchingVehicles::class,
        SearchKnowledgeBase::class,
        LogActivity::class,
    ];

    protected array $resources = [
        PolicyIndex::class,
        PolicyDocument::class,
    ];

    protected array $prompts = [
        DailyLeadReview::class,
    ];
}
