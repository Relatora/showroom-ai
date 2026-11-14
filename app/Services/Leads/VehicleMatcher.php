<?php

namespace App\Services\Leads;

use App\Ai\Agents\InventoryMatcher;
use App\Models\Lead;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Knowledge\HybridRetriever;
use App\Services\Knowledge\RetrievedChunk;
use Illuminate\Support\Collection;

/**
 * "What do we have for this customer?" in two steps:
 *
 * 1. Shortlist with plain code: search the stock by what the customer asked for, then keep only
 *    vehicles that are available and roughly within budget. Rules like "don't show sold cars"
 *    belong in code, where they're guaranteed, not in a prompt, where they're a suggestion.
 * 2. Let the model rank the shortlist and explain each pick in a sentence.
 */
class VehicleMatcher
{
    /** How far over budget a vehicle may be and still be shown. Customers often stretch a bit. */
    public const BUDGET_STRETCH = 1.10;

    private const SHORTLIST = 6;

    public function __construct(private readonly HybridRetriever $retriever) {}

    /**
     * @return Collection<int, Vehicle>
     */
    public function shortlist(Lead $lead, User $user): Collection
    {
        $hits = $this->retriever->search($lead->interest, $user, limit: 20, types: ['vehicle']);
        $ids = array_map(fn (RetrievedChunk $hit) => $hit->chunk->source_id, $hits);

        $vehicles = Vehicle::query()
            ->available()
            ->whereKey($ids)
            ->when($lead->budget_max, fn ($q, $budget) => $q->where('price', '<=', (int) round($budget * self::BUDGET_STRETCH)))
            ->get()
            ->keyBy('id');

        // Keep the search order: the best semantic match should be the first one the model reads.
        return collect($ids)
            ->map(fn (int $id) => $vehicles->get($id))
            ->filter()
            ->take(self::SHORTLIST)
            ->values();
    }

    /**
     * @return list<array{vehicle: Vehicle, fit: string, reason: string}>
     */
    public function match(Lead $lead, User $user): array
    {
        $shortlist = $this->shortlist($lead, $user);

        if ($shortlist->isEmpty()) {
            return [];
        }

        $response = InventoryMatcher::make()->prompt(InventoryMatcher::promptFor($lead, $shortlist));
        $byStock = $shortlist->keyBy('stock_number');

        $matches = [];

        foreach ($response['matches'] ?? [] as $match) {
            $vehicle = $byStock->get($match['stock_number'] ?? '');

            // Trust, but verify: drop anything that isn't on the shortlist (a made-up or
            // mistyped stock number) and any duplicates.
            if (! $vehicle || isset($matches[$vehicle->id])) {
                continue;
            }

            $matches[$vehicle->id] = [
                'vehicle' => $vehicle,
                'fit' => in_array($match['fit'] ?? null, ['strong', 'good', 'stretch'], true) ? $match['fit'] : 'good',
                'reason' => trim((string) ($match['reason'] ?? '')),
            ];
        }

        return array_values($matches);
    }
}
