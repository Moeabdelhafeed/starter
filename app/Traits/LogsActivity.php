<?php

namespace App\Traits;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

trait LogsActivity
{
    protected static function bootLogsActivity()
    {
        foreach (static::getLogEvents() as $event) {
            static::$event(function ($model) use ($event) {
                $model->recordActivity($event);
            });
        }
    }

    protected static function getLogEvents(): array
    {
        if (isset(static::$logEvents)) {
            return static::$logEvents;
        }

        return ['created', 'updated', 'deleted'];
    }

    public function recordActivity(string $event)
    {
        $user = Auth::guard('web')->user();

        if (! $user) {
            return;
        }

        $oldData = null;
        $newData = null;

        if ($event === 'updated') {
            $dirty = $this->getDirty();
            $oldData = [];
            $newData = [];

            foreach ($dirty as $key => $value) {
                $original = $this->getOriginal($key);

                // Skip if equivalent (handles 1 vs true, etc)
                if ($original == $value) {
                    continue;
                }

                $oldData[$key] = $original;
                $newData[$key] = $value;
            }

            // Remove sensitive fields
            $this->filterSensitiveData($oldData);
            $this->filterSensitiveData($newData);

            if (empty($newData)) {
                return;
            }
        } elseif ($event === 'created') {
            $newData = $this->toArray();
            $this->filterSensitiveData($newData);
        } elseif ($event === 'deleted') {
            $oldData = $this->toArray();
            $this->filterSensitiveData($oldData);
        }

        ActivityLog::create([
            'causer_name' => $user ? $user->name : 'System',
            'causer_email' => $user ? $user->email : 'system@system.com',
            'subject_type' => get_class($this),
            'subject_id' => $this->getKey(),
            'action' => $event,
            'old_data' => $oldData,
            'new_data' => $newData,
        ]);
    }

    protected function filterSensitiveData(&$data)
    {
        if (! $data) {
            return;
        }

        $fieldsToIgnore = [
            'password',
            'remember_token',
            'fcm_token',
            'created_at',
            'updated_at',
            'deleted_at',
        ];

        if (isset(static::$logIgnoreFields)) {
            $fieldsToIgnore = array_merge($fieldsToIgnore, static::$logIgnoreFields);
        }

        foreach ($fieldsToIgnore as $field) {
            if (array_key_exists($field, $data)) {
                unset($data[$field]);
            }
        }
    }
}
