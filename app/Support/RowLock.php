<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Production readiness PRD-11: a gate action checks the record's state and then changes it. Two
 * requests that loaded the record at the same moment both passed the check and both logged the
 * decision. Running the action under a row lock, on the record's current values, makes the check
 * and the change one step.
 */
final class RowLock
{
    /**
     * Run `$action` in a transaction, holding the model's row lock and with the model reloaded
     * from the locked row (so its "original" values are what the database holds now).
     *
     * @template T
     *
     * @param  callable(): T  $action
     * @return T
     */
    public static function run(Model $model, callable $action): mixed
    {
        return DB::transaction(function () use ($model, $action) {
            $current = $model->newQuery()->whereKey($model->getKey())->lockForUpdate()->firstOrFail();
            $model->setRawAttributes($current->getAttributes(), true);

            return $action();
        });
    }
}
