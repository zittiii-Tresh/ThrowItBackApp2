<?php

namespace App\Livewire;

use App\Enums\AssetType;
use App\Models\CrawlRun;
use App\Models\Snapshot;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * User Archive Screen 3 + 4 — Snapshot viewer + Assets panel.
 *
 * Mounted at /view/{snapshot}. Renders:
 *   - dark archive bar (URL, timestamp, counts)
 *   - viewport switcher: Desktop / Tablet / Mobile (sets iframe width)
 *   - page tabs across every snapshot in the same crawl run
 *   - an iframe that loads /archive/snapshot/{id} (Phase 3 playback)
 *   - a slide-out assets panel with type filter + download buttons
 */
#[Layout('layouts.app')]
#[Title('Snapshot viewer')]
class ArchiveViewer extends Component
{
    public Snapshot $snapshot;

    /** Desktop | tablet | mobile. Persists in URL so refresh preserves. */
    #[Url(as: 'v')]
    public string $viewport = 'desktop';

    /** "all", "image", "stylesheet", "javascript", "font", "other". */
    #[Url(as: 't')]
    public string $assetType = 'all';

    /** Whether the right-side assets panel is open. */
    #[Url(as: 'panel')]
    public bool $assetsPanelOpen = false;

    public function mount(Snapshot $snapshot): void
    {
        $this->snapshot = $snapshot->load('crawlRun.site');
    }

    /** Other snapshots in the same run, for the page-tab bar. */
    public function getSiblingSnapshotsProperty(): Collection
    {
        return Snapshot::query()
            ->where('crawl_run_id', $this->snapshot->crawl_run_id)
            ->where('status_code', 200)
            ->orderBy('path')
            ->get(['id', 'path', 'title']);
    }

    /** Filtered assets for the side panel. */
    public function getAssetsProperty(): Collection
    {
        $q = $this->snapshot->assets()
            ->where('status_code', '>=', 200)
            ->where('status_code', '<', 400)
            ->orderBy('type')
            ->orderByDesc('size_bytes');

        if ($this->assetType !== 'all') {
            $q->where('type', $this->assetType);
        }
        return $q->get();
    }

    /** Counts per type for the filter tabs. */
    public function getAssetCountsProperty(): array
    {
        $rows = $this->snapshot->assets()
            ->selectRaw('type, COUNT(*) as c')
            ->where('status_code', '>=', 200)
            ->where('status_code', '<', 400)
            ->groupBy('type')
            ->pluck('c', 'type');

        return [
            'all'        => (int) $rows->sum(),
            'image'      => (int) ($rows['image']      ?? 0),
            'stylesheet' => (int) ($rows['stylesheet'] ?? 0),
            'javascript' => (int) ($rows['javascript'] ?? 0),
            'font'       => (int) ($rows['font']       ?? 0),
            'other'      => (int) ($rows['other']      ?? 0),
        ];
    }

    public function setViewport(string $v): void
    {
        if (in_array($v, ['desktop', 'tablet', 'mobile'], true)) {
            $this->viewport = $v;
        }
    }

    public function setAssetType(string $t): void
    {
        if (in_array($t, ['all', 'image', 'stylesheet', 'javascript', 'font', 'other'], true)) {
            $this->assetType = $t;
        }
    }

    public function toggleAssetsPanel(): void
    {
        $this->assetsPanelOpen = ! $this->assetsPanelOpen;
    }

    public function render()
    {
        return view('livewire.archive-viewer', [
            'siblings'     => $this->siblingSnapshots,
            'assets'       => $this->assetsPanelOpen ? $this->assets : collect(),
            'assetCounts'  => $this->assetsPanelOpen ? $this->assetCounts : [],
        ]);
    }
}
