<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\Task;
use function GuzzleHttp\Promise\task;

class TaskController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($id)
    {
        $project = Project::findOrFail($id);
        $tasks = $project->tasks;
        return $tasks;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, $id)
    {
        $request->validate([
            'title' => 'required',
            'description' => 'required',
            'state_id' => 'required',
        ]);
        $new_task = new Task($request->all());
        $new_task['created_by_id'] = Auth::user()->getAuthIdentifier();
        $new_task['project_id'] = $id;
        $new_task->save();
        return response($new_task, 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $task = Task::findOrFail($id);
        return $task;
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'title' => 'required',
            'description' => 'required',
        ]);
        $task = Task::findOrFail($id);
        $user_id = Auth::user()->getAuthIdentifier();
        if($task->created_by_id != $user_id){
            return response(['message' => 'You don\'t have permission to update this task'], 403);
        }
        $task->update($request->all());
        $task->save();
        return response($task, 201);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $task = Task::findOrFail($id);
        $user_id = Auth::user()->getAuthIdentifier();
        if($task->created_by_id != $user_id){
            return response(['message' => 'You don\'t have permission to delete this record'], 403);
        }
        Task::destroy($id);
        return response(['message' => 'deleted'], 200);
    }
}
