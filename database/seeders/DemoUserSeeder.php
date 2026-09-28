<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(['email' => 'mariana.costa1@acin.com'], [
            'name'     => 'Mariana Cosya',
            'password' => Hash::make('demo1234'),
            'tin'      => env('DEMO_TIN', '51458985'),   // <-- TIN of a real account in your Salesforce sandbox
        ]);
    }
}
