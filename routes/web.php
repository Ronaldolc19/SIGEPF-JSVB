<?php

use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AdscripcionPersonalController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpedienteController;
use App\Http\Controllers\IncidenciaController;
use App\Http\Controllers\MaterialConsultaController;
use App\Http\Controllers\PersonaController;
use App\Http\Controllers\PersonalFormacionController;
use App\Http\Controllers\PromocionController;
use App\Http\Controllers\PromotionSelectionController;
use App\Http\Controllers\RegistroPersonalController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\UserPersonaController;
use App\Http\Controllers\UserRoleController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AuthenticatedSessionController::class, 'create'])->middleware('guest')->name('home');

Route::middleware(['auth'])->group(function () {
    Route::post('promocion-seleccionada', [PromotionSelectionController::class, 'update'])->name('promociones.select');
    Route::middleware('can:consulta.ver')->group(function () {
        Route::get('consulta', [MaterialConsultaController::class, 'index'])->name('consulta.index');
        Route::get('consulta/{id_material}/descargar', [MaterialConsultaController::class, 'download'])->whereNumber('id_material')->name('consulta.download');
        Route::middleware('can:panel.administrador')->group(function () {
            Route::get('consulta/crear', [MaterialConsultaController::class, 'create'])->middleware('can:consulta.crear')->name('consulta.create');
            Route::post('consulta', [MaterialConsultaController::class, 'store'])->middleware('can:consulta.crear')->name('consulta.store');
            Route::get('consulta/{id_material}/editar', [MaterialConsultaController::class, 'edit'])->whereNumber('id_material')->middleware('can:consulta.editar')->name('consulta.edit');
            Route::put('consulta/{id_material}', [MaterialConsultaController::class, 'update'])->whereNumber('id_material')->middleware('can:consulta.editar')->name('consulta.update');
            Route::delete('consulta/{id_material}', [MaterialConsultaController::class, 'destroy'])->whereNumber('id_material')->middleware('can:consulta.eliminar')->name('consulta.destroy');
        });
    });
    Route::middleware('can:incidencias.ver')->group(function () {
        Route::get('incidencias', [IncidenciaController::class, 'index'])->name('incidencias.index');
        Route::get('incidencias/crear', [IncidenciaController::class, 'create'])->middleware('can:incidencias.registrar')->name('incidencias.create');
        Route::post('incidencias', [IncidenciaController::class, 'store'])->middleware('can:incidencias.registrar')->name('incidencias.store');
        Route::get('incidencias/{id_incidencia}', [IncidenciaController::class, 'show'])->whereNumber('id_incidencia')->name('incidencias.show');
        Route::get('incidencias/{id_incidencia}/editar', [IncidenciaController::class, 'edit'])->whereNumber('id_incidencia')->middleware('can:incidencias.editar')->name('incidencias.edit');
        Route::put('incidencias/{id_incidencia}', [IncidenciaController::class, 'update'])->whereNumber('id_incidencia')->middleware('can:incidencias.editar')->name('incidencias.update');
        Route::post('incidencias/{id_incidencia}/seguimiento', [IncidenciaController::class, 'followup'])->whereNumber('id_incidencia')->middleware('can:incidencias.revisar')->name('incidencias.followup');
        Route::post('incidencias/{id_incidencia}/archivos', [IncidenciaController::class, 'attachment'])->whereNumber('id_incidencia')->middleware('can:incidencias.editar')->name('incidencias.attachment');
        Route::post('incidencias/{id_incidencia}/expediente', [IncidenciaController::class, 'linkEvidence'])->whereNumber('id_incidencia')->middleware('can:incidencias.editar')->name('incidencias.link');
        Route::get('incidencias/{id_incidencia}/archivos/{id_adjunto}', [IncidenciaController::class, 'download'])->whereNumber(['id_incidencia', 'id_adjunto'])->name('incidencias.download');
    });
    Route::middleware('can:expedientes.ver')->group(function () {
        Route::get('expedientes', [ExpedienteController::class, 'index'])->name('expedientes.index');
        Route::get('expedientes/{id_personal}', [ExpedienteController::class, 'show'])->whereNumber('id_personal')->name('expedientes.show');
        Route::post('expedientes/{id_personal}/documentos/{id_tipo_expediente}', [ExpedienteController::class, 'store'])->whereNumber(['id_personal', 'id_tipo_expediente'])->middleware('can:expedientes.registrar')->name('expedientes.store');
        Route::put('expedientes/{id_personal}/documentos/{id_tipo_expediente}/observaciones', [ExpedienteController::class, 'observations'])->whereNumber(['id_personal', 'id_tipo_expediente'])->middleware('can:expedientes.revisar')->name('expedientes.observations');
        Route::get('expedientes/{id_personal}/archivos/{id_archivo_expediente}', [ExpedienteController::class, 'download'])->whereNumber(['id_personal', 'id_archivo_expediente'])->name('expedientes.download');
        Route::delete('expedientes/{id_personal}/archivos/{id_archivo_expediente}', [ExpedienteController::class, 'destroy'])->whereNumber(['id_personal', 'id_archivo_expediente'])->middleware('can:expedientes.eliminar')->name('expedientes.destroy');
    });
    Route::get('personal', [PersonalFormacionController::class, 'index'])->name('personal.index');
    Route::get('personal/{id_personal}', [PersonalFormacionController::class, 'show'])->whereNumber('id_personal')->name('personal.show');
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    foreach (array_keys(config('access.roles')) as $role) {
        Route::get($role.'/inicio', [DashboardController::class, $role])
            ->middleware('can:panel.'.$role)->name($role.'.dashboard');
    }
    Route::middleware('can:panel.administrador')->group(function () {
        Route::get('promociones', [PromocionController::class, 'index'])->middleware('can:personal.ver')->name('promociones.index');
        Route::get('promociones/crear', [PromocionController::class, 'create'])->middleware('can:personal.crear')->name('promociones.create');
        Route::post('promociones', [PromocionController::class, 'store'])->middleware('can:personal.crear')->name('promociones.store');
        Route::get('promociones/{id_promocion}', [PromocionController::class, 'show'])->whereNumber('id_promocion')->middleware('can:personal.ver')->name('promociones.show');
        Route::get('promociones/{id_promocion}/editar', [PromocionController::class, 'edit'])->whereNumber('id_promocion')->middleware('can:personal.editar')->name('promociones.edit');
        Route::put('promociones/{id_promocion}', [PromocionController::class, 'update'])->whereNumber('id_promocion')->middleware('can:personal.editar')->name('promociones.update');
        Route::get('personal/registrar', [RegistroPersonalController::class, 'create'])->middleware('can:personal.crear')->name('personal.create');
        Route::post('personal', [RegistroPersonalController::class, 'store'])->middleware('can:personal.crear')->name('personal.store');
        Route::get('personal/{id_personal}/registro', [RegistroPersonalController::class, 'edit'])->whereNumber('id_personal')->middleware('can:personal.editar')->name('personal.registro.edit');
        Route::put('personal/{id_personal}/registro', [RegistroPersonalController::class, 'update'])->whereNumber('id_personal')->middleware('can:personal.editar')->name('personal.registro.update');
        Route::get('personal/unidades/{id_unidad}', [RegistroPersonalController::class, 'unidad'])->whereNumber('id_unidad')->middleware('can:personal.ver')->name('personal.unidad');
        Route::get('personal/{id_personal}/editar', [RegistroPersonalController::class, 'edit'])->whereNumber('id_personal')->middleware('can:personal.editar')->name('personal.edit');
        Route::put('personal/{id_personal}', [PersonalFormacionController::class, 'update'])->whereNumber('id_personal')->middleware('can:personal.editar')->name('personal.update');
        Route::get('personal/{id_personal}/adscripciones/crear', [AdscripcionPersonalController::class, 'create'])->whereNumber('id_personal')->middleware('can:personal.editar')->name('adscripciones.create');
        Route::post('personal/{id_personal}/adscripciones', [AdscripcionPersonalController::class, 'store'])->whereNumber('id_personal')->middleware('can:personal.editar')->name('adscripciones.store');
        Route::get('personal/{id_personal}/adscripciones/{id_adscripcion}/editar', [AdscripcionPersonalController::class, 'edit'])->whereNumber(['id_personal', 'id_adscripcion'])->middleware('can:personal.editar')->name('adscripciones.edit');
        Route::put('personal/{id_personal}/adscripciones/{id_adscripcion}', [AdscripcionPersonalController::class, 'update'])->whereNumber(['id_personal', 'id_adscripcion'])->middleware('can:personal.editar')->name('adscripciones.update');
        Route::get('administrador/usuarios/crear', [AdminUserController::class, 'create'])->middleware('can:acceso.editar')->name('usuarios.create');
        Route::post('administrador/usuarios', [AdminUserController::class, 'store'])->middleware('can:acceso.editar')->name('usuarios.store');
        Route::get('administrador/personas', [PersonaController::class, 'index'])->middleware('can:personal.ver')->name('personas.index');
        Route::get('administrador/personas/crear', [PersonaController::class, 'create'])->middleware('can:personal.crear')->name('personas.create');
        Route::post('administrador/personas', [PersonaController::class, 'store'])->middleware('can:personal.crear')->name('personas.store');
        Route::get('administrador/personas/{persona}', [PersonaController::class, 'show'])->whereNumber('persona')->middleware('can:personal.ver')->name('personas.show');
        Route::get('administrador/personas/{persona}/editar', [PersonaController::class, 'edit'])->whereNumber('persona')->middleware('can:personal.editar')->name('personas.edit');
        Route::put('administrador/personas/{persona}', [PersonaController::class, 'update'])->whereNumber('persona')->middleware('can:personal.editar')->name('personas.update');
        Route::get('administrador/catalogos', [CatalogoController::class, 'index'])->middleware('can:catalogos.ver')->name('catalogos.index');
        Route::get('administrador/catalogos/{catalogo}', [CatalogoController::class, 'show'])->middleware('can:catalogos.ver')->name('catalogos.show');
        Route::get('administrador/catalogos/{catalogo}/opciones', [CatalogoController::class, 'options'])->middleware('can:catalogos.ver')->name('catalogos.options');
        Route::get('administrador/catalogos/{catalogo}/crear', [CatalogoController::class, 'create'])->middleware('can:catalogos.crear')->name('catalogos.create');
        Route::post('administrador/catalogos/{catalogo}', [CatalogoController::class, 'store'])->middleware('can:catalogos.crear')->name('catalogos.store');
        Route::get('administrador/catalogos/{catalogo}/{registro}/editar', [CatalogoController::class, 'edit'])->whereNumber('registro')->middleware('can:catalogos.editar')->name('catalogos.edit');
        Route::put('administrador/catalogos/{catalogo}/{registro}', [CatalogoController::class, 'update'])->whereNumber('registro')->middleware('can:catalogos.editar')->name('catalogos.update');
        Route::delete('administrador/catalogos/{catalogo}/{registro}', [CatalogoController::class, 'destroy'])->whereNumber('registro')->middleware('can:catalogos.eliminar')->name('catalogos.destroy');

        Route::get('administrador/usuarios/{user}/persona', [UserPersonaController::class, 'edit'])->middleware('can:acceso.editar')->name('usuarios.persona.edit');
        Route::put('administrador/usuarios/{user}/persona', [UserPersonaController::class, 'update'])->middleware('can:acceso.editar')->name('usuarios.persona.update');
        Route::put('administrador/roles/{rol}/permisos', [RolePermissionController::class, 'update'])->middleware('can:acceso.editar')->name('roles.permisos.update');
        Route::get('administrador/usuarios', [UserRoleController::class, 'index'])->middleware('can:acceso.ver')->name('usuarios.index');
        Route::put('administrador/usuarios/{user}/roles', [UserRoleController::class, 'update'])->middleware('can:acceso.editar')->name('usuarios.roles.update');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
