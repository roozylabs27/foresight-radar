<?php

namespace App\Support;

/**
 * Origin and review convention for scenario planning artefacts (roadmap R0.6). Every artefact says
 * where it came from (`origin`) and whether a person has accepted it (its review state). Tables with
 * their own decision column use it as the review state instead of a second column:
 * critical uncertainties `status`, strategic responses `status`, exercise forces `inclusion_status`,
 * frameworks `framework_status`. See docs/architecture/origin-review-convention.md.
 */
final class Provenance
{
    /** Entered or decided by a person. */
    public const HUMAN = 'human';

    /** Written by an AI call (links an `ai_generation_runs` row). */
    public const AI = 'ai';

    /** Skeleton written by code without AI (narrative template, keyword rules). */
    public const TEMPLATE = 'template';

    /** Taken from the shared library (forces, their ratings). */
    public const LIBRARY = 'library';

    /** Converted from older free-text data (narrative implications or indicator strings). */
    public const MIGRATED = 'migrated';

    /** Fetched from an external data source (indicator observations, roadmap R3.6). */
    public const EXTERNAL = 'external';

    public const ORIGINS = [self::HUMAN, self::AI, self::TEMPLATE, self::LIBRARY, self::MIGRATED, self::EXTERNAL];
}
