<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string|null $password
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * The groups the user belongs to, including groups they own.
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class)
            ->using(GroupMember::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * The standalone rosters the user owns (not attached to a group).
     */
    public function standaloneRosters(): HasMany
    {
        return $this->hasMany(Roster::class, 'owner_id')->whereNull('group_id');
    }

    /**
     * Whether registering or logging in with this email should require a name.
     *
     * True for a brand new account and for an existing account that still
     * has no name (e.g. a shadow account invited before this was required).
     */
    public static function emailNeedsName(?string $email): bool
    {
        $user = static::query()->where('email', $email)->first();

        return ! $user || ! $user->name;
    }
}
