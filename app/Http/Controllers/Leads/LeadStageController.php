<?php

namespace App\Http\Controllers\Leads;

use App\Actions\Leads\MoveLeadToStage;
use App\Enums\LeadStage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leads\MoveLeadStageRequest;
use App\Models\Lead;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Moving a card on the board, or clicking a step on the lead page.
 */
class LeadStageController extends Controller
{
    public function update(MoveLeadStageRequest $request, Lead $lead, MoveLeadToStage $move): RedirectResponse
    {
        $stage = LeadStage::from($request->string('stage')->toString());

        $move->handle(
            $lead,
            $stage,
            $request->user(),
            $request->filled('position') ? $request->integer('position') : null,
            $request->filled('lost_reason') ? $request->string('lost_reason')->toString() : null,
        );

        Inertia::flash('toast', $stage === LeadStage::Won
            ? ['type' => 'success', 'message' => 'Deal won. Nice work! 🎉']
            : ['type' => 'success', 'message' => "Moved to {$stage->label()}."]);

        return back();
    }
}
