<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // La estructura actual ya excluye este dato.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // El atributo fue descartado del modelo y no se restaura.
    }
};
