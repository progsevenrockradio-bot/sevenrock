<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Services\AuditTrailService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model): void {
            app(AuditTrailService::class)->recordModel(
                event: 'created',
                model: $model,
                after: $model->getAttributes(),
                context: [
                    'source' => 'model-event',
                    'model' => $model::class,
                ],
            );
        });

        static::updated(function (Model $model): void {
            $changed = $model->getChanges();
            $changed = Arr::except($changed, ['updated_at', 'created_at']);

            if ($changed === []) {
                return;
            }

            $before = [];
            foreach (array_keys($changed) as $key) {
                $before[$key] = $model->getRawOriginal($key);
            }

            app(AuditTrailService::class)->recordModel(
                event: 'updated',
                model: $model,
                before: $before,
                after: $model->getAttributes(),
                changes: $changed,
                context: [
                    'source' => 'model-event',
                    'model' => $model::class,
                ],
            );
        });

        static::deleted(function (Model $model): void {
            app(AuditTrailService::class)->recordModel(
                event: 'deleted',
                model: $model,
                before: $model->getOriginal(),
                context: [
                    'source' => 'model-event',
                    'model' => $model::class,
                ],
                level: 'warning',
            );
        });
    }
}
