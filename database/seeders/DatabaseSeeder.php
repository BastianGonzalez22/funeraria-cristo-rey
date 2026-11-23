<?php

namespace Database\Seeders;

use App\Models\Chofer;
use App\Models\Convenio;
use App\Models\Proveedor;
use App\Models\Rol;
use App\Models\Sucursal;
use App\Models\Urna;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\StockService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Datos base obtenidos de las entrevistas con el dueño.
 * Ejecutar:  php artisan migrate --seed
 * Datos de demostración adicionales: se cargan con DemoSeeder (incluido abajo).
 */
class DatabaseSeeder extends Seeder
{
    public const CLAVE = 'CristoRey2026';

    public function run(): void
    {
        // Roles (RBAC)
        $roles = [
            'administrador' => 'Dueño: todas las funciones, incluidas finanzas',
            'encargado' => 'La mayoría de las funciones, excepto finanzas',
            'registrador' => 'Registra familias y solicitudes (apoyo en oficina)',
            'reponedor' => 'Bodega: reposición de urnas y etiquetas',
            'chofer' => 'Rutas e itinerarios; bodega para reposición',
            'solicitante' => 'Familias: portal de solicitudes y pagos',
        ];
        foreach ($roles as $nombre => $desc) {
            Rol::updateOrCreate(['nombre' => $nombre], [
                'descripcion' => $desc,
                'permisos' => config("funeraria.permisos_por_rol.{$nombre}", []),
                'sistema' => true,
            ]);
        }

        // Usuarios internos
        User::firstOrCreate(['email' => 'admin@cristorey.cl'], [
            'rol_id' => Rol::idDe('administrador'), 'nombre' => 'Renato Osses', 'telefono' => '+56 9 1234 5678', 'password' => self::CLAVE,
        ]);
        User::firstOrCreate(['email' => 'encargado@cristorey.cl'], [
            'rol_id' => Rol::idDe('encargado'), 'nombre' => 'Encargada de oficina', 'telefono' => '+56 9 2222 3333', 'password' => self::CLAVE,
        ]);
        $userChofer = User::firstOrCreate(['email' => 'chofer@cristorey.cl'], [
            'rol_id' => Rol::idDe('chofer'), 'nombre' => 'Pedro Lillo', 'telefono' => '+56 9 4444 5555', 'password' => self::CLAVE,
        ]);
        User::firstOrCreate(['email' => 'registro@cristorey.cl'], [
            'rol_id' => Rol::idDe('registrador'), 'nombre' => 'Registro de oficina', 'telefono' => '+56 9 6666 7777', 'password' => self::CLAVE,
        ]);
        User::firstOrCreate(['email' => 'bodega@cristorey.cl'], [
            'rol_id' => Rol::idDe('reponedor'), 'nombre' => 'Reponedor de bodega', 'telefono' => '+56 9 8888 9999', 'password' => self::CLAVE,
        ]);

        // Dos sucursales; la gestión se centraliza en la principal
        Sucursal::firstOrCreate(['nombre' => 'Sucursal principal (Temuco)'], ['direccion' => 'Temuco, La Araucanía', 'telefono' => config('funeraria.telefono')]);
        Sucursal::firstOrCreate(['nombre' => 'Sucursal Padre Las Casas'], ['direccion' => 'Padre Las Casas, La Araucanía']);

        // Flota real: una carroza y dos vehículos de instalación (+ apoyo externo)
        $vehiculos = [
            ['nombre' => 'Carroza 01 (velorio)', 'patente' => 'CR-0001', 'tipo' => 'carroza'],
            ['nombre' => 'Vehículo instalación 01', 'patente' => 'CR-0002', 'tipo' => 'instalacion'],
            ['nombre' => 'Vehículo instalación 02', 'patente' => 'CR-0003', 'tipo' => 'instalacion'],
            ['nombre' => 'Carroza de apoyo (Perquenco)', 'patente' => 'EXT-001', 'tipo' => 'externo', 'observacion' => 'Se solicita prestada cuando se acumulan servicios; viene con personal.'],
        ];
        foreach ($vehiculos as $v) {
            Vehiculo::firstOrCreate(['patente' => $v['patente']], $v);
        }

        // 4 choferes + 1 part-time
        Chofer::firstOrCreate(['nombre' => 'Pedro Lillo'], ['user_id' => $userChofer->id, 'telefono' => '+56 9 4444 5555', 'licencia' => 'A2', 'jornada' => 'completa']);
        foreach ([['Juan Pérez', 'completa'], ['Manuel Vega', 'completa'], ['Carlos Rojas', 'completa'], ['Luis Muñoz', 'part_time'], ['Chofer apoyo Perquenco', 'externo']] as [$n, $j]) {
            Chofer::firstOrCreate(['nombre' => $n], ['telefono' => '+56 9 '.rand(1000, 9999).' '.rand(1000, 9999), 'licencia' => 'A2', 'jornada' => $j]);
        }

        // Proveedores: camión mensual de Santiago (24 urnas) y reparto semanal de San Javier (5 a 8)
        $stgo = Proveedor::firstOrCreate(['nombre' => 'Santiago'], ['ciudad' => 'Santiago', 'modalidad' => 'Camión mensual - 24 urnas', 'periodicidad' => 'Fin de mes, según disponibilidad']);
        $sj = Proveedor::firstOrCreate(['nombre' => 'San Javier'], ['ciudad' => 'San Javier', 'modalidad' => 'Reparto semanal - 5 a 8 urnas', 'periodicidad' => 'Lunes de cada semana']);

        // Urnas (catálogo real descrito por el dueño)
        $urnas = [
            ['U-101', 'Lisa café', 'estandar', 'Madera', 'Café', 'lisa', 320000, 6, 5, $sj, '195 x 58 x 42 cm', 'Metálicas doradas, 6'],
            ['U-102', 'Tallada roble', 'estandar', 'Madera de roble', 'Roble', 'tallada', 450000, 6, 5, $stgo, '198 x 60 x 45 cm', 'Bronce, 6'],
            ['U-103', 'Metálica gris', 'estandar', 'Metal', 'Gris', 'metalica', 390000, 3, 5, $stgo, '200 x 62 x 46 cm', 'Barra lateral'],
            ['U-104', 'Infantil blanca', 'infantil', 'Madera', 'Blanco', 'lisa', 180000, 3, 2, $sj, '120 x 45 x 35 cm', 'Plateadas, 4'],
            ['U-105', 'Sobredimensionada', 'sobredimensionada', 'Madera reforzada', 'Nogal', 'lisa', 480000, 2, 2, $stgo, '215 x 75 x 55 cm', 'Reforzadas, 8'],
            ['U-106', 'Personalizada Carabineros', 'personalizada', 'Madera, institucional', 'Verde', 'lacada', 650000, 1, 0, $stgo, '198 x 60 x 45 cm', 'Doradas, 6', true],
            ['U-107', 'Personalizada Deportes Temuco', 'personalizada', 'Madera, institucional', 'Blanco y granate', 'lacada', 650000, 1, 0, $stgo, '198 x 60 x 45 cm', 'Doradas, 6', true],
            ['U-108', 'Cremación estándar', 'cremacion', 'Madera liviana', 'Natural', 'lisa', 160000, 3, 2, $sj, '190 x 55 x 40 cm', 'Sin manillas metálicas'],
            ['U-109', 'Lisa rosada', 'estandar', 'Madera', 'Rosado', 'lacada', 340000, 1, 1, $stgo, '195 x 58 x 42 cm', 'Plateadas, 6'],
            ['U-110', 'Lisa blanca', 'estandar', 'Madera', 'Blanco', 'lisa', 330000, 2, 1, $sj, '195 x 58 x 42 cm', 'Plateadas, 6'],
        ];
        $admin = User::where('email', 'admin@cristorey.cl')->first();
        Auth::setUser($admin);
        $stock = app(StockService::class);
        foreach ($urnas as $u) {
            if (Urna::where('codigo', $u[0])->exists()) {
                continue;
            }
            [$largo, $ancho, $alto] = array_map('intval', explode(' x ', str_replace(' cm', '', $u[10])));
            $urna = Urna::create([
                'codigo' => $u[0], 'modelo' => $u[1], 'categoria' => $u[2], 'material' => $u[3], 'color' => $u[4], 'acabado' => $u[5],
                'precio' => $u[6], 'stock_minimo' => $u[8], 'proveedor_id' => $u[9]->id,
                'medidas' => $u[10], 'largo_cm' => $largo, 'ancho_cm' => $ancho, 'alto_cm' => $alto,
                'manillas' => $u[11], 'bajo_pedido' => $u[12] ?? false, 'publica' => true,
                'descripcion' => 'Modelo '.$u[1].'. Incluye acolchado interior y crucifijo.',
            ]);
            // Cada urna física entra a bodega con su propio código (U-101-0001, U-101-0002…)
            if ($u[7] > 0) {
                $stock->registrarCompra($urna->id, $u[9]->id, $u[7], (int) round($u[6] * 0.55) * $u[7], $admin);
            }
            $this->fotosDemo($urna);
        }

        // ~10 convenios (AFP, IPS, cajas de previsión y empresas)
        $uf15 = 15 * 38000;
        $convenios = [
            ['AFP Habitat', 'afp', '#d71920'], ['AFP Provida', 'afp', '#003d79'], ['AFP Capital', 'afp', '#00548f'],
            ['AFP Cuprum', 'afp', '#005d9f'], ['AFP Modelo', 'afp', '#6cb33f'], ['AFP PlanVital', 'afp', '#e30613'], ['AFP Uno', 'afp', '#00a3e0'],
        ];
        foreach ($convenios as [$n, $t, $col]) {
            Convenio::firstOrCreate(['institucion' => $n], [
                'tipo' => $t, 'beneficio' => 'Cuota mortuoria de '.config('funeraria.cuota_mortuoria_uf').' UF', 'descuento_pct' => 0,
                'monto_cobertura' => $uf15, 'color' => $col, 'vigencia_desde' => now()->startOfYear(),
                'requisitos' => 'Afiliado activo o pensionado de la AFP.',
                'documentos' => 'Certificado de defunción, cédula del fallecido y del contratante, factura del servicio.',
            ]);
        }
        Convenio::firstOrCreate(['institucion' => 'IPS (Instituto de Previsión Social)'], [
            'tipo' => 'ips', 'beneficio' => 'Cuota mortuoria de 15 UF para pensionados IPS', 'descuento_pct' => 0, 'monto_cobertura' => $uf15,
            'color' => '#00529b', 'vigencia_desde' => now()->startOfYear(), 'requisitos' => 'Pensionado del IPS (ex INP).',
            'documentos' => 'Certificado de defunción, cédula, factura original del servicio.',
        ]);
        Convenio::firstOrCreate(['institucion' => 'DIPRECA'], [
            'tipo' => 'caja_prevision', 'beneficio' => 'Cuota mortuoria y 10% de descuento para imponentes de Carabineros', 'descuento_pct' => 10,
            'monto_cobertura' => $uf15, 'color' => '#1f5c2e', 'vigencia_desde' => now()->startOfYear(),
            'requisitos' => 'Imponente o pensionado DIPRECA.', 'documentos' => 'Certificado de defunción, TIP o cédula, factura.',
        ]);
        Convenio::firstOrCreate(['institucion' => 'CAPREDENA'], [
            'tipo' => 'caja_prevision', 'beneficio' => 'Cuota mortuoria y 10% de descuento para imponentes de las FF.AA.', 'descuento_pct' => 10,
            'monto_cobertura' => $uf15, 'color' => '#1b365d', 'vigencia_desde' => now()->startOfYear(),
            'requisitos' => 'Imponente o pensionado CAPREDENA.', 'documentos' => 'Certificado de defunción, cédula, factura.',
        ]);

        $this->call(DemoSeeder::class);
    }

    /** Copia las fotos de ejemplo (database/seeders/fotos/U-101-1.png…) a la galería del modelo. */
    private function fotosDemo(Urna $urna): void
    {
        foreach (File::glob(database_path("seeders/fotos/{$urna->codigo}-*.png")) as $i => $archivo) {
            $destino = 'urnas/'.basename($archivo);
            Storage::disk('public')->put($destino, File::get($archivo));
            $urna->imagenes()->create(['path' => $destino, 'orden' => $i + 1, 'titulo' => ['Vista frontal', 'Vista lateral', 'Interior'][$i] ?? null]);
        }
    }
}
