<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Spatie\Permission\Models\Permission;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@electrohr.test'],
            [
                'name'              => 'Administrador',
                'email_verified_at' => now(),
                'password'          => Hash::make('Admin1234!'),
            ]
        );

        $admin->syncRoles(['admin']);
        $admin->syncPermissions(Permission::all());
    }
}
