<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class ExamMirrorController extends Controller
{
    private const PEPPER = 'PROFATILIO_EXAM_MIRROR_LGPD_2026_V1';

    public const CAMPUSES = [
        'PTA' => 'Paulista',
        'SM'  => 'São Miguel',
        'GRU' => 'Guarulhos',
        'AF'  => 'Anália Franco',
        'VL'  => 'Villa Lobos',
    ];

    public const TURNOS = [
        'Matutino' => 'Matutino',
        'Noturno'  => 'Noturno',
    ];

    public const CURRENT_DISCIPLINES = [
        'Empreendedorismo e Inovação',
        'Fisiologia Animal II',
        'Medicina dos Animais Silvestres',
    ];

    /**
     * Formata e padroniza nomes de turma para garantir que nenhum número técnico de oferta
     * seja exibido (LGPD) e que a sigla do campus e turno estejam sempre claros.
     */
    public static function formatTurmaName(string $name): string
    {
        $name = trim($name);
        // Remove qualquer código numérico de oferta porventura existente (ex: 2280372)
        $name = preg_replace('/\s*\(?\b\d{6,8}\b\)?/i', '', $name);
        $name = trim($name, " \t\n\r\0\x0B-·");

        $map = [
            'Paulista Manhã'    => 'PTA — Paulista · Matutino',
            'Paulista Noite'    => 'PTA — Paulista · Noturno',
            'Guarulhos Manhã'   => 'GRU — Guarulhos · Matutino',
            'Guarulhos Noite'   => 'GRU — Guarulhos · Noturno',
            'São Miguel Manhã'  => 'SM — São Miguel · Matutino',
            'São Miguel Noite'  => 'SM — São Miguel · Noturno',
            'Matutino'          => 'PTA — Paulista · Matutino',
            'Noturno'           => 'PTA — Paulista · Noturno',
        ];

        return $map[$name] ?? $name;
    }

    /**
     * Gera candidatos de RGM com tolerância inteligente a dígitos extras de campus
     * (ex.: 9 dígitos com prefixo ou sufixo numérico de polo/campus ou dígito verificador).
     */
    public static function generateRgmCandidates(string $rawRgm): array
    {
        $clean = preg_replace('/\D+/', '', $rawRgm);
        if ($clean === '') {
            return [];
        }

        $candidates = [$clean];
        $len = strlen($clean);

        // Se o aluno digitou 9 dígitos (código de campus no início ou dígito extra no final)
        if ($len === 9) {
            $candidates[] = substr($clean, 0, 8); // remove dígito final
            $candidates[] = substr($clean, 1, 8); // remove prefixo de campus inicial
            $candidates[] = ltrim($clean, '0');  // remove zeros à esquerda
        }
        // Se digitou 10 dígitos (prefixo duplo de campus/ano ou sufixo)
        elseif ($len === 10) {
            $candidates[] = substr($clean, 0, 8);
            $candidates[] = substr($clean, 2, 8);
            $candidates[] = substr($clean, 1, 8);
            $candidates[] = substr($clean, -8);
        }
        // Se digitou com zeros à esquerda
        elseif ($len > 8 && str_starts_with($clean, '0')) {
            $candidates[] = substr($clean, -8);
        }

        return array_values(array_unique(array_filter($candidates, fn($c) => strlen($c) >= 5)));
    }

    /**
     * Infere ou padroniza a representação textual de campus para uma disciplina/pacote.
     */
    public static function inferCampus(string $disciplina, array $turmas = []): string
    {
        $discUpper = mb_strtoupper($disciplina, 'UTF-8');
        if (str_contains($discUpper, 'EMPREENDEDORISMO')) {
            return 'PTA — Paulista';
        }
        if (str_contains($discUpper, 'SILVESTRES')) {
            return 'PTA · GRU · SM';
        }
        if (str_contains($discUpper, 'FISIOLOGIA')) {
            return 'PTA — Paulista';
        }

        // Tenta inferir pelos campi registrados nas turmas
        $campi = [];
        foreach ($turmas as $t) {
            $c = $t['campus'] ?? null;
            if ($c && !in_array($c, $campi, true)) {
                $campi[] = $c;
            }
        }
        if (!empty($campi)) {
            return implode(' · ', $campi);
        }

        return 'PTA — Paulista';
    }

    /**
     * Gera o rótulo de exibição padronizado para o seletor público e painel.
     */
    public static function getPackageDisplayLabel(array $pkg): string
    {
        $tipo = ($pkg['tipo'] ?? '') === 'trabalho' ? '📁 [TRABALHO]' : '📝 [AVALIAÇÃO]';
        $disc = $pkg['disciplina'] ?? '';
        $campus = $pkg['campus'] ?? self::inferCampus($disc, $pkg['turmas'] ?? []);
        $aval = $pkg['avaliacao'] ?? '';

        $campusStr = $campus ? " ({$campus})" : '';
        return "{$tipo} {$disc}{$campusStr} — {$aval}";
    }

    /**
     * Retorna a configuração global de semestres (semestre ativo e status aberto/encerrado)
     */
    public static function getSystemConfig(): array
    {
        $file = storage_path('app/exam_mirrors/status_config.json');
        if (file_exists($file)) {
            $data = json_decode(@file_get_contents($file), true);
            if (is_array($data)) {
                return $data;
            }
        }
        return [
            'active_semester' => '2026.2',
            'semesters_status' => [
                '2026.2' => 'aberto',
            ],
        ];
    }

    /**
     * Salva a configuração de semestres em storage/app/exam_mirrors/status_config.json
     */
    public static function saveSystemConfig(array $cfg): void
    {
        $dir = storage_path('app/exam_mirrors');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        file_put_contents($dir . '/status_config.json', json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    /**
     * Verifica se um determinado semestre foi encerrado para visualização
     */
    public static function isSemesterClosed(string $semestre): bool
    {
        $cfg = self::getSystemConfig();
        $semestre = trim($semestre);
        $status = $cfg['semesters_status'][$semestre] ?? null;

        if ($status === 'encerrado') {
            return true;
        }

        if ($status === 'aberto') {
            return false;
        }

        // Semestres anteriores ao ativo são considerados encerrados por padrão
        $activeSem = (string) ($cfg['active_semester'] ?? '2026.2');
        if ($semestre !== '' && strcmp($semestre, $activeSem) < 0) {
            return true;
        }

        return false;
    }

    /**
     * Normaliza nomes para comparação e geração de token seguro (sem acentos e minúsculo).
     */
    public static function normalizeName(string $name): string
    {
        $str = mb_strtolower(trim($name), 'UTF-8');
        if (function_exists('transliterator_transliterate')) {
            $trans = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $str);
            if ($trans !== false) {
                $str = $trans;
            }
        } else {
            $str = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str) ?: $str;
        }
        $str = preg_replace('/[^a-z0-9\s]/', ' ', (string) $str);
        return preg_replace('/\s+/', ' ', trim((string) $str));
    }

    /**
     * Carrega todos os pacotes de devolutivas (avaliações e trabalhos) em storage/app/exam_mirrors/*.json
     */
    public static function loadPackages(bool $includeVaults = false): array
    {
        $dir = storage_path('app/exam_mirrors');
        if (!is_dir($dir)) {
            return [];
        }

        $files = glob($dir . '/*.json') ?: [];
        $packages = [];

        foreach ($files as $file) {
            $baseName = basename($file);
            if ($baseName === 'status_config.json' || $baseName === 'gabaritos_oficiais.json') {
                continue;
            }

            $raw = @file_get_contents($file);
            if (!$raw) {
                continue;
            }
            $data = json_decode($raw, true);
            if (!is_array($data) || empty($data['package_id'])) {
                continue;
            }

            $pkgId = (string) $data['package_id'];
            $vaultCount = isset($data['vaults']) && is_array($data['vaults']) ? count($data['vaults']) : 0;

            if (!$includeVaults) {
                unset($data['vaults']);
            }

            // Normalização de metadados
            $semestre = (string) ($data['semestre'] ?? '2026.2');
            $ano = (string) ($data['ano'] ?? (explode('.', $semestre)[0] ?? '2026'));
            $tipo = (string) ($data['tipo'] ?? 'avaliacao');
            $isClosed = self::isSemesterClosed($semestre);

            // Campus e turmas normalizados (sem números de oferta vazando - LGPD)
            $campus = (string) ($data['campus'] ?? self::inferCampus($data['disciplina'] ?? '', $data['turmas'] ?? []));
            if (isset($data['turmas']) && is_array($data['turmas'])) {
                $cleanTurmas = [];
                $numericKeyMap = [
                    '2280372' => 'PTA_MAT',
                    '2280737' => 'PTA_NOT',
                    '2276204' => 'GRU_MAT',
                    '2278972' => 'GRU_NOT',
                    '2278834' => 'SM_MAT',
                    '2278835' => 'SM_NOT',
                    '2280336' => 'FISIO_PTA_MAT',
                    '2280724' => 'FISIO_PTA_NOT',
                ];
                foreach ($data['turmas'] as $tKey => $tVal) {
                    $cleanName = self::formatTurmaName((string) ($tVal['turma'] ?? $tKey));
                    $cleanKey = (string) $tKey;
                    if (preg_match('/^\d{5,}$/', $cleanKey)) {
                        $cleanKey = $numericKeyMap[$cleanKey] ?? ('TURMA_' . $cleanKey);
                    }
                    $tVal['oferta'] = $cleanKey;
                    $tVal['turma'] = $cleanName;
                    $cleanTurmas[$cleanKey] = $tVal;
                }
                $data['turmas'] = $cleanTurmas;
            }

            $data['ano'] = $ano;
            $data['semestre'] = $semestre;
            $data['campus'] = $campus;
            $data['tipo'] = $tipo;
            $data['tipo_label'] = ($tipo === 'trabalho') ? 'Trabalho Acadêmico' : 'Avaliação / Prova';
            $data['display_label'] = self::getPackageDisplayLabel($data);
            $data['is_encerrado'] = $isClosed;
            $data['vault_count'] = $vaultCount;
            $data['filename'] = basename($file);
            $packages[$pkgId] = $data;
        }

        // Filtro defensivo: se houver pacote individual de turma fragmentado (ex: *_2280336)
        // coexistindo com o consolidado correspondente, manter apenas o consolidado principal
        $parentKeys = [];
        foreach ($packages as $k => $v) {
            if (preg_match('/^(.+)[_]{1,2}\d{5,}$/', $k, $m)) {
                $parentKeys[$m[1]][] = $k;
            }
        }
        foreach ($parentKeys as $parent => $children) {
            if (isset($packages[$parent])) {
                foreach ($children as $child) {
                    unset($packages[$child]);
                }
            }
        }

        uasort($packages, function (array $a, array $b) {
            $anoCmp = strcmp((string) ($b['ano'] ?? ''), (string) ($a['ano'] ?? ''));
            if ($anoCmp !== 0) {
                return $anoCmp;
            }
            $semCmp = strcmp((string) ($b['semestre'] ?? ''), (string) ($a['semestre'] ?? ''));
            if ($semCmp !== 0) {
                return $semCmp;
            }
            $ordemA = (int) ($a['ordem_exibicao'] ?? 99);
            $ordemB = (int) ($b['ordem_exibicao'] ?? 99);
            if ($ordemA !== $ordemB) {
                return $ordemA <=> $ordemB;
            }
            return strcmp((string) ($a['disciplina'] ?? ''), (string) ($b['disciplina'] ?? ''));
        });

        return $packages;
    }

    public static function loadOfficialAnswerKeys(): array
    {
        $path = storage_path('app/exam_mirrors/gabaritos_oficiais.json');
        if (!file_exists($path)) {
            return [];
        }
        $raw = @file_get_contents($path);
        return is_string($raw) ? (json_decode($raw, true) ?: []) : [];
    }

    public static function getAvailableYears(array $packages): array
    {
        $years = [];
        foreach ($packages as $pkg) {
            $y = (string) ($pkg['ano'] ?? '');
            if ($y !== '' && !in_array($y, $years, true)) {
                $years[] = $y;
            }
        }
        rsort($years);
        return $years ?: [date('Y')];
    }

    public static function getAvailableSemesters(array $packages): array
    {
        $sems = [];
        foreach ($packages as $pkg) {
            $s = (string) ($pkg['semestre'] ?? '');
            if ($s !== '' && !in_array($s, $sems, true)) {
                $sems[] = $s;
            }
        }
        rsort($sems);
        return $sems ?: [date('Y') . '.2'];
    }

    public function index(Request $request)
    {
        $catalog = self::loadPackages(false);
        $years = self::getAvailableYears($catalog);
        $semesters = self::getAvailableSemesters($catalog);
        $cfg = self::getSystemConfig();

        $activeSemester = $cfg['active_semester'] ?? ($semesters[0] ?? date('Y') . '.2');
        $selectedYear = $request->query('ano', explode('.', $activeSemester)[0] ?? date('Y'));
        $selectedSemester = $request->query('semestre', $activeSemester);

        // Se a avaliação solicitada existir, usa-a; senão escolhe a primeira do ano/semestre selecionado
        $requestedPkg = $request->query('avaliacao', $request->query('pacote', ''));
        $selectedPackage = '';
        if ($requestedPkg && isset($catalog[$requestedPkg])) {
            $selectedPackage = $requestedPkg;
            $selectedYear = $catalog[$requestedPkg]['ano'] ?? $selectedYear;
            $selectedSemester = $catalog[$requestedPkg]['semestre'] ?? $selectedSemester;
        } else {
            foreach ($catalog as $id => $item) {
                if (($item['semestre'] ?? '') === $selectedSemester) {
                    $selectedPackage = $id;
                    break;
                }
            }
            if (!$selectedPackage) {
                $selectedPackage = array_key_first($catalog) ?? '';
            }
        }

        return view('exam_mirror', [
            'catalog' => $catalog,
            'years' => $years,
            'semesters' => $semesters,
            'systemConfig' => $cfg,
            'selectedYear' => $selectedYear,
            'selectedSemester' => $selectedSemester,
            'result' => null,
            'selectedPackage' => $selectedPackage,
            'selectedOferta' => $request->query('turma', ''),
            'selectedVersao' => $request->query('versao', 'A'),
        ]);
    }

    public function consult(Request $request)
    {
        // Honeypot antibot
        if ($request->filled('secondary_address')) {
            return redirect()->route('exam.mirror');
        }

        $packageId = trim((string) $request->input('package_id', ''));
        $packagesWithVaults = self::loadPackages(true);
        $pkg = $packagesWithVaults[$packageId] ?? null;

        $ipKey = 'exam-mirror-ip:' . $request->ip();

        if (!$pkg) {
            RateLimiter::hit($ipKey, 600);
            return redirect()->route('exam.mirror')
                ->withInput($request->except(['secondary_address']))
                ->withErrors(['consulta' => 'Atividade ou avaliação não encontrada no catálogo.']);
        }

        // BLOQUEIO DE SEMESTRE ENCERRADO
        if (self::isSemesterClosed((string) ($pkg['semestre'] ?? ''))) {
            RateLimiter::hit($ipKey, 300);
            return redirect()->route('exam.mirror')
                ->withInput($request->except(['secondary_address']))
                ->withErrors([
                    'consulta' => sprintf(
                        'O período de consulta aos espelhos e devolutivas do semestre %s foi oficialmente encerrado pelo docente. Apenas o semestre letivo vigente permanece liberado.',
                        $pkg['semestre'] ?? ''
                    )
                ]);
        }

        $isTrabalho = (($pkg['tipo'] ?? 'avaliacao') === 'trabalho');

        if ($isTrabalho) {
            $validated = $request->validate([
                'package_id'    => 'required|string|max:120',
                'oferta'        => 'nullable|string|max:40',
                'rgm'           => 'required|string|max:30',
                'primeiro_nome' => 'required|string|min:2|max:80',
            ]);

            $cleanRgm = preg_replace('/\D+/', '', $validated['rgm']);
            $normName = self::normalizeName($validated['primeiro_nome']);
            $nameParts = explode(' ', $normName);
            $firstName = $nameParts[0] ?? $normName;

            $rgmKey = 'exam-mirror-rgm:' . $packageId . ':' . $cleanRgm;

            if (RateLimiter::tooManyAttempts($ipKey, 15) || RateLimiter::tooManyAttempts($rgmKey, 6)) {
                $seconds = max(RateLimiter::availableIn($ipKey), RateLimiter::availableIn($rgmKey));
                $minutes = max(1, (int) ceil($seconds / 60));

                return redirect()->route('exam.mirror')
                    ->withInput($request->except(['secondary_address']))
                    ->withErrors([
                        'consulta' => "Por segurança e proteção de privacidade (LGPD), o acesso para este RGM foi temporariamente pausado. Aguarde cerca de {$minutes} minuto(s) e tente novamente."
                    ]);
            }

            if ($cleanRgm === '' || $firstName === '') {
                RateLimiter::hit($ipKey, 600);
                return redirect()->route('exam.mirror')
                    ->withInput($request->except(['secondary_address']))
                    ->withErrors(['consulta' => 'Informe seu RGM e seu Primeiro Nome.']);
            }

            $rgmCandidates = self::generateRgmCandidates($cleanRgm);
            $candidates = array_unique([
                $firstName,
                $normName,
                (count($nameParts) >= 2 ? ($nameParts[0] . ' ' . $nameParts[1]) : $firstName)
            ]);

            $lookupHash = null;
            $cipherB64 = null;
            $matchedToken = null;

            foreach ($rgmCandidates as $candRgm) {
                foreach ($candidates as $cand) {
                    $tok = "{$packageId}|{$candRgm}|{$cand}";
                    $h = hash_hmac('sha256', "LOOKUP|{$tok}", self::PEPPER);
                    if (!empty($pkg['vaults'][$h])) {
                        $lookupHash = $h;
                        $cipherB64 = $pkg['vaults'][$h];
                        $matchedToken = $tok;
                        break 2;
                    }
                }
            }

            if (!$cipherB64) {
                RateLimiter::hit($ipKey, 600);
                RateLimiter::hit($rgmKey, 900);

                return redirect()->route('exam.mirror')
                    ->withInput($request->except(['secondary_address']))
                    ->withErrors([
                        'consulta' => 'Não encontramos a devolutiva com essa combinação de RGM e Primeiro Nome. Verifique se o RGM está correto e se o primeiro nome corresponde ao cadastro oficial da matrícula (ex.: Maria).'
                    ]);
            }

            $encKey = hash('sha256', 'ENCKEY|' . self::PEPPER . '|' . $matchedToken, true);
            $iv = md5('IV|' . self::PEPPER . '|' . $matchedToken, true);
            $decryptedJson = openssl_decrypt(base64_decode($cipherB64), 'aes-256-cbc', $encKey, OPENSSL_RAW_DATA, $iv);
            $result = $decryptedJson ? json_decode($decryptedJson, true) : null;

            if (!is_array($result)) {
                return redirect()->route('exam.mirror')
                    ->withInput($request->except(['secondary_address']))
                    ->withErrors(['consulta' => 'Erro ao descriptografar a devolutiva do trabalho.']);
            }

            // LGPD: Higienização rigorosa para não expor números técnicos de oferta
            unset($result['oferta']);
            if (!empty($result['turma'])) {
                $result['turma'] = self::formatTurmaName((string) $result['turma']);
            }
            $result['campus'] = $pkg['campus'] ?? self::inferCampus($result['disciplina'] ?? '', $pkg['turmas'] ?? []);

            RateLimiter::clear($ipKey);
            RateLimiter::clear($rgmKey);

            $catalog = self::loadPackages(false);
            $years = self::getAvailableYears($catalog);
            $semesters = self::getAvailableSemesters($catalog);
            $cfg = self::getSystemConfig();

            return view('exam_mirror', [
                'catalog' => $catalog,
                'years' => $years,
                'semesters' => $semesters,
                'systemConfig' => $cfg,
                'selectedYear' => $result['ano'] ?? ($pkg['ano'] ?? '2026'),
                'selectedSemester' => $result['semestre'] ?? ($pkg['semestre'] ?? '2026.2'),
                'result' => $result,
                'selectedPackage' => $packageId,
                'selectedOferta' => $validated['oferta'] ?? '',
                'selectedVersao' => 'A',
                'queriedAnswers' => [],
            ]);
        }

        // Caso Avaliação (Prova Óptica com Q1, Q2, Q3)
        $validated = $request->validate([
            'package_id' => 'required|string|max:120',
            'oferta'     => 'nullable|string|max:40',
            'versao'     => 'nullable|string|max:5',
            'rgm'        => 'required|string|max:30',
            'q1'         => 'required|string|in:A,B,C,D,E,X',
            'q2'         => 'required|string|in:A,B,C,D,E,X',
            'q3'         => 'required|string|in:A,B,C,D,E,X',
        ]);

        $cleanRgm = preg_replace('/\D+/', '', $validated['rgm']);
        $q1 = strtoupper(trim($validated['q1']));
        $q2 = strtoupper(trim($validated['q2']));
        $q3 = strtoupper(trim($validated['q3']));

        $rgmKey = 'exam-mirror-rgm:' . $packageId . ':' . $cleanRgm;

        if (RateLimiter::tooManyAttempts($ipKey, 12) || RateLimiter::tooManyAttempts($rgmKey, 5)) {
            $seconds = max(RateLimiter::availableIn($ipKey), RateLimiter::availableIn($rgmKey));
            $minutes = max(1, (int) ceil($seconds / 60));

            return redirect()->route('exam.mirror')
                ->withInput($request->except(['secondary_address']))
                ->withErrors([
                    'consulta' => "Por segurança e proteção de privacidade (LGPD), o acesso para este RGM foi temporariamente pausado após várias tentativas. Aguarde cerca de {$minutes} minuto(s) e tente novamente."
                ]);
        }

        if ($cleanRgm === '') {
            RateLimiter::hit($ipKey, 600);
            return redirect()->route('exam.mirror')
                ->withInput($request->except(['secondary_address']))
                ->withErrors(['consulta' => 'RGM inválido. Digite apenas os números da matrícula.']);
        }

        $rgmCandidates = self::generateRgmCandidates($cleanRgm);
        $lookupHash = null;
        $cipherB64 = null;
        $matchedToken = null;

        foreach ($rgmCandidates as $candRgm) {
            $tokenStr = "{$packageId}|{$candRgm}|{$q1}|{$q2}|{$q3}";
            $h = hash_hmac('sha256', "LOOKUP|{$tokenStr}", self::PEPPER);
            if (!empty($pkg['vaults'][$h])) {
                $lookupHash = $h;
                $cipherB64 = $pkg['vaults'][$h];
                $matchedToken = $tokenStr;
                break;
            }
        }

        if (!$cipherB64) {
            RateLimiter::hit($ipKey, 600);
            RateLimiter::hit($rgmKey, 900);

            return redirect()->route('exam.mirror')
                ->withInput($request->except(['secondary_address']))
                ->withErrors([
                    'consulta' => 'Não encontramos um espelho com essa combinação de Avaliação + RGM + Respostas de Q1, Q2 e Q3. Confira se selecionou a avaliação correta (ex.: P1 ou P2) e se as letras de Q1, Q2 e Q3 correspondem exatamente ao que você marcou na folha óptica.'
                ]);
        }

        $encKey = hash('sha256', 'ENCKEY|' . self::PEPPER . '|' . $matchedToken, true);
        $iv = md5('IV|' . self::PEPPER . '|' . $matchedToken, true);
        $decryptedJson = openssl_decrypt(base64_decode($cipherB64), 'aes-256-cbc', $encKey, OPENSSL_RAW_DATA, $iv);
        $result = $decryptedJson ? json_decode($decryptedJson, true) : null;

        if (!is_array($result)) {
            return redirect()->route('exam.mirror')
                ->withInput($request->except(['secondary_address']))
                ->withErrors(['consulta' => 'Erro ao descriptografar o espelho da avaliação.']);
        }

        // LGPD: Higienização rigorosa para não expor números técnicos de oferta
        unset($result['oferta']);
        if (!empty($result['turma'])) {
            $result['turma'] = self::formatTurmaName((string) $result['turma']);
        }
        $result['campus'] = $pkg['campus'] ?? self::inferCampus($result['disciplina'] ?? '', $pkg['turmas'] ?? []);

        RateLimiter::clear($ipKey);
        RateLimiter::clear($rgmKey);

        $catalog = self::loadPackages(false);
        $years = self::getAvailableYears($catalog);
        $semesters = self::getAvailableSemesters($catalog);
        $cfg = self::getSystemConfig();

        return view('exam_mirror', [
            'catalog' => $catalog,
            'years' => $years,
            'semesters' => $semesters,
            'systemConfig' => $cfg,
            'selectedYear' => $result['ano'] ?? ($pkg['ano'] ?? '2026'),
            'selectedSemester' => $result['semestre'] ?? ($pkg['semestre'] ?? '2026.2'),
            'result' => $result,
            'selectedPackage' => $packageId,
            'selectedOferta' => $validated['oferta'] ?? '',
            'selectedVersao' => $result['versao'] ?? ($validated['versao'] ?? 'A'),
            'queriedAnswers' => ['q1' => $q1, 'q2' => $q2, 'q3' => $q3],
        ]);
    }

    /**
     * Salva ou atualiza os metadados de um pacote JSON existente, preservando seus cofres (vaults).
     */
    public static function savePackageData(string $filename, array $updatedMetadata): bool
    {
        $clean = basename($filename);
        if ($clean === 'status_config.json' || !str_ends_with($clean, '.json')) {
            return false;
        }

        $path = storage_path('app/exam_mirrors/' . $clean);
        $existing = [];
        if (file_exists($path)) {
            $existing = json_decode(@file_get_contents($path), true) ?: [];
        }

        // Preserva cofres criptografados se não foram passados novos
        if (!isset($updatedMetadata['vaults']) && isset($existing['vaults'])) {
            $updatedMetadata['vaults'] = $existing['vaults'];
        }

        if (empty($updatedMetadata['vaults'])) {
            $updatedMetadata['vaults'] = $existing['vaults'] ?? [];
        }

        $updatedMetadata['updated_at'] = date('d/m/Y H:i');

        $dir = storage_path('app/exam_mirrors');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return (bool) file_put_contents($path, json_encode($updatedMetadata, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    /**
     * Criptografa um cofre individual para Trabalho Acadêmico usando Package_ID + RGM + Variante de Nome.
     */
    public static function encryptTrabalhoVault(string $packageId, string $rgm, string $nameVariant, array $payload): array
    {
        $cleanRgm = preg_replace('/\D+/', '', $rgm);
        $normName = self::normalizeName($nameVariant);
        $nameParts = explode(' ', $normName);
        $cand = $nameParts[0] ?? $normName;

        $tokenStr = "{$packageId}|{$cleanRgm}|{$cand}";
        $lookupHash = hash_hmac('sha256', "LOOKUP|{$tokenStr}", self::PEPPER);

        $encKey = hash('sha256', 'ENCKEY|' . self::PEPPER . '|' . $tokenStr, true);
        $iv = md5('IV|' . self::PEPPER . '|' . $tokenStr, true);

        $rawJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $cipherBytes = openssl_encrypt($rawJson, 'aes-256-cbc', $encKey, OPENSSL_RAW_DATA, $iv);
        $cipherB64 = base64_encode($cipherBytes);

        return [$lookupHash, $cipherB64];
    }

    /**
     * Gera os cofres criptografados para todas as variações prováveis do nome do aluno (primeiro nome, dois nomes, nome completo).
     */
    public static function encryptTrabalhoVaultVariants(string $packageId, string $rgm, string $fullName, array $payload): array
    {
        $cleanRgm = preg_replace('/\D+/', '', $rgm);
        $normName = self::normalizeName($fullName);
        $parts = explode(' ', $normName);
        $firstName = $parts[0] ?? $normName;
        $twoNames = count($parts) >= 2 ? ($parts[0] . ' ' . $parts[1]) : $firstName;

        $variants = array_unique(array_filter([$firstName, $twoNames, $normName]));
        $vaults = [];
        foreach ($variants as $v) {
            [$h, $c] = self::encryptTrabalhoVault($packageId, $cleanRgm, $v, $payload);
            $vaults[$h] = $c;
        }
        return $vaults;
    }

    /**
     * Criptografa um cofre individual para Avaliação (Prova) usando Package_ID + RGM + Q1 + Q2 + Q3.
     */
    public static function encryptProvaVault(string $packageId, string $rgm, string $q1, string $q2, string $q3, array $payload): array
    {
        $cleanRgm = preg_replace('/\D+/', '', $rgm);
        $q1 = strtoupper(trim($q1));
        $q2 = strtoupper(trim($q2));
        $q3 = strtoupper(trim($q3));

        $tokenStr = "{$packageId}|{$cleanRgm}|{$q1}|{$q2}|{$q3}";
        $lookupHash = hash_hmac('sha256', "LOOKUP|{$tokenStr}", self::PEPPER);

        $encKey = hash('sha256', 'ENCKEY|' . self::PEPPER . '|' . $tokenStr, true);
        $iv = md5('IV|' . self::PEPPER . '|' . $tokenStr, true);

        $rawJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $cipherBytes = openssl_encrypt($rawJson, 'aes-256-cbc', $encKey, OPENSSL_RAW_DATA, $iv);
        $cipherB64 = base64_encode($cipherBytes);

        return [$lookupHash, $cipherB64];
    }

    /**
     * Faz o parse flexível de linhas de CSV ou TSV com cabeçalhos normalizados.
     */
    public static function parseCsvRows(string $content): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        if (empty($lines)) {
            return [];
        }

        $firstLine = $lines[0];
        $semicolons = substr_count($firstLine, ';');
        $commas = substr_count($firstLine, ',');
        $tabs = substr_count($firstLine, "\t");

        $delim = ',';
        if ($semicolons > $commas && $semicolons > $tabs) {
            $delim = ';';
        } elseif ($tabs > $commas && $tabs > $semicolons) {
            $delim = "\t";
        }

        $rows = [];
        $headers = null;
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $cols = str_getcsv($line, $delim);
            if ($headers === null) {
                $headers = array_map(function ($h) {
                    $norm = mb_strtolower(trim($h), 'UTF-8');
                    $norm = preg_replace('/[^a-z0-9_]/', '_', $norm);
                    return trim($norm, '_');
                }, $cols);
                continue;
            }
            $row = [];
            foreach ($cols as $idx => $val) {
                $h = $headers[$idx] ?? "col_{$idx}";
                $row[$h] = trim($val);
            }
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Importa e criptografa notas a partir de texto CSV/TSV ou planilha diretamente para um pacote existente.
     */
    public static function importSpreadsheetContent(string $filename, string $turmaKey, string $csvContent): array
    {
        $clean = basename($filename);
        $path = storage_path('app/exam_mirrors/' . $clean);
        if (!file_exists($path)) {
            return ['success' => false, 'message' => 'Pacote não encontrado.'];
        }

        $package = json_decode(@file_get_contents($path), true);
        if (!is_array($package) || empty($package['package_id'])) {
            return ['success' => false, 'message' => 'Arquivo JSON de pacote inválido.'];
        }

        $packageId = $package['package_id'];
        $tipo = $package['tipo'] ?? 'avaliacao';
        $rows = self::parseCsvRows($csvContent);

        if (empty($rows)) {
            return ['success' => false, 'message' => 'Nenhuma linha de dados encontrada no arquivo enviado.'];
        }

        $importedCount = 0;
        $vaults = $package['vaults'] ?? [];

        foreach ($rows as $r) {
            // Extrai RGM
            $rawRgm = $r['rgm'] ?? ($r['matricula'] ?? ($r['ra'] ?? ($r['col_0'] ?? '')));
            $cleanRgm = preg_replace('/\D+/', '', $rawRgm);
            if ($cleanRgm === '' || strlen($cleanRgm) < 4) {
                continue;
            }

            if ($tipo === 'trabalho') {
                $nome = $r['nome'] ?? ($r['aluno'] ?? ($r['estudante'] ?? ($r['primeiro_nome'] ?? '')));
                if (trim($nome) === '') {
                    continue;
                }

                $notaTrab = (float) str_replace(',', '.', $r['nota_trabalho'] ?? ($r['trabalho'] ?? ($r['nota'] ?? '4.0')));
                $notaPbb  = (float) str_replace(',', '.', $r['nota_pbb'] ?? ($r['pbb'] ?? ($r['bb'] ?? ($r['prova'] ?? '1.0'))));
                $notaFinal = isset($r['nota_final']) ? (float) str_replace(',', '.', $r['nota_final']) : ($notaTrab + $notaPbb);
                $projeto = $r['projeto'] ?? ($r['grupo'] ?? ($r['modelo'] ?? 'Projeto Prático'));

                $payload = [
                    'tipo'          => 'trabalho',
                    'ano'           => (string) ($package['ano'] ?? '2026'),
                    'semestre'      => (string) ($package['semestre'] ?? '2026.2'),
                    'disciplina'    => $package['disciplina'] ?? '',
                    'avaliacao'     => $package['avaliacao'] ?? '',
                    'turma'         => $package['turmas'][$turmaKey]['turma'] ?? $turmaKey,
                    'projeto_grupo' => $projeto,
                    'nota_trabalho' => $notaTrab,
                    'max_trabalho'  => 4.0,
                    'nota_pbb'      => $notaPbb,
                    'max_pbb'       => 1.0,
                    'nota_final'    => $notaFinal,
                    'max_final'     => 5.0,
                    'criterios'     => [
                        ['criterio' => 'Proposta de Valor & Oportunidade', 'nota' => min(1.5, round($notaTrab * 0.375, 2)), 'max' => 1.5, 'descricao' => 'Clareza do problema de mercado e proposta de valor.'],
                        ['criterio' => 'Business Model Canvas (9 Blocos)', 'nota' => min(1.5, round($notaTrab * 0.375, 2)), 'max' => 1.5, 'descricao' => 'Estruturação coerente dos 9 blocos.'],
                        ['criterio' => 'Plano de Implementação 5W2H', 'nota' => min(1.0, round($notaTrab * 0.25, 2)), 'max' => 1.0, 'descricao' => 'Detalhamento de etapas operacionais e custos.'],
                    ],
                    'pontos_fortes' => !empty($r['pontos_fortes']) ? explode(';', $r['pontos_fortes']) : ['Boa fundamentação do projeto e aderência aos conceitos.'],
                    'pontos_falhos' => !empty($r['pontos_falhos']) ? explode(';', $r['pontos_falhos']) : ['Sem falhas impeditivas identificadas.'],
                    'updated_at'    => date('d/m/Y H:i'),
                ];

                $newVaults = self::encryptTrabalhoVaultVariants($packageId, $cleanRgm, $nome, $payload);
                foreach ($newVaults as $h => $c) {
                    $vaults[$h] = $c;
                }
                $importedCount++;

            } else {
                // Caso Avaliação (Prova)
                $q1 = strtoupper(trim($r['q1'] ?? ($r['questao_1'] ?? 'A')));
                $q2 = strtoupper(trim($r['q2'] ?? ($r['questao_2'] ?? 'A')));
                $q3 = strtoupper(trim($r['q3'] ?? ($r['questao_3'] ?? 'A')));
                $versao = strtoupper(trim($r['versao'] ?? ($r['key'] ?? 'A')));

                $isFisio = str_contains(mb_strtoupper($package['disciplina'] ?? '', 'UTF-8'), 'FISIOLOGIA');
                $escalaMax = (float) ($package['escala_maxima'] ?? ($isFisio ? 3.5 : 2.5));
                $notaFinal = (float) str_replace(',', '.', $r['nota_final'] ?? ($r['nota'] ?? (string) $escalaMax));
                $acertosObj = (int) ($r['acertos'] ?? ($r['acertos_obj'] ?? ($isFisio ? 18 : 12)));
                $totalObj = (int) ($r['total_obj'] ?? ($isFisio ? 20 : 15));

                if ($isFisio) {
                    $notaObj = $notaFinal;
                    $maxObj = $escalaMax;
                    $temDiscursiva = false;
                    $discursivaBlock = null;
                } else {
                    $notaObj = min($notaFinal, round($acertosObj * 0.1, 2));
                    $maxObj = 1.5;
                    $temDiscursiva = true;
                    $notaDiscTotal = max(0.0, round($notaFinal - $notaObj, 2));
                    $notaA = round($notaDiscTotal * 0.25, 2);
                    $notaB = round($notaDiscTotal * 0.55, 2);
                    $notaC = max(0.0, round($notaDiscTotal - $notaA - $notaB, 2));
                    $discursivaBlock = [
                        'titulo_caso' => 'Questão Prática / Estudo de Caso',
                        'nota_total'  => $notaDiscTotal,
                        'max_total'   => 1.0,
                        'nota_a'      => $notaA,
                        'nota_b'      => $notaB,
                        'nota_c'      => $notaC,
                        'resumo_respostas' => ['a' => 'Avaliação técnica do caso.', 'b' => 'Prescrição / Conduta.', 'c' => 'Manejo e recomendações.'],
                        'feedback_professor' => $r['feedback'] ?? 'Resolução coerente com os parâmetros técnicos esperados.',
                    ];
                }

                $payload = [
                    'tipo'           => 'avaliacao',
                    'ano'            => (string) ($package['ano'] ?? '2026'),
                    'semestre'       => (string) ($package['semestre'] ?? '2026.2'),
                    'disciplina'     => $package['disciplina'] ?? '',
                    'avaliacao'      => $package['avaliacao'] ?? '',
                    'turma'          => $package['turmas'][$turmaKey]['turma'] ?? $turmaKey,
                    'data_prova'     => $package['turmas'][$turmaKey]['data'] ?? date('d/m/Y'),
                    'versao'         => $versao,
                    'versao_label'   => "Versão {$versao}",
                    'acertos_obj'    => $acertosObj,
                    'total_obj'      => $totalObj,
                    'nota_obj'       => $notaObj,
                    'max_obj'        => $maxObj,
                    'nota_final'     => $notaFinal,
                    'max_final'      => $escalaMax,
                    'tem_discursiva' => $temDiscursiva,
                    'feedback_geral' => $r['feedback'] ?? '',
                    'discursiva'     => $discursivaBlock,
                    'itens_objetivos'=> [],
                    'updated_at'     => date('d/m/Y H:i'),
                ];

                [$h, $c] = self::encryptProvaVault($packageId, $cleanRgm, $q1, $q2, $q3, $payload);
                $vaults[$h] = $c;
                $importedCount++;
            }
        }

        $package['vaults'] = $vaults;
        if (isset($package['turmas'][$turmaKey])) {
            $package['turmas'][$turmaKey]['total_provas'] = ($package['turmas'][$turmaKey]['total_provas'] ?? 0) + $importedCount;
            $package['turmas'][$turmaKey]['status'] = 'publicada';
        }
        $package['updated_at'] = date('d/m/Y H:i');

        self::savePackageData($clean, $package);

        return [
            'success' => true,
            'message' => "Importação concluída com sucesso! {$importedCount} aluno(s) criptografados e inseridos no pacote.",
            'count'   => $importedCount,
        ];
    }

    /**
     * Insere ou atualiza um aluno avulso no pacote via dados manuais.
     */
    public static function insertSingleStudent(string $filename, string $turmaKey, array $data): array
    {
        $clean = basename($filename);
        $path = storage_path('app/exam_mirrors/' . $clean);
        if (!file_exists($path)) {
            return ['success' => false, 'message' => 'Pacote não encontrado.'];
        }

        $package = json_decode(@file_get_contents($path), true);
        $packageId = $package['package_id'];
        $tipo = $package['tipo'] ?? 'avaliacao';
        $vaults = $package['vaults'] ?? [];

        $cleanRgm = preg_replace('/\D+/', '', $data['rgm'] ?? '');
        if ($cleanRgm === '') {
            return ['success' => false, 'message' => 'RGM inválido.'];
        }

        if ($tipo === 'trabalho') {
            $nome = trim($data['nome'] ?? '');
            if ($nome === '') {
                return ['success' => false, 'message' => 'Informe o nome do aluno.'];
            }

            $notaTrab = (float) ($data['nota_trabalho'] ?? 4.0);
            $notaPbb  = (float) ($data['nota_pbb'] ?? 1.0);
            $notaFinal = (float) ($data['nota_final'] ?? ($notaTrab + $notaPbb));

            $payload = [
                'tipo'          => 'trabalho',
                'ano'           => (string) ($package['ano'] ?? '2026'),
                'semestre'      => (string) ($package['semestre'] ?? '2026.2'),
                'disciplina'    => $package['disciplina'] ?? '',
                'avaliacao'     => $package['avaliacao'] ?? '',
                'turma'         => $package['turmas'][$turmaKey]['turma'] ?? $turmaKey,
                'projeto_grupo' => $data['projeto'] ?? 'Projeto Prático',
                'nota_trabalho' => $notaTrab,
                'max_trabalho'  => 4.0,
                'nota_pbb'      => $notaPbb,
                'max_pbb'       => 1.0,
                'nota_final'    => $notaFinal,
                'max_final'     => 5.0,
                'criterios'     => [
                    ['criterio' => 'Proposta de Valor & Oportunidade', 'nota' => min(1.5, round($notaTrab * 0.375, 2)), 'max' => 1.5, 'descricao' => 'Clareza do problema de mercado e proposta de valor.'],
                    ['criterio' => 'Business Model Canvas (9 Blocos)', 'nota' => min(1.5, round($notaTrab * 0.375, 2)), 'max' => 1.5, 'descricao' => 'Estruturação coerente dos 9 blocos.'],
                    ['criterio' => 'Plano de Implementação 5W2H', 'nota' => min(1.0, round($notaTrab * 0.25, 2)), 'max' => 1.0, 'descricao' => 'Detalhamento de etapas operacionais e custos.'],
                ],
                'pontos_fortes' => !empty($data['pontos_fortes']) ? explode(';', $data['pontos_fortes']) : ['Boa articulação dos conceitos exigidos.'],
                'pontos_falhos' => !empty($data['pontos_falhos']) ? explode(';', $data['pontos_falhos']) : ['Sem falhas estruturais identificadas.'],
                'updated_at'    => date('d/m/Y H:i'),
            ];

            $newVaults = self::encryptTrabalhoVaultVariants($packageId, $cleanRgm, $nome, $payload);
            foreach ($newVaults as $h => $c) {
                $vaults[$h] = $c;
            }
        } else {
            $q1 = strtoupper(trim($data['q1'] ?? 'A'));
            $q2 = strtoupper(trim($data['q2'] ?? 'A'));
            $q3 = strtoupper(trim($data['q3'] ?? 'A'));
            $versao = strtoupper(trim($data['versao'] ?? 'A'));

            $notaFinal = (float) ($data['nota_final'] ?? 2.0);
            $acertosObj = (int) ($data['acertos_obj'] ?? 12);
            $totalObj = (int) ($data['total_obj'] ?? 15);

            $notaObj = min($notaFinal, round($acertosObj * 0.1, 2));
            $notaDiscTotal = max(0.0, round($notaFinal - $notaObj, 2));
            $notaA = round($notaDiscTotal * 0.25, 2);
            $notaB = round($notaDiscTotal * 0.55, 2);
            $notaC = max(0.0, round($notaDiscTotal - $notaA - $notaB, 2));

            $payload = [
                'tipo'          => 'avaliacao',
                'ano'           => (string) ($package['ano'] ?? '2026'),
                'semestre'      => (string) ($package['semestre'] ?? '2026.2'),
                'disciplina'    => $package['disciplina'] ?? '',
                'avaliacao'     => $package['avaliacao'] ?? '',
                'turma'         => $package['turmas'][$turmaKey]['turma'] ?? $turmaKey,
                'data_prova'    => $package['turmas'][$turmaKey]['data'] ?? date('d/m/Y'),
                'versao'        => $versao,
                'versao_label'  => "Versão {$versao}",
                'acertos_obj'   => $acertosObj,
                'total_obj'     => $totalObj,
                'nota_obj'      => $notaObj,
                'max_obj'       => 1.5,
                'nota_final'    => $notaFinal,
                'max_final'     => (float) ($package['escala_maxima'] ?? 2.5),
                'tem_discursiva'=> true,
                'discursiva'    => [
                    'titulo_caso' => 'Estudo de Caso / Questão Discursiva',
                    'nota_total'  => $notaDiscTotal,
                    'max_total'   => 1.0,
                    'nota_a'      => $notaA,
                    'nota_b'      => $notaB,
                    'nota_c'      => $notaC,
                    'resumo_respostas' => ['a' => 'Item a)', 'b' => 'Item b)', 'c' => 'Item c)'],
                    'feedback_professor' => $data['feedback'] ?? 'Resolução coerente com os parâmetros técnicos esperados.',
                ],
                'itens_objetivos' => [],
                'updated_at'    => date('d/m/Y H:i'),
            ];

            [$h, $c] = self::encryptProvaVault($packageId, $cleanRgm, $q1, $q2, $q3, $payload);
            $vaults[$h] = $c;
        }

        $package['vaults'] = $vaults;
        if (isset($package['turmas'][$turmaKey])) {
            $package['turmas'][$turmaKey]['total_provas'] = ($package['turmas'][$turmaKey]['total_provas'] ?? 0) + 1;
            $package['turmas'][$turmaKey]['status'] = 'publicada';
        }
        $package['updated_at'] = date('d/m/Y H:i');

        self::savePackageData($clean, $package);

        return ['success' => true, 'message' => "Aluno RGM {$cleanRgm} criptografado e salvo com sucesso no pacote!"];
    }

    /**
     * Exclui um arquivo de pacote com segurança.
     */
    public static function deletePackageFile(string $filename): bool
    {
        $clean = basename($filename);
        if ($clean === 'status_config.json' || !str_ends_with($clean, '.json')) {
            return false;
        }

        $path = storage_path('app/exam_mirrors/' . $clean);
        if (is_file($path)) {
            return @unlink($path);
        }

        return false;
    }

    /**
     * Mescla um arquivo JSON de turma (ex.: gerado isoladamente) diretamente dentro do pacote principal,
     * vinculando os cofres (vaults) à turma específica e alterando seu status para "publicada".
     */
    public static function mergeTurmaJson(string $parentFilename, string $turmaKey, array $turmaData): array
    {
        $clean = basename($parentFilename);
        $path = storage_path('app/exam_mirrors/' . $clean);
        if (!file_exists($path)) {
            return ['success' => false, 'message' => 'Pacote principal não encontrado no servidor.'];
        }

        $package = json_decode(@file_get_contents($path), true);
        if (!is_array($package) || empty($package['package_id'])) {
            return ['success' => false, 'message' => 'Arquivo JSON de pacote principal inválido.'];
        }

        $newVaults = $turmaData['vaults'] ?? [];
        if (!is_array($newVaults) || empty($newVaults)) {
            return ['success' => false, 'message' => 'Nenhum cofre de aluno (vaults) encontrado no JSON enviado.'];
        }

        if (!isset($package['vaults']) || !is_array($package['vaults'])) {
            $package['vaults'] = [];
        }

        $count = 0;
        foreach ($newVaults as $hash => $encCipher) {
            $package['vaults'][$hash] = $encCipher;
            $count++;
        }

        // Localiza a turma no pacote pai (pela chave exata, oferta numérica ou sigla)
        $matchedKey = $turmaKey;
        if (!isset($package['turmas'][$matchedKey])) {
            $aliasMap = [
                'PTA_MAT' => '2280372',
                'PTA_NOT' => '2280737',
                'GRU_MAT' => '2276204',
                'GRU_NOT' => '2278972',
                'SM_MAT'  => '2278834',
                'SM_NOT'  => '2278835',
                '2280372' => 'PTA_MAT',
                '2280737' => 'PTA_NOT',
                '2276204' => 'GRU_MAT',
                '2278972' => 'GRU_NOT',
                '2278834' => 'SM_MAT',
                '2278835' => 'SM_NOT',
            ];
            $alt = $aliasMap[$turmaKey] ?? null;
            if ($alt && isset($package['turmas'][$alt])) {
                $matchedKey = $alt;
            } else {
                foreach ($package['turmas'] as $k => $info) {
                    if ($k === $turmaKey || ($info['oferta'] ?? '') === $turmaKey) {
                        $matchedKey = $k;
                        break;
                    }
                }
            }
        }

        if (isset($package['turmas'][$matchedKey])) {
            $package['turmas'][$matchedKey]['status'] = 'publicada';
            $package['turmas'][$matchedKey]['total_provas'] = $count;
            if (!empty($turmaData['turmas'])) {
                $tFirst = reset($turmaData['turmas']);
                if (!empty($tFirst['versoes'])) {
                    $package['turmas'][$matchedKey]['versoes'] = $tFirst['versoes'];
                }
            }
        }

        $package['updated_at'] = date('d/m/Y H:i');
        self::savePackageData($clean, $package);

        return [
            'success' => true,
            'message' => "Turma carregada com sucesso! {$count} prova(s) vinculadas e liberadas no pacote.",
            'count'   => $count,
        ];
    }

    /**
     * Localiza e descriptografa o espelho de um aluno em memória para Vista de Prova / Auditoria.
     */
    public static function findAndDecryptStudent(string $filename, string $rgm, ?string $nome = null): ?array
    {
        $clean = basename($filename);
        $path = storage_path('app/exam_mirrors/' . $clean);
        if (!file_exists($path)) {
            return null;
        }

        $package = json_decode(@file_get_contents($path), true);
        if (!is_array($package) || empty($package['package_id']) || empty($package['vaults'])) {
            return null;
        }

        $packageId = $package['package_id'];
        $tipo = $package['tipo'] ?? 'avaliacao';
        $rgmCandidates = self::generateRgmCandidates($rgm);

        if (empty($rgmCandidates)) {
            return null;
        }

        if ($tipo === 'trabalho') {
            if (empty($nome)) {
                return null;
            }
            $normName = self::normalizeName($nome);
            $nameParts = explode(' ', $normName);
            $firstName = $nameParts[0] ?? $normName;
            $candidates = array_unique([$firstName, $normName]);

            foreach ($rgmCandidates as $candRgm) {
                foreach ($candidates as $cand) {
                    $tok = "{$packageId}|{$candRgm}|{$cand}";
                    $h = hash_hmac('sha256', "LOOKUP|{$tok}", self::PEPPER);
                    if (isset($package['vaults'][$h])) {
                        $cipherB64 = $package['vaults'][$h];
                        $encKey = hash('sha256', 'ENCKEY|' . self::PEPPER . '|' . $tok, true);
                        $iv = md5('IV|' . self::PEPPER . '|' . $tok, true);
                        $decrypted = openssl_decrypt(base64_decode($cipherB64), 'aes-256-cbc', $encKey, OPENSSL_RAW_DATA, $iv);
                        $payload = json_decode($decrypted, true);
                        if (is_array($payload)) {
                            return [
                                'lookup_hash' => $h,
                                'token'       => $tok,
                                'type'        => 'trabalho',
                                'rgm'         => $candRgm,
                                'key1'        => $cand,
                                'key2'        => '',
                                'key3'        => '',
                                'payload'     => $payload,
                            ];
                        }
                    }
                }
            }
            return null;
        }

        // Caso Avaliação (Q1, Q2, Q3) - testa combinações com os candidatos de RGM
        $letters = ['A', 'B', 'C', 'D', 'E', 'X'];
        foreach ($rgmCandidates as $candRgm) {
            foreach ($letters as $a1) {
                foreach ($letters as $a2) {
                    foreach ($letters as $a3) {
                        $tok = "{$packageId}|{$candRgm}|{$a1}|{$a2}|{$a3}";
                        $h = hash_hmac('sha256', "LOOKUP|{$tok}", self::PEPPER);
                        if (isset($package['vaults'][$h])) {
                            $cipherB64 = $package['vaults'][$h];
                            $encKey = hash('sha256', 'ENCKEY|' . self::PEPPER . '|' . $tok, true);
                            $iv = md5('IV|' . self::PEPPER . '|' . $tok, true);
                            $decrypted = openssl_decrypt(base64_decode($cipherB64), 'aes-256-cbc', $encKey, OPENSSL_RAW_DATA, $iv);
                            $payload = json_decode($decrypted, true);
                            if (is_array($payload)) {
                                return [
                                    'lookup_hash' => $h,
                                    'token'       => $tok,
                                    'type'        => 'avaliacao',
                                    'rgm'         => $candRgm,
                                    'key1'        => $a1,
                                    'key2'        => $a2,
                                    'key3'        => $a3,
                                    'payload'     => $payload,
                                ];
                            }
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * Atualiza o payload criptografado de um aluno individual após ajuste manual / vista de prova.
     */
    public static function updateStudentPayload(string $filename, string $lookupHash, string $token, array $updatedPayload): bool
    {
        $clean = basename($filename);
        $path = storage_path('app/exam_mirrors/' . $clean);
        if (!file_exists($path)) {
            return false;
        }

        $package = json_decode(@file_get_contents($path), true);
        if (!is_array($package) || !isset($package['vaults'][$lookupHash])) {
            return false;
        }

        $encKey = hash('sha256', 'ENCKEY|' . self::PEPPER . '|' . $token, true);
        $iv = md5('IV|' . self::PEPPER . '|' . $token, true);

        $rawJson = json_encode($updatedPayload, JSON_UNESCAPED_UNICODE);
        $cipherBytes = openssl_encrypt($rawJson, 'aes-256-cbc', $encKey, OPENSSL_RAW_DATA, $iv);
        $package['vaults'][$lookupHash] = base64_encode($cipherBytes);
        $package['updated_at'] = date('d/m/Y H:i');

        self::savePackageData($clean, $package);
        return true;
    }
}
