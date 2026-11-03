<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Mapeo opcional sector de GestPro -> sector SGR. Solo se usa para
        // preseleccionar el sector al crear un expediente; no restringe nada.
        Schema::table('sectores', function (Blueprint $table) {
            $table->foreignId('requisitos_sector_id')->nullable()->after('nombre')
                ->constrained('requisitos_sectores')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sectores', function (Blueprint $table) {
            $table->dropForeign(['requisitos_sector_id']);
            $table->dropColumn('requisitos_sector_id');
        });
    }
};
