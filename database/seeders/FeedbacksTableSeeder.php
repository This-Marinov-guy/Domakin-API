<?php

namespace Database\Seeders;

use App\Models\Feedback;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FeedbacksTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        Feedback::factory()->count(10)->create();
    }
}
