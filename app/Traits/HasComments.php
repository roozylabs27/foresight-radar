<?php

namespace App\Traits;

use App\Models\Comment;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** An exercise artefact that takes comments (roadmap R2.2). */
trait HasComments
{
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable')->orderBy('id');
    }

    /** `withCount` spec for the open (unresolved) comments, as `open_comments`. */
    public static function openCommentsCount(): array
    {
        return ['comments as open_comments' => fn ($query) => $query->whereNull('resolved_at')];
    }
}
