<?php

namespace App\Livewire;

use App\Models\CrawlRun;
use App\Models\Site;
use App\Models\Snapshot;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use SebastianBergmann\Diff\Differ;
use SebastianBergmann\Diff\Output\UnifiedDiffOutputBuilder;

/**
 * User Archive Screen 5 — Compare / diff.
 *
 * Mounted at /compare/{site}. The admin picks a page path + two crawl runs,
 * we pull both HTMLs from the archive disk, and render a side-by-side line
 * diff (added/removed/unchanged) using sebastian/diff.
 *
 * Paths come from the crawl_runs joined with snapshots so only pages that
 * exist across runs are offered — no dead dropdown items.
 */
#[Layout('layouts.app')]
#[Title('Compare snapshots')]
class ArchiveCompare extends Component
{
    public Site $site;

    #[Url(as: 'path')]
    public ?string $path = null;

    #[Url(as: 'a')]
    public ?int $leftRunId = null;

    #[Url(as: 'b')]
    public ?int $rightRunId = null;

    public bool $hasRunDiff = false;

    public function mount(Site $site): void
    {
        $this->site = $site;

        // Sensible defaults — newest two runs, newest page.
        $runs = $this->runsForSite();
        if (! $this->leftRunId && $runs->count() >= 2) {
            $this->rightRunId = $runs->first()->id;
            $this->leftRunId  = $runs->skip(1)->first()->id;
        }
        if (! $this->path && $this->leftRunId && $this->rightRunId) {
            $this->path = $this->availablePaths()->first();
        }
    }

    /** All complete runs for this site, newest first. */
    public function runsForSite(): Collection
    {
        return CrawlRun::where('site_id', $this->site->id)
            ->where('status', 'complete')
            ->orderByDesc('created_at')
            ->get(['id', 'created_at', 'pages_crawled']);
    }

    /** Paths captured in BOTH selected runs (so the dropdown never offers dead choices). */
    public function availablePaths(): Collection
    {
        if (! $this->leftRunId || ! $this->rightRunId) {
            return collect();
        }
        $left  = Snapshot::where('crawl_run_id', $this->leftRunId)
            ->where('status_code', 200)
            ->pluck('path');
        $right = Snapshot::where('crawl_run_id', $this->rightRunId)
            ->where('status_code', 200)
            ->pluck('path');
        return $left->intersect($right)->sort()->values();
    }

    public function runDiff(): void
    {
        $this->hasRunDiff = true;
    }

    /**
     * Produces the rendered diff. Returns an array of segments:
     *   ['type' => 'added'|'removed'|'unchanged', 'content' => string]
     * Pure function so the view can just iterate.
     *
     * @return array{left: array, right: array, added: int, removed: int, unchanged: int}
     */
    public function computeDiff(): array
    {
        if (! $this->hasRunDiff || ! $this->path || ! $this->leftRunId || ! $this->rightRunId) {
            return ['left' => [], 'right' => [], 'added' => 0, 'removed' => 0, 'unchanged' => 0];
        }

        $left  = Snapshot::where('crawl_run_id', $this->leftRunId)
            ->where('path', $this->path)->first();
        $right = Snapshot::where('crawl_run_id', $this->rightRunId)
            ->where('path', $this->path)->first();

        if (! $left || ! $right) {
            return ['left' => [], 'right' => [], 'added' => 0, 'removed' => 0, 'unchanged' => 0];
        }

        // We want to diff the ORIGINAL markup, not our post-rewrite version —
        // otherwise every asset URL shows as a "change" because archive paths
        // differ between runs. Strip our /archive/asset/... rewrites back to
        // something stable before diffing.
        $l = $this->stripArchiveRewrites($left->readHtml());
        $r = $this->stripArchiveRewrites($right->readHtml());

        return $this->lineDiff($l, $r);
    }

    /** Replace /archive/asset/{runId}/{sha1} → [ASSET:{sha1}] so the diff ignores per-run path IDs. */
    protected function stripArchiveRewrites(string $html): string
    {
        return preg_replace(
            '#/archive/asset/\d+/([a-f0-9]{40})#',
            '[ASSET:$1]',
            $html,
        ) ?? $html;
    }

    /**
     * Line-by-line diff using sebastian/diff. Groups each side's lines into
     * segments with a `type` marker the view can style.
     */
    protected function lineDiff(string $a, string $b): array
    {
        $aLines = preg_split('/\r?\n/', $a);
        $bLines = preg_split('/\r?\n/', $b);

        $builder = new UnifiedDiffOutputBuilder(commonLineThreshold: PHP_INT_MAX);
        $differ  = new Differ($builder);
        $diffStr = $differ->diff(implode("\n", $aLines), implode("\n", $bLines));

        $leftSeg = $rightSeg = [];
        $added = $removed = $unchanged = 0;

        foreach (preg_split('/\r?\n/', $diffStr) as $line) {
            if ($line === '' || Str::startsWith($line, ['---', '+++', '@@'])) {
                continue;
            }
            $mark = substr($line, 0, 1);
            $content = substr($line, 1);

            if ($mark === '-') {
                $leftSeg[]  = ['type' => 'removed', 'content' => $content];
                $rightSeg[] = ['type' => 'placeholder', 'content' => ''];
                $removed++;
            } elseif ($mark === '+') {
                $leftSeg[]  = ['type' => 'placeholder', 'content' => ''];
                $rightSeg[] = ['type' => 'added', 'content' => $content];
                $added++;
            } else {
                $leftSeg[]  = ['type' => 'unchanged', 'content' => $content];
                $rightSeg[] = ['type' => 'unchanged', 'content' => $content];
                $unchanged++;
            }
        }

        return compact('leftSeg', 'rightSeg', 'added', 'removed', 'unchanged') + [
            'left' => $leftSeg, 'right' => $rightSeg,
        ];
    }

    public function render()
    {
        $diff = $this->computeDiff();

        return view('livewire.archive-compare', [
            'runs'           => $this->runsForSite(),
            'availablePaths' => $this->availablePaths(),
            'diff'           => $diff,
        ]);
    }
}
