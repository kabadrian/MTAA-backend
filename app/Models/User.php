<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function created_projects(){
        return $this->hasMany(Project::class, 'created_by_id');
    }

    public function projects(){
        return $this->belongsToMany(Project::class, 'projects_users', 'user_id', 'project_id');
    }


//    public function assigned_tasks()
//    {
//        return $this->hasManyThrough(Task::class,Project::class,  'project_id', 'asignee_id');
//    }
//
//    public function created_tasks(){
//        return $this->hasManyThrough(Task::class, Project::class, 'project_id', 'created_by_id');
//    }
}
