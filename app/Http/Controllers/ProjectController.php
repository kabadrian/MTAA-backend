<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Project;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use App\Models\User;

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

        return $user->projects()->with(['tasks', 'creator', 'collaborators'])->paginate(10);
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
        $user = Auth::user();
        $new_project = new Project($request->all());
        $new_project['created_by_id'] = $user->getAuthIdentifier();
        $new_project->save();
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
        $project = Project::findOrFail($id);
        $file_path = Storage::path("$project->file_path");
        return response()->file($file_path);
    }

    public function saveAttachment(Request $request, $id){
        $project = Project::findOrFail($id);
        if ($request->hasFile('file')) {
            if($request->file('file')->isValid()){
                $attachment = $request->file('file');
                $content = File::get($attachment);
                $fileName ='pdf-'. $id . '.' . $attachment->getClientOriginalExtension();
                $project->file_path = $fileName;
                $project->save();
                Storage::put($fileName,$content);
                return response(['message' => 'OK'], 200);
            }
        }
        else{
            return response(['message' => 'no file']);
        }
        return response(['message' => 'no file']);
    }

    public function addUsersToProject(Request $request, $id){
        $project = Project::findOrFail($id);
        $users_id_array = $request->get('user_id');

        foreach ($users_id_array as $user_id){
            $user = User::find($user_id);
            if(!$user){
                return response(['message'=>"User with id $user_id doesn\'t exist"],400);
            }
            if($project->collaborators->contains($user)){
                return response(['message'=>"User with id $user_id is already on project"],400);
            }
            $project->collaborators()->attach($user);
        }
        return (Project::with(['tasks', 'creator', 'collaborators'])->find($id));
    }
}
