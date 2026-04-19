<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class TranslationValue extends Model
{
    use LogsActivity;

    protected $fillable = [
        'translation_key_id',
        'value',
        'locale',
    ];

    public function key()
    {
        return $this->belongsTo(TranslationKey::class, 'translation_key_id');
    }
}
