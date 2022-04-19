<?php

namespace Database\Seeders;

use App\Models\State;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Project;
use App\Models\Task;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {

        State::create([
            'name' => 'TODO',
            'color' => '#900D09'
        ]);
        State::create([
            'name' => 'In Progress',
            'color' => '#FFCC00'
        ]);
        State::create([
            'name' => 'Done',
            'color' => '#028A0F'
        ]);

  //      $user = User::factory()->create();
   //     $user->createToken('apiToken')->plainTextToken;
        $user = User::find(1);

//      create 6 projects for user, each containing 3 tasks
        $projects = Project::factory(6)->has(
            Task::factory(3)
        )->create([
            'created_by_id' => $user->id,
        ]);

        $user->projects()->attach($projects);

    }
}
