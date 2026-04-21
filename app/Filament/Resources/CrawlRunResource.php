<?php

namespace App\Filament\Resources;

use App\Enums\CrawlStatus;
use App\Enums\TriggerSource;
use App\Filament\Resources\CrawlRunResource\Pages;
use App\Models\CrawlRun;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Admin Screen 4 — Crawl History.
 *
 * A read-only log of every crawl run across all sites. Columns match the
 * PDF mockup: Site / Started / Pages / Assets / Status / Triggered / Duration.
 *
 * Not a normal CRUD resource — crawl runs are created by the job engine,
 * not by admins. Hence no create/edit forms, no bulk delete.
 */
class CrawlRunResource extends Resource
{
    protected static ?string $model = CrawlRun::class;

    protected static ?string $navigationIcon  = 'heroicon-o-clock';
    protected static ?string $navigationLabel = 'Crawl history';
    protected static ?int    $navigationSort  = 20;

    protected static ?string $modelLabel       = 'crawl run';
    protected static ?string $pluralModelLabel = 'crawl runs';

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('site.name')
                    ->label('Site')
                    ->weight('semibold')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('started_at')
                    ->label('Started')
                    ->dateTime('M j, H:i')
                    ->sortable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('pages_crawled')
                    ->label('Pages')
                    ->numeric()
                    ->alignment('end')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('assets_downloaded')
                    ->label('Assets')
                    ->numeric()
                    ->alignment('end')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (CrawlStatus $state) => $state->label())
                    ->color(fn (CrawlStatus $state) => $state->color()),

                Tables\Columns\TextColumn::make('triggered_by')
                    ->label('Triggered')
                    ->formatStateUsing(fn (TriggerSource $state) => $state->label())
                    ->toggleable(),

                Tables\Columns\TextColumn::make('duration')
                    ->label('Duration')
                    ->state(fn (CrawlRun $r): string => $r->durationHuman())
                    ->alignment('end')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('storage_bytes')
                    ->label('Storage')
                    ->state(fn (CrawlRun $r) => $r->storage_bytes > 0
                        ? number_format($r->storage_bytes / 1024 / 1024, 1) . ' MB'
                        : '—')
                    ->alignment('end')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(CrawlStatus::cases())
                        ->mapWithKeys(fn (CrawlStatus $s) => [$s->value => $s->label()])
                        ->all())
                    ->native(false),

                SelectFilter::make('triggered_by')
                    ->label('Triggered')
                    ->options(collect(TriggerSource::cases())
                        ->mapWithKeys(fn (TriggerSource $t) => [$t->value => $t->label()])
                        ->all())
                    ->native(false),

                SelectFilter::make('site')
                    ->relationship('site', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                // View → opens first successful snapshot in the archive preview.
                // Phase 6 will replace this with the proper per-run viewer.
                Tables\Actions\Action::make('viewFirstSnapshot')
                    ->label('View')
                    ->icon('heroicon-m-eye')
                    ->color('primary')
                    ->url(fn (CrawlRun $r) => optional(
                        $r->snapshots()->where('status_code', 200)->first()
                    )?->id ? "/archive/snapshot/" . $r->snapshots()->where('status_code', 200)->first()->id : null)
                    ->openUrlInNewTab()
                    ->visible(fn (CrawlRun $r) => $r->snapshots()->where('status_code', 200)->exists()),
            ])
            ->bulkActions([])
            ->emptyStateHeading('No crawl runs yet')
            ->emptyStateDescription('Crawls dispatched by the scheduler or manual triggers will appear here.')
            ->emptyStateIcon('heroicon-o-clock');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCrawlRuns::route('/'),
        ];
    }

    // Explicitly deny the create/edit routes — crawl runs are machine-generated.
    public static function canCreate(): bool { return false; }
}
