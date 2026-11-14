<?php

namespace App\Http\Controllers\Leads;

use App\Ai\Agents\FollowUpWriter;
use App\Http\Controllers\Controller;
use App\Http\Resources\VehicleResource;
use App\Models\Lead;
use App\Models\Vehicle;
use App\Services\Leads\VehicleMatcher;
use Generator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\StreamedEvent;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Streaming\Events\TextDelta;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * The AI helpers on a lead's page: matching stock to the customer, and drafting a follow-up.
 *
 * Both are plain fetch endpoints rather than Inertia visits. They take seconds (much longer on
 * a CPU), and the page should stay usable while they run instead of blocking on a navigation.
 */
class LeadAssistantController extends Controller
{
    public function matches(Request $request, Lead $lead, VehicleMatcher $matcher): JsonResponse
    {
        Gate::authorize('view', $lead);

        // A structured answer can't be streamed usefully, so give a slow local model room.
        set_time_limit(330);
        $started = hrtime(true);

        try {
            $matches = $matcher->match($lead, $request->user());
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Matching is unavailable right now. Check that Ollama is running (php artisan showroom:ai-check).',
            ], 503);
        }

        return response()->json([
            'matches' => array_map(fn (array $m) => [
                'vehicle' => VehicleResource::make($m['vehicle'])->resolve($request),
                'fit' => $m['fit'],
                'reason' => $m['reason'],
            ], $matches),
            'ms' => (int) ((hrtime(true) - $started) / 1e6),
        ]);
    }

    /**
     * Streams a draft email as server-sent events: "delta" pieces of text, then "done" (or
     * "error"). Same event names as Ask Showroom, so the front end reuses useEventStream.
     */
    public function followUp(Request $request, Lead $lead): StreamedResponse
    {
        // Writing to the customer is acting on the lead, so it needs the same right as editing it.
        Gate::authorize('update', $lead);

        $validated = $request->validate([
            'vehicle_ids' => ['sometimes', 'array', 'max:3'],
            'vehicle_ids.*' => ['integer', 'exists:vehicles,id'],
        ]);

        $vehicles = Vehicle::query()->whereKey($validated['vehicle_ids'] ?? [])->get();
        $prompt = FollowUpWriter::promptFor($lead, $vehicles);
        $writer = new FollowUpWriter($lead->assignee ?? $request->user());

        return response()->eventStream(function () use ($writer, $prompt): Generator {
            set_time_limit(330);
            $started = hrtime(true);

            try {
                foreach ($writer->stream($prompt) as $event) {
                    if ($event instanceof TextDelta) {
                        yield new StreamedEvent('delta', ['text' => $event->delta]);
                    }
                }

                yield new StreamedEvent('done', ['total_ms' => (int) ((hrtime(true) - $started) / 1e6)]);
            } catch (Throwable $e) {
                report($e);

                yield new StreamedEvent('error', [
                    'message' => 'The writer is unavailable right now. Check that Ollama is running (php artisan showroom:ai-check).',
                ]);
            }
        }, endStreamWith: null);
    }
}
