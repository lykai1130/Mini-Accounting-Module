<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'entry_date',
        'description',
    ];

    protected $casts = [
        'entry_date' => 'date',
    ];

    /**
     * @return HasMany<JournalLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }
}
