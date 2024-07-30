<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dimension extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'environment_id',
        'name'
    ];

    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }
}
