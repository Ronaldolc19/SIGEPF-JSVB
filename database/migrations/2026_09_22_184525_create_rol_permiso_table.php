<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rol_permiso', function (Blueprint $table) {
            $table->foreignId('rol_id');
            $table->foreignId('permiso_id');
            $table->timestamps();

            $table->primary(['rol_id', 'permiso_id']);
            $table->index('permiso_id');

            $table->foreign('rol_id')->references('id')->on('roles')->restrictOnDelete();
            $table->foreign('permiso_id')->references('id')->on('permisos')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rol_permiso');
    }
};
