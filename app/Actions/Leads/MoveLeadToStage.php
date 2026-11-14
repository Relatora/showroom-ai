<?php

namespace App\Actions\Leads;

use App\Enums\ActivityType;
use App\Enums\LeadStage;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Moves a lead along the pipeline (from the board, the stage stepper or an AI client).
 *
 * Besides changing the stage it keeps the board order tidy, stamps closed_at when the lead is
 * won or lost, and writes a timeline entry so there's a record of who moved it and when.
 */
class MoveLeadToStage
{
    public function __construct(private readonly LogActivity $logActivity) {}

    public function handle(Lead $lead, LeadStage $stage, ?User $by = null, ?int $position = null, ?string $lostReason = null): Lead
    {
        return DB::transaction(function () use ($lead, $stage, $by, $position, $lostReason) {
            $from = $lead->stage;
            $position ??= (int) Lead::where('stage', $stage)->max('position') + 1;

            // Make room at the drop position so two cards never share a slot.
            Lead::where('stage', $stage)
                ->where('position', '>=', $position)
                ->whereKeyNot($lead->getKey())
                ->increment('position');

            $lead->forceFill([
                'stage' => $stage,
                'position' => $position,
                'closed_at' => $stage->isOpen() ? null : ($lead->closed_at ?? now()),
                'lost_reason' => $stage === LeadStage::Lost ? $lostReason : null,
            ])->save();

            if ($from !== $stage) {
                $this->logActivity->handle(
                    $lead,
                    $by,
                    ActivityType::StageChange,
                    "Moved from {$from->label()} to {$stage->label()}.".($lostReason ? " Reason: {$lostReason}." : ''),
                    ['from' => $from->value, 'to' => $stage->value],
                );
            }

            return $lead;
        });
    }
}
