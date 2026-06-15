<?php

namespace App\Traits;

use App\Models\ActivityLog;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Adds CSV export to a model. Excel opens the file directly (UTF-8 BOM, comma-delimited).
 *
 * Configure on the model:
 *   protected array $exportable = ['id', 'name', 'email'];                 // optional, defaults to id + fillable + created_at
 *   protected array $exportHeaders = ['email' => 'Email Address'];         // optional friendly labels
 *
 * Override per-row formatting (e.g. eager-loaded relations, computed fields):
 *   public function toExportRow(): array { return [...]; }
 *
 * Use from a controller:
 *   return User::query()->where(...)->exportCsv('users.csv');
 */
trait Exportable
{
    public static function getExportableColumns(): array
    {
        $instance = new static;
        $columns = $instance->exportable ?? [];

        if (empty($columns)) {
            $columns = array_merge(['id'], $instance->getFillable(), ['created_at']);
        }

        return array_values(array_unique($columns));
    }

    public static function getExportHeaders(): array
    {
        $instance = new static;
        $custom = $instance->exportHeaders ?? [];

        $headers = [];
        foreach (self::getExportableColumns() as $col) {
            $headers[$col] = $custom[$col] ?? ucwords(str_replace('_', ' ', $col));
        }

        return $headers;
    }

    public function toExportRow(): array
    {
        $row = [];
        foreach (self::getExportableColumns() as $col) {
            $value = data_get($this, $col);

            if ($value instanceof DateTimeInterface) {
                $value = $value->format('Y-m-d H:i');
            } elseif (is_bool($value)) {
                $value = $value ? '1' : '0';
            } elseif (is_array($value) || is_object($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }

            $row[$col] = $value;
        }

        return $row;
    }

    public function scopeExportCsv(Builder $query, string $filename): StreamedResponse
    {
        $headers = self::getExportHeaders();

        self::recordExportActivity((clone $query)->count(), request()->query(), $filename);

        return response()->streamDownload(function () use ($query, $headers) {
            $out = fopen('php://output', 'w');

            // UTF-8 BOM so Excel renders Arabic / unicode correctly.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_values($headers));

            $query->chunk(500, function ($rows) use ($out, $headers) {
                foreach ($rows as $row) {
                    $data = $row->toExportRow();
                    fputcsv($out, array_map(fn ($k) => $data[$k] ?? '', array_keys($headers)));
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected static function recordExportActivity(int $count, array $filters, string $filename): void
    {
        $user = Auth::guard('web')->user();

        if (! $user) {
            return;
        }

        ActivityLog::create([
            'causer_name' => $user->name,
            'causer_email' => $user->email,
            'subject_type' => static::class,
            'subject_id' => null,
            'action' => 'exported',
            'old_data' => null,
            'new_data' => [
                'filename' => $filename,
                'count' => $count,
                'filters' => array_filter($filters, fn ($v) => $v !== null && $v !== ''),
                'columns' => self::getExportableColumns(),
            ],
        ]);
    }
}
