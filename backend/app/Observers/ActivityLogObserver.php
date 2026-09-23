<?php

namespace App\Observers;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ActivityLogObserver
{
    public function created(Model $model): void
    {
        $this->record('created', $model);
    }

    public function updated(Model $model): void
    {
        $this->record('updated', $model, $model->getChanges());
    }

    public function deleted(Model $model): void
    {
        $this->record('deleted', $model);
    }

    private function record(string $action, Model $model, array $changes = []): void
    {
        if ($model instanceof ActivityLog) {
            return;
        }

        unset($changes['password'], $changes['remember_token']);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'auditable_type' => $model::class,
            'auditable_id' => $model->getKey(),
            'changes' => $changes ?: null,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }
}
