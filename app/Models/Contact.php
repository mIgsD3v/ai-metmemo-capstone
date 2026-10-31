<?php

namespace App\Models;

use App\Enums\ContactSource;
use App\Observers\ContactObserver;
use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[ObservedBy(ContactObserver::class)]
class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'card_id',
        'swapped_user_id',
        'full_name',
        'email',
        'phone',
        'company',
        'job_title',
        'photo_path',
        'source',
        'tags',
        'event',
        'met_at',
        'next_follow_up_at',
        'last_contacted_at',
        'first_followed_up_at',
    ];

    /** Where contact photos live (copied from a card on a swap): the same disk as card photos. */
    public const PHOTO_DISK = 'public';

    protected $appends = [
        'photo_url',
    ];

    protected function photoUrl(): Attribute
    {
        return Attribute::get(fn () => $this->photo_path
            ? Storage::disk(self::PHOTO_DISK)->url($this->photo_path)
            : null);
    }

    protected function casts(): array
    {
        return [
            'source' => ContactSource::class,
            'tags' => 'array',
            'met_at' => 'date:Y-m-d',
            'next_follow_up_at' => 'date:Y-m-d',
            'last_contacted_at' => 'datetime',
            'first_followed_up_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ContactNote::class)->latest();
    }
}
