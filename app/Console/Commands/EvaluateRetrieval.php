<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\Chunk;
use App\Models\User;
use App\Services\Knowledge\HybridRetriever;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Measures how well retrieval finds the right policy section for a fixed set of questions.
 *
 * If retrieval misses, the answer is wrong no matter how good the model is, so this is the
 * number to watch when changing the chunker, the embedding model or the fusion. It scores the
 * vector half, the keyword half and the fused result separately, which shows what each one
 * contributes:
 *
 *   hit@1   the right section came first
 *   hit@k   the right section was somewhere in the top k (what the assistant actually reads)
 *   MRR     mean reciprocal rank: 1 for first place, 1/2 for second... averaged. Rewards
 *           getting the right passage near the top, not just somewhere in the list.
 *
 * Runs against the real database and embedding model, so seed and index first:
 *   php artisan migrate:fresh --seed && php artisan showroom:index
 */
#[Signature('showroom:eval {--k=5 : How many results count as a hit} {--min=0.8 : Fail if hybrid hit@k is below this} {--keyword-weight= : Try a different keyword weight than the one in HybridRetriever::WEIGHTS} {--questions=evals/questions.json : The question set, relative to the project root} {--write : Save the results to evals/results.md}')]
#[Description('Score retrieval quality against the evals/questions.json question set')]
class EvaluateRetrieval extends Command
{
    /** Ranks beyond this count as a miss for MRR. */
    private const MRR_DEPTH = 10;

    public function handle(HybridRetriever $retriever): int
    {
        $k = max(1, (int) $this->option('k'));
        $questions = json_decode(File::get(base_path((string) $this->option('questions'))), true)['questions'];

        if (Chunk::count() === 0) {
            $this->components->error('The index is empty. Run php artisan showroom:index first.');

            return self::FAILURE;
        }

        // A manager sees everything, so the eval measures search quality, not access rules.
        // Never saved; visibleTo() only looks at the role.
        $evaluator = new User(['role' => UserRole::Manager]);

        $weights = HybridRetriever::WEIGHTS;

        if ($this->option('keyword-weight') !== null) {
            $weights['keyword'] = (float) $this->option('keyword-weight');
        }

        $ranks = ['vector' => [], 'keyword' => [], 'hybrid' => []];
        $rows = [];

        $this->components->info(sprintf('Scoring %d questions against %d chunks (keyword weight %.2f)', count($questions), Chunk::count(), $weights['keyword']));

        foreach ($questions as $q) {
            $candidates = $retriever->candidates($q['question'], $evaluator);
            $hybridIds = array_keys(HybridRetriever::fuse($candidates, $weights));

            $relevant = $this->relevantIds($q);

            $found = [
                'vector' => $this->rankOfFirst($candidates['vector'], $relevant),
                'keyword' => $this->rankOfFirst($candidates['keyword'], $relevant),
                'hybrid' => $this->rankOfFirst($hybridIds, $relevant),
            ];

            foreach ($found as $mode => $rank) {
                $ranks[$mode][] = $rank;
            }

            $rows[] = [$q['question'], $this->expected($q), ...array_map(fn (?int $r) => $r ?? '–', array_values($found))];
        }

        $summary = [];

        foreach ($ranks as $mode => $list) {
            $summary[$mode] = [
                'hit@1' => $this->hitRate($list, 1),
                "hit@{$k}" => $this->hitRate($list, $k),
                'mrr' => $this->mrr($list),
            ];
        }

        $this->newLine();
        $this->table(
            ['Search', 'hit@1', "hit@{$k}", 'MRR'],
            array_map(fn (string $mode, array $s) => [$mode, ...array_map(fn (float $v) => number_format($v, 2), array_values($s))], array_keys($summary), $summary),
        );

        $misses = array_filter($rows, fn (array $row) => ! is_int($row[4]) || $row[4] > $k);

        if ($misses !== []) {
            $this->components->warn('Hybrid search missed these (rank shown, – means not in the candidates):');
            $this->table(['Question', 'Expected', 'Vector', 'Keyword', 'Hybrid'], $misses);
        }

        if ($this->option('write')) {
            $this->writeReport($summary, $rows, $k, count($questions));
        }

        $passed = $summary['hybrid']["hit@{$k}"] >= (float) $this->option('min');

        $passed
            ? $this->components->info(sprintf('Hybrid hit@%d is %.2f, above the %.2f bar.', $k, $summary['hybrid']["hit@{$k}"], $this->option('min')))
            : $this->components->error(sprintf('Hybrid hit@%d is %.2f, below the %.2f bar.', $k, $summary['hybrid']["hit@{$k}"], $this->option('min')));

        return $passed ? self::SUCCESS : self::FAILURE;
    }

    /**
     * The chunks that count as a right answer: either a policy section, or a vehicle by stock
     * number. Document chunk headings look like
     * "Trade-in Appraisal Policy (F-204): How long an offer is valid", and vehicle ones like
     * "Inventory: 2026 Ford Mustang Mach-E GT (S1023)".
     *
     * @param  array{document: string, section: string}|array{stock: string}  $q
     * @return list<int>
     */
    private function relevantIds(array $q): array
    {
        $query = isset($q['stock'])
            ? Chunk::query()->where('source_type', 'vehicle')->where('heading', 'like', "%({$q['stock']})")
            : Chunk::query()->where('source_type', 'document')
                ->where('heading', 'like', "%({$q['document']}):%")
                ->where('heading', 'like', "%: {$q['section']}");

        /** @var list<int> */
        return $query->pluck('id')->all();
    }

    /**
     * @param  array{document: string, section: string}|array{stock: string}  $q
     */
    private function expected(array $q): string
    {
        return isset($q['stock']) ? "Vehicle {$q['stock']}" : "{$q['document']}: {$q['section']}";
    }

    /**
     * @param  list<int>  $ids
     * @param  list<int>  $relevant
     */
    private function rankOfFirst(array $ids, array $relevant): ?int
    {
        foreach ($ids as $index => $id) {
            if (in_array($id, $relevant, true)) {
                return $index + 1;
            }
        }

        return null;
    }

    /**
     * @param  list<int|null>  $ranks
     */
    private function hitRate(array $ranks, int $k): float
    {
        return count(array_filter($ranks, fn (?int $r) => $r !== null && $r <= $k)) / max(1, count($ranks));
    }

    /**
     * @param  list<int|null>  $ranks
     */
    private function mrr(array $ranks): float
    {
        $sum = array_sum(array_map(fn (?int $r) => $r !== null && $r <= self::MRR_DEPTH ? 1 / $r : 0, $ranks));

        return $sum / max(1, count($ranks));
    }

    /**
     * A Markdown report that's committed to the repo, so a change to retrieval shows up as a
     * diff in the numbers right there in the pull request.
     *
     * @param  array<string, array<string, float>>  $summary
     * @param  list<array<int, mixed>>  $rows
     */
    private function writeReport(array $summary, array $rows, int $k, int $questionCount): void
    {
        $provider = config('ai.default');
        $lines = [
            '# Retrieval eval results',
            '',
            '<!-- Generated by `php artisan showroom:eval --write`. Do not edit by hand. -->',
            '',
            sprintf(
                '%d questions from `'.$this->option('questions').'`, %s chunks indexed, embeddings from `%s` via %s.',
                $questionCount,
                number_format(Chunk::count()),
                config("ai.providers.{$provider}.models.embeddings.default"),
                $provider,
            ),
            '',
            "| Search | hit@1 | hit@{$k} | MRR |",
            '|---|---|---|---|',
        ];

        foreach ($summary as $mode => $s) {
            $lines[] = sprintf('| %s | %s |', $mode, implode(' | ', array_map(fn (float $v) => number_format($v, 2), $s)));
        }

        $lines = [...$lines, '', '## Rank of the right section, per question', '', '`–` means it wasn\'t in that search\'s top 30 candidates.', '', '| Question | Expected | Vector | Keyword | Hybrid |', '|---|---|---|---|---|'];

        foreach ($rows as $row) {
            $lines[] = '| '.implode(' | ', $row).' |';
        }

        File::put(base_path('evals/results.md'), implode("\n", $lines)."\n");

        $this->components->info('Wrote evals/results.md');
    }
}
