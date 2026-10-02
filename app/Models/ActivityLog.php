<?php

namespace App\Models;

use App\Traits\Exportable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class ActivityLog extends Model
{
    use Exportable, HasFactory;

    /**
     * The log is an audit trail: an admin who could delete an entry could delete the record
     * of what they did. The panel offers no delete and no route reaches one; this is the
     * backstop for any code path added later (the same guard NotificationTemplate and Page
     * use for rows that must never go).
     */
    protected static function booted(): void
    {
        static::deleting(function (): void {
            throw new RuntimeException('Activity log entries cannot be deleted.');
        });
    }

    protected $fillable = [
        'causer_name',
        'causer_email',
        'subject_type',
        'subject_id',
        'action',
        'old_data',
        'new_data',
    ];

    protected function casts(): array
    {
        return [
            'old_data' => 'array',
            'new_data' => 'array',
        ];
    }

    protected array $exportable = [
        'id',
        'causer_name',
        'causer_email',
        'subject_type',
        'subject_id',
        'action',
        'created_at',
    ];

    protected array $exportHeaders = [
        'subject_type' => 'Subject',
        'causer_name' => 'Performed By',
        'causer_email' => 'Email',
        'created_at' => 'Date',
    ];

    /**
     * @return array<string, mixed>
     */
    public function toExportRow(): array
    {
        return [
            'id' => $this->id,
            'causer_name' => $this->causer_name,
            'causer_email' => $this->causer_email,
            'subject_type' => $this->subject_type ? class_basename($this->subject_type) : null,
            'subject_id' => $this->subject_id,
            'action' => $this->action,
            'created_at' => $this->created_at?->format('Y-m-d H:i'),
        ];
    }
}
