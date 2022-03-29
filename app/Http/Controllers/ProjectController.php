<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Project;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Models\State;
use Illuminate\Support\Facades\DB;

class ProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {

        $user = Auth::user();
        $project_counts = [];
        $query = $user->projects();
        $query->when(request('ownership') == 'mine', function($q) use($user){
            return $q->where('created_by_id', $user->getAuthIdentifier());
        });
        $query->when(request('ownership') == 'others', function($q) use($user){
            return $q->where('created_by_id', '!=', $user->getAuthIdentifier());
        });
        $projects = $query->withCount(['collaborators', 'tasks'])->paginate(5);

        foreach ($projects as $project){
            foreach (State::all() as $state){
                $project_counts[$project->id][$state->name] = DB::table('projects')
                    ->where('projects.id', $project->id)
                    ->join('tasks', 'projects.id', '=', 'tasks.project_id')
                    ->where('tasks.state_id', '=', $state->id)->count();
            }
        }
        return response(['projects'=>$projects, 'project_counts' => $project_counts], 200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required',
            'description' => 'required'
        ]);
        $array_of_users = [];
        $users_email_array = $request->get('user_emails');
        foreach ($users_email_array as $user_email) {
            $user_id = User::where('email', $user_email)->first();
            if($user_id){
                $user_id = $user_id->id;
                $user = User::find($user_id);
                if(!isset($user)) {
                    return response(['message' => 'Assigned user doesn\'t exist'], 422);
                }
            }
            else{
                return response(['message' => 'Email of assigned user doesn\'t exist'], 422);
            }
            if (in_array($user_id, $array_of_users)) {
                return response(['message' => "Duplicate emails added to projects"], 422);
            }
            $array_of_users[] = $user->id;
        }

        $user = Auth::user();

        if (in_array($user->getAuthIdentifier(), $array_of_users)) {
            return response(['message' => "Duplicate emails added to projects"], 422);
        }

        $new_project = new Project($request->all());
        $new_project['created_by_id'] = $user->getAuthIdentifier();
        $new_project->save();
        $new_project->collaborators()->attach($array_of_users);
        $new_project->collaborators()->attach($user);

        if($request->has('project_users_id')) {
            $user_ids = $request->json()->all()['project_users_id'];
            $users = User::whereIn('id', $user_ids)->get();
            $new_project->collaborators()->attach($users);
        }
        $created_project = Project::with('collaborators', 'tasks', 'creator')->find($new_project['id']);
        return response($created_project, 201);
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
        $project = Project::findOrFail($id);
        $collaborators_ids = $project->collaborators->pluck('id')->toArray();
        if(in_array($user->getAuthIdentifier(), $collaborators_ids)) {
            return Project::with(['tasks', 'collaborators', 'creator'])->find($id);
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
        $project = Project::findOrFail($id);
        $user_id = Auth::user()->getAuthIdentifier();
        if($project->created_by_id != $user_id){
            return response(['message' => 'You don\'t have permission to update this record'], 403);
        }
        $project->update($request->all());
        return $project;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    public function getAttachment($id){
        $user = Auth::user();
        $project = Project::findOrFail($id);
        $collaborators_ids = $project->collaborators->pluck('id')->toArray();

        if(in_array($user->getAuthIdentifier(), $collaborators_ids)) {
            try{
                $file_path = Storage::path("$project->file_path");
                if (!$file_path){
                    return response(['message' => 'File not found'],404);
                }
                return response()->file($file_path);
            }
           catch(\Exception $e){
                return response(['message' => 'File not found'],404);
           }


        }
        return response(['message' => 'You don\'t have permissions to see this project'],403);
    }

    public function saveAttachment(Request $request, $id){
        $user = Auth::user();
        $project = Project::findOrFail($id);
        if($user->getAuthIdentifier() == $project['created_by_id']) {
            if ($request->hasFile('file')) {
                if ($request->file('file')->isValid()) {
                    $attachment = $request->file('file');
                    $content = File::get($attachment);
                    $fileName = 'pdf-' . $id . '.' . $attachment->getClientOriginalExtension();
                    $project->file_path = $fileName;
                    $project->save();
                    Storage::put($fileName, $content);
                    return response(['message' => 'OK'], 200);
                }
            } else {
                return response(['message' => 'no file'], 404);
            }
            return response(['message' => 'no file'], 404);
        }
        return response(['message' => 'Only project creator can upload documentation for project'],403);
    }

    public function addUsersToProject(Request $request, $id)
    {
        $user = Auth::user();
        $project = Project::findOrFail($id);
        if ($user->getAuthIdentifier() == $project['created_by_id']) {
            $array_of_users = [];
            $users_email_array = $request->get('user_emails');
            foreach ($users_email_array as $user_email) {
                $user_id = User::where('email', $user_email)->first();
                if($user_id){
                    $user_id = $user_id->id;
                    $user = User::find($user_id);
                    if(!isset($user)) {
                        return response(['message' => 'Assigned user doesn\'t exist'], 422);
                    }
                }
                else{
                    return response(['message' => 'Email of assigned user doesn\'t exist'], 422);
                }
                if ($project->collaborators->contains($user_id)) {
                    return response(['message' => "Email already exists - $user->email"], 422);
                }
                $array_of_users[] = $user->id;
            }
            $project->collaborators()->attach($array_of_users);
            return (Project::with(['tasks', 'creator', 'collaborators'])->find($id));
        }
        return response(['message' => 'Only project creator can add users to project'],403);
    }
}
