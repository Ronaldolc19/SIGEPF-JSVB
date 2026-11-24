<?php

use App\Http\Controllers\AcademicoController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AdscripcionPersonalController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\CorrespondenciaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpedienteController;
use App\Http\Controllers\IncidenciaController;
use App\Http\Controllers\MaterialConsultaController;
use App\Http\Controllers\PersonaController;
use App\Http\Controllers\PersonalFormacionController;
use App\Http\Controllers\ProductividadController;
use App\Http\Controllers\ProgramacionProductividadController;
use App\Http\Controllers\PromocionController;
use App\Http\Controllers\PromotionSelectionController;
use App\Http\Controllers\RegistroPersonalController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\UserPersonaController;
use App\Http\Controllers\UserRoleController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AuthenticatedSessionController::class, 'create'])->middleware('guest')->name('home');

Route::middleware(['auth'])->group(function () {
    Route::middleware('can:academico.ver')->group(function () {
        Route::get('academico', [AcademicoController::class, 'index'])->name('academico.index');
        Route::get('academico/entregar', [AcademicoController::class, 'form'])->middleware('can:academico.registrar')->name('academico.create');
        Route::post('academico', [AcademicoController::class, 'store'])->middleware('can:academico.registrar')->name('academico.store');
        Route::get('academico/{id_documento}', [AcademicoController::class, 'show'])->whereNumber('id_documento')->name('academico.show');
        Route::get('academico/{id_documento}/archivo', [AcademicoController::class, 'download'])->whereNumber('id_documento')->name('academico.download');
        Route::post('academico/{id_documento}/revision', [AcademicoController::class, 'review'])->whereNumber('id_documento')->middleware('can:academico.revisar')->name('academico.review');
    });
    Route::middleware('can:correspondencia.ver')->prefix('correspondencia')->name('correspondencia.')->group(function () {
        Route::get('/', [CorrespondenciaController::class, 'index'])->name('index');
        Route::middleware('can:panel.administrador')->group(function () {
            Route::get('crear', [CorrespondenciaController::class, 'form'])->middleware('can:correspondencia.registrar')->name('create');
            Route::post('/', [CorrespondenciaController::class, 'save'])->middleware('can:correspondencia.registrar')->name('store');
            Route::get('{id_correspondencia}/editar', [CorrespondenciaController::class, 'form'])->whereNumber('id_correspondencia')->middleware('can:correspondencia.editar')->name('edit');
            Route::put('{id_correspondencia}', [CorrespondenciaController::class, 'save'])->whereNumber('id_correspondencia')->middleware('can:correspondencia.editar')->name('update');
            Route::post('{id_correspondencia}/publicar', [CorrespondenciaController::class, 'publish'])->whereNumber('id_correspondencia')->middleware('can:correspondencia.editar')->name('publish');
            Route::post('{id_correspondencia}/seguimiento', [CorrespondenciaController::class, 'transition'])->whereNumber('id_correspondencia')->middleware('can:correspondencia.editar')->name('transition');
            Route::post('{id_correspondencia}/respuestas/{id_respuesta}/revision', [CorrespondenciaController::class, 'review'])->whereNumber(['id_correspondencia', 'id_respuesta'])->middleware('can:correspondencia.editar')->name('review');
            Route::delete('{id_correspondencia}', [CorrespondenciaController::class, 'destroy'])->whereNumber('id_correspondencia')->middleware('can:correspondencia.eliminar')->name('destroy');
        });
        Route::get('{id_correspondencia}', [CorrespondenciaController::class, 'show'])->whereNumber('id_correspondencia')->name('show');
        Route::post('{id_correspondencia}/destinatarios/{id_destinatario}/confirmar', [CorrespondenciaController::class, 'acknowledge'])->whereNumber(['id_correspondencia', 'id_destinatario'])->middleware('can:correspondencia.registrar')->name('acknowledge');
        Route::post('{id_correspondencia}/respuestas', [CorrespondenciaController::class, 'respond'])->whereNumber('id_correspondencia')->middleware('can:correspondencia.registrar')->name('respond');
        Route::get('{id_correspondencia}/archivos/{id_archivo}', [CorrespondenciaController::class, 'download'])->whereNumber(['id_correspondencia', 'id_archivo'])->name('download');
        Route::get('{id_correspondencia}/respuestas/{id_respuesta}/archivo', [CorrespondenciaController::class, 'downloadResponse'])->whereNumber(['id_correspondencia', 'id_respuesta'])->name('downloadResponse');
    });
    Route::middleware('can:productividad.ver')->group(function () {
        Route::middleware(['can:panel.administrador', 'can:productividad.editar'])->group(function () {
            Route::get('productividad/programacion', [ProgramacionProductividadController::class, 'index'])->name('productividad.programacion.index');
            Route::get('productividad/programacion/crear', [ProgramacionProductividadController::class, 'form'])->name('productividad.programacion.create');
            Route::post('productividad/programacion', [ProgramacionProductividadController::class, 'save'])->name('productividad.programacion.store');
            Route::get('productividad/programacion/{id_programacion}/editar', [ProgramacionProductividadController::class, 'form'])->whereNumber('id_programacion')->name('productividad.programacion.edit');
            Route::put('productividad/programacion/{id_programacion}', [ProgramacionProductividadController::class, 'save'])->whereNumber('id_programacion')->name('productividad.programacion.update');
            Route::post('productividad/programacion/{id_programacion}/estado', [ProgramacionProductividadController::class, 'toggle'])->whereNumber('id_programacion')->name('productividad.programacion.toggle');
            Route::post('productividad/programacion/{id_programacion}/meta', [ProgramacionProductividadController::class, 'goal'])->whereNumber('id_programacion')->name('productividad.programacion.goal');
        });
        Route::get('productividad/personal/{id_personal}/resumen', [ProductividadController::class, 'report'])->whereNumber('id_personal')->name('productividad.report');
        Route::get('productividad', [ProductividadController::class, 'index'])->name('productividad.index');
        Route::get('productividad/crear', [ProductividadController::class, 'form'])->middleware('can:productividad.registrar')->name('productividad.create');
        Route::post('productividad', [ProductividadController::class, 'save'])->middleware('can:productividad.registrar')->name('productividad.store');
        Route::get('productividad/{id_productividad}/editar', [ProductividadController::class, 'form'])->whereNumber('id_productividad')->middleware('can:productividad.editar')->name('productividad.edit');
        Route::put('productividad/{id_productividad}', [ProductividadController::class, 'save'])->whereNumber('id_productividad')->middleware('can:productividad.editar')->name('productividad.update');
        Route::post('productividad/{id_productividad}/revision', [ProductividadController::class, 'review'])->whereNumber('id_productividad')->middleware('can:productividad.revisar')->name('productividad.review');
        Route::delete('productividad/{id_productividad}', [ProductividadController::class, 'destroy'])->whereNumber('id_productividad')->middleware('can:productividad.eliminar')->name('productividad.destroy');
    });
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
