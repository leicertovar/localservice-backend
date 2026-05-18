<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Rol;

class RoleSeeder extends Seeder
{
    /**
     * Llena la tabla de roles con los datos por defecto.
     */
    public function run(): void
    {
        $roles = [
            [
                'id' => 1,
                'nombre' => 'cliente',
                'nombre_mostrar' => 'Cliente',
                'descripcion' => 'Usuario que solicita y contrata servicios locales.',
            ],
            [
                'id' => 2,
                'nombre' => 'proveedor',
                'nombre_mostrar' => 'Proveedor',
                'descripcion' => 'Profesional o empresa que ofrece servicios locales y requiere aprobación de documentos.',
            ],
            [
                'id' => 3,
                'nombre' => 'admin',
                'nombre_mostrar' => 'Administrador',
                'descripcion' => 'Usuario administrativo con control total de la plataforma y aprobación de proveedores.',
            ],
        ];

        foreach ($roles as $rol) {
            Rol::updateOrCreate(
                ['nombre' => $rol['nombre']],
                [
                    'nombre_mostrar' => $rol['nombre_mostrar'],
                    'descripcion' => $rol['descripcion']
                ]
            );
        }
    }
}
