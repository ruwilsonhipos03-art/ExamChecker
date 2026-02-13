<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnswerSheet extends Model
{
    use HasFactory;

    protected $fillable = [
        'qr_payload',
        'exam_id',
        'user_id',
        'image_path',
        'scanned_data',
        'total_score',
        'status',
    ];

    protected $casts = [
        'scanned_data' => 'array',
    ];

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }
}
