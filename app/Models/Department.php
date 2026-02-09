<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'Department_Name',
    ];

    public function programs()
    {
        return $this->hasMany(Program::class);
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }
}
