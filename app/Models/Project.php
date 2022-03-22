<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Task;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'created_by_id'
    ];

    public function tasks(){
        return $this->hasMany(Task::class);
    }

    public function creator(){
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function collaborators(){
        return $this->belongsToMany(User::class, 'projects_users', 'project_id', 'user_id');
    }
}
