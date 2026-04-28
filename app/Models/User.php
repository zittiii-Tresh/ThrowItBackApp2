<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Admin user for the SiteArchive panel.
 *
 * The email-verification flow was removed for the internal-tool deployment:
 * new admins created via the Admins screen are auto-verified on save and
 * can log in immediately with the password the inviter set. No verification
 * email goes out, no signed-link click required.
 */
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Filament panel access gate. Any authenticated user row reaches /admin
     * — the verification check was dropped along with the rest of the
     * verification flow.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }
}
