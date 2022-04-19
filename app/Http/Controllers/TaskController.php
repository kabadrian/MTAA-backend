<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\State;
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
    public function index(Request $request, $id)
    {
        $user = Auth::user();
        $project = Project::findOrFail($id);
        //return request('assigned_to');
        $query = $project->tasks->toQuery()->with(['state', 'asignee'])->orderBy('state_id')->get();
        $query->when(request('assigned_to') == 'mine', function($q) use($user){
            return $q->where('assignee_id', $user->getAuthIdentifier());
        });
        $query->when(request('assigned_to') == 'others', function($q) use($user){
            return $q->where('assignee_id', '!=', $user->getAuthIdentifier());
        });
        $query->when(request('assigned_to') == 'unassigned', function($q) use($user){
            return $q->where('assignee_id', '==', null);
        });

        //$tasks = $query->paginate(5);

        $collaborators_ids = $project->collaborators->pluck('id')->toArray();
        if(in_array($user->getAuthIdentifier(), $collaborators_ids)) {
            $tasks = $project->tasks->toQuery()->with(['state', 'asignee'])->get();
            return $query;
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
        $assignee_id = null;
        $project = Project::findOrFail($id);
        $collaborators_ids = $project->collaborators->pluck('id')->toArray();
        if(in_array($user->getAuthIdentifier(), $collaborators_ids)) {
            if ($request->has('assignee_email')){
                $assignee_id = User::where('email', $request->get('assignee_email'))->first();
                if($assignee_id){
                    $assignee_id = $assignee_id->id;
                    $user = User::find($assignee_id);
                    if(!isset($user)) {
                        return response(['message' => 'Assigned user doesn\'t exist'], 422);
                    }
                }

            }
            $new_task = new Task($request->all());
            $new_task['created_by_id'] = Auth::user()->getAuthIdentifier();
            $new_task['project_id'] = $id;
            if ($assignee_id){
                $new_task['asignee_id'] = $assignee_id;
            }
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
            if ($request->get('assignee_email')){
                $assignee_id = User::where('email', $request->get('assignee_email'))->first();
                if($assignee_id){
                    $assignee_id = $assignee_id->id;
                    $user = User::find($assignee_id);
                    if(!isset($user)) {
                        return response(['message' => 'Assigned user doesn\'t exist'], 422);
                    }
                    else{
                        $task['asignee_id'] = $assignee_id;
                    }
                }
                else{
                    return response(['message' => 'Email of assigned user doesn\'t exist'], 422);
                }
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

    public function changeState(Request $request, $id)
    {
        $request->validate([
            'state_id' => 'required',
        ]);
        $state = State::find($request->get('state_id'));
        if ($state == null){
            return response(['message' => 'State with given id doesn\'t exist'], 422);
        }

        $task = Task::with('state')->findOrFail($id);
        $user_id = Auth::user()->getAuthIdentifier();
        $user = Auth::user();
        $project = Project::findOrFail($task->project_id);
        $assignee_id = $task->asignee_id;
        if($user_id != $assignee_id) {
                return response(['message' => 'You don\'t have permission to update this task'], 403);
            }
        $task->update($request->only('state_id'));
        $task->save();
        return response($task, 200);
    }

    public function getAllStates()
    {
        return State::all();
    }
}
