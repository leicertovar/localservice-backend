<?php

namespace Database\Seeders;

use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Siembra los datos generales de la aplicación.
     */
    public function run(): void
    {
        // 1. Sembrar Roles primero
        $this->call(RoleSeeder::class);

        // 2. Crear administrador por defecto
        Usuario::updateOrCreate(
            ['email' => 'admin@localservice.com'],
            [
                'nombre' => 'Admin',
                'apellido' => 'LocalService',
                'telefono' => '123456789',
                'password' => Hash::make('admin12345'),
                'rol_id' => 3, // Rol Admin
                'esta_aprobado' => true,
            ]
        );

        // 3. Crear cliente por defecto
        Usuario::updateOrCreate(
            ['email' => 'client@localservice.com'],
            [
                'nombre' => 'Juan',
                'apellido' => 'Pérez',
                'telefono' => '987654321',
                'password' => Hash::make('client12345'),
                'rol_id' => 1, // Rol Cliente
                'esta_aprobado' => true,
            ]
        );
    }
}
