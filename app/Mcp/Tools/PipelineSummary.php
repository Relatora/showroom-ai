<?php

namespace App\Mcp\Tools;

use App\Enums\AppointmentStatus;
use App\Enums\LeadStage;
use App\Mcp\Concerns\ActsForStaff;
use App\Models\Appointment;
use App\Models\Lead;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * The dashboard's numbers, for a model: today's workload and the shape of the pipeline. Scoped
 * like everything else, so a rep gets their own pipeline and a manager gets the floor.
 */
#[Name('pipeline_summary')]
#[Description("A snapshot of your pipeline today: open leads per stage with their total budget, how many leads are stale (no contact for more than 3 days), follow-ups due today, and today's scheduled appointments. A good first call when asked how things are going.")]
#[IsReadOnly]
class PipelineSummary extends Tool
{
    use ActsForStaff;

    public function handle(Request $request): ResponseFactory
    {
        $user = $this->staff($request);

        $byStage = Lead::visibleTo($user)->open()
            ->select('stage', DB::raw('count(*) as total'), DB::raw('coalesce(sum(budget_max), 0) as value'))
            ->groupBy('stage')
            ->get()
            ->keyBy(fn (Lead $row) => $row->stage->value);

        return Response::structured([
            'as_of' => now()->toIso8601String(),
            'scope' => $user->seesWholePipeline() ? 'whole dealership' : "{$user->name}'s leads",
            'open_by_stage' => array_map(fn (LeadStage $stage) => [
                'stage' => $stage->value,
                'leads' => (int) ($byStage->get($stage->value)?->getAttribute('total') ?? 0),
                'budget_total' => (int) ($byStage->get($stage->value)?->getAttribute('value') ?? 0),
            ], LeadStage::open()),
            'stale_leads' => Lead::visibleTo($user)->stale()->count(),
            'follow_ups_due_today' => Lead::visibleTo($user)->open()
                ->where('next_follow_up_at', '<=', now()->endOfDay())
                ->count(),
            'appointments_today' => Appointment::visibleTo($user)
                ->whereBetween('starts_at', [now()->startOfDay(), now()->endOfDay()])
                ->where('status', AppointmentStatus::Scheduled)
                ->count(),
            'won_this_month' => Lead::visibleTo($user)
                ->where('stage', LeadStage::Won)
                ->where('closed_at', '>=', now()->startOfMonth())
                ->count(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
