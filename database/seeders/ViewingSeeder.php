<?php

namespace Database\Seeders;


use Illuminate\Database\Seeder;
use App\Models\Viewing;
use Faker\Factory as Faker;
use Illuminate\Support\Str;

class ViewingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();
        
        Viewing::create([
            'name' => $faker->firstName,
            'surname' => $faker->lastName,
            'phone' => $faker->phoneNumber,
            'email' => $faker->unique()->safeEmail,
            'city' => $faker->city,
            'address' => Str::limit($faker->address, limit: 49),
            'date' => $faker->date,
            'time' => $faker->time,
            'note' => $faker->sentence,
        ]);
    }
}
