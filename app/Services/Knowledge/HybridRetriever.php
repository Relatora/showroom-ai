<?php

namespace App\Services\Knowledge;

use App\Models\Chunk;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Finds the passages that answer a question, using several searches and merging the results.
 *
 * - Vector search (pgvector cosine distance) finds passages that *mean* the same thing:
 *   "tow a boat" matches "pulls a 2,000 lb fishing boat".
 * - Keyword search (Postgres full-text) finds the words themselves, which helps with trims and
 *   jargon that embeddings blur: "XLE", "CPO", "payout letter".
 * - Identifier search looks only for codes, stock numbers and VINs ("F-204", "S1046"). When
 *   someone types one of those they mean exactly that thing, so a match goes straight to the top.
 *
 * The lists are merged with Reciprocal Rank Fusion: every result scores weight / (k + rank) in
 * each list it appears in, and the scores add up. It only looks at ranks, so we never have to
 * compare a cosine distance with a ts_rank score (they're on different scales). k = 60 is the
 * value from the original RRF paper. The weights come from showroom:eval; see evals/results.md
 * and ADR-003.
 *
 * Access control is applied inside every query with Chunk::visibleTo(), so a passage the user
 * isn't allowed to see is never retrieved in the first place (rather than filtered out after).
 */
class HybridRetriever
{
    public const RRF_K = 60;

    /**
     * How much each search counts in the fusion. The identifier weight is deliberately huge: an
     * exact match on "S1046" should beat anything that merely sounds similar.
     */
    public const WEIGHTS = ['vector' => 1.0, 'keyword' => 0.5, 'identifier' => 10.0];

    /** How many candidates each search contributes before fusion. */
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

        $candidates = $this->candidates($question, $user, $types);
        $top = array_slice(self::fuse($candidates), 0, $limit, preserve_keys: true);

        $chunks = Chunk::query()->whereKey(array_keys($top))->get()->keyBy('id');

        $results = [];

        foreach ($top as $id => $score) {
            if ($chunk = $chunks->get($id)) {
                $results[] = new RetrievedChunk(
                    $chunk,
                    $score,
                    self::rankOf($id, $candidates['vector']),
                    self::rankOf($id, $candidates['keyword']),
                );
            }
        }

        return $results;
    }

    /**
     * Each search's ranked ids, best first, before fusion. Public so showroom:eval can score
     * every search on its own as well as the fused result.
     *
     * @param  list<string>|null  $types
     * @return array{vector: list<int>, keyword: list<int>, identifier: list<int>}
     */
    public function candidates(string $question, User $user, ?array $types = null): array
    {
        return [
            'vector' => $this->vectorSearch($question, $user, $types),
            'keyword' => $this->keywordSearch($question, $user, $types),
            'identifier' => $this->identifierSearch($question, $user, $types),
        ];
    }

    /**
     * Weighted Reciprocal Rank Fusion. Takes named, ranked id lists (best first) and returns
     * id => score, best first. Public and static so it can be tested on its own, and so
     * showroom:eval can try other weights.
     *
     * @param  array<string, list<int>>  $rankings
     * @param  array<string, float>  $weights  by ranking name; missing names count as 1
     * @return array<int, float>
     */
    public static function fuse(array $rankings, array $weights = self::WEIGHTS): array
    {
        $scores = [];

        foreach ($rankings as $name => $ids) {
            $weight = $weights[$name] ?? 1.0;

            foreach ($ids as $index => $id) {
                $scores[$id] = ($scores[$id] ?? 0.0) + $weight / (self::RRF_K + $index + 1);
            }
        }

        arsort($scores);

        return $scores;
    }

    /**
     * Words that mix letters and digits: F-204, S1046, a 17-character VIN. Plain numbers
     * ("500 km", "2011") aren't identifiers; they're far too common to single out.
     *
     * @return list<string>
     */
    public static function identifiers(string $text): array
    {
        preg_match_all('/\b(?=[A-Za-z-]*\d)(?=[\d-]*[A-Za-z])[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*\b/', $text, $matches);

        return array_values(array_unique($matches[0]));
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
     * Full-text search for the identifiers alone. ts_rank_cd doesn't know which words are rare,
     * so in "customer is asking about VIN 98RCUNY7KNFLRWJGJ" the keyword search above ranks
     * hundreds of notes that say "customer" next to the one passage with the VIN. Searching for
     * just the VIN finds that passage first.
     *
     * @param  list<string>|null  $types
     * @return list<int>
     */
    private function identifierSearch(string $question, User $user, ?array $types): array
    {
        $identifiers = self::identifiers($question);

        if ($identifiers === []) {
            return [];
        }

        $any = implode(' || ', array_fill(0, count($identifiers), "plainto_tsquery('english', ?)"));

        /** @var list<int> */
        return $this->baseQuery($user, $types)
            ->whereRaw("search @@ ({$any})", $identifiers)
            ->orderByRaw("ts_rank_cd(search, {$any}) desc", $identifiers)
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
