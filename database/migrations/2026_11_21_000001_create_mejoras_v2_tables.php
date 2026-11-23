<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mejoras v2 (revisión con el profesor, 21-11-2026):
 *  - Cada urna física es una UNIDAD con código propio (reservar / comprar / cambiar / etiqueta).
 *  - Galería de fotos y medidas estructuradas por modelo.
 *  - Roles con permisos por módulo (el administrador crea, edita y elimina roles).
 *  - Abonos declarados por la familia desde el portal (pendientes de confirmación).
 *  - Ajustes del sitio (imagen del login, imagen de portada).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('urnas', function (Blueprint $table) {
            $table->unsignedSmallInteger('largo_cm')->nullable()->after('medidas');
            $table->unsignedSmallInteger('ancho_cm')->nullable()->after('largo_cm');
            $table->unsignedSmallInteger('alto_cm')->nullable()->after('ancho_cm');
        });

        Schema::create('urna_imagenes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('urna_id')->constrained('urnas')->cascadeOnDelete();
            $table->string('path');
            $table->string('titulo', 80)->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        Schema::create('urna_unidades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('urna_id')->constrained('urnas')->restrictOnDelete();
            $table->string('codigo', 30)->unique();
            $table->enum('estado', ['disponible', 'reservada', 'vendida', 'baja'])->default('disponible');
            $table->foreignId('solicitud_id')->nullable()->constrained('solicitudes')->restrictOnDelete();
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->restrictOnDelete();
            $table->foreignId('ingresada_por')->nullable()->constrained('usuarios')->restrictOnDelete();
            $table->unsignedBigInteger('costo')->default(0);
            $table->string('ubicacion', 80)->default('Bodega principal');
            $table->date('fecha_ingreso')->nullable();
            $table->timestamp('reservada_at')->nullable();
            $table->timestamp('vendida_at')->nullable();
            $table->string('observacion', 255)->nullable();
            $table->timestamps();
            $table->index(['urna_id', 'estado']);
        });

        Schema::table('solicitudes', function (Blueprint $table) {
            $table->foreignId('urna_unidad_id')->nullable()->after('urna_id')->constrained('urna_unidades')->restrictOnDelete();
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->json('permisos')->nullable()->after('descripcion');
            $table->boolean('sistema')->default(false)->after('permisos');
        });

        Schema::table('abonos', function (Blueprint $table) {
            $table->enum('estado', ['pendiente', 'registrado', 'rechazado', 'anulado'])->default('registrado')->change();
            $table->foreignId('confirmado_por')->nullable()->after('estado')->constrained('usuarios')->restrictOnDelete();
            $table->timestamp('confirmado_at')->nullable()->after('confirmado_por');
        });

        Schema::create('ajustes', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 60)->unique();
            $table->text('valor')->nullable();
            $table->timestamps();
        });

        $this->migrarDatosExistentes();
    }

    /** Convierte el stock por modelo en unidades individuales y asigna permisos a los roles existentes. */
    private function migrarDatosExistentes(): void
    {
        $permisos = config('funeraria.permisos_por_rol', []);
        foreach ($permisos as $rol => $lista) {
            DB::table('roles')->where('nombre', $rol)->update(['permisos' => json_encode($lista), 'sistema' => true]);
        }

        $ahora = now();
        foreach (DB::table('urnas')->whereNull('deleted_at')->where('bajo_pedido', false)->get() as $urna) {
            $reservadas = DB::table('solicitudes')->where('urna_id', $urna->id)->where('estado', 'aprobada')->pluck('id')->all();
            $n = 0;
            for ($i = 0; $i < $urna->stock_actual; $i++) {
                $n++;
                $solicitudId = $i < min(count($reservadas), $urna->stock_reservado) ? $reservadas[$i] : null;
                $id = DB::table('urna_unidades')->insertGetId([
                    'urna_id' => $urna->id,
                    'codigo' => sprintf('%s-%04d', $urna->codigo, $n),
                    'estado' => $solicitudId ? 'reservada' : 'disponible',
                    'solicitud_id' => $solicitudId,
                    'proveedor_id' => $urna->proveedor_id,
                    'fecha_ingreso' => $ahora->toDateString(),
                    'reservada_at' => $solicitudId ? $ahora : null,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
                if ($solicitudId) {
                    DB::table('solicitudes')->where('id', $solicitudId)->update(['urna_unidad_id' => $id]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('solicitudes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('urna_unidad_id');
        });
        Schema::table('abonos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('confirmado_por');
            $table->dropColumn('confirmado_at');
        });
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['permisos', 'sistema']);
        });
        Schema::table('urnas', function (Blueprint $table) {
            $table->dropColumn(['largo_cm', 'ancho_cm', 'alto_cm']);
        });
        Schema::dropIfExists('ajustes');
        Schema::dropIfExists('urna_unidades');
        Schema::dropIfExists('urna_imagenes');
    }
};
