<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Usuario;

class PerfilFotoService
{
    private const NOMBRES_MASCULINOS = [
        'aaron', 'abel', 'abelardo', 'abraham', 'adelaido', 'adolfo', 'adrian', 'agustin', 'alan', 'aldo', 'alejandro', 'alessandro', 'alexis',
        'alberto', 'alfredo', 'alonso', 'alvaro', 'andres', 'angel', 'anibal', 'antonio', 'armando', 'arturo', 'aurelio',
        'azael', 'axel', 'balam', 'baltazar', 'benito', 'benjamin', 'brandon', 'brayan', 'brayant', 'braulio', 'brian', 'carlos', 'cesar', 'christian', 'claudio',
        'cristian', 'daniel', 'david', 'demetrio', 'diego', 'dilan', 'edgar', 'edilberto', 'edmundo', 'edson', 'eduardo',
        'edwin', 'eder', 'elias', 'emiliano', 'emmanuel', 'enrique', 'ernesto', 'esdras', 'esteban', 'ezequiel', 'fabian', 'fausto', 'felipe',
        'federico', 'fernando', 'fidel', 'francisco', 'gabriel', 'genaro', 'gerardo', 'gilberto', 'gonzalo', 'gustavo',
        'guillermo', 'hector', 'horacio', 'huberto', 'hugo', 'humberto', 'ignacio', 'ismael', 'israel', 'ivan', 'jaciel', 'jacobo', 'jadiel',
        'jaime', 'javier', 'jeronimo', 'jesus', 'joacim', 'joel', 'johan', 'jonathan', 'jorge', 'jose', 'josue', 'juan', 'julio', 'kevin',
        'leobardo', 'leonardo', 'lino', 'luis', 'manuel', 'marco', 'mario', 'martin', 'mateo', 'mauricio', 'maximiliano', 'miguel', 'moises', 'narciso', 'nestor', 'neftali', 'noe',
        'octaviano', 'octavio', 'omar', 'orlando', 'oscar', 'osvaldo', 'pablo', 'patricio', 'pedro', 'porfirio', 'rafael', 'ramiro', 'raul', 'rene',
        'ricardo', 'rigo', 'roberto', 'rodrigo', 'rodolfo', 'rogelio', 'roman', 'romario', 'ruperto', 'ruben', 'salvador', 'samuel', 'saul', 'santiago',
        'sergio', 'silvino', 'silverio', 'tadeo', 'teodulfo', 'tomas', 'ubaldo', 'ulises', 'urbano', 'uriel', 'victor',
        'vicente', 'william', 'yossue', 'jose maria', 'juan jose', 'juan manuel', 'miguel angel'
    ];

    private const NOMBRES_FEMENINOS = [
        'adela', 'adriana', 'aide', 'alicia', 'alina', 'alondra', 'alinne', 'ana', 'andrea', 'angela', 'angelica', 'araceli', 'ariadna', 'areli', 'aranza', 'aurora',
        'azenet', 'beatriz', 'belem', 'berenice', 'blanca', 'brenda', 'camila', 'carla', 'carmen', 'carolina', 'claudia', 'citlali', 'concepcion', 'consuelo',
        'cristina', 'daniela', 'denisse', 'diana', 'dulce', 'edith', 'elena', 'elisa', 'elizabeth', 'emilia', 'emma',
        'esmeralda', 'estela', 'esther', 'evelin', 'evelyn', 'fanny', 'fernanda', 'gabriela', 'ines', 'inocencia', 'irene',
        'isabel', 'ileana', 'itzel', 'ivonne', 'jacqueline', 'janeth', 'jenny', 'jessica', 'jesselyn', 'jazmin', 'jimena', 'josefina', 'juana',
        'karen', 'karina', 'karla', 'katya', 'kenia', 'laura', 'leonor', 'leyda', 'leticia', 'lidia', 'lilia', 'liliana', 'lizeth', 'lucia',
        'luisa', 'luz', 'manuela', 'marcela', 'margarita', 'maria', 'marlene', 'mariana', 'maribel', 'marisol', 'martha', 'mayra',
        'miriam', 'monica', 'monserrat', 'nancy', 'nayeli', 'naomi', 'natalia', 'nelly', 'noemi', 'norma', 'ofelia', 'patricia', 'paola', 'perla',
        'raquel', 'rebeca', 'regina', 'rocio', 'rosa', 'rosaura', 'rosalba', 'rosalva', 'samantha', 'sandra', 'sara', 'silvia', 'sofia', 'soledad', 'suleyma',
        'susana', 'teresa', 'thelma', 'valeria', 'vanelly', 'vanelli', 'veronica', 'victoria', 'violeta', 'ximena', 'yesenia', 'yesica',
        'yolanda', 'yudith', 'zaira', 'zuly', 'zulina', 'maria jose', 'ana maria', 'ana sofia', 'ma jose'
    ];

    private const TRATAMIENTOS = [
        'arq', 'c', 'cp', 'dr', 'dra', 'ing', 'lic', 'mtro', 'mtra', 'prof', 'sr', 'sra', 'srta'
    ];

    public static function url(?Usuario $usuario): string
    {
        $foto = basename(trim((string) ($usuario?->foto ?? '')));
        if ($foto !== '' && is_file(PUBLIC_PATH . '/uploads/foto-perfil/' . $foto)) {
            return rtrim((string) ($_ENV['APP_URL'] ?? ''), '/') . '/uploads/foto-perfil/' . rawurlencode($foto);
        }

        $imagen = match (self::generoPorNombre((string) ($usuario?->nombre ?? ''))) {
            'masculino' => 'user-m.jpg',
            'femenino' => 'user-f.jpg',
            default => 'user-general.jpg',
        };

        return asset('images/profile/' . $imagen);
    }

    /** Indica si el perfil está sirviendo una foto cargada y existente del usuario. */
    public static function tieneFotoPersonalizada(?Usuario $usuario): bool
    {
        $foto = basename(trim((string) ($usuario?->foto ?? '')));

        return $foto !== '' && is_file(PUBLIC_PATH . '/uploads/foto-perfil/' . $foto);
    }

    private static function generoPorNombre(string $nombreCompleto): ?string
    {
        $nombre = function_exists('mb_strtolower')
            ? mb_strtolower(trim($nombreCompleto), 'UTF-8')
            : strtolower(trim($nombreCompleto));
        $nombre = strtr($nombre, [
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a',
            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
            'ñ' => 'n'
        ]);
        // La puntuación suele venir de abreviaturas como "Ing." o iniciales. Separarla
        // evita que se pegue al nombre real (por ejemplo, "J. María").
        $nombre = preg_replace('/[^a-z\s]/', ' ', $nombre) ?? '';
        $nombre = preg_replace('/\s+/', ' ', trim($nombre)) ?? '';
        if ($nombre === '') {
            return null;
        }

        $partes = array_values(array_filter(
            explode(' ', $nombre),
            static fn (string $parte): bool => $parte !== '' && !in_array($parte, self::TRATAMIENTOS, true)
        ));
        if ($partes === []) {
            return null;
        }

        $primerNombre = $partes[0];
        $compuesto = count($partes) > 1 ? $primerNombre . ' ' . $partes[1] : '';

        if (in_array($compuesto, self::NOMBRES_MASCULINOS, true) || in_array($primerNombre, self::NOMBRES_MASCULINOS, true)) {
            return 'masculino';
        }
        if (in_array($compuesto, self::NOMBRES_FEMENINOS, true) || in_array($primerNombre, self::NOMBRES_FEMENINOS, true)) {
            return 'femenino';
        }

        // Algunos registros incluyen una inicial o un primer nombre poco común seguido de un nombre reconocible.
        // Sólo se consulta el segundo término si el primero no permitió una clasificación.
        $segundoNombre = $partes[1] ?? '';
        if ($segundoNombre !== '') {
            if (in_array($segundoNombre, self::NOMBRES_MASCULINOS, true)) {
                return 'masculino';
            }
            if (in_array($segundoNombre, self::NOMBRES_FEMENINOS, true)) {
                return 'femenino';
            }
        }

        return null;
    }
}
