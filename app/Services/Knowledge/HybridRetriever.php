<?php

namespace App\Services\Knowledge;

use App\Models\Chunk;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Finds the passages that answer a question, using two searches and merging the results.
 *
 * - Vector search (pgvector cosine distance) finds passages that *mean* the same thing:
 *   "tow a boat" matches "pulls a 2,000 lb fishing boat".
 * - Keyword search (Postgres full-text) finds exact words, codes and numbers that embeddings
 *   blur: "F-204", "XLE", "CPO".
 *
 * The two lists are merged with Reciprocal Rank Fusion: every result scores 1 / (k + rank) in
 * each list it appears in, and the scores add up. It only looks at ranks, so we never have to
 * compare a cosine distance with a ts_rank score (they're on different scales). k = 60 is the
 * value from the original RRF paper and works well without tuning. See ADR-003.
 *
 * Access control is applied inside both queries with Chunk::visibleTo(), so a passage the user
 * isn't allowed to see is never retrieved in the first place (rather than filtered out after).
 */
class HybridRetriever
{
    public const RRF_K = 60;

    /** How many candidates each half contributes before fusion. */
    private const CANDIDATES = 30;

    public function __construct(private readonly Embedder $embedder) {}

    /**
     * @param  list<string>|null  $types  limit to some source types, e.g. ['document']
     * @return list<RetrievedChunk>
     */
    public function search(string $question, User $user, int $limit = 6, ?array $types = null): array
    {
        $question = trim($question);

        if ($question === '') {
            return [];
        }

        $vectorIds = $this->vectorSearch($question, $user, $types);
        $keywordIds = $this->keywordSearch($question, $user, $types);

        $fused = self::fuse($vectorIds, $keywordIds);
        $top = array_slice($fused, 0, $limit, preserve_keys: true);

        $chunks = Chunk::query()->whereKey(array_keys($top))->get()->keyBy('id');

        $results = [];

        foreach ($top as $id => $score) {
            if ($chunk = $chunks->get($id)) {
                $results[] = new RetrievedChunk(
                    $chunk,
                    $score,
                    self::rankOf($id, $vectorIds),
                    self::rankOf($id, $keywordIds),
                );
            }
        }

        return $results;
    }

    /**
     * Reciprocal Rank Fusion. Takes any number of ranked id lists, best first, and returns
     * id => score, best first. Public and static so it can be unit tested on its own.
     *
     * @param  list<int>  ...$rankings
     * @return array<int, float>
     */
    public static function fuse(array ...$rankings): array
    {
        $scores = [];

        foreach ($rankings as $ids) {
            foreach ($ids as $index => $id) {
                $scores[$id] = ($scores[$id] ?? 0.0) + 1 / (self::RRF_K + $index + 1);
            }
        }

        arsort($scores);

        return $scores;
    }

    /**
     * @param  list<string>|null  $types
     * @return list<int>
     */
    private function vectorSearch(string $question, User $user, ?array $types): array
    {
        $vector = $this->embedder->query($question);

        /** @var list<int> */
        return $this->baseQuery($user, $types)
            ->whereNotNull('embedding')
            ->orderByVectorDistance('embedding', $vector)
            ->limit(self::CANDIDATES)
            ->pluck('id')
            ->all();
    }

    /**
     * Postgres' plainto_tsquery ANDs every word together, so a long natural question would only
     * match passages containing *all* of its words. Turning the ANDs into ORs lets ts_rank_cd
     * do the work instead: passages with more (and closer) matching words still rank higher.
     * Stemming and stop words are still handled by Postgres' english config.
     *
     * @param  list<string>|null  $types
     * @return list<int>
     */
    private function keywordSearch(string $question, User $user, ?array $types): array
    {
        $tsquery = "to_tsquery('english', replace(plainto_tsquery('english', ?)::text, ' & ', ' | '))";

        // A question made only of stop words ("is it?") gives an empty tsquery; skip the search.
        if (DB::scalar("select numnode({$tsquery})", [$question]) === 0) {
            return [];
        }

        /** @var list<int> */
        return $this->baseQuery($user, $types)
            ->whereRaw("search @@ {$tsquery}", [$question])
            ->orderByRaw("ts_rank_cd(search, {$tsquery}) desc", [$question])
            ->limit(self::CANDIDATES)
            ->pluck('id')
            ->all();
    }

    /**
     * @param  list<string>|null  $types
     * @return Builder<Chunk>
     */
    private function baseQuery(User $user, ?array $types): Builder
    {
        return Chunk::query()
            ->visibleTo($user)
            ->when($types, fn (Builder $q) => $q->whereIn('source_type', $types));
    }

    /**
     * @param  list<int>  $ids
     */
    private static function rankOf(int $id, array $ids): ?int
    {
        $index = array_search($id, $ids, true);

        return $index === false ? null : $index + 1;
    }
}
