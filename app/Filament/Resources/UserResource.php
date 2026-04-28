<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * Admin user management — a sidebar tab where existing admins can add
 * new admins. New accounts can log in immediately with the password the
 * inviter set; no email verification step (removed for the internal-tool
 * deployment).
 *
 * Guard rails baked in:
 *   - You can't delete yourself (prevents locking out the only admin).
 *   - Password field is required only on create; leaving it blank on edit
 *     preserves the existing hash.
 *   - email_verified_at is auto-stamped on create so listings still
 *     show the row as "verified" and any external check on
 *     hasVerifiedEmail() returns true.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Admins';
    protected static ?int    $navigationSort  = 50;

    protected static ?string $modelLabel       = 'admin';
    protected static ?string $pluralModelLabel = 'admins';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Account')
                ->description('The new admin can log in immediately with the password set here.')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(120),

                    Forms\Components\TextInput::make('email')
                        ->email()
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),

                    Forms\Components\TextInput::make('password')
                        ->password()
                        ->revealable()
                        ->confirmed()
                        // Project-wide rule from AppServiceProvider::configurePasswordDefaults()
                        // — currently min 8 characters.
                        ->rule(Password::default())
                        ->helperText('Min 8 characters. Leave blank on edit to keep current password.')
                        // Required only when creating; optional on edit.
                        ->required(fn (string $operation) => $operation === 'create')
                        // Hash if provided; skip the column entirely when blank.
                        ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                        ->dehydrated(fn (?string $state) => filled($state)),

                    Forms\Components\TextInput::make('password_confirmation')
                        ->password()
                        ->revealable()
                        ->requiredWith('password')
                        ->dehydrated(false),

                    // Auto-stamp the verification timestamp on create so
                    // every new admin is treated as fully active. Hidden
                    // field — never shown in the form UI.
                    Forms\Components\Hidden::make('email_verified_at')
                        ->default(fn () => now()),
                ])
                ->columns(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->weight('semibold')
                    ->searchable()
                    ->sortable()
                    ->alignment('center'),

                Tables\Columns\TextColumn::make('email')
                    ->copyable()
                    ->searchable()
                    ->alignment('center'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Added')
                    ->since()
                    ->sortable()
                    ->alignment('center'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                // Delete is always surfaced inline (not hidden in a dropdown)
                // so it's one click away. Hidden only on your own row to
                // prevent self-lockout.
                Tables\Actions\DeleteAction::make()
                    ->label('Delete')
                    ->icon('heroicon-m-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(fn (User $u) => "Delete admin {$u->name}?")
                    ->modalDescription('They will lose access to the admin panel immediately. This cannot be undone.')
                    ->modalSubmitActionLabel('Yes, delete admin')
                    ->successNotificationTitle(fn (User $u) => "{$u->name} deleted")
                    ->visible(fn (User $u) => $u->id !== auth()->id()),
            ])
            ->bulkActions([
                // Bulk delete — skips your own row automatically.
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function (\Illuminate\Database\Eloquent\Collection $records) {
                            // Drop yourself from the selected set before the
                            // delete runs — prevents self-lockout even if your
                            // row slipped into the selection.
                            $records->reject(fn (User $u) => $u->id === auth()->id());
                        }),
                ]),
            ])
            ->emptyStateHeading('No admins yet')
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit'   => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
