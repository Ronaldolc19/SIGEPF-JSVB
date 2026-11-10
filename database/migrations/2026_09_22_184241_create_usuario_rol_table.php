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
        Schema::create('usuario_rol', function (Blueprint $table) {
            $table->foreignId('user_id');
            $table->foreignId('rol_id');
            $table->timestamps();

            $table->primary(['user_id', 'rol_id']);
            $table->index('rol_id');

            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('rol_id')->references('id')->on('roles')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usuario_rol');
    }
};
