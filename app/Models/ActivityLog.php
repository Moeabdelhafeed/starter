<?php

namespace App\Models;

use App\Traits\Exportable;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use Exportable;

    protected $fillable = [
        'causer_name',
        'causer_email',
        'subject_type',
        'subject_id',
        'action',
        'old_data',
        'new_data',
    ];

    protected $casts = [
        'old_data' => 'array',
        'new_data' => 'array',
    ];

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
