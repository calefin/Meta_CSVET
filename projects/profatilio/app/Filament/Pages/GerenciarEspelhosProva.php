<?php

namespace App\Filament\Pages;

use App\Http\Controllers\ExamMirrorController;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\WithFileUploads;

class GerenciarEspelhosProva extends Page
{
    use WithFileUploads;

    protected static ?string $navigationIcon  = 'heroicon-o-academic-cap';
    protected static string  $view            = 'filament.pages.gerenciar-espelhos-prova';
    protected static ?string $navigationLabel = 'Devolutivas & Espelhos de Prova';
    protected static ?string $title           = 'Gestão de Devolutivas e Espelhos de Prova (LGPD)';
    protected static ?string $navigationGroup = 'Gestão Acadêmica & Devolutivas';
    protected static ?int    $navigationSort  = 1;

    public $jsonFile = null;

    // Navegação Principal por Abas no Painel do Professor
    public string $activeTab = 'gabaritos'; // 'gabaritos', 'pacotes', 'vista', 'semestres'

    // Visualizador Interativo de Gabaritos Oficiais
    public string $selectedGabaritoDiscipline = 'fisiologia_ii';
    public string $selectedGabaritoTurma = 'PTA_MAT';
    public string $selectedGabaritoVersao = 'A';
    public string $searchQuestion = '';
    public string $filterAlternative = 'ALL';

    // Confirmação de Exclusão (Segurança)
    public bool $confirmingDeletion = false;
    public string $deletingFilename = '';
    public string $deletingPackageLabel = '';

    // Carga Direta por Turma
    public bool $isTurmaLoading = false;
    public string $turmaLoadingPackage = '';
    public string $turmaLoadingKey = '';
    public array $turmaLoadingInfo = [];
    public $turmaLoadingJsonFile = null;
    public $turmaLoadingSpreadsheetFile = null;
    public string $turmaLoadingRawText = '';

    // Listas de Turmas nos Modais (corrige bug do pathinfo)
    public array $importTurmasList = [];
    public array $studentTurmasList = [];

    // Vista de Prova & Edição Individual de Aluno
    public bool $isVistaDeProva = false;
    public string $vistaPackage = '';
    public string $vistaRgm = '';
    public string $vistaNome = '';
    public ?array $vistaStudent = null;
    public ?string $vistaHash = null;
    public ?string $vistaToken = null;
    public array $vistaForm = [
        'nota_disc'  => 0.0,
        'nota_final' => 0.0,
        'feedback'   => '',
        'obs'        => '',
    ];

    // Estados do CRUD
    public bool $isEditing = false;
    public string $editingFilename = '';
    public array $editForm = [];

    public bool $isCreating = false;
    public array $createForm = [];

    public ?array $viewingPackage = null;

    // Estados de Importação Direta (sem scripts)
    public bool $isImporting = false;
    public string $importPackage = '';
    public string $importTurma = '';
    public $importFile = null;
    public string $importRawText = '';

    // Estado de Aluno Avulso
    public bool $isAddingStudent = false;
    public string $studentPackage = '';
    public string $studentTurma = '';
    public array $studentData = [
        'rgm'           => '',
        'nome'          => '',
        'projeto'       => 'Projeto Prático',
        'nota_trabalho' => 4.0,
        'nota_pbb'      => 1.0,
        'versao'        => 'A',
        'q1'            => 'A',
        'q2'            => 'A',
        'q3'            => 'A',
        'acertos_obj'   => 12,
        'nota_final'    => 2.0,
        'feedback'      => '',
    ];

    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    public function getPackages(): array
    {
        return ExamMirrorController::loadPackages(false);
    }

    public function getSystemConfig(): array
    {
        return ExamMirrorController::getSystemConfig();
    }

    public function getAvailableSemesters(): array
    {
        return ExamMirrorController::getAvailableSemesters($this->getPackages());
    }

    public function getCampusesList(): array
    {
        return ExamMirrorController::CAMPUSES;
    }

    public function getTurnosList(): array
    {
        return ExamMirrorController::TURNOS;
    }

    public function getDisciplinesList(): array
    {
        return ExamMirrorController::CURRENT_DISCIPLINES;
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function selectGabarito(string $disc, ?string $turma = null, ?string $versao = null): void
    {
        $this->selectedGabaritoDiscipline = $disc;
        $keys = $this->getOfficialAnswerKeys();
        $turmas = $keys[$disc]['turmas'] ?? [];

        if ($turma && isset($turmas[$turma])) {
            $this->selectedGabaritoTurma = $turma;
        } else {
            $this->selectedGabaritoTurma = array_key_first($turmas) ?? '';
        }

        $versoes = $turmas[$this->selectedGabaritoTurma]['versoes'] ?? [];
        if ($versao && isset($versoes[$versao])) {
            $this->selectedGabaritoVersao = $versao;
        } else {
            $this->selectedGabaritoVersao = array_key_first($versoes) ?? 'A';
        }
    }

    public function selectGabaritoTurma(string $turma): void
    {
        $this->selectedGabaritoTurma = $turma;
        $keys = $this->getOfficialAnswerKeys();
        $versoes = $keys[$this->selectedGabaritoDiscipline]['turmas'][$turma]['versoes'] ?? [];
        if (!isset($versoes[$this->selectedGabaritoVersao])) {
            $this->selectedGabaritoVersao = array_key_first($versoes) ?? 'A';
        }
    }

    public function selectGabaritoVersao(string $versao): void
    {
        $this->selectedGabaritoVersao = $versao;
    }

    public function getOfficialAnswerKeys(): array
    {
        return ExamMirrorController::loadOfficialAnswerKeys();
    }

    public function setActiveSemester(string $semestre): void
    {
        $cfg = ExamMirrorController::getSystemConfig();
        $cfg['active_semester'] = $semestre;
        if (!isset($cfg['semesters_status'][$semestre])) {
            $cfg['semesters_status'][$semestre] = 'aberto';
        }
        ExamMirrorController::saveSystemConfig($cfg);

        Notification::make()
            ->title('Semestre Vigente Atualizado')
            ->body("O semestre {$semestre} foi definido como ativo no portal.")
            ->success()
            ->send();
    }

    public function toggleSemesterStatus(string $semestre): void
    {
        $cfg = ExamMirrorController::getSystemConfig();
        $current = $cfg['semesters_status'][$semestre] ?? 'aberto';
        $newStatus = ($current === 'aberto') ? 'encerrado' : 'aberto';
        $cfg['semesters_status'][$semestre] = $newStatus;
        ExamMirrorController::saveSystemConfig($cfg);

        Notification::make()
            ->title('Status do Semestre Atualizado')
            ->body(sprintf('O semestre %s foi marcado como %s.', $semestre, strtoupper($newStatus)))
            ->success()
            ->send();
    }

    // ==========================================
    // LEITURA / INSPEÇÃO DETALHADA (READ)
    // ==========================================
    public function viewPackage(string $filename): void
    {
        $clean = basename($filename);
        $path = storage_path('app/exam_mirrors/' . $clean);
        if (!file_exists($path)) {
            Notification::make()->title('Arquivo não encontrado')->danger()->send();
            return;
        }

        $raw = json_decode(@file_get_contents($path), true) ?: [];
        $vaultCount = isset($raw['vaults']) && is_array($raw['vaults']) ? count($raw['vaults']) : 0;
        unset($raw['vaults']);

        $raw['vault_count'] = $vaultCount;
        $raw['filename'] = $clean;
        $this->viewingPackage = $raw;
    }

    public function closeViewPackage(): void
    {
        $this->viewingPackage = null;
    }

    // ==========================================
    // EDIÇÃO DE METADADOS & TURMAS (UPDATE)
    // ==========================================
    public function editPackage(string $filename): void
    {
        $clean = basename($filename);
        $path = storage_path('app/exam_mirrors/' . $clean);
        if (!file_exists($path)) {
            Notification::make()->title('Arquivo não encontrado')->danger()->send();
            return;
        }

        $data = json_decode(@file_get_contents($path), true) ?: [];
        $this->editingFilename = $clean;

        $turmasFormatted = [];
        if (!empty($data['turmas']) && is_array($data['turmas'])) {
            foreach ($data['turmas'] as $tk => $tv) {
                $turmasFormatted[$tk] = [
                    'key'    => $tk,
                    'turma'  => ExamMirrorController::formatTurmaName($tv['turma'] ?? $tk),
                    'campus' => $tv['campus'] ?? 'PTA',
                    'turno'  => $tv['turno'] ?? 'Matutino',
                    'data'   => $tv['data'] ?? '',
                    'status' => $tv['status'] ?? 'publicada',
                    'total_provas' => $tv['total_provas'] ?? 0,
                ];
            }
        }

        $this->editForm = [
            'package_id'       => $data['package_id'] ?? '',
            'disciplina'       => $data['disciplina'] ?? '',
            'campus'           => $data['campus'] ?? ExamMirrorController::inferCampus($data['disciplina'] ?? '', $data['turmas'] ?? []),
            'avaliacao'        => $data['avaliacao'] ?? '',
            'tipo'             => $data['tipo'] ?? 'avaliacao',
            'ano'              => (string) ($data['ano'] ?? '2026'),
            'semestre'         => (string) ($data['semestre'] ?? '2026.2'),
            'ordem_exibicao'   => (int) ($data['ordem_exibicao'] ?? 1),
            'escala_maxima'    => (float) ($data['escala_maxima'] ?? 2.5),
            'descricao_escala' => $data['descricao_escala'] ?? '',
            'turmas'           => $turmasFormatted,
        ];

        $this->isEditing = true;
        $this->isCreating = false;
        $this->viewingPackage = null;
    }

    public function cancelEdit(): void
    {
        $this->isEditing = false;
        $this->editingFilename = '';
        $this->editForm = [];
    }

    public function addTurmaToEdit(string $campus = 'PTA', string $turno = 'Matutino'): void
    {
        $campusName = ExamMirrorController::CAMPUSES[$campus] ?? $campus;
        $key = strtoupper($campus) . '_' . strtoupper(substr($turno, 0, 3));
        
        $counter = 1;
        $origKey = $key;
        while (isset($this->editForm['turmas'][$key])) {
            $key = $origKey . '_' . (++$counter);
        }

        $this->editForm['turmas'][$key] = [
            'key'    => $key,
            'turma'  => "{$campus} — {$campusName} · {$turno}",
            'campus' => $campus,
            'turno'  => $turno,
            'data'   => date('d/m/Y'),
            'status' => 'publicada',
            'total_provas' => 0,
        ];
    }

    public function removeTurmaFromEdit(string $key): void
    {
        unset($this->editForm['turmas'][$key]);
    }

    public function savePackageEdits(): void
    {
        if (empty($this->editingFilename)) {
            return;
        }

        $this->validate([
            'editForm.disciplina' => 'required|string|max:150',
            'editForm.avaliacao'  => 'required|string|max:150',
            'editForm.campus'     => 'required|string|max:100',
            'editForm.tipo'       => 'required|in:avaliacao,trabalho',
            'editForm.ano'        => 'required|string|max:10',
            'editForm.semestre'   => 'required|string|max:20',
        ]);

        $path = storage_path('app/exam_mirrors/' . $this->editingFilename);
        $existing = file_exists($path) ? json_decode(@file_get_contents($path), true) : [];

        // Monta estrutura limpa de turmas
        $cleanTurmas = [];
        foreach ($this->editForm['turmas'] as $k => $t) {
            $existingVersoes = $existing['turmas'][$k]['versoes'] ?? [];
            $cleanTurmas[$k] = [
                'oferta'       => $k,
                'turma'        => ExamMirrorController::formatTurmaName($t['turma'] ?? $k),
                'campus'       => $t['campus'] ?? 'PTA',
                'turno'        => $t['turno'] ?? 'Matutino',
                'data'         => trim((string) ($t['data'] ?? '')),
                'status'       => $t['status'] ?? 'publicada',
                'total_provas' => (int) ($t['total_provas'] ?? 0),
                'versoes'      => $existingVersoes,
            ];
        }

        $existing['disciplina']       = trim($this->editForm['disciplina']);
        $existing['campus']           = trim($this->editForm['campus']);
        $existing['avaliacao']        = trim($this->editForm['avaliacao']);
        $existing['tipo']             = $this->editForm['tipo'];
        $existing['ano']              = trim($this->editForm['ano']);
        $existing['semestre']         = trim($this->editForm['semestre']);
        $existing['ordem_exibicao']   = (int) ($this->editForm['ordem_exibicao'] ?? 1);
        $existing['escala_maxima']    = (float) ($this->editForm['escala_maxima'] ?? 2.5);
        $existing['descricao_escala'] = trim((string) ($this->editForm['descricao_escala'] ?? ''));
        $existing['turmas']           = $cleanTurmas;
        $existing['updated_at']       = date('d/m/Y H:i');

        ExamMirrorController::savePackageData($this->editingFilename, $existing);

        Notification::make()
            ->title('Ajustes salvos com sucesso!')
            ->body("O pacote {$this->editingFilename} foi atualizado.")
            ->success()
            ->send();

        $this->cancelEdit();
    }

    // ==========================================
    // CRIAÇÃO DE NOVO PACOTE / DISCIPLINA (CREATE)
    // ==========================================
    public function openCreatePackage(?string $presetDiscipline = null): void
    {
        $disciplina = $presetDiscipline ?? 'Fisiologia Animal II';
        $campus = ($disciplina === 'Medicina dos Animais Silvestres') ? 'PTA · GRU · SM' : 'PTA — Paulista';
        $isFisio = str_contains(mb_strtoupper($disciplina, 'UTF-8'), 'FISIOLOGIA');

        $this->createForm = [
            'disciplina'       => $disciplina,
            'campus'           => $campus,
            'avaliacao'        => 'Avaliação A2 (Prova Presencial)',
            'tipo'             => 'avaliacao',
            'ano'              => '2026',
            'semestre'         => '2026.2',
            'ordem_exibicao'   => 5,
            'escala_maxima'    => $isFisio ? 3.5 : 2.5,
            'descricao_escala' => $isFisio
                ? '20 Questões Objetivas × 0,175 pt = 3,50 pontos'
                : '15 Questões Objetivas (1,50 pt) + 1 Questão Discursiva (1,00 pt) = 2,50 pontos',
            'turmas'           => [
                'PTA_MAT' => [
                    'key'    => 'PTA_MAT',
                    'turma'  => 'PTA — Paulista · Matutino',
                    'campus' => 'PTA',
                    'turno'  => 'Matutino',
                    'data'   => '06/11/2026',
                    'status' => 'aguardando_correcao',
                    'total_provas' => 0,
                ],
                'PTA_NOT' => [
                    'key'    => 'PTA_NOT',
                    'turma'  => 'PTA — Paulista · Noturno',
                    'campus' => 'PTA',
                    'turno'  => 'Noturno',
                    'data'   => '06/11/2026',
                    'status' => 'aguardando_correcao',
                    'total_provas' => 0,
                ],
            ],
        ];

        $this->isCreating = true;
        $this->isEditing = false;
        $this->viewingPackage = null;
    }

    public function cancelCreate(): void
    {
        $this->isCreating = false;
        $this->createForm = [];
    }

    public function addTurmaToCreate(string $campus = 'PTA', string $turno = 'Matutino'): void
    {
        $campusName = ExamMirrorController::CAMPUSES[$campus] ?? $campus;
        $key = strtoupper($campus) . '_' . strtoupper(substr($turno, 0, 3));
        
        $counter = 1;
        $origKey = $key;
        while (isset($this->createForm['turmas'][$key])) {
            $key = $origKey . '_' . (++$counter);
        }

        $this->createForm['turmas'][$key] = [
            'key'    => $key,
            'turma'  => "{$campus} — {$campusName} · {$turno}",
            'campus' => $campus,
            'turno'  => $turno,
            'data'   => date('d/m/Y'),
            'status' => 'aguardando_correcao',
            'total_provas' => 0,
        ];
    }

    public function removeTurmaFromCreate(string $key): void
    {
        unset($this->createForm['turmas'][$key]);
    }

    public function storeNewPackage(): void
    {
        $this->validate([
            'createForm.disciplina' => 'required|string|max:150',
            'createForm.avaliacao'  => 'required|string|max:150',
            'createForm.campus'     => 'required|string|max:100',
            'createForm.tipo'       => 'required|in:avaliacao,trabalho',
            'createForm.ano'        => 'required|string|max:10',
            'createForm.semestre'   => 'required|string|max:20',
        ]);

        $safeSlug = preg_replace('/[^a-zA-Z0-9]/', '_', strtolower(
            $this->createForm['semestre'] . '_' . $this->createForm['disciplina'] . '_' . $this->createForm['avaliacao']
        ));
        $packageId = mb_strtoupper(preg_replace('/_+/', '__', trim($safeSlug, '_')), 'UTF-8');
        $filename = strtolower($safeSlug) . '.json';

        $cleanTurmas = [];
        foreach ($this->createForm['turmas'] as $k => $t) {
            $cleanTurmas[$k] = [
                'oferta'       => $k,
                'turma'        => ExamMirrorController::formatTurmaName($t['turma'] ?? $k),
                'campus'       => $t['campus'] ?? 'PTA',
                'turno'        => $t['turno'] ?? 'Matutino',
                'data'         => trim((string) ($t['data'] ?? '')),
                'status'       => $t['status'] ?? 'aguardando_correcao',
                'total_provas' => 0,
                'versoes'      => [],
            ];
        }

        $packageData = [
            'package_id'       => $packageId,
            'ano'              => trim($this->createForm['ano']),
            'semestre'         => trim($this->createForm['semestre']),
            'campus'           => trim($this->createForm['campus']),
            'disciplina'       => trim($this->createForm['disciplina']),
            'avaliacao'        => trim($this->createForm['avaliacao']),
            'tipo'             => $this->createForm['tipo'],
            'ordem_exibicao'   => (int) ($this->createForm['ordem_exibicao'] ?? 5),
            'escala_maxima'    => (float) ($this->createForm['escala_maxima'] ?? 2.5),
            'descricao_escala' => trim((string) ($this->createForm['descricao_escala'] ?? '')),
            'updated_at'       => date('d/m/Y H:i'),
            'turmas'           => $cleanTurmas,
            'vaults'           => [],
        ];

        ExamMirrorController::savePackageData($filename, $packageData);

        Notification::make()
            ->title('Novo pacote cadastrado!')
            ->body("A estrutura para {$packageData['disciplina']} ({$packageData['campus']}) foi criada com sucesso.")
            ->success()
            ->send();

        $this->cancelCreate();
    }

    // ==========================================
    // IMPORTAÇÃO DIRETA DE NOTAS (SEM SCRIPTS)
    // ==========================================
    public function openImport(string $filename, ?string $turmaKey = null): void
    {
        $this->importPackage = basename($filename);
        $path = storage_path('app/exam_mirrors/' . $this->importPackage);
        $pkg = file_exists($path) ? json_decode(@file_get_contents($path), true) : [];
        $this->importTurmasList = $pkg['turmas'] ?? [];

        $this->importTurma = $turmaKey ?? (array_key_first($this->importTurmasList) ?? '');
        $this->importFile = null;
        $this->importRawText = '';
        $this->isImporting = true;
        $this->isTurmaLoading = false;
        $this->isVistaDeProva = false;
        $this->isEditing = false;
        $this->isCreating = false;
        $this->isAddingStudent = false;
        $this->viewingPackage = null;
    }

    public function cancelImport(): void
    {
        $this->isImporting = false;
        $this->importPackage = '';
        $this->importTurma = '';
        $this->importTurmasList = [];
        $this->importFile = null;
        $this->importRawText = '';
    }

    public function runImport(): void
    {
        if (empty($this->importPackage) || empty($this->importTurma)) {
            Notification::make()->title('Selecione o pacote e a turma')->danger()->send();
            return;
        }

        $content = '';
        if ($this->importFile) {
            $content = (string) @file_get_contents($this->importFile->getRealPath());
        } elseif (!empty(trim($this->importRawText))) {
            $content = trim($this->importRawText);
        }

        if (empty(trim($content))) {
            Notification::make()->title('Envie um arquivo CSV/TSV ou cole as linhas de texto')->danger()->send();
            return;
        }

        $res = ExamMirrorController::importSpreadsheetContent($this->importPackage, $this->importTurma, $content);

        if (!empty($res['success'])) {
            Notification::make()
                ->title('Notas Importadas e Criptografadas!')
                ->body($res['message'] ?? 'Alunos inseridos com sucesso.')
                ->success()
                ->send();
            $this->cancelImport();
        } else {
            Notification::make()
                ->title('Erro na importação')
                ->body($res['message'] ?? 'Verifique o formato das colunas.')
                ->danger()
                ->send();
        }
    }

    // ==========================================
    // CARGA DIRETA POR TURMA (CLIQUE NA TURMA)
    // ==========================================
    public function openTurmaLoader(string $filename, string $turmaKey): void
    {
        $this->turmaLoadingPackage = basename($filename);
        $this->turmaLoadingKey = $turmaKey;
        $path = storage_path('app/exam_mirrors/' . $this->turmaLoadingPackage);
        $pkg = file_exists($path) ? json_decode(@file_get_contents($path), true) : [];
        $this->turmaLoadingInfo = $pkg['turmas'][$turmaKey] ?? [
            'turma' => $turmaKey,
            'campus' => $pkg['campus'] ?? 'PTA',
            'turno' => 'Matutino',
            'status' => 'aguardando_correcao',
        ];
        $this->turmaLoadingJsonFile = null;
        $this->turmaLoadingSpreadsheetFile = null;
        $this->turmaLoadingRawText = '';
        $this->isTurmaLoading = true;
        $this->isImporting = false;
        $this->isEditing = false;
        $this->isCreating = false;
        $this->isAddingStudent = false;
        $this->isVistaDeProva = false;
        $this->viewingPackage = null;
    }

    public function cancelTurmaLoader(): void
    {
        $this->isTurmaLoading = false;
        $this->turmaLoadingPackage = '';
        $this->turmaLoadingKey = '';
        $this->turmaLoadingInfo = [];
        $this->turmaLoadingJsonFile = null;
        $this->turmaLoadingSpreadsheetFile = null;
        $this->turmaLoadingRawText = '';
    }

    public function runTurmaLoaderJson(): void
    {
        $this->validate([
            'turmaLoadingJsonFile' => 'required|file|max:20480',
        ]);

        $raw = @file_get_contents($this->turmaLoadingJsonFile->getRealPath());
        $data = $raw ? json_decode($raw, true) : null;

        if (!is_array($data) || empty($data['vaults'])) {
            Notification::make()->title('Arquivo JSON inválido')->body('Nenhum cofre de aluno (vaults) encontrado no arquivo enviado.')->danger()->send();
            return;
        }

        $res = ExamMirrorController::mergeTurmaJson($this->turmaLoadingPackage, $this->turmaLoadingKey, $data);
        if (!empty($res['success'])) {
            Notification::make()->title('Turma Carregada e Publicada!')->body($res['message'])->success()->send();
            $this->cancelTurmaLoader();
        } else {
            Notification::make()->title('Erro ao carregar turma')->body($res['message'] ?? 'Falha ao processar arquivo.')->danger()->send();
        }
    }

    public function runTurmaLoaderSpreadsheet(): void
    {
        $content = '';
        if ($this->turmaLoadingSpreadsheetFile) {
            $content = (string) @file_get_contents($this->turmaLoadingSpreadsheetFile->getRealPath());
        } elseif (!empty(trim($this->turmaLoadingRawText))) {
            $content = trim($this->turmaLoadingRawText);
        }

        if (empty(trim($content))) {
            Notification::make()->title('Envie uma planilha CSV/TSV ou cole as linhas de texto')->danger()->send();
            return;
        }

        $res = ExamMirrorController::importSpreadsheetContent($this->turmaLoadingPackage, $this->turmaLoadingKey, $content);
        if (!empty($res['success'])) {
            Notification::make()->title('Notas da Turma Importadas!')->body($res['message'])->success()->send();
            $this->cancelTurmaLoader();
        } else {
            Notification::make()->title('Erro na importação')->body($res['message'] ?? 'Verifique o formato das colunas.')->danger()->send();
        }
    }

    // ==========================================
    // VISTA DE PROVA & EDIÇÃO INDIVIDUAL DE ALUNO
    // ==========================================
    public function openVista(string $filename): void
    {
        $this->vistaPackage = basename($filename);
        $this->vistaRgm = '';
        $this->vistaNome = '';
        $this->vistaStudent = null;
        $this->vistaHash = null;
        $this->vistaToken = null;
        $this->vistaForm = [
            'nota_disc'  => 0.0,
            'nota_final' => 0.0,
            'feedback'   => '',
            'obs'        => '',
        ];
        $this->isVistaDeProva = true;
        $this->activeTab = 'vista';
        $this->isTurmaLoading = false;
        $this->isImporting = false;
        $this->isEditing = false;
        $this->isCreating = false;
        $this->isAddingStudent = false;
        $this->viewingPackage = null;
    }

    public function cancelVista(): void
    {
        $this->isVistaDeProva = false;
        $this->vistaPackage = '';
        $this->vistaRgm = '';
        $this->vistaNome = '';
        $this->vistaStudent = null;
        $this->vistaHash = null;
        $this->vistaToken = null;
    }

    public function searchStudentVista(): void
    {
        if (empty(trim($this->vistaRgm))) {
            Notification::make()->title('Digite o RGM do aluno')->danger()->send();
            return;
        }

        $res = ExamMirrorController::findAndDecryptStudent($this->vistaPackage, $this->vistaRgm, $this->vistaNome);
        if (!$res) {
            Notification::make()->title('Aluno não localizado')->body("Nenhum espelho com RGM {$this->vistaRgm} foi encontrado no pacote.")->warning()->send();
            $this->vistaStudent = null;
            return;
        }

        $this->vistaStudent = $res['payload'];
        $this->vistaHash = $res['lookup_hash'];
        $this->vistaToken = $res['token'];

        $p = $res['payload'];
        $notaDisc = $p['discursiva']['nota_total'] ?? ($p['nota_trabalho'] ?? 0.0);
        $feedback = $p['discursiva']['feedback_professor'] ?? ($p['feedback_professor'] ?? ($p['discursiva']['motivos_desconto'] ?? ''));
        if (is_array($feedback)) {
            $feedback = implode("\n", $feedback);
        }

        $this->vistaForm = [
            'nota_disc'  => (float) $notaDisc,
            'nota_final' => (float) ($p['nota_final'] ?? 0.0),
            'feedback'   => (string) $feedback,
            'obs'        => (string) ($p['obs_prova'] ?? ''),
        ];

        Notification::make()->title('Espelho do Aluno Aberto!')->body('Dados descriptografados em memória prontos para conferência e ajuste.')->success()->send();
    }

    public function saveStudentVista(): void
    {
        if (!$this->vistaStudent || !$this->vistaHash || !$this->vistaToken) {
            Notification::make()->title('Nenhum aluno em edição')->danger()->send();
            return;
        }

        $payload = $this->vistaStudent;
        $payload['nota_final'] = (float) $this->vistaForm['nota_final'];
        $payload['obs_prova'] = trim((string) $this->vistaForm['obs']);

        if (isset($payload['discursiva']) && is_array($payload['discursiva'])) {
            $payload['discursiva']['nota_total'] = (float) $this->vistaForm['nota_disc'];
            $payload['discursiva']['feedback_professor'] = trim((string) $this->vistaForm['feedback']);
        } elseif (isset($payload['tipo']) && $payload['tipo'] === 'trabalho') {
            $payload['nota_trabalho'] = (float) $this->vistaForm['nota_disc'];
            $payload['feedback_professor'] = trim((string) $this->vistaForm['feedback']);
        } else {
            $payload['discursiva'] = [
                'nota_total' => (float) $this->vistaForm['nota_disc'],
                'feedback_professor' => trim((string) $this->vistaForm['feedback']),
            ];
        }
        $payload['updated_at'] = date('d/m/Y H:i');

        $ok = ExamMirrorController::updateStudentPayload($this->vistaPackage, $this->vistaHash, $this->vistaToken, $payload);
        if ($ok) {
            $this->vistaStudent = $payload;
            Notification::make()->title('Alterações Salvas na Prova do Aluno!')->body('O cofre foi recriptografado e já está visível para o estudante no portal.')->success()->send();
        } else {
            Notification::make()->title('Erro ao salvar cofre')->danger()->send();
        }
    }

    // ==========================================
    // INSERÇÃO / AJUSTE DE ALUNO AVULSO
    // ==========================================
    public function openAddStudent(string $filename, ?string $turmaKey = null): void
    {
        $this->studentPackage = basename($filename);
        $path = storage_path('app/exam_mirrors/' . $this->studentPackage);
        $pkg = file_exists($path) ? json_decode(@file_get_contents($path), true) : [];
        $this->studentTurmasList = $pkg['turmas'] ?? [];

        $this->studentTurma = $turmaKey ?? (array_key_first($this->studentTurmasList) ?? '');
        $this->studentData = [
            'rgm'           => '',
            'nome'          => '',
            'projeto'       => 'Projeto Prático',
            'nota_trabalho' => 4.0,
            'nota_pbb'      => 1.0,
            'versao'        => 'A',
            'q1'            => 'A',
            'q2'            => 'A',
            'q3'            => 'A',
            'acertos_obj'   => 12,
            'nota_final'    => 2.0,
            'feedback'      => '',
        ];

        $this->isAddingStudent = true;
        $this->isTurmaLoading = false;
        $this->isVistaDeProva = false;
        $this->isImporting = false;
        $this->isEditing = false;
        $this->isCreating = false;
        $this->viewingPackage = null;
    }

    public function cancelAddStudent(): void
    {
        $this->isAddingStudent = false;
        $this->studentPackage = '';
        $this->studentTurma = '';
        $this->studentTurmasList = [];
    }

    public function saveSingleStudent(): void
    {
        if (empty($this->studentPackage) || empty($this->studentTurma)) {
            Notification::make()->title('Selecione pacote e turma')->danger()->send();
            return;
        }

        $res = ExamMirrorController::insertSingleStudent($this->studentPackage, $this->studentTurma, $this->studentData);

        if (!empty($res['success'])) {
            Notification::make()
                ->title('Aluno Gravado com Sucesso!')
                ->body($res['message'])
                ->success()
                ->send();
            $this->cancelAddStudent();
        } else {
            Notification::make()
                ->title('Erro ao gravar aluno')
                ->body($res['message'] ?? 'Confira os dados digitados.')
                ->danger()
                ->send();
        }
    }

    // ==========================================
    // UPLOAD DE PACOTE JSON CRIPTOGRAFADO
    // ==========================================
    public function uploadPackage(): void
    {
        $this->validate([
            'jsonFile' => 'required|file|max:20480',
        ]);

        $raw = @file_get_contents($this->jsonFile->getRealPath());
        $data = $raw ? json_decode($raw, true) : null;

        if (!is_array($data) || empty($data['package_id']) || !isset($data['vaults']) || !is_array($data['vaults'])) {
            Notification::make()
                ->title('Arquivo JSON inválido')
                ->body('O arquivo enviado não possui a estrutura de pacote criptografado (package_id e vaults).')
                ->danger()
                ->send();
            return;
        }

        // Sanitiza nomes de turmas no JSON enviado para não conter ofertas numéricas
        if (isset($data['turmas']) && is_array($data['turmas'])) {
            foreach ($data['turmas'] as $k => $tv) {
                if (isset($tv['turma'])) {
                    $data['turmas'][$k]['turma'] = ExamMirrorController::formatTurmaName((string) $tv['turma']);
                }
            }
        }
        $data['campus'] = $data['campus'] ?? ExamMirrorController::inferCampus($data['disciplina'] ?? '', $data['turmas'] ?? []);

        $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', strtolower((string) $data['package_id'])) . '.json';
        $dir = storage_path('app/exam_mirrors');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        // Se já existe um arquivo com o mesmo package_id, reutiliza o nome para não duplicar
        $targetFilename = $safeName;
        foreach (glob($dir . '/*.json') as $exPath) {
            if (basename($exPath) === 'status_config.json') continue;
            $exData = json_decode(@file_get_contents($exPath), true);
            if (is_array($exData) && ($exData['package_id'] ?? '') === $data['package_id']) {
                $targetFilename = basename($exPath);
                break;
            }
        }

        file_put_contents($dir . '/' . $targetFilename, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        if ($safeName !== $targetFilename && file_exists($dir . '/' . $safeName)) {
            @unlink($dir . '/' . $safeName);
        }

        $this->jsonFile = null;

        Notification::make()
            ->title('Pacote de devolutivas publicado com sucesso!')
            ->body(sprintf(
                '%s (%s) — %s (%d cofres individuais carregados).',
                $data['disciplina'] ?? '',
                $data['campus'] ?? 'PTA',
                $data['avaliacao'] ?? '',
                count($data['vaults'])
            ))
            ->success()
            ->send();
    }

    // ==========================================
    // CONFIRMAÇÃO E REMOÇÃO SEGURA (DELETE)
    // ==========================================
    public function confirmDelete(string $filename, string $label = ''): void
    {
        $this->deletingFilename = basename($filename);
        $this->deletingPackageLabel = $label ?: $this->deletingFilename;
        $this->confirmingDeletion = true;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDeletion = false;
        $this->deletingFilename = '';
        $this->deletingPackageLabel = '';
    }

    public function executeDelete(): void
    {
        if (!empty($this->deletingFilename)) {
            $this->deletePackage($this->deletingFilename);
        }
        $this->cancelDelete();
    }

    public function deletePackage(string $filename): void
    {
        $clean = basename($filename);
        if ($clean === 'status_config.json') {
            return;
        }

        if (ExamMirrorController::deletePackageFile($clean)) {
            Notification::make()
                ->title('Pacote removido com sucesso')
                ->body("O arquivo {$clean} foi excluído do portal.")
                ->success()
                ->send();
            
            if ($this->viewingPackage && ($this->viewingPackage['filename'] ?? '') === $clean) {
                $this->viewingPackage = null;
            }
            if ($this->editingFilename === $clean) {
                $this->cancelEdit();
            }
        }
    }
}
