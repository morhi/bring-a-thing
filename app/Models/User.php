<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\PasskeyAuthenticatable;

/**
 * @property int $id
 * @property string|null $name
 * @property string|null $email
 * @property string|null $pending_email
 * @property string|null $language
 * @property Carbon|null $email_verified_at
 * @property string|null $password
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read GroupMember|null $pivot
 */
#[Fillable(['name', 'email', 'language', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements HasLocalePreference, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable;

    /**
     * Well-formed bcrypt string no password can match, used for accounts without a password.
     */
    private const UNUSABLE_PASSWORD_HASH = '$2y$12$.....................................................';

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
            'credential_hint_dismissed_at' => 'datetime',
        ];
    }

    /**
     * The hash the auth guards compare against.
     *
     * The session guard refuses to restore a user from the remember cookie
     * when this is not a string, which logged out every passwordless account
     * (F-01). The fallback is never stored and matches no input.
     */
    public function getAuthPassword(): string
    {
        return $this->password ?? self::UNUSABLE_PASSWORD_HASH;
    }

    /**
     * Whether the account has an email whose ownership was proven.
     *
     * Checks the address too, since a verified timestamp alone says nothing
     * when the account has no email (F-01).
     */
    public function hasVerifiedEmail(): bool
    {
        return $this->email !== null && $this->email_verified_at !== null;
    }

    /**
     * The locale mails and notifications to this user are rendered in.
     */
    public function preferredLocale(): ?string
    {
        return $this->language;
    }

    /**
     * The groups the user belongs to, including groups they own.
     *
     * @return BelongsToMany<Group, $this, GroupMember>
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
     *
     * @return HasMany<Roster, $this>
     */
    public function standaloneRosters(): HasMany
    {
        return $this->hasMany(Roster::class, 'owner_id')->whereNull('group_id');
    }

    /**
     * The friend entries this user has saved, for easier re-referencing of previously invited accounts.
     *
     * @return HasMany<Friend, $this>
     */
    public function friends(): HasMany
    {
        return $this->hasMany(Friend::class)->with('friendUser');
    }

    /**
     * The events and polls this user reaches through an invite link without group membership (F-26, F-27).
     *
     * @return HasMany<AccessGrant, $this>
     */
    public function accessGrants(): HasMany
    {
        return $this->hasMany(AccessGrant::class);
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

    /**
     * Whether logging in with this email should prompt for a password
     * instead of sending a magic link, because the account has one set.
     */
    public static function emailHasPassword(?string $email): bool
    {
        $user = static::query()->where('email', $email)->first();

        return (bool) $user?->password;
    }
}
