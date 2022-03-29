<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TaskController;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// routes that need api token authorization
Route::group(['middleware' => ['auth:sanctum']], function() {
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('/projects/{project_id}/tasks', [TaskController::class, 'index']);
    Route::get('/tasks/{task_id}', [TaskController::class, 'show']);
    Route::post('/projects/{project_id}/tasks', [TaskController::class, 'store']);
    Route::put('/tasks/{task_id}', [TaskController::class, 'update']);
    Route::delete('/tasks/{task_id}', [TaskController::class, 'destroy']);
    Route::delete('/tasks/{task_id}/changeState', [TaskController::class, 'changeState']);

    Route::resource('/projects', ProjectController::class);
    Route::get('/projects/{id}/attachment', [ProjectController::class, 'getAttachment']);
    Route::post('/projects/{id}/attachment', [ProjectController::class, 'saveAttachment']);
    Route::post('/projects/{id}/add-user', [ProjectController::class, 'addUsersToProject']);
});

