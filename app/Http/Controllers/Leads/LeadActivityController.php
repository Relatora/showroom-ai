<?php

namespace App\Http\Controllers\Leads;

use App\Actions\Leads\LogActivity;
use App\Enums\ActivityType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leads\StoreActivityRequest;
use App\Models\Lead;
use Illuminate\Http\RedirectResponse;

class LeadActivityController extends Controller
{
    public function store(StoreActivityRequest $request, Lead $lead, LogActivity $log): RedirectResponse
    {
        $log->handle(
            $lead,
            $request->user(),
            ActivityType::from($request->string('type')->toString()),
            $request->string('body')->toString(),
        );

        return back();
    }
}
