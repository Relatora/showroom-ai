<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\AppointmentStatus;
use App\Enums\LeadSource;
use App\Enums\LeadStage;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\AppointmentResource;
use App\Http\Resources\LeadResource;
use App\Models\Appointment;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The morning view: what needs doing today, and how the pipeline looks.
 *
 * Everything is scoped through Lead::visibleTo(), so a rep sees their own day and a manager sees
 * the floor. The slower aggregate queries are deferred props: the page paints with "today" first
 * and the charts stream in a moment later.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Dashboard', [
            'today' => [
                'appointments' => Appointment::visibleTo($user)
                    ->whereBetween('starts_at', [now()->startOfDay(), now()->endOfDay()])
                    ->where('status', AppointmentStatus::Scheduled)
                    ->count(),
                'followUpsDue' => Lead::visibleTo($user)->open()
                    ->where('next_follow_up_at', '<=', now()->endOfDay())
                    ->count(),
                'stale' => Lead::visibleTo($user)->stale()->count(),
            ],

            // Oldest silence first: the people most likely to have gone cold.
            'callFirst' => LeadResource::collection(
                Lead::visibleTo($user)->stale()
                    ->with(['customer', 'assignee', 'vehicle'])
                    ->orderByRaw('last_contacted_at asc nulls first')
                    ->limit(6)
                    ->get(),
            ),

            'schedule' => AppointmentResource::collection(
                Appointment::visibleTo($user)
                    ->whereBetween('starts_at', [now()->startOfDay(), now()->endOfDay()])
                    ->with(['lead.customer', 'user', 'vehicle'])
                    ->orderBy('starts_at')
                    ->get(),
            ),

            'pipeline' => Inertia::defer(fn () => $this->pipeline($user)),
            'sources' => Inertia::defer(fn () => $this->sources($user)),
            'leaderboard' => $user->seesWholePipeline()
                ? Inertia::defer(fn () => $this->leaderboard())
                : null,
        ]);
    }

    /**
     * Open leads per stage, with the sum of their stated budgets.
     *
     * @return list<array{stage: string, count: int, value: int}>
     */
    private function pipeline(User $user): array
    {
        $rows = Lead::visibleTo($user)->open()
            ->select('stage', DB::raw('count(*) as total'), DB::raw('coalesce(sum(budget_max), 0) as value'))
            ->groupBy('stage')
            ->get()
            ->keyBy(fn (Lead $row) => $row->stage->value);

        return array_map(fn (LeadStage $stage) => [
            'stage' => $stage->value,
            'count' => (int) $rows->get($stage->value)?->getAttribute('total'),
            'value' => (int) $rows->get($stage->value)?->getAttribute('value'),
        ], LeadStage::open());
    }

    /**
     * Where the last 90 days of leads came from, and how many of each turned into a sale.
     *
     * @return array<int, array{source: string, count: int, won: int}>
     */
    private function sources(User $user): array
    {
        $rows = Lead::visibleTo($user)
            ->where('created_at', '>=', now()->subDays(90))
            ->select(
                'source',
                DB::raw('count(*) as total'),
                DB::raw("count(*) filter (where stage = 'won') as won"),
            )
            ->groupBy('source')
            ->get()
            ->keyBy(fn (Lead $row) => $row->source->value);

        return collect(LeadSource::cases())
            ->map(fn (LeadSource $source) => [
                'source' => $source->value,
                'count' => (int) $rows->get($source->value)?->getAttribute('total'),
                'won' => (int) $rows->get($source->value)?->getAttribute('won'),
            ])
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    /**
     * Deals won this month per rep, for managers.
     *
     * @return array<int, array{id: int, name: string, won: int, open: int}>
     */
    private function leaderboard(): array
    {
        return User::where('role', UserRole::Rep)
            ->withCount([
                'leads as won_count' => fn ($q) => $q->where('stage', LeadStage::Won)
                    ->where('closed_at', '>=', now()->startOfMonth()),
                'leads as open_count' => fn ($q) => $q->whereIn('stage', LeadStage::open()),
            ])
            ->orderByDesc('won_count')
            ->orderBy('name')
            ->get()
            ->map(fn (User $rep) => [
                'id' => $rep->id,
                'name' => $rep->name,
                'won' => (int) $rep->getAttribute('won_count'),
                'open' => (int) $rep->getAttribute('open_count'),
            ])
            ->all();
    }
}
