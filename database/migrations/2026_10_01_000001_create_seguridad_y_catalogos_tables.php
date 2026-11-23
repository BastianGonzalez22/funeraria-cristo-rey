<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 40)->unique();
            $table->string('descripcion', 255)->nullable();
            $table->enum('estado', ['activo', 'inactivo'])->default('activo');
            $table->timestamps();
        });

        Schema::create('usuarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rol_id')->constrained('roles')->restrictOnDelete();
            $table->string('nombre', 120);
            $table->string('email', 150)->unique();
            $table->string('telefono', 30)->nullable();
            $table->string('password');
            $table->enum('estado', ['activo', 'inactivo', 'bloqueado'])->default('activo');
            $table->unsignedTinyInteger('intentos_fallidos')->default(0);
            $table->timestamp('ultimo_acceso_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('sucursales', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->string('direccion', 200);
            $table->string('telefono', 30)->nullable();
            $table->enum('estado', ['activa', 'inactiva'])->default('activa');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('convenios', function (Blueprint $table) {
            $table->id();
            $table->string('institucion', 120);
            $table->enum('tipo', ['afp', 'ips', 'caja_prevision', 'empresa']);
            $table->string('beneficio', 255);
            $table->unsignedTinyInteger('descuento_pct')->default(0);
            $table->unsignedBigInteger('monto_cobertura')->default(0);
            $table->text('requisitos')->nullable();
            $table->text('documentos')->nullable();
            $table->string('contacto', 150)->nullable();
            $table->string('logo')->nullable();
            $table->string('color', 9)->default('#8a6d4b');
            $table->date('vigencia_desde')->nullable();
            $table->date('vigencia_hasta')->nullable();
            $table->enum('estado', ['vigente', 'inactivo'])->default('vigente');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('proveedores', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120);
            $table->string('ciudad', 80)->nullable();
            $table->string('modalidad', 150)->nullable();   // "Camión mensual - 24 urnas"
            $table->string('periodicidad', 150)->nullable(); // "Lunes de cada semana"
            $table->string('telefono', 30)->nullable();
            $table->enum('estado', ['activo', 'inactivo'])->default('activo');
            $table->timestamps();
        });

        Schema::create('urnas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('modelo', 120);
            $table->enum('categoria', ['estandar', 'infantil', 'sobredimensionada', 'personalizada', 'cremacion']);
            $table->string('material', 80)->nullable();
            $table->string('color', 60)->nullable();
            $table->string('acabado', 40)->nullable();
            $table->string('medidas', 80)->nullable();
            $table->string('manillas', 80)->nullable();
            $table->text('descripcion')->nullable();
            $table->unsignedBigInteger('precio');
            $table->unsignedInteger('stock_actual')->default(0);
            $table->unsignedInteger('stock_reservado')->default(0);
            $table->unsignedInteger('stock_minimo')->default(5);
            $table->boolean('bajo_pedido')->default(false);
            $table->boolean('publica')->default(true);
            $table->string('imagen')->nullable();
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->restrictOnDelete();
            $table->enum('estado', ['normal', 'en_umbral', 'reponer', 'bajo_pedido'])->default('normal');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('usuarios')->restrictOnDelete();
            $table->string('nombre_familia', 120);
            $table->string('nombre_contratante', 150);
            $table->text('rut_contratante');             // cifrado (Ley 21.719)
            $table->string('rut_hash', 64)->index();      // búsqueda por RUT
            $table->string('telefono', 30);
            $table->string('email', 150)->nullable();
            $table->string('direccion', 200)->nullable();
            $table->string('comuna', 80)->nullable();
            $table->enum('medio_contacto', ['telefono', 'whatsapp', 'email'])->default('telefono');
            $table->foreignId('convenio_id')->nullable()->constrained('convenios')->restrictOnDelete();
            $table->foreignId('urna_preferida_id')->nullable()->constrained('urnas')->restrictOnDelete();
            $table->json('documentos')->nullable();
            $table->text('notas')->nullable();
            $table->enum('estado', ['activo', 'inactivo'])->default('activo');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('movimientos_urna', function (Blueprint $table) {
            $table->id();
            $table->foreignId('urna_id')->constrained('urnas')->restrictOnDelete();
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->restrictOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->restrictOnDelete();
            $table->enum('tipo', ['compra', 'reserva', 'liberacion', 'salida', 'ajuste']);
            $table->integer('cantidad');
            $table->unsignedBigInteger('costo_total')->default(0);
            $table->string('referencia', 200)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_urna');
        Schema::dropIfExists('clientes');
        Schema::dropIfExists('urnas');
        Schema::dropIfExists('proveedores');
        Schema::dropIfExists('convenios');
        Schema::dropIfExists('sucursales');
        Schema::dropIfExists('usuarios');
        Schema::dropIfExists('roles');
    }
};
