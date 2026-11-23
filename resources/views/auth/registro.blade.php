@extends('layouts.publico')
@section('titulo', 'Crear cuenta')
@section('contenido')
<section class="seccion-pub" style="max-width:720px">
    <h2>Crear cuenta de familia</h2>
    <p class="muted">Con su cuenta podrá ingresar la solicitud del servicio, adjuntar el certificado de defunción y revisar sus pagos y saldo en línea.</p>
    <div class="card mt-2">
        <div class="card-body">
            <form method="POST" action="{{ route('registro') }}">
                @csrf
                <div class="form-grid">
                    <div class="campo">
                        <label>Nombre completo del contratante *</label>
                        <input class="input" name="nombre" value="{{ old('nombre') }}" required maxlength="120">
                    </div>
                    <div class="campo">
                        <label>Apellido de la familia *</label>
                        <input class="input" name="apellido_familia" value="{{ old('apellido_familia') }}" required placeholder="Ej: Contreras">
                    </div>
                    <div class="campo">
                        <label>RUT *</label>
                        <input class="input" name="rut" value="{{ old('rut') }}" required data-rut placeholder="12.345.678-5">
                        <div class="ayuda">Dato sensible: se almacena cifrado.</div>
                    </div>
                    <div class="campo">
                        <label>Teléfono *</label>
                        <input class="input" name="telefono" value="{{ old('telefono') }}" required placeholder="+56 9 1234 5678">
                    </div>
                    <div class="campo full">
                        <label>Correo electrónico *</label>
                        <input class="input" type="email" name="email" value="{{ old('email') }}" required>
                    </div>
                    <div class="campo">
                        <label>Contraseña *</label>
                        <input class="input" type="password" name="password" required minlength="8">
                        <div class="ayuda">Mínimo 8 caracteres, con letras y números.</div>
                    </div>
                    <div class="campo">
                        <label>Repetir contraseña *</label>
                        <input class="input" type="password" name="password_confirmation" required>
                    </div>
                </div>
                <div class="form-acciones">
                    <a href="{{ route('login') }}" class="btn btn-outline">Ya tengo cuenta</a>
                    <button class="btn btn-primary">Crear cuenta</button>
                </div>
            </form>
        </div>
    </div>
</section>
@endsection
