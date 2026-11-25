<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cementerios y crematorios frecuentes: en la solicitud se eligen de una lista según el
 * destino final (sepultura → cementerios, cremación → crematorios), ordenados por cercanía
 * a Temuco. El administrador los mantiene en Configuración.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lugares_destino', function (Blueprint $table) {
            $table->id();
            $table->enum('tipo', ['cementerio', 'crematorio']);
            $table->string('nombre', 150);
            $table->string('direccion', 200)->nullable();
            $table->string('comuna', 80);
            $table->string('telefono', 30)->nullable();
            $table->unsignedSmallInteger('orden')->default(50); // menor = más cerca de Temuco
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->index(['tipo', 'activo', 'orden']);
        });

        // Cementerios de Temuco y alrededores (fuente: catastro de cementerios de La Araucanía, genealog.cl).
        // Los crematorios con los que trabaja la funeraria se agregan desde Configuración.
        $ahora = now();
        $cem = fn ($nombre, $comuna, $orden, $direccion = null) => [
            'tipo' => 'cementerio', 'nombre' => $nombre, 'direccion' => $direccion, 'comuna' => $comuna,
            'orden' => $orden, 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora,
        ];
        DB::table('lugares_destino')->insert([
            $cem('Cementerio General de Temuco', 'Temuco', 1, 'Balmaceda 143'),
            $cem('Cementerio Parque del Sendero Temuco', 'Temuco', 2, 'Villa Las Encinas 01151'),
            $cem('Cementerio Parque Jardín de Temuco', 'Temuco', 3),
            $cem('Cementerio Parque Las Flores', 'Temuco', 4),
            $cem('Cementerio Parque Monteverde', 'Temuco', 5),
            $cem('Cementerio Judío de Temuco', 'Temuco', 6),
            $cem('Cementerio Municipal de Padre Las Casas', 'Padre Las Casas', 10),
            $cem('Cementerio Municipal de Vilcún', 'Vilcún', 20),
            $cem('Cementerio de Freire', 'Freire', 21),
            $cem('Cementerio General de Lautaro', 'Lautaro', 22),
            $cem('Cementerio Municipal de Nueva Imperial', 'Nueva Imperial', 23),
            $cem('Cementerio Municipal de Perquenco', 'Perquenco', 30),
            $cem('Cementerio Municipal de Cunco', 'Cunco', 31),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('lugares_destino');
    }
};
