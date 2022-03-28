<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\Task;
use App\Models\User;

class TaskController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($id)
    {
        $user = Auth::user();
        $project = Project::findOrFail($id);

        $query = $project->tasks();
        $query->when(request('assigned_to') == 'mine', function($q) use($user){
            return $q->where('assignee_id', $user->getAuthIdentifier());
        });
        $query->when(request('assigned_to') == 'others', function($q) use($user){
            return $q->where('assignee_id', '!=', $user->getAuthIdentifier());
        });
        $query->when(request('assigned_to') == 'unassigned', function($q) use($user){
            return $q->where('assignee_id', '!=', $user->getAuthIdentifier());
        });

        $tasks = $query->paginate(5);

        $collaborators_ids = $project->collaborators->pluck('id')->toArray();
        if(in_array($user->getAuthIdentifier(), $collaborators_ids)) {
            $tasks = $project->tasks->toQuery()->with('state')->get();
            return $tasks;
        }
        return response(['message' => 'You don\'t have permissions to see this project'],403);
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
        $user = Auth::user();
        $project = Project::findOrFail($id);
        $collaborators_ids = $project->collaborators->pluck('id')->toArray();
        if(in_array($user->getAuthIdentifier(), $collaborators_ids)) {
            if($request->has('asignee_id')){
                $user = User::find($request['asignee_id']);
                if(!isset($user)) {
                    return response(['message' => 'Assigned user doesn\'t exist'], 400);
                }
            }
            $new_task = new Task($request->all());
            $new_task['created_by_id'] = Auth::user()->getAuthIdentifier();
            $new_task['project_id'] = $id;
            $new_task->save();
            return response($new_task, 201);
        }
        return response(['message' => 'You don\'t have permissions to see this project'],403);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $user = Auth::user();
        $task = Task::findOrFail($id);
        $project = Project::findOrFail($task->project_id);
        $collaborators_ids = $project->collaborators->pluck('id')->toArray();
        if(in_array($user->getAuthIdentifier(), $collaborators_ids)) {
            $task = Task::with('state')->findOrFail($id);
            return $task;
        }
        return response(['message' => 'You don\'t have permissions to see this project'],403);
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
        $task = Task::with('state')->findOrFail($id);
        $user_id = Auth::user()->getAuthIdentifier();
        $user = Auth::user();
        $project = Project::findOrFail($task->project_id);
        $collaborators_ids = $project->collaborators->pluck('id')->toArray();
        if(in_array($user->getAuthIdentifier(), $collaborators_ids)) {
            if ($task->created_by_id != $user_id) {
                return response(['message' => 'You don\'t have permission to update this task'], 403);
            }
            $task->update($request->all());
            $task->save();
            return response($task, 200);
        }
        return response(['message' => 'You don\'t have permissions to see this project'],403);
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
        $user = Auth::user();
        $project = Project::findOrFail($task->project_id);
        $collaborators_ids = $project->collaborators->pluck('id')->toArray();
        if(in_array($user->getAuthIdentifier(), $collaborators_ids)) {
            if ($task->created_by_id != $user_id) {
                return response(['message' => 'You don\'t have permission to delete this record'], 403);
            }
            Task::destroy($id);
            return response(['message' => 'deleted'], 200);
        }
        return response(['message' => 'You don\'t have permissions to see this project'],403);
    }
}
