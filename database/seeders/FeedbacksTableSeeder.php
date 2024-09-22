<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FeedbacksTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        DB::table('feedbacks')->insert([
            [
                'language' => 'English',
                'name' => 'John Doe',
                'content' => 'This is a great product!',
            ],
            [
                'language' => 'Spanish',
                'name' => 'Anonymous',
                'content' => 'Me gusta este producto.',
            ],

        ]);
    }
}
