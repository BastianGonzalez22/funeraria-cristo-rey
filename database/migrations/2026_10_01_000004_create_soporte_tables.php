<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Control de gastos (reportes del administrador)
        Schema::create('gastos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios')->restrictOnDelete();
            $table->string('concepto', 150);
            $table->string('categoria', 30);
            $table->unsignedBigInteger('monto');
            $table->date('fecha');
            $table->timestamps();
        });

        // Registro de auditoría de solo inserción (Historia 10).
        // En producción puede redirigirse a MongoDB (ver AuditService).
        Schema::create('auditorias', function (Blueprint $table) {
            $table->id();
            $table->string('evento', 60)->index();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->restrictOnDelete();
            $table->string('auditable_type', 120)->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->string('descripcion', 255);
            $table->json('detalle')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['auditable_type', 'auditable_id']);
        });

        // Libro de condolencias digital
        Schema::create('condolencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_id')->constrained('solicitudes')->restrictOnDelete();
            $table->string('nombre', 120);
            $table->text('mensaje');
            $table->boolean('visible')->default(true);
            $table->timestamps();
        });

        // Opiniones / comentarios de clientes (fidelización)
        Schema::create('opiniones', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120);
            $table->unsignedTinyInteger('calificacion');
            $table->text('comentario');
            $table->boolean('publicada')->default(false);
            $table->timestamps();
        });

        // Formulario de contacto del sitio
        Schema::create('mensajes_contacto', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120);
            $table->string('telefono', 30);
            $table->string('email', 150)->nullable();
            $table->enum('medio_contacto', ['telefono', 'whatsapp', 'email'])->default('telefono');
            $table->foreignId('urna_id')->nullable()->constrained('urnas')->restrictOnDelete();
            $table->text('mensaje');
            $table->boolean('atendido')->default(false);
            $table->timestamps();
        });

        // Verificación de beneficios AFP / IPS
        Schema::create('verificaciones_beneficio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios')->restrictOnDelete();
            $table->foreignId('solicitud_id')->nullable()->constrained('solicitudes')->restrictOnDelete();
            $table->text('rut_fallecido');                       // cifrado
            $table->enum('institucion', ['afp', 'ips', 'dipreca', 'capredena']);
            $table->enum('estado', ['pendiente', 'por_verificar', 'con_beneficio', 'sin_beneficio'])->default('por_verificar');
            $table->unsignedBigInteger('monto_beneficio')->default(0);
            $table->string('nota', 255)->nullable();
            $table->dateTime('consultar_desde')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verificaciones_beneficio');
        Schema::dropIfExists('mensajes_contacto');
        Schema::dropIfExists('opiniones');
        Schema::dropIfExists('condolencias');
        Schema::dropIfExists('auditorias');
        Schema::dropIfExists('gastos');
    }
};
