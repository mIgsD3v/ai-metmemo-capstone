<?php

namespace App\Models;

use App\Enums\ContactSource;
use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'card_id',
        'full_name',
        'email',
        'phone',
        'company',
        'job_title',
        'source',
        'tags',
        'event',
        'met_at',
        'next_follow_up_at',
        'last_contacted_at',
        'first_followed_up_at',
    ];

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
