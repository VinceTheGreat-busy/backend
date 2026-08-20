<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'first_name' => 'Vince',
            'last_name' => 'Palaming',
            'password' => 'vinceP@112108',
            'username' => 'Super Admin',
            'email' => 'vincepalaming2108@gmail.com',
            'role' => 'ADMIN'
        ]);
    }
}
