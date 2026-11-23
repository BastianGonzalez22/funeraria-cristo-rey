<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Chofer extends Model
{
    use SoftDeletes;

    protected $table = 'choferes';

    protected $attributes = ['estado' => 'disponible', 'jornada' => 'completa'];

    protected $fillable = ['user_id', 'nombre', 'telefono', 'licencia', 'jornada', 'estado'];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(AsignacionFlota::class);
    }

    public function ausencias(): HasMany
    {
        return $this->hasMany(AusenciaChofer::class)->orderByDesc('desde');
    }

    public function ausenteEntre(Carbon $desde, Carbon $hasta): ?AusenciaChofer
    {
        return $this->ausencias()
            ->where('estado', '!=', 'finalizada')
            ->whereDate('desde', '<=', $hasta->toDateString())
            ->whereDate('hasta', '>=', $desde->toDateString())
            ->first();
    }

    public function ausenteEl(Carbon $dia): bool
    {
        return (bool) $this->ausenteEntre($dia->copy()->startOfDay(), $dia->copy()->endOfDay());
    }

    public function nombreCorto(): string
    {
        $p = preg_split('/\s+/', trim($this->nombre));

        return count($p) > 1 ? mb_substr($p[0], 0, 1).'. '.$p[1] : $this->nombre;
    }
}
