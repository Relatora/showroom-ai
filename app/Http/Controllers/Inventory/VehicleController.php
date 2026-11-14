<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\BodyStyle;
use App\Enums\LeadStage;
use App\Enums\Powertrain;
use App\Enums\VehicleCondition;
use App\Enums\VehicleStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\LeadResource;
use App\Http\Resources\VehicleResource;
use App\Models\Lead;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The lot. Everyone on the sales floor can see all the stock, so there's no per-user scoping
 * here, only on the leads attached to a vehicle.
 */
class VehicleController extends Controller
{
    private const SORTS = [
        'newest' => ['arrived_on', 'desc'],
        'oldest' => ['arrived_on', 'asc'],
        'price_low' => ['price', 'asc'],
        'price_high' => ['price', 'desc'],
        'km_low' => ['km', 'asc'],
    ];

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'body' => ['nullable', Rule::enum(BodyStyle::class)],
            'powertrain' => ['nullable', Rule::enum(Powertrain::class)],
            'condition' => ['nullable', Rule::enum(VehicleCondition::class)],
            'status' => ['nullable', Rule::enum(VehicleStatus::class)],
            'max_price' => ['nullable', 'integer', 'min:0'],
            'min_seats' => ['nullable', 'integer', 'min:1', 'max:9'],
            'min_towing' => ['nullable', 'integer', 'min:0'],
            'sort' => ['nullable', Rule::in(array_keys(self::SORTS))],
        ]);

        [$column, $direction] = self::SORTS[$filters['sort'] ?? 'newest'];

        $vehicles = Vehicle::query()
            ->withCount(['leads' => fn (Builder $q) => $q->whereIn('stage', LeadStage::open())])
            ->when($filters['q'] ?? null, function (Builder $q, string $term) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

                // Stock numbers and VINs are matched exactly-ish so a pasted VIN finds its car.
                $q->where(fn (Builder $inner) => $inner
                    ->whereRaw("concat_ws(' ', year, make, model, trim) ilike ?", [$like])
                    ->orWhere('stock_number', 'ilike', $like)
                    ->orWhere('vin', 'ilike', $like)
                    ->orWhere('exterior_color', 'ilike', $like));
            })
            ->when($filters['body'] ?? null, fn (Builder $q, string $v) => $q->where('body_style', $v))
            ->when($filters['powertrain'] ?? null, fn (Builder $q, string $v) => $q->where('powertrain', $v))
            ->when($filters['condition'] ?? null, fn (Builder $q, string $v) => $q->where('condition', $v))
            // Sold stock is hidden unless someone asks for it.
            ->when(
                $filters['status'] ?? null,
                fn (Builder $q, string $v) => $q->where('status', $v),
                fn (Builder $q) => $q->where('status', '!=', VehicleStatus::Sold),
            )
            ->when($filters['max_price'] ?? null, fn (Builder $q, int|string $v) => $q->where('price', '<=', (int) $v))
            ->when($filters['min_seats'] ?? null, fn (Builder $q, int|string $v) => $q->where('seats', '>=', (int) $v))
            ->when($filters['min_towing'] ?? null, fn (Builder $q, int|string $v) => $q->where('towing_lb', '>=', (int) $v))
            ->orderBy($column, $direction)
            ->orderBy('id')
            ->paginate(24)
            ->withQueryString();

        return Inertia::render('inventory/Index', [
            'vehicles' => VehicleResource::collection($vehicles),
            // Cast to an object: an empty PHP array would reach JS as [], where filters.sort is Array.prototype.sort.
            'filters' => (object) $filters,
            'summary' => [
                'available' => Vehicle::available()->count(),
                // Units past 60 days are what a used-car manager loses sleep over.
                'aged' => Vehicle::available()->where('arrived_on', '<', now()->subDays(60))->count(),
            ],
        ]);
    }

    public function show(Request $request, Vehicle $vehicle): Response
    {
        $leads = Lead::visibleTo($request->user())
            ->whereBelongsTo($vehicle)
            ->open()
            ->with(['customer', 'assignee'])
            ->latest('updated_at')
            ->get();

        return Inertia::render('inventory/Show', [
            'vehicle' => VehicleResource::make($vehicle),
            'leads' => LeadResource::collection($leads),
            // Same body style and within about 15% on price: "if they like this, they'll look at…"
            'similar' => VehicleResource::collection(
                Vehicle::available()
                    ->whereKeyNot($vehicle->id)
                    ->where('body_style', $vehicle->body_style)
                    ->whereBetween('price', [(int) ($vehicle->price * 0.85), (int) ($vehicle->price * 1.15)])
                    ->orderByRaw('abs(price - ?)', [$vehicle->price])
                    ->limit(4)
                    ->get(),
            ),
        ]);
    }
}
