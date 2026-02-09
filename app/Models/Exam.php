<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Exam extends Model
{
    protected $fillable = [
        'Exam_Title',
        'Exam_Type',
        'created_by'
    ];

    public function creator()
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }
}
