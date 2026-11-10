<?php

return [
    'admin' => [
        'name' => env('SIGEPF_ADMIN_NAME', 'Administrador'),
        'email' => env('SIGEPF_ADMIN_EMAIL'),
        'password' => env('SIGEPF_ADMIN_PASSWORD'),
    ],
    'roles' => ['administrador' => 'Administrador', 'asesor' => 'Asesor', 'pasante' => 'Pasante'],
    'role_modules' => [
        'asesor' => [
            'personal' => ['ver'],
            'incidencias' => ['ver', 'registrar', 'editar', 'revisar'],
            'productividad' => ['ver', 'registrar', 'editar'],
            'academico' => ['ver', 'revisar'],
        ],
        'pasante' => [
            'incidencias' => ['ver'],
            'expedientes' => ['ver', 'registrar', 'eliminar'],
            'personal' => ['ver'],
            'consulta' => ['ver'],
        ],
    ],
    'modules' => [
        'personal' => ['nombre' => 'Personal en formación', 'acciones' => ['crear', 'ver', 'editar', 'eliminar']],
        'expedientes' => ['nombre' => 'Expediente digital', 'acciones' => ['registrar', 'ver', 'editar', 'eliminar', 'revisar']],
        'incidencias' => ['nombre' => 'Incidencias', 'acciones' => ['registrar', 'ver', 'editar', 'eliminar', 'revisar']],
        'productividad' => ['nombre' => 'Productividad semanal', 'acciones' => ['registrar', 'ver', 'editar', 'eliminar', 'revisar']],
        'correspondencia' => ['nombre' => 'Correspondencia', 'acciones' => ['registrar', 'ver', 'editar', 'eliminar']],
        'consulta' => ['nombre' => 'Portal de consulta', 'acciones' => ['crear', 'ver', 'editar', 'eliminar', 'publicar']],
        'academico' => ['nombre' => 'Repositorio académico', 'acciones' => ['registrar', 'ver', 'editar', 'eliminar', 'revisar']],
        'acceso' => ['nombre' => 'Usuarios y permisos', 'acciones' => ['ver', 'editar']],
        'catalogos' => ['nombre' => 'Catálogos y asignaciones', 'acciones' => ['crear', 'ver', 'editar', 'eliminar']],
    ],
];
