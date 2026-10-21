<?php

namespace App\Models;

use Database\Factories\CardFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Card extends Model
{
    /** @use HasFactory<CardFactory> */
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'full_name',
        'job_title',
        'company',
        'bio',
        'email',
        'phone',
        'website',
        'location',
        'is_public',
        'photo_path',
    ];

    /** Same disk as Jetstream's account photos. */
    public const PHOTO_DISK = 'public';

    protected $hidden = [
        'photo_path',
    ];

    protected $appends = [
        'photo_url',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
        ];
    }

    /** This card's own photo, or null when it has none. */
    protected function photoUrl(): Attribute
    {
        return Attribute::get(fn () => $this->photo_path
            ? Storage::disk(self::PHOTO_DISK)->url($this->photo_path)
            : null);
    }

    /** What visitors see: the card's photo, else the owner's uploaded account photo, else null (initials). */
    public function displayPhotoUrl(): ?string
    {
        return $this->photo_url
            ?? ($this->user->profile_photo_path ? $this->user->profile_photo_url : null);
    }

    /**
     * The same photo as displayPhotoUrl(), as a stored file (for embedding, e.g. in the vCard).
     *
     * @return array{disk: string, path: string}|null
     */
    public function displayPhotoFile(): ?array
    {
        if ($this->photo_path) {
            return ['disk' => self::PHOTO_DISK, 'path' => $this->photo_path];
        }

        if ($this->user->profile_photo_path) {
            return ['disk' => config('jetstream.profile_photo_disk', 'public'), 'path' => $this->user->profile_photo_path];
        }

        return null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }
}
