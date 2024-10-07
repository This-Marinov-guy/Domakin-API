<?php

namespace Database\Seeders;


use Illuminate\Database\Seeder;
use App\Models\Viewing;

class ViewingSeeder extends Seeder
{
    //TODO does cannot seed this yes, need to fix

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Viewing::create([
            'name' => 'John',
            'surname' => 'Name',
            'phone' => '0123456789',
            'email' => 'john.name@example.com',
            'city' => 'Groningen',
            'address' => 'Example Street 123, 1234 AB, Groningen ',
            'date' => '2024-01-01',
            'note' => '',
        ]);
    }
}
