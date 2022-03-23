<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'state_id',
        'created_by_id'
    ];

    public function project(){
        return $this->belongsTo(Project::class);
    }

    public function creator(){
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function asignee(){
        return $this->belongsTo(User::class, 'asignee_id');
    }

    public function state(){
        return $this->belongsTo(State::class);
    }
}
