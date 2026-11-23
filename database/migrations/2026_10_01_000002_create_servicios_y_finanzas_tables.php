<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->foreignId('urna_id')->nullable()->constrained('urnas')->restrictOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales')->restrictOnDelete();
            $table->foreignId('usuario_solicitante_id')->constrained('usuarios')->restrictOnDelete();
            $table->foreignId('convenio_id')->nullable()->constrained('convenios')->restrictOnDelete();

            $table->string('tipo_servicio', 40);
            $table->dateTime('fecha_hora');                       // inicio del cortejo / servicio
            $table->enum('destino_final', ['sepultura', 'cremacion'])->default('sepultura');

            // Datos del fallecido (sensibles)
            $table->string('nombre_fallecido', 150);
            $table->text('rut_fallecido')->nullable();          // cifrado
            $table->date('fecha_defuncion')->nullable();
            $table->string('certificado_path')->nullable();     // escaneo en disco privado

            // Logística
            $table->string('lugar_retiro', 200)->nullable();
            $table->string('lugar_velatorio', 200)->nullable();
            $table->string('misa_lugar', 200)->nullable();
            $table->dateTime('misa_hora')->nullable();
            $table->string('cementerio_crematorio', 150)->nullable();
            $table->dateTime('sepultura_hora')->nullable();
            $table->string('cementerio_contacto', 150)->nullable();
            $table->boolean('cementerio_confirmado')->default(false);
            $table->string('comuna_destino', 80)->nullable();
            $table->boolean('fuera_comuna')->default(false);
            $table->boolean('caso_especial')->default(false);   // muertes violentas / investigación
            $table->boolean('requiere_apoyo_externo')->default(false);

            // Permisos
            $table->enum('permiso_traslado', ['no_requiere', 'pendiente', 'en_tramite', 'aprobado'])->default('no_requiere');
            $table->enum('permiso_sanitario', ['no_requiere', 'pendiente', 'en_tramite', 'aprobado'])->default('pendiente');

            $table->enum('medio_contacto', ['telefono', 'whatsapp', 'email'])->default('telefono');
            $table->text('mensaje')->nullable();
            $table->json('documentos')->nullable();
            $table->json('elementos')->nullable();
            $table->json('personal')->nullable();
            $table->text('observacion')->nullable();

            $table->string('token_publico', 40)->unique();
            $table->boolean('publicar_servicio')->default(false);

            $table->enum('estado', ['en_revision', 'con_observaciones', 'aprobada', 'rechazada', 'finalizada'])->default('en_revision');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['estado', 'fecha_hora']);
        });

        Schema::create('contratos', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 20)->unique();
            $table->foreignId('solicitud_id')->unique()->constrained('solicitudes')->restrictOnDelete();
            $table->foreignId('convenio_id')->nullable()->constrained('convenios')->restrictOnDelete();
            $table->unsignedBigInteger('monto_servicio');
            $table->unsignedBigInteger('monto_urna');
            $table->unsignedBigInteger('descuento')->default(0);
            $table->unsignedBigInteger('monto_total');
            $table->unsignedBigInteger('abono_minimo');
            $table->unsignedBigInteger('saldo_pendiente');    // CHECK >= 0 por ser UNSIGNED
            $table->unsignedTinyInteger('cuotas')->default(1);
            $table->date('fecha_vencimiento');
            $table->boolean('cuota_mortuoria_en_tramite')->default(false);
            $table->text('clausulas_adicionales')->nullable();
            $table->enum('estado', ['vigente', 'liquidado'])->default('vigente');
            $table->timestamp('liquidado_at')->nullable();
            $table->timestamps();
        });

        Schema::create('abonos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrato_id')->constrained('contratos')->restrictOnDelete();
            $table->foreignId('usuario_id')->constrained('usuarios')->restrictOnDelete();
            $table->string('numero_comprobante', 20)->nullable()->unique();
            $table->unsignedBigInteger('monto');
            $table->enum('medio_pago', ['efectivo', 'transferencia', 'tarjeta', 'cuota_afp', 'cuota_ips', 'convenio']);
            $table->date('fecha');
            $table->string('referencia', 120)->nullable();
            $table->string('adjunto_path')->nullable();
            $table->enum('estado', ['registrado', 'anulado'])->default('registrado');
            $table->string('motivo_anulacion', 255)->nullable();
            $table->timestamps();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE abonos ADD CONSTRAINT chk_abono_monto CHECK (monto > 0)');
            DB::statement('ALTER TABLE urnas ADD CONSTRAINT chk_urna_reserva CHECK (stock_reservado <= stock_actual OR bajo_pedido = 1)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('abonos');
        Schema::dropIfExists('contratos');
        Schema::dropIfExists('solicitudes');
    }
};
