<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Generic audit observer.
 *
 * Bound automatically by the Auditable trait. Writes an audit_logs entry
 * on create/update/delete/restore. Uses the model's `$auditModule`
 * property if set, otherwise the lowercase class basename.
 */
class AuditObserver
{
    public function created(Model $model): void
    {
        $this->write($model, AuditAction::Created, oldValues: null, newValues: $this->capture($model));
    }

    public function updated(Model $model): void
    {
        $changes = $model->getChanges();
        // Skip if nothing meaningful changed (timestamps already filtered).
        $filtered = $this->filterAttributes($model, $changes);
        if ($filtered === []) {
            return;
        }

        $original = array_intersect_key($model->getOriginal(), $filtered);

        $this->write($model, AuditAction::Updated, oldValues: $original, newValues: $filtered);
    }

    public function deleted(Model $model): void
    {
        // Distinguish soft-delete (archive) from force-delete.
        $action = $this->isSoftDeleting($model)
            ? AuditAction::Archived
            : AuditAction::Deleted;

        $this->write($model, $action, oldValues: $this->capture($model), newValues: null);
    }

    public function restored(Model $model): void
    {
        $this->write($model, AuditAction::Restored, oldValues: null, newValues: $this->capture($model));
    }

    // ─── Internals ──────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    private function write(
        Model $model,
        AuditAction $action,
        ?array $oldValues,
        ?array $newValues,
    ): void {
        AuditLog::create([
            'user_id'        => Auth::id(),
            'module'         => $this->moduleName($model),
            'action'         => $action->value,
            'auditable_type' => $model::class,
            'auditable_id'   => (string) $model->getKey(),
            'old_values'     => $oldValues,
            'new_values'     => $newValues,
            'ip_address'     => Request::ip(),
            'user_agent'     => Request::userAgent(),
            'created_at'     => now(),
        ]);
    }

    /** @return array<string, mixed> */
    private function capture(Model $model): array
    {
        return $this->filterAttributes($model, $model->getAttributes());
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function filterAttributes(Model $model, array $attributes): array
    {
        $excluded = method_exists($model, 'auditExcludedAttributes')
            ? $model->auditExcludedAttributes()
            : [];

        $whitelist = method_exists($model, 'auditableAttributes')
            ? $model->auditableAttributes()
            : [];

        if ($whitelist !== []) {
            $attributes = array_intersect_key($attributes, array_flip($whitelist));
        }

        return array_diff_key($attributes, array_flip($excluded));
    }

    private function moduleName(Model $model): string
    {
        if (isset($model->auditModule) && is_string($model->auditModule)) {
            return $model->auditModule;
        }
        return strtolower(class_basename($model));
    }

    private function isSoftDeleting(Model $model): bool
    {
        if (! in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
            return false;
        }
        // After a soft delete `deleted_at` is populated and the model
        // is still considered "trashed" rather than gone.
        return method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting();
    }
}
