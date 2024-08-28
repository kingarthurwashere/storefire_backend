<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('users')->insert([
            [
                'name' => 'Admin User',
                'email' => 'admin@dxbrunners.co.zw',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ],
            [
                'name' => 'Editor User',
                'email' => 'editor@dxbrunners.co.zw',
                'password' => Hash::make('password'),
                'role' => 'editor',
            ],
            [
                'name' => 'Viewer User',
                'email' => 'viewer@dxbrunners.co.zw',
                'password' => Hash::make('password'),
                'role' => 'viewer',
            ],
        ]);
    }
}
