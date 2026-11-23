<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehiculos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 80);                 // "Carroza 01 (velorio)"
            $table->string('patente', 10)->unique();
            $table->enum('tipo', ['carroza', 'instalacion', 'externo']);
            $table->string('observacion', 200)->nullable();
            $table->enum('estado', ['disponible', 'en_servicio', 'en_mantencion'])->default('disponible');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('choferes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('usuarios')->restrictOnDelete();
            $table->string('nombre', 120);
            $table->string('telefono', 30)->nullable();
            $table->string('licencia', 20)->nullable();
            $table->enum('jornada', ['completa', 'part_time', 'externo'])->default('completa');
            $table->enum('estado', ['disponible', 'en_servicio', 'no_disponible'])->default('disponible');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('asignaciones_flota', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_id')->unique()->constrained('solicitudes')->restrictOnDelete();
            $table->foreignId('vehiculo_id')->constrained('vehiculos')->restrictOnDelete();
            $table->foreignId('chofer_id')->constrained('choferes')->restrictOnDelete();
            $table->dateTime('inicio');
            $table->dateTime('fin');
            $table->string('origen', 200)->nullable();
            $table->string('destino', 200)->nullable();
            $table->json('hoja_ruta')->nullable();         // [{hora, lugar, direccion, nota}]
            $table->text('notas')->nullable();
            $table->boolean('apoyo_externo')->default(false);
            $table->enum('estado', ['programada', 'en_curso', 'completada', 'cancelada'])->default('programada');
            $table->timestamps();
            $table->index(['vehiculo_id', 'inicio', 'fin']);
            $table->index(['chofer_id', 'inicio', 'fin']);
        });

        Schema::create('ausencias_chofer', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chofer_id')->constrained('choferes')->restrictOnDelete();
            $table->enum('motivo', ['vacaciones', 'permiso', 'licencia', 'inasistencia']);
            $table->date('desde');
            $table->date('hasta');
            $table->string('detalle', 200)->nullable();
            $table->enum('estado', ['programada', 'vigente', 'finalizada'])->default('programada');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ausencias_chofer');
        Schema::dropIfExists('asignaciones_flota');
        Schema::dropIfExists('choferes');
        Schema::dropIfExists('vehiculos');
    }
};
