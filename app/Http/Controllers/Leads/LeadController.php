<?php

namespace App\Http\Controllers\Leads;

use App\Actions\Leads\CreateLead;
use App\Enums\LeadStage;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leads\StoreLeadRequest;
use App\Http\Requests\Leads\UpdateLeadRequest;
use App\Http\Resources\LeadResource;
use App\Http\Resources\UserSummaryResource;
use App\Http\Resources\VehicleResource;
use App\Models\Lead;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LeadController extends Controller
{
    /**
     * The pipeline, as a kanban board or a filterable list.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $filters = $request->only(['q', 'temperature', 'assignee', 'stale']);
        $view = $request->query('view') === 'list' ? 'list' : 'board';

        $query = $this->filtered(Lead::visibleTo($user), $filters, $user)
            ->with(['customer', 'assignee', 'vehicle']);

        $leads = $view === 'board'
            // The board shows everything open, plus what closed in the last fortnight so a win
            // doesn't vanish the moment you drag it into the column.
            ? $query->where(fn (Builder $q) => $q
                ->whereIn('stage', LeadStage::open())
                ->orWhere('closed_at', '>=', now()->subDays(14)))
                ->orderBy('position')
                ->get()
            : $query->orderByRaw('last_contacted_at asc nulls first')
                ->paginate(20)
                ->withQueryString();

        return Inertia::render('leads/Index', [
            'view' => $view,
            // Same shape either way (a "data" key), so the page doesn't care which view it's in.
            'leads' => $view === 'board' ? ['data' => LeadResource::collection($leads)] : LeadResource::collection($leads),
            'filters' => $filters,
            'team' => $user->seesWholePipeline()
                ? UserSummaryResource::collection(User::where('role', UserRole::Rep)->orderBy('name')->get())
                : [],
        ]);
    }

    public function show(Request $request, Lead $lead): Response
    {
        Gate::authorize('view', $lead);

        $lead->load([
            'customer',
            'assignee',
            'vehicle',
            'activities.user',
            'appointments' => fn ($q) => $q->with(['vehicle', 'user'])->orderByDesc('starts_at'),
        ]);

        return Inertia::render('leads/Show', [
            'lead' => LeadResource::make($lead),
            'team' => $request->user()->can('reassign', $lead)
                ? UserSummaryResource::collection(User::where('role', UserRole::Rep)->orderBy('name')->get())
                : [],
            // Lazily loaded: only fetched when the rep opens the "change vehicle" picker.
            'inventory' => Inertia::optional(fn () => VehicleResource::collection(
                Vehicle::available()->orderBy('make')->orderBy('model')->get(),
            )),
        ]);
    }

    public function store(StoreLeadRequest $request, CreateLead $createLead): RedirectResponse
    {
        /** @var array{first_name: string, last_name: string, email?: string|null, phone?: string|null, source: string, interest: string, budget_max?: int|null, temperature: string, trade_in?: string|null, vehicle_id?: int|null, assigned_user_id?: int|null} $data */
        $data = $request->validated();

        $lead = $createLead->handle($data, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Lead created.']);

        return to_route('leads.show', $lead);
    }

    public function update(UpdateLeadRequest $request, Lead $lead): RedirectResponse
    {
        $lead->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Lead updated.']);

        return back();
    }

    /**
     * @param  Builder<Lead>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<Lead>
     */
    private function filtered(Builder $query, array $filters, User $user): Builder
    {
        return $query
            ->when($filters['q'] ?? null, function (Builder $q, string $term) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

                $q->where(fn (Builder $inner) => $inner
                    ->where('interest', 'ilike', $like)
                    ->orWhereHas('customer', fn (Builder $c) => $c
                        ->where('first_name', 'ilike', $like)
                        ->orWhere('last_name', 'ilike', $like)
                        ->orWhere('email', 'ilike', $like)
                        ->orWhereRaw("first_name || ' ' || last_name ilike ?", [$like])));
            })
            ->when($filters['temperature'] ?? null, fn (Builder $q, string $t) => $q->where('temperature', $t))
            ->when($filters['assignee'] ?? null, fn (Builder $q, string $a) => match ($a) {
                'me' => $q->where('assigned_user_id', $user->id),
                'unassigned' => $q->whereNull('assigned_user_id'),
                default => $q->where('assigned_user_id', (int) $a),
            })
            ->when(filter_var($filters['stale'] ?? false, FILTER_VALIDATE_BOOL), fn (Builder $q) => $q->stale());
    }
}
