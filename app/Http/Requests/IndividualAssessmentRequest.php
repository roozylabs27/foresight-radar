<?php

namespace App\Http\Requests;

/** A participant's own score of one driving force (roadmap R2.1): anyone who can open the exercise. */
class IndividualAssessmentRequest extends ForceAssessmentRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view-scenario') ?? false;
    }
}
