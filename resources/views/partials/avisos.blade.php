@if (session('ok'))
    <div class="aviso aviso-ok">{{ session('ok') }}</div>
@endif
@if (session('error'))
    <div class="aviso aviso-error">{{ session('error') }}</div>
@endif
@if ($errors->any())
    <div class="aviso aviso-error">
        <strong>Revise los datos ingresados:</strong>
        <ul>
            @foreach ($errors->all() as $e)
                <li>{{ $e }}</li>
            @endforeach
        </ul>
    </div>
@endif
