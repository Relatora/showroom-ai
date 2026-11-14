<?php

namespace App\Http\Controllers\Assistant;

use App\Ai\Agents\ShowroomAssistant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Assistant\AskRequest;
use App\Models\Document;
use App\Models\Lead;
use App\Models\Vehicle;
use App\Services\Knowledge\HybridRetriever;
use App\Services\Knowledge\RetrievedChunk;
use Generator;
use Illuminate\Http\Request;
use Illuminate\Http\StreamedEvent;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Ai\Streaming\Events\TextDelta;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Ask Showroom: the chat page, and the endpoint that streams its answers.
 */
class AssistantController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('assistant/Index', [
            // What the assistant can draw on *for this user*: a rep only counts their own leads.
            'coverage' => [
                'documents' => Document::where('status', 'ready')->count(),
                'vehicles' => Vehicle::count(),
                'leads' => Lead::visibleTo($request->user())->count(),
            ],
            // Questions that show off what the knowledge base can answer. Each one is backed by
            // a seeded policy, so a first-time visitor gets a good answer on the first click.
            'suggestions' => [
                'How long is a trade-in appraisal good for?',
                'Can a customer take a car home overnight?',
                'What does the certified pre-owned warranty cover?',
                'How much range does an EV lose in winter?',
                'Which customers are looking for something that can tow?',
            ],
        ]);
    }

    /**
     * Streams the answer as server-sent events:
     *
     *   sources  the passages we retrieved, sent first so the citation chips can render early
     *   delta    a piece of the answer text, many of these
     *   done     timing, so the UI (and anyone reading the network tab) can see where time went
     *   error    something went wrong; the message is safe to show
     */
    public function ask(AskRequest $request, HybridRetriever $retriever): StreamedResponse
    {
        $user = $request->user();
        $question = $request->string('question')->trim()->toString();

        return response()->eventStream(function () use ($request, $retriever, $user, $question): Generator {
            // PHP's default 30-second limit kills a long answer mid-sentence (a local model on a
            // CPU easily takes longer). Match the agent's own timeout instead, so the model call
            // gives up first and we can still send a proper error event.
            set_time_limit(330);

            $started = hrtime(true);
            $finished = false;

            try {
                $sources = $retriever->search(
                    $this->searchQuery($question, $request->history()),
                    $user,
                    config('showroom.assistant.sources'),
                );
                $retrievalMs = (int) ((hrtime(true) - $started) / 1e6);

                yield new StreamedEvent('sources', [
                    'sources' => array_map(fn (RetrievedChunk $s) => $s->toArray(), $sources),
                    'ms' => $retrievalMs,
                ]);

                $stream = (new ShowroomAssistant($user, $request->history()))
                    ->stream(ShowroomAssistant::promptFor($question, $sources));

                $firstTokenMs = null;

                foreach ($stream as $event) {
                    if ($event instanceof TextDelta) {
                        $firstTokenMs ??= (int) ((hrtime(true) - $started) / 1e6);

                        yield new StreamedEvent('delta', ['text' => $event->delta]);
                    }
                }

                $timing = [
                    'retrieval_ms' => $retrievalMs,
                    'first_token_ms' => $firstTokenMs,
                    'total_ms' => (int) ((hrtime(true) - $started) / 1e6),
                ];

                // Finish the stream before logging, so a logging problem can never cost the
                // user the end of their answer.
                yield new StreamedEvent('done', $timing);
                $finished = true;

                // One log line per answer: enough to spot slow or source-less answers later
                // without storing what anyone asked.
                Log::info('assistant.answered', [
                    'user_id' => $user->id,
                    'sources' => array_map(fn (RetrievedChunk $s) => $s->chunk->id, $sources),
                    ...$timing,
                ]);
            } catch (Throwable $e) {
                report($e);

                if ($finished) {
                    return;
                }

                yield new StreamedEvent('error', [
                    'message' => 'The assistant is unavailable right now. Check that Ollama is running (php artisan showroom:ai-check).',
                ]);
            }
        }, endStreamWith: null);
    }

    /**
     * A follow-up like "what if the car costs $22,000?" means nothing to a search engine on its
     * own; it only makes sense next to the question before it. The textbook fix is an extra
     * model call that rewrites it into a standalone question, but on a local model that adds
     * seconds to every answer. Searching with the previous question and the new one together
     * gets most of the benefit for free.
     *
     * @param  list<array{role: string, content: string}>  $history
     */
    private function searchQuery(string $question, array $history): string
    {
        $previous = collect($history)->where('role', 'user')->last();

        return $previous ? "{$previous['content']} {$question}" : $question;
    }
}
