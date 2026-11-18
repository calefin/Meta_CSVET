<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DocumentSubmissionResource\Pages;
use App\Models\DocumentSubmission;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DocumentSubmissionResource extends Resource
{
    protected static ?string $model = DocumentSubmission::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?int $navigationSort = -10;

    public static function getNavigationLabel(): string
    {
        return auth()->user()?->role === 'student' ? 'Minhas Submissões' : 'Trabalhos dos Alunos';
    }

    public static function getNavigationGroup(): ?string
    {
        return auth()->user()?->role === 'student' ? 'Meus Trabalhos' : 'Orientação Acadêmica';
    }

    /**
     * Quantidade de processos aguardando alguma resposta do professor (triagem inicial +
     * status "com a bola" com ele), para o item do menu já mostrar de cara o que precisa
     * de atenção, sem precisar abrir a lista.
     */
    public static function getNavigationBadge(): ?string
    {
        if (auth()->user()?->role !== 'student') {
            $count = DocumentSubmission::where('status', 'Aguardando Triagem')
                ->orWhereIn('status', \App\Filament\Resources\DocumentSubmissionResource\Pages\ListDocumentSubmissions::PROFESSOR_STATUSES)
                ->count();

            return $count > 0 ? (string) $count : null;
        }

        return null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }
    
    public static function getModelLabel(): string
    {
        return 'Submissão';
    }
    
    public static function getPluralModelLabel(): string
    {
        return auth()->user()?->role === 'student' ? 'Minhas Submissões' : 'Submissões dos Alunos';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(fn ($record) => $record?->status === 'Aguardando Envio do Aluno'
                        ? 'Documento Solicitado pelo Orientador'
                        : 'Correções Solicitadas pelo Orientador')
                    ->description(fn ($record) => $record?->status === 'Aguardando Envio do Aluno'
                        ? 'Leia as instruções abaixo e envie o arquivo solicitado usando o campo de upload logo adiante.'
                        : 'Leia o parecer abaixo, faça as correções no documento e reenvie usando o botão "Enviar Correções" no final da página.')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->iconColor('warning')
                    ->extraAttributes(['class' => 'border-l-4 border-amber-400 bg-amber-50 dark:bg-amber-950/20'])
                    ->schema([
                        Forms\Components\Placeholder::make('_parecer_notice')
                            ->label(fn ($record) => $record?->status === 'Aguardando Envio do Aluno' ? 'Instruções do Orientador' : 'Parecer do Orientador')
                            ->content(fn ($record) => $record?->ai_executive_summary
                                ? new \Illuminate\Support\HtmlString(
                                    '<div class="prose prose-sm max-w-none text-gray-700 dark:text-gray-300">'
                                    . $record->ai_executive_summary
                                    . '</div>'
                                )
                                : new \Illuminate\Support\HtmlString(
                                    '<em class="text-gray-400">Nenhum comentário escrito. '
                                    . 'Verifique se há um arquivo com marcações disponível para download na seção abaixo.</em>'
                                )
                            ),
                        Forms\Components\Placeholder::make('_instrucoes_notice')
                            ->label('O que fazer agora')
                            ->content(fn ($record) => $record?->status === 'Aguardando Envio do Aluno'
                                ? new \Illuminate\Support\HtmlString('
                                    <ol class="list-decimal list-inside space-y-1 text-sm text-gray-700 dark:text-gray-300">
                                        <li>Leia as instruções do orientador acima.</li>
                                        <li>Faça o upload do documento solicitado no campo <strong>Upload do Arquivo</strong>.</li>
                                        <li>Use o campo <strong>Observações para o Orientador</strong> para explicar algo, se necessário (opcional).</li>
                                        <li>Salve para enviar o documento ao orientador.</li>
                                    </ol>
                                ')
                                : new \Illuminate\Support\HtmlString('
                                    <ol class="list-decimal list-inside space-y-1 text-sm text-gray-700 dark:text-gray-300">
                                        <li>Leia o parecer acima com atenção.</li>
                                        <li>Se houver arquivo com marcações, baixe-o na seção <strong>Arquivo Retornado</strong> mais abaixo.</li>
                                        <li>Corrija o seu documento conforme solicitado.</li>
                                        <li>Faça o upload do arquivo corrigido no campo <strong>Upload do Arquivo</strong>.</li>
                                        <li>Use o campo <strong>Observações para o Orientador</strong> para explicar o que foi alterado (opcional).</li>
                                        <li>Clique em <strong>Enviar Correções</strong> para reenviar ao orientador.</li>
                                    </ol>
                                ')),
                    ])
                    ->visible(fn ($record) =>
                        $record !== null &&
                        auth()->check() &&
                        auth()->user()->role === 'student' &&
                        in_array($record->status, [
                            'Aguardando Ajustes do Aluno',
                            'needs_corrections',
                            'Aguardando Ajustes no Laudo pelo Aluno',
                            'Aguardando Envio do Aluno',
                        ])
                    ),
                Forms\Components\Section::make('Detalhes do Trabalho / Documento')
                    ->schema([
                        Forms\Components\Select::make('student_id')
                            ->relationship('student', 'id')
                            ->getOptionLabelFromRecordUsing(function ($record) {
                                $name = $record->user->name;
                                return $name . ' (' . $record->academic_level . ')';
                            })
                            ->required(fn () => auth()->user()->role === 'admin')
                            ->visible(fn () => auth()->user()->role === 'admin')
                            ->disabled(fn ($record) => $record !== null)
                            ->dehydrated()
                            ->default(fn () => \App\Models\Student::where('user_id', auth()->id())->first()?->id)
                            ->label('Aluno / Orientando'),
                        Forms\Components\Checkbox::make('signature_only_helper')
                            ->label('Este envio é apenas para assinatura (sem necessidade de correção de conteúdo)')
                            ->helperText('Marque para documentos como Termo de Aceite de Orientação, Plano de Estágio ou Termo de Aceite de IC — o professor apenas assina/devolve, sem revisar o conteúdo. A lista de "Tipo de Documento" abaixo será filtrada.')
                            ->live()
                            ->dehydrated(false)
                            ->default(false)
                            ->visible(fn ($record) => $record === null)
                            ->columnSpanFull()
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                $signatureOnlyTypes = ['Termo de Aceite de Orientação', 'Plano de Estágio', 'Termo de Aceite de IC'];
                                if ($state && !in_array($get('document_type'), $signatureOnlyTypes)) {
                                    $set('document_type', 'Termo de Aceite de Orientação');
                                }
                            }),
                         Forms\Components\Select::make('document_type')
                            ->options(function (Forms\Get $get) {
                                $all = [
                                    'Artigo Científico' => 'Artigo Científico',
                                    'Avaliações de Estágio' => 'Avaliações de Estágio',
                                    'Outro' => 'Outro',
                                    'Plano de Estágio' => 'Plano de Estágio',
                                    'Iniciação Científica' => 'Projeto de Iniciação Científica',
                                    'Protocolo CEUA/CEP' => 'Protocolo CEUA/CEP',
                                    'Relato de Caso' => 'Relato de Caso',
                                    'Relatório' => 'Relatório',
                                    'Relatório de Aula' => 'Relatório de Aula',
                                    'Relatório de Estágio' => 'Relatório de Estágio',
                                    'Relatório de Necropsia' => 'Relatório de Necropsia',
                                    'TCC' => 'TCC (Trabalho de Conclusão de Curso)',
                                    'Termo de Aceite de IC' => 'Termo de Aceite de IC',
                                    'Termo de Aceite de Orientação' => 'Termo de Aceite de Orientação',
                                    'Trabalho de Congresso' => 'Trabalho de Congresso / Certificado de Apresentação em Evento',
                                    'Concepção de TCC' => 'Concepção de TCC (Sem Tema/Título Definido)',
                                    'Objetivos de TCC' => 'Objetivos de TCC (Geral e Específicos)',
                                    'Introdução de TCC' => 'Introdução do TCC',
                                    'Materiais e Métodos de TCC' => 'Materiais e Métodos do TCC',
                                    'Resultados de TCC' => 'Resultados do TCC (opcional — pule se for revisão de literatura)',
                                    'Discussão de TCC' => 'Discussão do TCC',
                                    'Conclusão de TCC' => 'Conclusão do TCC',
                                ];

                                // "Documento Solicitado" só pode ser criado pelo professor (via botão
                                // "Solicitar Documento para Assinatura" na lista de Alunos) — o aluno
                                // nunca deve poder escolher esse tipo ao abrir uma submissão própria.
                                if (auth()->user()->role === 'admin') {
                                    $all['Documento Solicitado'] = 'Documento Solicitado (Assinatura)';
                                }

                                if ($get('signature_only_helper')) {
                                    return collect($all)
                                        ->only(['Termo de Aceite de Orientação', 'Plano de Estágio', 'Termo de Aceite de IC'])
                                        ->toArray();
                                }

                                return $all;
                            })
                            ->required()
                            ->live()
                            ->disabled(fn ($record) => $record !== null)
                            ->dehydrated()
                            ->default('TCC')
                            ->label('Tipo de Documento')
                            ->rules([
                                function (Forms\Get $get, $record) {
                                    return function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                                        if ($record !== null) {
                                            return;
                                        }

                                        $studentId = auth()->user()->role === 'student'
                                            ? auth()->user()->student?->id
                                            : $get('student_id');

                                        if (!$studentId) {
                                            return;
                                        }

                                        // Prerequisite validations for student submissions
                                        if (auth()->check() && auth()->user()->role === 'student') {
                                            if ($value === 'Objetivos de TCC') {
                                                $hasApprovedTheme = \App\Models\DocumentSubmission::where('student_id', $studentId)
                                                    ->where('document_type', 'Concepção de TCC')
                                                    ->where('status', 'Processo Concluído - Tema Definido')
                                                    ->exists();
                                                if (!$hasApprovedTheme) {
                                                    $fail("Você precisa ter uma sugestão de Tema de TCC aprovada pelo orientador (Processo de 'Concepção de TCC' concluído) antes de enviar os Objetivos.");
                                                    return;
                                                }
                                            }

                                            // Desde 2026-08-24, as 5 etapas de redação do TCC (Introdução,
                                            // Materiais e Métodos, Resultados, Discussão, Conclusão) não têm
                                            // mais ordem obrigatória entre si — o aluno pode enviar qualquer
                                            // uma, em qualquer ordem, assim que os Objetivos estiverem
                                            // aprovados. O orientador corrige cada uma independentemente.
                                            $writingStageTypes = [
                                                'Introdução de TCC',
                                                'Materiais e Métodos de TCC',
                                                'Resultados de TCC',
                                                'Discussão de TCC',
                                                'Conclusão de TCC',
                                            ];

                                            if (in_array($value, $writingStageTypes)) {
                                                $hasApprovedObjectives = \App\Models\DocumentSubmission::where('student_id', $studentId)
                                                    ->where('document_type', 'Objetivos de TCC')
                                                    ->where('status', 'Processo Concluído - Objetivos Aprovados')
                                                    ->exists();
                                                if (!$hasApprovedObjectives) {
                                                    $fail("Você precisa ter os Objetivos Geral e Específicos aprovados pelo orientador (Processo de 'Objetivos de TCC' concluído) antes de enviar qualquer etapa de redação do TCC.");
                                                    return;
                                                }
                                            }

                                            // A versão final (TCC) só libera quando todas as etapas
                                            // obrigatórias de redação estiverem aprovadas em paralelo
                                            // (Resultados continua opcional).
                                            if ($value === 'TCC') {
                                                $missingStages = \App\Models\AcademicTitle::missingTccWritingStages($studentId);
                                                if ($missingStages) {
                                                    $fail('Você precisa ter todas as etapas de redação aprovadas pelo orientador antes de solicitar a versão final do TCC. Faltam: '.implode(', ', $missingStages).' (Resultados é opcional).');
                                                    return;
                                                }
                                            }
                                        }

                                        // Apply duplicate prevention only to projects in correction/review
                                        $blockedTypes = [
                                            'TCC',
                                            'Relatório de Estágio',
                                            'Iniciação Científica',
                                            'Relatório',
                                        ];

                                        if (!in_array($value, $blockedTypes)) {
                                            return;
                                        }

                                        $academicTitleId = $get('academic_title_id');
                                        $titleText = trim($get('title'));

                                        if (!$academicTitleId && !$titleText) {
                                            return;
                                        }

                                        $activeSubmission = \App\Models\DocumentSubmission::where('student_id', $studentId)
                                            ->where('document_type', $value)
                                            ->get()
                                            ->filter(fn ($sub) => !$sub->isConcluded())
                                            ->filter(function ($sub) use ($academicTitleId, $titleText) {
                                                if ($academicTitleId && $sub->academic_title_id == $academicTitleId) {
                                                    return true;
                                                }
                                                if ($titleText && strcasecmp(trim($sub->title), $titleText) === 0) {
                                                    return true;
                                                }
                                                return false;
                                            })
                                            ->first();

                                        if ($activeSubmission) {
                                            $fail("Você já possui um processo em andamento para este título/projeto ({$activeSubmission->process_id}). Por favor, envie correções no próprio processo em andamento ou aguarde a conclusão.");
                                        }
                                    };
                                }
                            ]),
                        Forms\Components\Select::make('academic_title_id')
                            ->label('Título Acadêmico Vinculado')
                            ->options(function (Forms\Get $get) {
                                $studentId = auth()->user()->role === 'student'
                                    ? auth()->user()->student?->id
                                    : $get('student_id');

                                if (!$studentId) {
                                    return [];
                                }

                                $type = match ($get('document_type')) {
                                    'TCC', 'Objetivos de TCC' => 'TCC',
                                    'Iniciação Científica', 'Termo de Aceite de IC' => 'IC',
                                    default => null,
                                };

                                $query = \App\Models\AcademicTitle::where('student_id', $studentId);
                                if ($type) {
                                    $query->where('type', $type);
                                }

                                return $query->pluck('title', 'id');
                            })
                            ->live()
                            ->visible(function (Forms\Get $get) {
                                if (!in_array($get('document_type'), ['TCC', 'Objetivos de TCC', 'Iniciação Científica', 'Termo de Aceite de IC'])) {
                                    return false;
                                }
                                $studentId = auth()->user()->role === 'student'
                                    ? auth()->user()->student?->id
                                    : $get('student_id');
                                if (!$studentId) return false;
                                $type = match ($get('document_type')) {
                                    'TCC', 'Objetivos de TCC' => 'TCC',
                                    'Iniciação Científica', 'Termo de Aceite de IC' => 'IC',
                                    default => null,
                                };
                                $query = \App\Models\AcademicTitle::where('student_id', $studentId);
                                if ($type) $query->where('type', $type);
                                return $query->exists();
                            })
                            ->required(false)
                            ->disabled(fn ($record) => auth()->user()->role === 'student' && $record !== null)
                            ->dehydrated()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if ($state) {
                                    $titleText = \App\Models\AcademicTitle::find($state)?->title;
                                    if ($titleText) {
                                        $set('title', $titleText);
                                    }
                                }
                            }),
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->disabled(fn ($record) => auth()->user()->role === 'student' && $record !== null && !$record->canStudentEdit())
                            ->dehydrated()
                            ->maxLength(255)
                            ->label(fn (Forms\Get $get) => $get('document_type') === 'Concepção de TCC' ? 'Área ou Tema Geral Proposto' : 'Título ou Nome do Documento')
                            ->placeholder(fn (Forms\Get $get) => $get('document_type') === 'Concepção de TCC' ? 'Ex: Dermatologia Canina / Casos de Dermatofitose' : 'Título do seu documento...'),
                        Forms\Components\TextInput::make('metadata.advisor_suggested_title')
                            ->label('Sugestão de Alteração de Tema/Título pelo Orientador')
                            ->placeholder('Caso queira sugerir uma alteração de título/tema, digite aqui...')
                            ->disabled(fn () => auth()->user()->role === 'student')
                            ->dehydrated()
                            ->maxLength(255)
                            ->visible(fn (Forms\Get $get) => $get('document_type') === 'Concepção de TCC'),
                        Forms\Components\FileUpload::make('google_drive_file_id')
                            ->label(fn (Forms\Get $get) => match ($get('document_type')) {
                                'Avaliações de Estágio' => '📥 Upload das Avaliações (Enviado pelo Aluno)',
                                'Trabalho de Congresso' => '📥 Certificado de Apresentação (PDF)',
                                default => '📥 Documento do Aluno (Envio Original)',
                            })
                            ->extraAttributes(['class' => 'ring-1 ring-blue-400/50 dark:ring-blue-400/40 rounded-xl p-2 bg-blue-50/60 dark:bg-blue-950/20'])
                            ->formatStateUsing(function ($state) {
                                if (blank($state)) {
                                    return [];
                                }
                                if (is_string($state)) {
                                    if (str_starts_with($state, '[') && str_ends_with($state, ']')) {
                                        return json_decode($state, true) ?: [];
                                    }
                                    return [$state];
                                }
                                return is_array($state) ? $state : [$state];
                            })
                            ->dehydrateStateUsing(fn ($state) => is_array($state) ? $state : ($state ? [$state] : []))
                            ->disk(in_array(config('filesystems.default'), ['google', 'onedrive']) ? config('filesystems.default') : 'public')
                            ->directory('submissions')
                            ->downloadable()
                            ->openable()
                            ->acceptedFileTypes(function (Forms\Get $get) {
                                if ($get('document_type') === 'Avaliações de Estágio') {
                                    return ['application/pdf'];
                                }

                                $docxAllowed = [
                                    'TCC',
                                    'Iniciação Científica',
                                    'Relatório',
                                    'Relatório de Estágio',
                                    'Artigo Científico',
                                    'Relato de Caso',
                                    'Trabalho de Congresso',
                                    'Protocolo CEUA/CEP',
                                    'Outro',
                                    'Termo de Aceite de Orientação',
                                    'Plano de Estágio',
                                    'Termo de Aceite de IC',
                                    'Documento Solicitado',
                                ];

                                return in_array($get('document_type'), $docxAllowed)
                                    ? ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']
                                    : ['application/pdf'];
                            })
                            ->maxSize(10240) // 10MB limit
                            ->multiple(fn (Forms\Get $get) => $get('document_type') === 'Avaliações de Estágio')
                            ->maxFiles(fn (Forms\Get $get) => $get('document_type') === 'Avaliações de Estágio' ? 10 : 1)
                            ->disabled(fn ($record) => $record !== null && (
                                (auth()->user()->role === 'student' && !$record->canStudentEdit())
                                || auth()->user()->role !== 'student'
                            ))
                            ->visible(function ($record) {
                                if (!$record || auth()->user()->role !== 'student') {
                                    return true;
                                }
                                // Uma vez concluído com um arquivo assinado/retornado disponível, o
                                // arquivo original enviado pelo aluno para assinatura deixa de ficar
                                // disponível, evitando que ele baixe por engano a versão sem assinatura.
                                return !($record->isConcluded() && !empty($record->google_drive_returned_file_id));
                            })
                            ->dehydrated()
                             ->required(fn (Forms\Get $get, $record) => $record === null && !in_array($get('document_type'), ['Termo de Aceite de Orientação', 'Concepção de TCC', 'Objetivos de TCC', 'Documento Solicitado', 'Introdução de TCC', 'Materiais e Métodos de TCC', 'Resultados de TCC', 'Discussão de TCC', 'Conclusão de TCC']))
                            ->placeholder(fn (Forms\Get $get) => $get('document_type') === 'Avaliações de Estágio'
                                ? 'Arraste seus arquivos PDF das avaliações ou clique para selecionar...'
                                : (in_array($get('document_type'), ['Termo de Aceite de Orientação', 'Concepção de TCC', 'Objetivos de TCC', 'Documento Solicitado', 'Introdução de TCC', 'Materiais e Métodos de TCC', 'Resultados de TCC', 'Discussão de TCC', 'Conclusão de TCC'])
                                    ? 'Arraste seu arquivo PDF/DOCX ou clique para selecionar (opcional)...'
                                    : 'Arraste seu arquivo PDF/DOCX ou clique para selecionar...')
                            )
                            ->helperText(fn (Forms\Get $get) => $get('document_type') === 'Avaliações de Estágio'
                                ? 'Apenas arquivos PDF são permitidos. Limite total de 10MB.'
                                : ($get('document_type') === 'Trabalho de Congresso'
                                    ? 'Envie aqui o certificado de participação/apresentação emitido pelo evento. Ele só é válido se contiver o nome do orientador (Prof. Atilio) impresso.'
                                    : (in_array($get('document_type'), ['Termo de Aceite de Orientação', 'Concepção de TCC', 'Objetivos de TCC', 'Documento Solicitado', 'Introdução de TCC', 'Materiais e Métodos de TCC', 'Resultados de TCC', 'Discussão de TCC', 'Conclusão de TCC'])
                                        ? 'Opcional para Termo de Aceite, Concepção, Objetivos de TCC e Documento Solicitado. Este é o arquivo enviado pelo ALUNO — não é o campo do documento assinado.'
                                        : (in_array($get('document_type'), [
                                            'Relatório de Necropsia',
                                        ]) ? 'Apenas arquivos PDF são permitidos. Limite total de 10MB. Este é o arquivo enviado pelo ALUNO — não é o campo do documento assinado.' : 'Formatos permitidos: PDF, DOCX. Limite total de 10MB. Este é o arquivo enviado pelo ALUNO — não é o campo do documento assinado.')))
                            )
                            ->rule(function (Forms\Get $get) {
                                return function (string $attribute, $value, \Closure $fail) use ($get) {
                                    if ($get('document_type') === 'Avaliações de Estágio' && is_array($value)) {
                                        $totalSize = 0;
                                        foreach ($value as $file) {
                                            if ($file instanceof \Illuminate\Http\UploadedFile) {
                                                $totalSize += $file->getSize();
                                            }
                                        }
                                        if ($totalSize > 10 * 1024 * 1024) {
                                            $fail('O tamanho máximo total dos arquivos combinados é de 10MB.');
                                        }
                                    }
                                };
                            }),
                        Forms\Components\FileUpload::make('google_drive_returned_file_id')
                            ->label(fn ($record) => auth()->user()->role === 'student' && $record?->isConcluded() && !empty($record->google_drive_returned_file_id)
                                ? '✅ Documento Assinado Disponível — Baixe Aqui'
                                : 'Upload do Arquivo Retornado / Assinado')
                            ->formatStateUsing(function ($state) {
                                if (blank($state)) {
                                    return [];
                                }
                                if (is_string($state)) {
                                    if (str_starts_with($state, '[') && str_ends_with($state, ']')) {
                                        return json_decode($state, true) ?: [];
                                    }
                                    return [$state];
                                }
                                return is_array($state) ? $state : [$state];
                            })
                            ->dehydrateStateUsing(fn ($state) => is_array($state) ? $state : ($state ? [$state] : []))
                            ->disk(in_array(config('filesystems.default'), ['google', 'onedrive']) ? config('filesystems.default') : 'public')
                            ->directory('returns')
                            ->downloadable()
                            ->openable()
                            ->acceptedFileTypes(['application/pdf'])
                            ->maxSize(10240) // 10MB limit
                            ->disabled(fn () => auth()->user()->role === 'student')
                            ->dehydrated(fn () => auth()->user()->role === 'admin')
                            ->placeholder('Selecione ou arraste o PDF assinado/corrigido para o aluno...')
                            ->helperText(fn ($record) => auth()->user()->role === 'student' && $record?->isConcluded() && !empty($record->google_drive_returned_file_id)
                                ? 'Este é o documento final assinado digitalmente. É a versão que você deve guardar/usar.'
                                : 'Envie aqui o PDF assinado digitalmente ou corrigido. Ele será salvo diretamente na nuvem.')
                            ->extraAttributes(fn ($record) => auth()->user()->role === 'student' && $record?->isConcluded() && !empty($record->google_drive_returned_file_id)
                                ? ['class' => 'ring-2 ring-success-500 rounded-xl p-2 bg-success-50 dark:bg-success-950/20']
                                : [])
                            ->visible(fn ($record) => $record !== null && $record->status !== 'Aguardando Triagem'),
                        Forms\Components\TextInput::make('file_size_kb')
                            ->numeric()
                            ->default(0)
                            ->disabled(fn () => auth()->user()->role === 'student')
                            ->dehydrated()
                            ->label('Tamanho do Arquivo (KB)'),
                        Forms\Components\Select::make('status')
                            ->options(function (Forms\Get $get, $record) {
                                $currentStatus = $record?->status ?? 'Aguardando Triagem';
                                
                                if ($currentStatus === 'Aguardando Triagem') {
                                    return [
                                        'Aguardando Triagem' => 'Aguardando Triagem',
                                        'Habilitado' => 'Habilitar Processo',
                                        'Cancelado' => 'Cancelar Processo',
                                    ];
                                }
                                
                                $typeOptions = match ($get('document_type')) {
                                    'Concepção de TCC' => [
                                        'Aguardando Tema do Aluno' => 'Aguardando Tema do Aluno',
                                        'Aguardando Definição de Tema' => 'Aguardando Definição de Tema',
                                        'Aguardando Ajustes do Aluno' => 'Aguardando Ajustes do Aluno',
                                        'Processo Concluído - Tema Definido' => 'Processo Concluído - Tema Definido',
                                    ],
                                    'Objetivos de TCC' => [
                                        'Aguardando Objetivos do Aluno' => 'Aguardando Objetivos do Aluno',
                                        'Aguardando Correção dos Objetivos' => 'Aguardando Correção dos Objetivos',
                                        'Aguardando Ajustes do Aluno' => 'Aguardando Ajustes do Aluno',
                                        'Processo Concluído - Objetivos Aprovados' => 'Processo Concluído - Objetivos Aprovados',
                                    ],
                                    'Introdução de TCC' => [
                                        'Aguardando Introdução do Aluno' => 'Aguardando Introdução do Aluno',
                                        'Aguardando Correção da Introdução' => 'Aguardando Correção da Introdução',
                                        'Aguardando Ajustes do Aluno' => 'Aguardando Ajustes do Aluno',
                                        'Processo Concluído - Introdução Aprovada' => 'Processo Concluído - Introdução Aprovada',
                                    ],
                                    'Materiais e Métodos de TCC' => [
                                        'Aguardando Materiais e Métodos do Aluno' => 'Aguardando Materiais e Métodos do Aluno',
                                        'Aguardando Correção de Materiais e Métodos' => 'Aguardando Correção de Materiais e Métodos',
                                        'Aguardando Ajustes do Aluno' => 'Aguardando Ajustes do Aluno',
                                        'Processo Concluído - Materiais e Métodos Aprovados' => 'Processo Concluído - Materiais e Métodos Aprovados',
                                    ],
                                    'Resultados de TCC' => [
                                        'Aguardando Resultados do Aluno' => 'Aguardando Resultados do Aluno',
                                        'Aguardando Correção dos Resultados' => 'Aguardando Correção dos Resultados',
                                        'Aguardando Ajustes do Aluno' => 'Aguardando Ajustes do Aluno',
                                        'Processo Concluído - Resultados Aprovados' => 'Processo Concluído - Resultados Aprovados',
                                    ],
                                    'Discussão de TCC' => [
                                        'Aguardando Discussão do Aluno' => 'Aguardando Discussão do Aluno',
                                        'Aguardando Correção da Discussão' => 'Aguardando Correção da Discussão',
                                        'Aguardando Ajustes do Aluno' => 'Aguardando Ajustes do Aluno',
                                        'Processo Concluído - Discussão Aprovada' => 'Processo Concluído - Discussão Aprovada',
                                    ],
                                    'Conclusão de TCC' => [
                                        'Aguardando Conclusão do Aluno' => 'Aguardando Conclusão do Aluno',
                                        'Aguardando Correção da Conclusão' => 'Aguardando Correção da Conclusão',
                                        'Aguardando Ajustes do Aluno' => 'Aguardando Ajustes do Aluno',
                                        'Processo Concluído - Conclusão Aprovada' => 'Processo Concluído - Conclusão Aprovada',
                                    ],
                                    'TCC' => [
                                        'Aguardando Correção do Professor' => 'Aguardando Correção do Professor',
                                        'Aguardando Ajustes do Aluno' => 'Aguardando Ajustes do Aluno',
                                        'Em análise final para assinatura institucional' => 'Em análise final para assinatura institucional',
                                        'Processo Concluído - Documento Assinado Disponível' => 'Processo Concluído - Documento Assinado Disponível',
                                    ],
                                    'Iniciação Científica' => [
                                        'Projeto em Análise Inicial pelo Orientador' => 'Projeto em Análise Inicial pelo Orientador',
                                        'Aguardando Ajustes do Aluno' => 'Aguardando Ajustes do Aluno',
                                        'Processo Concluído - Projeto Arquivado' => 'Processo Concluído - Projeto Arquivado',
                                    ],
                                    'Relatório', 'Artigo Científico', 'Relato de Caso', 'Trabalho de Congresso', 'Relatório de Estágio', 'Relatório de Aula' => [
                                        'Aguardando Revisão do Professor' => 'Aguardando Revisão do Professor',
                                        'Aguardando Ajustes do Aluno' => 'Aguardando Ajustes do Aluno',
                                        'Aguardando Relatório Parcial' => 'Aguardando Relatório Parcial',
                                        'Processo Concluído - Versão Final Definida' => 'Processo Concluído - Versão Final Definida',
                                        'Processo Concluído - Documento Assinado Disponível' => 'Processo Concluído - Documento Assinado Disponível',
                                    ],
                                    'Plano de Estágio' => [
                                        'Aguardando Assinatura do Professor' => 'Aguardando Assinatura do Professor',
                                        'Aguardando Ajustes do Aluno' => 'Aguardando Ajustes do Aluno',
                                        'Processo Concluído - Documento Assinado Disponível' => 'Processo Concluído - Documento Assinado Disponível',
                                    ],
                                    'Termo de Aceite de Orientação' => [
                                        'Aguardando Assinatura do Professor' => 'Aguardando Assinatura do Professor',
                                        'Aguardando Ajustes do Aluno' => 'Aguardando Ajustes do Aluno',
                                        'Processo Concluído - Termo de Aceite Assinado' => 'Processo Concluído - Termo de Aceite Assinado',
                                    ],
                                    'Avaliações de Estágio' => [
                                        'Avaliações de Estágio Registradas e Recebidas' => 'Avaliações de Estágio Registradas e Recebidas',
                                        'Processo Concluído - Estágio Homologado com Sucesso' => 'Processo Concluído - Estágio Homologado com Sucesso',
                                    ],
                                    'Relatório de Necropsia' => [
                                        'Relatório de Necropsia Recebido - Aguardando Análise do Professor' => 'Relatório de Necropsia Recebido - Aguardando Análise do Professor',
                                        'Aguardando Ajustes no Laudo pelo Aluno' => 'Aguardando Ajustes no Laudo pelo Aluno',
                                        'Processo Concluído - Laudo de Necropsia Homologado' => 'Processo Concluído - Laudo de Necropsia Homologado',
                                    ],
                                    'Termo de Aceite de IC' => [
                                        'Termos Aceitos e Auditados - Aguardando Assinatura do Orientador' => 'Termos Aceitos e Auditados - Aguardando Assinatura do Orientador',
                                        'Processo Concluído - Orientação de IC Homologada e Ativa' => 'Processo Concluído - Orientação de IC Homologada e Ativa',
                                    ],
                                    'Documento Solicitado' => [
                                        'Aguardando Envio do Aluno' => 'Aguardando Envio do Aluno',
                                        'Aguardando Assinatura do Professor' => 'Aguardando Assinatura do Professor',
                                        'Aguardando Ajustes do Aluno' => 'Aguardando Ajustes do Aluno',
                                        'Processo Concluído - Documento Assinado Disponível' => 'Processo Concluído - Documento Assinado Disponível',
                                    ],
                                    default => [
                                        'pending' => 'Pendente',
                                        'reviewing' => 'Em Análise',
                                        'approved' => 'Aprovado',
                                        'needs_corrections' => 'Devolvido com Correções',
                                        'returned_signed' => 'Devolvido Assinado',
                                        'failed' => 'Rejeitado',
                                    ],
                                };
                                
                                $typeOptions['Cancelado'] = 'Cancelar Processo';

                                // Compatibilidade: processos já encerrados podem ter um status que
                                // deixou de ser oferecido (ex.: Termo de Aceite concluído como
                                // "Documento Assinado Disponível" antes de separarmos os fluxos).
                                // Preservamos o status atual do registro na lista para não quebrar
                                // a visualização/edição — sem oferecê-lo aos processos novos.
                                if ($record && ! array_key_exists($record->status, $typeOptions)) {
                                    $typeOptions[$record->status] = $record->status;
                                }

                                return $typeOptions;
                            })
                            ->required()
                            ->disabled(fn () => auth()->user()->role === 'student')
                            ->dehydrated()
                            ->default('Aguardando Triagem')
                            ->label('Status')
                            ->live(),
                        Forms\Components\Textarea::make('metadata.cancellation_reason')
                            ->label('Motivo do Cancelamento')
                            ->placeholder('Explique o motivo do cancelamento e sugira a abertura de um novo processo...')
                            ->required()
                            ->visible(fn (Forms\Get $get) => $get('status') === 'Cancelado')
                            ->columnSpan(2),
                        Forms\Components\Toggle::make('student_abnt_statement')
                            ->label('Aluno declarou conformidade ABNT')
                            ->disabled(fn () => auth()->user()->role === 'student')
                            ->dehydrated()
                            ->default(false),
                        Forms\Components\RichEditor::make('student_notes')
                            ->label('Observações do Aluno para o Orientador')
                            ->placeholder('Escreva aqui dúvidas, observações ou comentários para o orientador sobre esta submissão...')
                            ->toolbarButtons(['bold','italic','underline','bulletList','orderedList','blockquote','link','undo','redo'])
                            ->disabled(fn ($record) => auth()->user()->role !== 'student' || ($record !== null && !$record->canStudentEdit()))
                            ->dehydrated(fn () => auth()->user()->role === 'student')
                            ->columnSpan(2)
                            ->helperText(fn () => auth()->user()->role === 'student'
                                ? 'Seu comentário ficará visível apenas para o professor orientador.'
                                : null),
                    ])->columns(2),

                Forms\Components\Section::make('Barreira de Autoverificação Pré-Envio (Gatekeeper)')
                    ->visible(fn (Forms\Get $get, $record) => $record === null && in_array($get('document_type'), [
                        'TCC',
                        'Iniciação Científica',
                        'Relatório',
                        'Relatório de Estágio',
                        'Artigo Científico',
                        'Relato de Caso',
                        'Trabalho de Congresso',
                        'Relatório de Necropsia'
                    ]))
                    ->schema([
                        Forms\Components\Checkbox::make('gatekeeper_abnt')
                            ->label('Confirmo que revisei todas as citações entre parênteses e nenhuma está em caixa alta, seguindo a ABNT NBR 10520:2023.')
                            ->accepted()
                            ->required()
                            ->dehydrated(false)
                            ->default(false),
                        Forms\Components\Checkbox::make('gatekeeper_nomenclature')
                            ->label('Confirmo que não utilizei o termo "tutor" em nenhuma parte do texto, aplicando o termo legal "responsável legal".')
                            ->accepted()
                            ->required()
                            ->dehydrated(false)
                            ->default(false),
                        Forms\Components\Checkbox::make('gatekeeper_file')
                            ->label('Confirmo que o arquivo está em formato PDF e não ultrapassa o limite de 10MB.')
                            ->accepted()
                            ->required()
                            ->dehydrated(false)
                            ->default(false),
                    ])
                    ->columns(1),

                Forms\Components\Section::make('Preenchimento Automático do Termo de Aceite')
                    ->visible(fn (Forms\Get $get) => $get('document_type') === 'Termo de Aceite de Orientação')
                    ->schema([
                        Forms\Components\Select::make('metadata.orientation_level')
                            ->label('Nível da Orientação')
                            ->options([
                                'Graduação' => 'Graduação (TCC)',
                                'Especialização' => 'Especialização',
                                'Mestrado' => 'Mestrado',
                                'Doutorado' => 'Doutorado',
                            ])
                            ->required(fn (Forms\Get $get) => $get('document_type') === 'Termo de Aceite de Orientação' && $get('status') !== 'Cancelado')
                            ->default('Graduação'),
                        Forms\Components\TextInput::make('metadata.orientation_topic')
                            ->label('Tema / Título do Trabalho de Pesquisa')
                            ->required(fn (Forms\Get $get) => $get('document_type') === 'Termo de Aceite de Orientação' && $get('status') !== 'Cancelado')
                            ->placeholder('Ex: Análise Epidemiológica de Zoonoses...'),
                        Forms\Components\DatePicker::make('metadata.orientation_start_date')
                            ->label('Data de Início')
                            ->required(fn (Forms\Get $get) => $get('document_type') === 'Termo de Aceite de Orientação' && $get('status') !== 'Cancelado')
                            ->default(now()->format('Y-m-d')),
                        Forms\Components\DatePicker::make('metadata.orientation_end_date')
                            ->label('Data de Término Prevista')
                            ->required(fn (Forms\Get $get) => $get('document_type') === 'Termo de Aceite de Orientação' && $get('status') !== 'Cancelado')
                            ->default(now()->addYear()->format('Y-m-d')),
                        Forms\Components\TextInput::make('metadata.orientation_institution')
                            ->label('Instituição')
                            ->required(fn (Forms\Get $get) => $get('document_type') === 'Termo de Aceite de Orientação' && $get('status') !== 'Cancelado')
                            ->default(function (Forms\Get $get) {
                                $studentId = $get('student_id') ?: (\App\Models\Student::where('user_id', auth()->id())->first()?->id);
                                if ($studentId) {
                                    return \App\Models\Student::find($studentId)?->institution;
                                }
                                return null;
                            }),
                        Forms\Components\TextInput::make('metadata.orientation_campus')
                            ->label('Campus')
                            ->required(fn (Forms\Get $get) => $get('document_type') === 'Termo de Aceite de Orientação' && $get('status') !== 'Cancelado')
                            ->default(function (Forms\Get $get) {
                                $studentId = $get('student_id') ?: (\App\Models\Student::where('user_id', auth()->id())->first()?->id);
                                if ($studentId) {
                                    return \App\Models\Student::find($studentId)?->campus;
                                }
                                return null;
                            }),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Metadados Específicos do Fluxo')
                    ->visible(fn (Forms\Get $get) => in_array($get('document_type'), [
                        'TCC',
                        'Avaliações de Estágio',
                        'Relatório de Necropsia',
                        'Termo de Aceite de IC',
                        'Artigo Científico',
                        'Concepção de TCC',
                        'Objetivos de TCC',
                    ]))
                    ->schema([
                        // Objetivos de TCC specific fields
                        Forms\Components\Textarea::make('metadata.tcc_general_objective')
                            ->required(fn (Forms\Get $get) => $get('document_type') === 'Objetivos de TCC' && $get('status') !== 'Cancelado')
                            ->label('Objetivo Geral')
                            ->placeholder('Escreva aqui o objetivo geral do seu TCC (o que você pretende alcançar de forma geral)...')
                            ->rows(3)
                            ->visible(fn (Forms\Get $get) => $get('document_type') === 'Objetivos de TCC')
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('metadata.tcc_specific_objectives')
                            ->required(fn (Forms\Get $get) => $get('document_type') === 'Objetivos de TCC' && $get('status') !== 'Cancelado')
                            ->label('Objetivos Específicos')
                            ->placeholder('Escreva aqui os objetivos específicos, um por linha ou numerados (as etapas para atingir o objetivo geral)...')
                            ->rows(5)
                            ->visible(fn (Forms\Get $get) => $get('document_type') === 'Objetivos de TCC')
                            ->columnSpanFull(),
                        Forms\Components\Select::make('metadata.tcc_conception_area')
                            ->options([
                                'Clínica Médica' => 'Clínica Médica',
                                'Clínica Cirúrgica' => 'Clínica Cirúrgica',
                                'Patologia Veterinária' => 'Patologia Veterinária / Diagnóstico',
                                'Patologia Clínica' => 'Patologia Clínica / Exames Laboratoriais',
                                'Anestesiologia' => 'Anestesiologia / Farmacologia',
                                'Diagnóstico por Imagem' => 'Diagnóstico por Imagem',
                                'Outro' => 'Outro Tema / Campo'
                            ])
                            ->required(fn (Forms\Get $get) => $get('document_type') === 'Concepção de TCC' && !in_array($get('status'), ['Cancelado', 'Aguardando Ajustes do Aluno']))
                            ->label('Área de Interesse Principal')
                            ->visible(fn (Forms\Get $get) => $get('document_type') === 'Concepção de TCC'),
                        Forms\Components\TextInput::make('metadata.tcc_conception_species')
                            ->required(fn (Forms\Get $get) => $get('document_type') === 'Concepção de TCC' && !in_array($get('status'), ['Cancelado', 'Aguardando Ajustes do Aluno']))
                            ->label('Espécie(s) Animal de Interesse')
                            ->placeholder('Ex: Pequenos Animais (Cães/Gatos), Equinos, Bovinos, Animais Silvestres...')
                            ->visible(fn (Forms\Get $get) => $get('document_type') === 'Concepção de TCC'),
                        Forms\Components\Select::make('metadata.tcc_conception_type')
                            ->options([
                                'Revisão de Literatura' => 'Revisão de Literatura (Artigo de Revisão)',
                                'Relato de Caso' => 'Relato de Caso Clínico',
                                'Estudo Retrospectivo' => 'Estudo Estatístico / Retrospectivo',
                                'Experimental' => 'Estudo Experimental / Campo'
                            ])
                            ->required(fn (Forms\Get $get) => $get('document_type') === 'Concepção de TCC' && !in_array($get('status'), ['Cancelado', 'Aguardando Ajustes do Aluno']))
                            ->label('Tipo de Estudo Preferido')
                            ->visible(fn (Forms\Get $get) => $get('document_type') === 'Concepção de TCC'),
                        Forms\Components\Textarea::make('metadata.tcc_conception_ideas')
                            ->required(fn (Forms\Get $get) => $get('document_type') === 'Concepção de TCC' && !in_array($get('status'), ['Cancelado', 'Aguardando Ajustes do Aluno']))
                            ->label('Ideias Gerais / Assuntos que gostaria de abordar')
                            ->placeholder('Escreva brevemente o que você tem em mente (Ex: gostaria de descrever um caso de tumor específico ou revisar a literatura sobre certa terapia)...')
                            ->rows(4)
                            ->visible(fn (Forms\Get $get) => $get('document_type') === 'Concepção de TCC')
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('metadata.tcc_conception_questions')
                            ->label('Perguntas / Dúvidas principais para o Orientador')
                            ->placeholder('Opcional: se você tiver alguma pergunta específica para o orientador ajudar a delimitar o tema, escreva aqui...')
                            ->rows(3)
                            ->visible(fn (Forms\Get $get) => $get('document_type') === 'Concepção de TCC')
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('metadata.target_journal')
                            ->required(fn (Forms\Get $get) => $get('document_type') === 'Artigo Científico' && $get('status') !== 'Cancelado')
                            ->label('Revista de Envio / Destino')
                            ->placeholder('Ex: Revista Brasileira de Medicina Veterinária')
                            ->visible(fn (Forms\Get $get) => $get('document_type') === 'Artigo Científico'),
                        Forms\Components\TextInput::make('metadata.journal_formatting_url')
                            ->url()
                            ->required(fn (Forms\Get $get) => $get('document_type') === 'Artigo Científico' && $get('status') !== 'Cancelado')
                            ->label('Link das Normas de Formatação da Revista')
                            ->placeholder('Ex: https://www.revista.com.br/normas')
                            ->visible(fn (Forms\Get $get) => $get('document_type') === 'Artigo Científico'),

                        Forms\Components\Select::make('metadata.tcc_stage')
                            ->options([
                                'Parcial' => 'Parcial',
                                'Final' => 'Final',
                            ])
                            ->required(fn (Forms\Get $get) => $get('document_type') === 'TCC' && $get('status') !== 'Cancelado')
                            ->label('Etapa do TCC')
                            ->visible(fn (Forms\Get $get) => $get('document_type') === 'TCC'),
                        
                        Forms\Components\TextInput::make('metadata.campus')
                            ->required(fn (Forms\Get $get) => in_array($get('document_type'), ['TCC', 'Concepção de TCC']) && !in_array($get('status'), ['Cancelado', 'Aguardando Ajustes do Aluno']))
                            ->label('Campus')
                            ->placeholder('Ex: Butantã')
                            ->default(fn () => auth()->user()?->student?->campus)
                            ->visible(fn (Forms\Get $get) => in_array($get('document_type'), ['TCC', 'Concepção de TCC'])),
                            
                        Forms\Components\Select::make('metadata.period')
                            ->options([
                                'Matutino' => 'Matutino',
                                'Vespertino' => 'Vespertino',
                                'Noturno' => 'Noturno',
                            ])
                            ->required(fn (Forms\Get $get) => in_array($get('document_type'), ['TCC', 'Concepção de TCC']) && !in_array($get('status'), ['Cancelado', 'Aguardando Ajustes do Aluno']))
                            ->label('Período')
                            ->default(fn () => auth()->user()?->student?->period)
                            ->visible(fn (Forms\Get $get) => in_array($get('document_type'), ['TCC', 'Concepção de TCC'])),
                        
                        Forms\Components\TextInput::make('metadata.locations_count')
                            ->numeric()
                            ->required(fn (Forms\Get $get) => $get('document_type') === 'Avaliações de Estágio' && $get('status') !== 'Cancelado')
                            ->label('Quantidade de Locais de Estágio')
                            ->visible(fn (Forms\Get $get) => $get('document_type') === 'Avaliações de Estágio'),
                        Forms\Components\TextInput::make('metadata.total_hours')
                            ->numeric()
                            ->required(fn (Forms\Get $get) => $get('document_type') === 'Avaliações de Estágio' && $get('status') !== 'Cancelado')
                            ->label('Carga Horária Total (horas)')
                            ->visible(fn (Forms\Get $get) => $get('document_type') === 'Avaliações de Estágio'),
                        Forms\Components\TextInput::make('metadata.final_grade')
                            ->numeric()
                            ->required(fn (Forms\Get $get) => $get('document_type') === 'Avaliações de Estágio' && $get('status') !== 'Cancelado')
                            ->label('Nota Final')
                            ->visible(fn (Forms\Get $get) => $get('document_type') === 'Avaliações de Estágio'),

                        Forms\Components\TextInput::make('metadata.animal_name')
                            ->default('Não identificado')
                            ->required(fn (Forms\Get $get) => $get('document_type') === 'Relatório de Necropsia' && $get('status') !== 'Cancelado')
                            ->label('Nome do Animal')
                            ->visible(fn (Forms\Get $get) => $get('document_type') === 'Relatório de Necropsia'),
                        Forms\Components\TextInput::make('metadata.necropsy_protocol')
                            ->required(fn (Forms\Get $get) => $get('document_type') === 'Relatório de Necropsia' && $get('status') !== 'Cancelado')
                            ->label('Número do Registro/Protocolo')
                            ->visible(fn (Forms\Get $get) => $get('document_type') === 'Relatório de Necropsia'),
                        Forms\Components\DatePicker::make('metadata.necropsy_date')
                            ->required(fn (Forms\Get $get) => $get('document_type') === 'Relatório de Necropsia' && $get('status') !== 'Cancelado')
                            ->label('Data da Necropsia')
                            ->visible(fn (Forms\Get $get) => $get('document_type') === 'Relatório de Necropsia'),

                        Forms\Components\TextInput::make('metadata.ic_project_title')
                            ->label('Título do Projeto')
                            ->required(fn (Forms\Get $get) => $get('document_type') === 'Termo de Aceite de IC')
                            ->default(fn () => auth()->user()->student?->research_topic ?? '')
                            ->visible(fn (Forms\Get $get) => $get('document_type') === 'Termo de Aceite de IC'),
                        Forms\Components\Select::make('metadata.ic_scholarship_type')
                            ->options([
                                'FAPESP' => 'FAPESP',
                                'CNPq' => 'CNPq',
                                'PIBIC' => 'PIBIC',
                                'Voluntária' => 'Voluntária',
                                'Outra' => 'Outra',
                            ])
                            ->required(fn (Forms\Get $get) => $get('document_type') === 'Termo de Aceite de IC')
                            ->label('Tipo de Bolsa')
                            ->visible(fn (Forms\Get $get) => $get('document_type') === 'Termo de Aceite de IC'),
                        Forms\Components\DatePicker::make('metadata.ic_start_date')
                            ->required(fn (Forms\Get $get) => $get('document_type') === 'Termo de Aceite de IC')
                            ->label('Vigência - Início')
                            ->visible(fn (Forms\Get $get) => $get('document_type') === 'Termo de Aceite de IC'),
                        Forms\Components\DatePicker::make('metadata.ic_end_date')
                            ->required(fn (Forms\Get $get) => $get('document_type') === 'Termo de Aceite de IC')
                            ->label('Vigência - Término')
                            ->visible(fn (Forms\Get $get) => $get('document_type') === 'Termo de Aceite de IC'),
                        
                        // 4 Mandatory Checks
                        Forms\Components\Checkbox::make('metadata.ic_check_1')
                            ->label('Entrega obrigatória de relatórios parciais a cada 3 meses sob pena de encerramento imediato.')
                            ->accepted()
                            ->required()
                            ->visible(fn (Forms\Get $get) => $get('document_type') === 'Termo de Aceite de IC'),
                        Forms\Components\Checkbox::make('metadata.ic_check_2')
                            ->label('Compromisso de entrega de resultados em forma de artigo, relatório final ou resumo (Padrão FAPESP), sob pena de bloqueio de certificados.')
                            ->accepted()
                            ->required()
                            ->visible(fn (Forms\Get $get) => $get('document_type') === 'Termo de Aceite de IC'),
                        Forms\Components\Checkbox::make('metadata.ic_check_3')
                            ->label('Propriedade e exclusividade dos dados brutos pertencentes ao laboratório do Prof. Atilio.')
                            ->accepted()
                            ->required()
                            ->visible(fn (Forms\Get $get) => $get('document_type') === 'Termo de Aceite de IC'),
                        Forms\Components\Checkbox::make('metadata.ic_check_4')
                            ->label('Cumprimento estrito das Boas Práticas Científicas contra plágio e falsificação de dados.')
                            ->accepted()
                            ->required()
                            ->visible(fn (Forms\Get $get) => $get('document_type') === 'Termo de Aceite de IC'),
                    ])->columns(2),

                Forms\Components\Section::make('Conteúdo da Etapa do TCC')
                    ->icon('heroicon-o-pencil-square')
                    ->description('Escreva o texto desta seção do TCC. Se preferir, anexe também um arquivo formatado no campo de upload acima (opcional).')
                    ->visible(fn (Forms\Get $get) => in_array($get('document_type'), [
                        'Introdução de TCC',
                        'Materiais e Métodos de TCC',
                        'Resultados de TCC',
                        'Discussão de TCC',
                        'Conclusão de TCC',
                    ]))
                    ->schema([
                        Forms\Components\RichEditor::make('metadata.tcc_stage_text')
                            ->label(fn (Forms\Get $get) => match ($get('document_type')) {
                                'Introdução de TCC' => 'Texto da Introdução',
                                'Materiais e Métodos de TCC' => 'Texto de Materiais e Métodos',
                                'Resultados de TCC' => 'Texto dos Resultados',
                                'Discussão de TCC' => 'Texto da Discussão',
                                'Conclusão de TCC' => 'Texto da Conclusão',
                                default => 'Texto da Etapa',
                            })
                            ->required(fn (Forms\Get $get) => $get('status') !== 'Cancelado')
                            ->disabled(fn ($record) => $record !== null && (
                                (auth()->user()->role === 'student' && !$record->canStudentEdit())
                                || auth()->user()->role !== 'student'
                            ))
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Detalhes da Apresentação em Evento')
                    ->icon('heroicon-o-presentation-chart-line')
                    ->description('Preencha os dados do trabalho apresentado e anexe o resumo (e os anais, se existirem). O certificado do evento é enviado no campo "Certificado de Apresentação (PDF)" acima.')
                    ->visible(fn (Forms\Get $get) => $get('document_type') === 'Trabalho de Congresso')
                    ->schema([
                        Forms\Components\TextInput::make('metadata.event_name')
                            ->label('Nome do Evento')
                            ->placeholder('Ex: Congresso Brasileiro de Medicina Veterinária')
                            ->required(fn (Forms\Get $get) => $get('document_type') === 'Trabalho de Congresso' && $get('status') !== 'Cancelado')
                            ->disabled(fn ($record) => $record !== null && (
                                (auth()->user()->role === 'student' && !$record->canStudentEdit())
                                || auth()->user()->role !== 'student'
                            )),
                        Forms\Components\Select::make('metadata.presentation_format')
                            ->label('Forma de Apresentação')
                            ->options([
                                'Oral' => 'Oral',
                                'Pôster' => 'Pôster',
                                'Resumo' => 'Resumo',
                                'Resumo Expandido' => 'Resumo Expandido',
                                'Outra' => 'Outra',
                            ])
                            ->required(fn (Forms\Get $get) => $get('document_type') === 'Trabalho de Congresso' && $get('status') !== 'Cancelado')
                            ->disabled(fn ($record) => $record !== null && (
                                (auth()->user()->role === 'student' && !$record->canStudentEdit())
                                || auth()->user()->role !== 'student'
                            )),
                        Forms\Components\TextInput::make('metadata.presentation_year')
                            ->label('Ano')
                            ->numeric()
                            ->minLength(4)
                            ->maxLength(4)
                            ->placeholder('Ex: 2026')
                            ->required(fn (Forms\Get $get) => $get('document_type') === 'Trabalho de Congresso' && $get('status') !== 'Cancelado')
                            ->disabled(fn ($record) => $record !== null && (
                                (auth()->user()->role === 'student' && !$record->canStudentEdit())
                                || auth()->user()->role !== 'student'
                            )),
                        Forms\Components\TextInput::make('metadata.event_location')
                            ->label('Local do Evento')
                            ->placeholder('Ex: São Paulo/SP')
                            ->required(fn (Forms\Get $get) => $get('document_type') === 'Trabalho de Congresso' && $get('status') !== 'Cancelado')
                            ->disabled(fn ($record) => $record !== null && (
                                (auth()->user()->role === 'student' && !$record->canStudentEdit())
                                || auth()->user()->role !== 'student'
                            )),
                        Forms\Components\FileUpload::make('metadata.abstract_pdf_path')
                            ->label('PDF do Resumo Apresentado')
                            ->disk(in_array(config('filesystems.default'), ['google', 'onedrive']) ? config('filesystems.default') : 'public')
                            ->directory('submissions')
                            ->downloadable()
                            ->openable()
                            ->acceptedFileTypes(['application/pdf'])
                            ->maxSize(10240)
                            ->required(fn (Forms\Get $get) => $get('document_type') === 'Trabalho de Congresso' && $get('status') !== 'Cancelado')
                            ->disabled(fn ($record) => $record !== null && (
                                (auth()->user()->role === 'student' && !$record->canStudentEdit())
                                || auth()->user()->role !== 'student'
                            )),
                        Forms\Components\FileUpload::make('metadata.annals_pdf_path')
                            ->label('PDF dos Anais do Evento (opcional, se contiver o resumo)')
                            ->disk(in_array(config('filesystems.default'), ['google', 'onedrive']) ? config('filesystems.default') : 'public')
                            ->directory('submissions')
                            ->downloadable()
                            ->openable()
                            ->acceptedFileTypes(['application/pdf'])
                            ->maxSize(10240)
                            ->disabled(fn ($record) => $record !== null && (
                                (auth()->user()->role === 'student' && !$record->canStudentEdit())
                                || auth()->user()->role !== 'student'
                            )),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Assinatura & Protocolo Digital (Forense)')
                    ->visible(fn (Forms\Get $get, $record) => $get('document_type') === 'Termo de Aceite de IC' && $record !== null)
                    ->schema([
                        Forms\Components\Placeholder::make('forensic_hash')
                            ->label('Assinatura Digital (SHA-256 Hash)')
                            ->content(fn ($record) => $record?->metadata['forensic_hash'] ?? 'Não gerado'),
                        Forms\Components\Placeholder::make('forensic_ip')
                            ->label('IP de Origem')
                            ->content(fn ($record) => $record?->metadata['forensic_ip'] ?? 'N/A'),
                        Forms\Components\Placeholder::make('forensic_timestamp')
                            ->label('Timestamp')
                            ->content(fn ($record) => $record?->metadata['forensic_timestamp'] ?? 'N/A'),
                        Forms\Components\Placeholder::make('forensic_user_agent')
                            ->label('User-Agent')
                            ->content(fn ($record) => $record?->metadata['forensic_user_agent'] ?? 'N/A'),
                    ])->columns(2),

                Forms\Components\Section::make('Histórico do Processo')
                    ->icon('heroicon-o-clock')
                    ->collapsible()
                    ->collapsed(false)
                    ->visible(fn ($record) => $record !== null)
                    ->schema([
                        Forms\Components\Placeholder::make('history_timeline')
                            ->label('')
                            ->content(function ($record): \Illuminate\Support\HtmlString {
                                $entries = array_reverse($record->metadata['history'] ?? []);

                                if (empty($entries)) {
                                    return new \Illuminate\Support\HtmlString(
                                        '<p class="text-sm text-slate-400 italic">Nenhum histórico registrado ainda.</p>'
                                    );
                                }

                                $isStudent = auth()->user()?->role === 'student';
                                $total     = count($entries);
                                $html      = '<div class="space-y-0 py-1">';

                                $statusLabels = [
                                    'pending'           => 'Aguardando Auditoria de IA',
                                    'reviewing'         => 'Em Revisão pelo Professor',
                                    'approved'          => 'Aprovado pelo Orientador',
                                    'returned_signed'   => 'Devolvido com Assinatura',
                                    'needs_corrections' => 'Devolvido para Correções',
                                    'failed'            => 'Falha no Processamento',
                                    'draft'             => 'Rascunho',
                                ];

                                foreach ($entries as $i => $entry) {
                                    $rawStatus = $entry['status'] ?? '—';
                                    $status    = $statusLabels[$rawStatus] ?? $rawStatus;
                                    $timestamp = $entry['timestamp']   ?? '—';
                                    $actor     = $entry['actor']       ?? 'Sistema';
                                    $comment   = $entry['comments']    ?? null;
                                    $isLast    = ($i === $total - 1);
                                    $isFirst   = ($i === 0);

                                    // dot colour
                                    if (str_starts_with($rawStatus, 'Processo Concluído') || $rawStatus === 'approved' || $rawStatus === 'returned_signed') {
                                        $dot = 'bg-green-500 ring-2 ring-green-500/25';
                                    } elseif ($rawStatus === 'needs_corrections' || str_contains($rawStatus, 'Ajustes') || str_contains($rawStatus, 'Correção')) {
                                        $dot = 'bg-amber-500 ring-2 ring-amber-500/25';
                                    } elseif ($rawStatus === 'failed') {
                                        $dot = 'bg-red-500 ring-2 ring-red-500/25';
                                    } elseif ($isFirst) {
                                        $dot = 'bg-teal-500 ring-2 ring-teal-500/25';
                                    } else {
                                        $dot = 'bg-slate-500';
                                    }

                                    $html .= '<div class="relative flex gap-4">';
                                    // connector column
                                    $html .= '<div class="flex flex-col items-center w-5 flex-shrink-0">';
                                    $html .= '<div class="w-3 h-3 rounded-full ' . $dot . ' mt-0.5 z-10 flex-shrink-0"></div>';
                                    if (!$isLast) {
                                        $html .= '<div class="w-px flex-1 bg-slate-700/60 mt-1 mb-0"></div>';
                                    }
                                    $html .= '</div>';
                                    // content
                                    $html .= '<div class="pb-5 flex-1 min-w-0">';
                                    $html .= '<div class="flex items-start justify-between gap-3 flex-wrap">';
                                    $html .= '<p class="text-sm font-semibold text-slate-100 leading-snug">' . e($status) . '</p>';
                                    $html .= '<span class="text-xs text-slate-500 font-mono whitespace-nowrap">' . e($timestamp) . '</span>';
                                    $html .= '</div>';
                                    if (!$isStudent) {
                                        $html .= '<p class="text-xs text-slate-400 mt-0.5">por <span class="text-slate-300">' . e($actor) . '</span></p>';
                                    }
                                    if ($comment) {
                                        // Comentário pode ser HTML (RichEditor) — renderiza sem escapar
                                        $html .= '<div class="mt-2 p-2.5 rounded-lg bg-slate-900/70 border border-white/5 text-xs text-slate-300 leading-relaxed [&_strong]:text-white [&_em]:italic [&_ul]:list-disc [&_ul]:pl-4 [&_ol]:list-decimal [&_ol]:pl-4 [&_a]:text-teal-400 [&_a]:underline">' . $comment . '</div>';
                                    }
                                    $html .= '</div>';
                                    $html .= '</div>';
                                }

                                $html .= '</div>';
                                return new \Illuminate\Support\HtmlString($html);
                            }),
                    ]),


                Forms\Components\Section::make('Avaliação / Parecer do Orientador')
                    ->visible(fn ($record) => $record !== null && $record->status !== 'Aguardando Triagem')
                    ->schema([
                        Forms\Components\RichEditor::make('ai_executive_summary')
                            ->label('Parecer do Orientador')
                            ->toolbarButtons(['bold','italic','underline','strike','link','blockquote','bulletList','orderedList','h3','undo','redo'])
                            ->placeholder('Escreva aqui as observações gerais, correções de conteúdo ou anotações...')
                            ->disabled(fn () => auth()->user()->role === 'student')
                            ->dehydrated(fn () => auth()->user()->role === 'admin')
                            ->visible(fn ($record) => auth()->user()->role === 'admin' || !empty($record?->ai_executive_summary))
                            ->helperText(fn ($record) => auth()->user()->role === 'student' && !empty($record?->ai_executive_summary)
                                ? 'Parecer do orientador sobre esta submissão.'
                                : null),
                        Forms\Components\Repeater::make('ai_abnt_feedback_json')
                            ->label('Pontos de Correção Especificados (ABNT / Conteúdo)')
                            ->schema([
                                Forms\Components\TextInput::make('citation')
                                    ->label('Trecho ou Citação')
                                    ->placeholder('Ex: (SILVA, 2020)'),
                                Forms\Components\TextInput::make('issue')
                                    ->label('Inconformidade')
                                    ->placeholder('Ex: Sobrenome em caixa alta dentro de parênteses'),
                                Forms\Components\TextInput::make('suggestion')
                                    ->label('Sugestão de Ajuste')
                                    ->placeholder('Ex: Alterar para (Silva, 2020)'),
                            ])
                            ->columns(3)
                            ->itemLabel(fn (array $state): ?string => $state['citation'] ?? null)
                            ->disabled(fn () => auth()->user()->role === 'student')
                            ->dehydrated(fn () => auth()->user()->role === 'admin'),
                        Forms\Components\Placeholder::make('abnt_verified_at_display')
                            ->label('Data da Última Avaliação')
                            ->content(fn ($record) => $record?->abnt_verified_at
                                ? $record->abnt_verified_at->timezone('America/Sao_Paulo')->format('d/m/Y \à\s H:i:s')
                                : new \Illuminate\Support\HtmlString('<span class="text-slate-400 italic text-sm">Preenchida automaticamente ao despachar</span>'))
                            ->visible(fn ($record) => $record !== null),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        $table = $table
            ->columns([
                Tables\Columns\TextColumn::make('process_id')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->label('ID do Processo'),
                Tables\Columns\TextColumn::make('student.user.name')
                    ->searchable()
                    ->sortable()
                    ->label('Aluno')
                    ->formatStateUsing(function ($state) {
                        if (auth()->user()->role === 'student' && $state) {
                            $parts = explode(' ', trim($state));
                            return count($parts) > 1 ? $parts[0] . ' ' . end($parts) : $state;
                        }
                        return $state;
                    }),
                Tables\Columns\TextColumn::make('document_type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'TCC', 'Introdução de TCC', 'Materiais e Métodos de TCC', 'Resultados de TCC', 'Discussão de TCC', 'Conclusão de TCC' => 'primary',
                        'Iniciação Científica', 'Termo de Aceite de IC', 'Protocolo CEUA/CEP' => 'warning',
                        'Relatório', 'Artigo Científico', 'Relato de Caso', 'Trabalho de Congresso' => 'info',
                        'Plano de Estágio', 'Termo de Aceite de Orientação', 'Avaliações de Estágio', 'Relatório de Necropsia', 'Relatório de Estágio', 'Documento Solicitado' => 'success',
                        default => 'gray',
                    })
                    ->sortable()
                    ->label('Tipo'),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->limit(30)
                    ->label('Título/Nome'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved', 'returned_signed', 
                        'Processo Concluído - Documento Assinado Disponível', 
                        'Processo Concluído - Projeto Arquivado', 
                        'Processo Concluído - Versão Final Definida', 
                        'Processo Concluído - Termo de Aceite Assinado', 
                        'Processo Concluído - Estágio Homologado com Sucesso', 
                        'Processo Concluído - Laudo de Necropsia Homologado', 
                        'Processo Concluído - Orientação de IC Homologada e Ativa',
                        'Processo Concluído - Tema Definido',
                        'Processo Concluído - Objetivos Aprovados',
                        'Processo Concluído - Introdução Aprovada',
                        'Processo Concluído - Materiais e Métodos Aprovados',
                        'Processo Concluído - Resultados Aprovados',
                        'Processo Concluído - Discussão Aprovada',
                        'Processo Concluído - Conclusão Aprovada' => 'success',
                        
                        'pending', 
                        'Aguardando Correção do Professor', 
                        'Projeto em Análise Inicial pelo Orientador', 
                        'Aguardando Revisão do Professor', 
                        'Aguardando Assinatura do Professor', 
                        'Avaliações de Estágio Registradas e Recebidas', 
                        'Relatório de Necropsia Recebido - Aguardando Análise do Professor', 
                        'Termos Aceitos e Auditados - Aguardando Assinatura do Orientador',
                        'Aguardando Relatório Parcial',
                        'Aguardando Envio do Aluno' => 'warning',
                        
                        'reviewing', 
                        'Em análise final para assinatura institucional' => 'info',
                        
                        'needs_corrections',
                        'Aguardando Ajustes do Aluno',
                        'Aguardando Ajustes no Laudo pelo Aluno',
                        'Aguardando Definição de Tema',
                        'Aguardando Tema do Aluno',
                        'Aguardando Objetivos do Aluno',
                        'Aguardando Correção dos Objetivos',
                        'Aguardando Introdução do Aluno',
                        'Aguardando Correção da Introdução',
                        'Aguardando Materiais e Métodos do Aluno',
                        'Aguardando Correção de Materiais e Métodos',
                        'Aguardando Resultados do Aluno',
                        'Aguardando Correção dos Resultados',
                        'Aguardando Discussão do Aluno',
                        'Aguardando Correção da Discussão',
                        'Aguardando Conclusão do Aluno',
                        'Aguardando Correção da Conclusão' => 'danger',
                        
                        'failed' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Pendente',
                        'reviewing' => 'Em Análise',
                        'approved' => 'Aprovado',
                        'needs_corrections' => 'Devolvido com Correções',
                        'returned_signed' => 'Devolvido Assinado',
                        'failed' => 'Rejeitado',
                        default => $state,
                    })
                    ->label('Status'),
                Tables\Columns\TextColumn::make('google_drive_file_id')
                    ->label('Enviado')
                    ->formatStateUsing(function ($state, $record) {
                        if (!$state) {
                            return null;
                        }
                        // Uma vez concluído com assinatura disponível, oculta para o aluno o
                        // arquivo original (não assinado) que ele enviou.
                        if (auth()->user()->role === 'student' && $record->isConcluded() && !empty($record->google_drive_returned_file_id)) {
                            return null;
                        }
                        return 'Abrir';
                    })
                    ->icon('heroicon-o-document')
                    ->url(function ($record) {
                        if (!$record->google_drive_file_id) {
                            return null;
                        }
                        if (auth()->user()->role === 'student' && $record->isConcluded() && !empty($record->google_drive_returned_file_id)) {
                            return null;
                        }
                        return is_string($record->google_drive_file_id) && str_contains($record->google_drive_file_id, 'http')
                            ? $record->google_drive_file_id
                            : route('documento.download', ['id' => $record->id, 'type' => 'original']);
                    }, true),
                Tables\Columns\TextColumn::make('google_drive_returned_file_id')
                    ->label(fn ($record) => $record && $record->isConcluded() && !empty($record->google_drive_returned_file_id) ? '✅ Assinado' : 'Retornado / Assinado')
                    ->formatStateUsing(fn ($state) => $state ? 'Baixar Assinado' : null)
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->weight(fn ($record) => $record && $record->isConcluded() && !empty($record->google_drive_returned_file_id) ? 'bold' : null)
                    ->url(fn ($record) => $record->google_drive_returned_file_id
                        ? (is_string($record->google_drive_returned_file_id) && str_contains($record->google_drive_returned_file_id, 'http')
                            ? $record->google_drive_returned_file_id
                            : route('documento.download', ['id' => $record->id, 'type' => 'returned']))
                        : null, true),
                Tables\Columns\TextColumn::make('abnt_verified_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label('Avaliado em'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('document_type')
                    ->options([
                        'Artigo Científico' => 'Artigo Científico',
                        'Avaliações de Estágio' => 'Avaliações de Estágio',
                        'Outro' => 'Outro',
                        'Plano de Estágio' => 'Plano de Estágio',
                        'Iniciação Científica' => 'Projeto de IC',
                        'Protocolo CEUA/CEP' => 'Protocolo CEUA/CEP',
                        'Relato de Caso' => 'Relato de Caso',
                        'Relatório' => 'Relatório',
                        'Relatório de Aula' => 'Relatório de Aula',
                        'Relatório de Estágio' => 'Relatório de Estágio',
                        'Relatório de Necropsia' => 'Relatório de Necropsia',
                        'TCC' => 'TCC',
                        'Termo de Aceite de IC' => 'Termo de Aceite de IC',
                        'Termo de Aceite de Orientação' => 'Termo de Aceite de Orientação',
                        'Trabalho de Congresso' => 'Trabalho de Congresso',
                        'Concepção de TCC' => 'Concepção de TCC',
                        'Objetivos de TCC' => 'Objetivos de TCC',
                        'Introdução de TCC' => 'Introdução de TCC',
                        'Materiais e Métodos de TCC' => 'Materiais e Métodos de TCC',
                        'Resultados de TCC' => 'Resultados de TCC',
                        'Discussão de TCC' => 'Discussão de TCC',
                        'Conclusão de TCC' => 'Conclusão de TCC',
                        'Documento Solicitado' => 'Documento Solicitado',
                    ])
                    ->label('Tipo de Documento'),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pendente',
                        'reviewing' => 'Em Análise',
                        'approved' => 'Aprovado',
                        'failed' => 'Rejeitado',
                        'Aguardando Correção do Professor' => 'Aguardando Correção do Professor',
                        'Aguardando Ajustes do Aluno' => 'Aguardando Ajustes do Aluno',
                        'Projeto em Análise Inicial pelo Orientador' => 'Projeto em Análise Inicial pelo Orientador',
                        'Aguardando Revisão do Professor' => 'Aguardando Revisão do Professor',
                        'Aguardando Assinatura do Professor' => 'Aguardando Assinatura do Professor',
                        'Avaliações de Estágio Registradas e Recebidas' => 'Avaliações de Estágio Registradas e Recebidas',
                        'Relatório de Necropsia Recebido - Aguardando Análise do Professor' => 'Relatório de Necropsia Recebido - Aguardando Análise do Professor',
                        'Termos Aceitos e Auditados - Aguardando Assinatura do Orientador' => 'Termos Aceitos e Auditados - Aguardando Assinatura do Orientador',
                        'Aguardando Relatório Parcial' => 'Aguardando Relatório Parcial',
                        'Aguardando Envio do Aluno' => 'Aguardando Envio do Aluno',
                        'Processo Concluído - Documento Assinado Disponível' => 'Processo Concluído - Documento Assinado Disponível',
                        'Processo Concluído - Projeto Arquivado' => 'Processo Concluído - Projeto Arquivado',
                        'Processo Concluído - Versão Final Definida' => 'Processo Concluído - Versão Final Definida',
                        'Processo Concluído - Termo de Aceite Assinado' => 'Processo Concluído - Termo de Aceite Assinado',
                        'Processo Concluído - Estágio Homologado com Sucesso' => 'Processo Concluído - Estágio Homologado com Sucesso',
                        'Processo Concluído - Laudo de Necropsia Homologado' => 'Processo Concluído - Laudo de Necropsia Homologado',
                        'Processo Concluído - Orientação de IC Homologada e Ativa' => 'Processo Concluído - Orientação de IC Homologada e Ativa',
                        'Aguardando Tema do Aluno' => 'Aguardando Tema do Aluno',
                        'Aguardando Definição de Tema' => 'Aguardando Definição de Tema',
                        'Processo Concluído - Tema Definido' => 'Processo Concluído - Tema Definido',
                        'Aguardando Objetivos do Aluno' => 'Aguardando Objetivos do Aluno',
                        'Aguardando Correção dos Objetivos' => 'Aguardando Correção dos Objetivos',
                        'Processo Concluído - Objetivos Aprovados' => 'Processo Concluído - Objetivos Aprovados',
                        'Aguardando Introdução do Aluno' => 'Aguardando Introdução do Aluno',
                        'Aguardando Correção da Introdução' => 'Aguardando Correção da Introdução',
                        'Processo Concluído - Introdução Aprovada' => 'Processo Concluído - Introdução Aprovada',
                        'Aguardando Materiais e Métodos do Aluno' => 'Aguardando Materiais e Métodos do Aluno',
                        'Aguardando Correção de Materiais e Métodos' => 'Aguardando Correção de Materiais e Métodos',
                        'Processo Concluído - Materiais e Métodos Aprovados' => 'Processo Concluído - Materiais e Métodos Aprovados',
                        'Aguardando Resultados do Aluno' => 'Aguardando Resultados do Aluno',
                        'Aguardando Correção dos Resultados' => 'Aguardando Correção dos Resultados',
                        'Processo Concluído - Resultados Aprovados' => 'Processo Concluído - Resultados Aprovados',
                        'Aguardando Discussão do Aluno' => 'Aguardando Discussão do Aluno',
                        'Aguardando Correção da Discussão' => 'Aguardando Correção da Discussão',
                        'Processo Concluído - Discussão Aprovada' => 'Processo Concluído - Discussão Aprovada',
                        'Aguardando Conclusão do Aluno' => 'Aguardando Conclusão do Aluno',
                        'Aguardando Correção da Conclusão' => 'Aguardando Correção da Conclusão',
                        'Processo Concluído - Conclusão Aprovada' => 'Processo Concluído - Conclusão Aprovada',
                    ])
                    ->label('Status'),
            ])
            ->actions([
                Tables\Actions\Action::make('habilitarRapido')
                    ->label('Habilitar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Habilitar Processo')
                    ->modalDescription('O processo entrará em análise/andamento e o aluno será notificado por e-mail/Telegram.')
                    ->modalSubmitActionLabel('Habilitar')
                    ->visible(fn ($record) => auth()->user()->role === 'admin' && $record->status === 'Aguardando Triagem')
                    ->action(function ($record) {
                        $record->update(['status' => 'Habilitado']);

                        \Filament\Notifications\Notification::make()
                            ->title('Processo Habilitado')
                            ->success()
                            ->body("O processo {$record->process_id} foi habilitado e o aluno foi notificado.")
                            ->send();
                    }),
                Tables\Actions\Action::make('reprovarRapido')
                    ->label('Reprovar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->modalHeading('Reprovar Processo')
                    ->modalSubmitActionLabel('Reprovar')
                    ->visible(fn ($record) => auth()->user()->role === 'admin' && $record->status === 'Aguardando Triagem')
                    ->form([
                        Forms\Components\Textarea::make('cancellation_reason')
                            ->label('Motivo da Reprovação')
                            ->placeholder('Explique o motivo e, se aplicável, sugira a abertura de um novo processo com as correções...')
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        $metadata = $record->metadata ?? [];
                        $metadata['cancellation_reason'] = $data['cancellation_reason'];

                        $record->update([
                            'status' => 'Cancelado',
                            'metadata' => $metadata,
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->title('Processo Reprovado')
                            ->success()
                            ->body("O processo {$record->process_id} foi reprovado e o aluno foi notificado.")
                            ->send();
                    }),
                Tables\Actions\Action::make('despachar')
                    ->label('Publicar Despacho')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->modalWidth('lg')
                    ->visible(fn ($record) => auth()->user()->role === 'admin' && !$record->isConcluded())
                    ->form([
                        Forms\Components\Select::make('status')
                            ->label('Novo Status do Processo')
                            ->options(function ($record) {
                                $currentStatus = $record?->status ?? 'Aguardando Triagem';
                                
                                if ($currentStatus === 'Aguardando Triagem') {
                                    return [
                                        'Aguardando Triagem' => 'Aguardando Triagem',
                                        'Habilitado' => 'Habilitar Processo',
                                        'Cancelado' => 'Cancelar Processo',
                                    ];
                                }
                                
                                $typeOptions = match ($record?->document_type) {
                                    'Concepção de TCC' => [
                                        'Aguardando Tema do Aluno' => 'Aguardando Tema do Aluno',
                                        'Aguardando Definição de Tema' => 'Aguardando Definição de Tema',
                                        'Aguardando Ajustes do Aluno' => 'Aguardando Ajustes do Aluno',
                                        'Processo Concluído - Tema Definido' => 'Processo Concluído - Tema Definido',
                                    ],
                                    'Objetivos de TCC' => [
                                        'Aguardando Objetivos do Aluno' => 'Aguardando Objetivos do Aluno',
                                        'Aguardando Correção dos Objetivos' => 'Aguardando Correção dos Objetivos',
                                        'Aguardando Ajustes do Aluno' => 'Aguardando Ajustes do Aluno',
                                        'Processo Concluído - Objetivos Aprovados' => 'Processo Concluído - Objetivos Aprovados',
                                    ],
                                    'Introdução de TCC' => [
                                        'Aguardando Introdução do Aluno' => 'Aguardando Introdução do Aluno',
                                        'Aguardando Correção da Introdução' => 'Aguardando Correção da Introdução',
                                        'Aguardando Ajustes do Aluno' => 'Aguardando Ajustes do Aluno',
                                        'Processo Concluído - Introdução Aprovada' => 'Processo Concluído - Introdução Aprovada',
                                    ],
                                    'Materiais e Métodos de TCC' => [
                                        'Aguardando Materiais e Métodos do Aluno' => 'Aguardando Materiais e Métodos do Aluno',
                                        'Aguardando Correção de Materiais e Métodos' => 'Aguardando Correção de Materiais e Métodos',
                                        'Aguardando Ajustes do Aluno' => 'Aguardando Ajustes do Aluno',
                                        'Processo Concluído - Materiais e Métodos Aprovados' => 'Processo Concluído - Materiais e Métodos Aprovados',
                                    ],
                                    'Resultados de TCC' => [
                                        'Aguardando Resultados do Aluno' => 'Aguardando Resultados do Aluno',
                                        'Aguardando Correção dos Resultados' => 'Aguardando Correção dos Resultados',
                                        'Aguardando Ajustes do Aluno' => 'Aguardando Ajustes do Aluno',
                                        'Processo Concluído - Resultados Aprovados' => 'Processo Concluído - Resultados Aprovados',
                                    ],
                                    'Discussão de TCC' => [
                                        'Aguardando Discussão do Aluno' => 'Aguardando Discussão do Aluno',
                                        'Aguardando Correção da Discussão' => 'Aguardando Correção da Discussão',
                                        'Aguardando Ajustes do Aluno' => 'Aguardando Ajustes do Aluno',
                                        'Processo Concluído - Discussão Aprovada' => 'Processo Concluído - Discussão Aprovada',
                                    ],
                                    'Conclusão de TCC' => [
                                        'Aguardando Conclusão do Aluno' => 'Aguardando Conclusão do Aluno',
                                        'Aguardando Correção da Conclusão' => 'Aguardando Correção da Conclusão',
                                        'Aguardando Ajustes do Aluno' => 'Aguardando Ajustes do Aluno',
                                        'Processo Concluído - Conclusão Aprovada' => 'Processo Concluído - Conclusão Aprovada',
                                    ],
                                    'TCC' => [
                                        'Aguardando Correção do Professor' => 'Aguardando Correção do Professor',
                                        'Aguardando Ajustes do Aluno' => 'Aguardando Ajustes do Aluno',
                                        'Em análise final para assinatura institucional' => 'Em análise final para assinatura institucional',
                                        'Processo Concluído - Documento Assinado Disponível' => 'Processo Concluído - Documento Assinado Disponível',
                                    ],
                                    'Iniciação Científica' => [
                                        'Projeto em Análise Inicial pelo Orientador' => 'Projeto em Análise Inicial pelo Orientador',
                                        'Aguardando Ajustes do Aluno' => 'Aguardando Ajustes do Aluno',
                                        'Processo Concluído - Projeto Arquivado' => 'Processo Concluído - Projeto Arquivado',
                                    ],
                                    'Relatório', 'Artigo Científico', 'Relato de Caso', 'Trabalho de Congresso', 'Relatório de Estágio', 'Relatório de Aula' => [
                                        'Aguardando Revisão do Professor' => 'Aguardando Revisão do Professor',
                                        'Aguardando Ajustes do Aluno' => 'Aguardando Ajustes do Aluno',
                                        'Aguardando Relatório Parcial' => 'Aguardando Relatório Parcial',
                                        'Processo Concluído - Versão Final Definida' => 'Processo Concluído - Versão Final Definida',
                                        'Processo Concluído - Documento Assinado Disponível' => 'Processo Concluído - Documento Assinado Disponível',
                                    ],
                                    'Plano de Estágio' => [
                                        'Aguardando Assinatura do Professor' => 'Aguardando Assinatura do Professor',
                                        'Aguardando Ajustes do Aluno' => 'Aguardando Ajustes do Aluno',
                                        'Processo Concluído - Documento Assinado Disponível' => 'Processo Concluído - Documento Assinado Disponível',
                                    ],
                                    'Termo de Aceite de Orientação' => [
                                        'Aguardando Assinatura do Professor' => 'Aguardando Assinatura do Professor',
                                        'Aguardando Ajustes do Aluno' => 'Aguardando Ajustes do Aluno',
                                        'Processo Concluído - Termo de Aceite Assinado' => 'Processo Concluído - Termo de Aceite Assinado',
                                    ],
                                    'Avaliações de Estágio' => [
                                        'Avaliações de Estágio Registradas e Recebidas' => 'Avaliações de Estágio Registradas e Recebidas',
                                        'Processo Concluído - Estágio Homologado com Sucesso' => 'Processo Concluído - Estágio Homologado com Sucesso',
                                    ],
                                    'Relatório de Necropsia' => [
                                        'Relatório de Necropsia Recebido - Aguardando Análise do Professor' => 'Relatório de Necropsia Recebido - Aguardando Análise do Professor',
                                        'Aguardando Ajustes no Laudo pelo Aluno' => 'Aguardando Ajustes no Laudo pelo Aluno',
                                        'Processo Concluído - Laudo de Necropsia Homologado' => 'Processo Concluído - Laudo de Necropsia Homologado',
                                    ],
                                    'Termo de Aceite de IC' => [
                                        'Termos Aceitos e Auditados - Aguardando Assinatura do Orientador' => 'Termos Aceitos e Auditados - Aguardando Assinatura do Orientador',
                                        'Processo Concluído - Orientação de IC Homologada e Ativa' => 'Processo Concluído - Orientação de IC Homologada e Ativa',
                                    ],
                                    'Documento Solicitado' => [
                                        'Aguardando Envio do Aluno' => 'Aguardando Envio do Aluno',
                                        'Aguardando Assinatura do Professor' => 'Aguardando Assinatura do Professor',
                                        'Aguardando Ajustes do Aluno' => 'Aguardando Ajustes do Aluno',
                                        'Processo Concluído - Documento Assinado Disponível' => 'Processo Concluído - Documento Assinado Disponível',
                                    ],
                                    default => [
                                        'pending' => 'Pendente',
                                        'reviewing' => 'Em Análise',
                                        'approved' => 'Aprovado',
                                        'needs_corrections' => 'Devolvido com Correções',
                                        'returned_signed' => 'Devolvido Assinado',
                                        'failed' => 'Rejeitado',
                                    ],
                                };
                                
                                $typeOptions['Cancelado'] = 'Cancelar Processo';

                                // Compatibilidade: processos já encerrados podem ter um status que
                                // deixou de ser oferecido (ex.: Termo de Aceite concluído como
                                // "Documento Assinado Disponível" antes de separarmos os fluxos).
                                // Preservamos o status atual do registro na lista para não quebrar
                                // a visualização/edição — sem oferecê-lo aos processos novos.
                                if ($record && ! array_key_exists($record->status, $typeOptions)) {
                                    $typeOptions[$record->status] = $record->status;
                                }

                                return $typeOptions;
                            })
                            ->default(fn ($record) => $record->status)
                            ->required()
                            ->live(),
                        Forms\Components\Textarea::make('metadata.cancellation_reason')
                            ->label('Motivo do Cancelamento')
                            ->placeholder('Explique o motivo do cancelamento e sugira a abertura de um novo processo...')
                            ->required()
                            ->visible(fn (Forms\Get $get) => $get('status') === 'Cancelado')
                            ->default(fn ($record) => $record->metadata['cancellation_reason'] ?? ''),
                        Forms\Components\Placeholder::make('_despacho_context')
                            ->label('Documento Original do Aluno (para conferência)')
                            ->content(function ($record) {
                                $files = is_array($record->google_drive_file_id) ? $record->google_drive_file_id : ($record->google_drive_file_id ? [$record->google_drive_file_id] : []);
                                $aluno = e($record->student?->user?->name ?? 'aluno');
                                $tipo = e($record->document_type);

                                if (empty($files)) {
                                    return new \Illuminate\Support\HtmlString(
                                        "<div class=\"text-xs text-slate-400 italic\">Nenhum arquivo original de {$aluno} ({$tipo}) encontrado.</div>"
                                    );
                                }

                                return new \Illuminate\Support\HtmlString(
                                    "<div class=\"text-xs text-amber-600 dark:text-amber-400\">⚠️ Confira o documento enviado por <strong>{$aluno}</strong> ({$tipo}) na lista antes de prosseguir. O campo abaixo é só para a devolução ASSINADA/CORRIGIDA — não reenvie aqui o arquivo original do aluno.</div>"
                                );
                            }),
                        Forms\Components\FileUpload::make('google_drive_returned_file_id')
                            ->label('✅ Devolução ao Aluno (Assinado / Corrigido)')
                            ->extraAttributes(['class' => 'ring-1 ring-emerald-400/50 dark:ring-emerald-400/40 rounded-xl p-2 bg-emerald-50/60 dark:bg-emerald-950/20'])
                            ->formatStateUsing(function ($state) {
                                if (blank($state)) {
                                    return [];
                                }
                                if (is_string($state)) {
                                    if (str_starts_with($state, '[') && str_ends_with($state, ']')) {
                                        return json_decode($state, true) ?: [];
                                    }
                                    return [$state];
                                }
                                return is_array($state) ? $state : [$state];
                            })
                            ->disk(in_array(config('filesystems.default'), ['google', 'onedrive']) ? config('filesystems.default') : 'public')
                            ->directory('returns')
                            ->downloadable()
                            ->openable()
                            ->acceptedFileTypes(['application/pdf'])
                            ->maxSize(10240)
                            ->default(fn ($record) => is_array($record->google_drive_returned_file_id) ? $record->google_drive_returned_file_id : ($record->google_drive_returned_file_id ? [$record->google_drive_returned_file_id] : []))
                            ->dehydrateStateUsing(fn ($state) => is_array($state) ? $state : ($state ? [$state] : []))
                            ->placeholder('Arraste seu arquivo PDF assinado ou clique para selecionar (opcional)...'),
                        Forms\Components\RichEditor::make('ai_executive_summary')
                            ->label('Parecer / Observações do Orientador')
                            ->toolbarButtons(['bold','italic','underline','strike','link','blockquote','bulletList','orderedList','h3','undo','redo'])
                            ->default(fn ($record) => $record->ai_executive_summary)
                            ->placeholder('Escreva suas observações, anotações ou correções...'),
                    ])
                    ->action(function ($record, array $data) {
                        $metadata = $record->metadata ?? [];
                        if (isset($data['metadata']['cancellation_reason'])) {
                            $metadata['cancellation_reason'] = $data['metadata']['cancellation_reason'];
                        }

                        // Esta ação rápida não expõe os campos do tema (área, espécie, tipo,
                        // ideias, campus, período) — só o Editar completo tem esses campos. Sem
                        // esta checagem, dava para "Publicar Despacho" direto de uma Concepção
                        // de TCC ainda em branco, concluindo o processo sem o tema definido.
                        if ($record->document_type === 'Concepção de TCC' && $data['status'] === 'Processo Concluído - Tema Definido') {
                            $requiredFields = [
                                'tcc_conception_area' => 'Área de Interesse Principal',
                                'tcc_conception_species' => 'Espécie(s) Animal de Interesse',
                                'tcc_conception_type' => 'Tipo de Estudo Preferido',
                                'tcc_conception_ideas' => 'Ideias Gerais / Assuntos que gostaria de abordar',
                                'campus' => 'Campus',
                                'period' => 'Período',
                            ];

                            $missing = [];
                            foreach ($requiredFields as $key => $label) {
                                if (blank($metadata[$key] ?? null)) {
                                    $missing[] = $label;
                                }
                            }

                            if ($missing) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Não é possível concluir com o tema em branco')
                                    ->danger()
                                    ->body('Antes de concluir, preencha estes campos pelo botão "Editar" do processo: '.implode(', ', $missing).'.')
                                    ->send();

                                return;
                            }
                        }

                        $record->update([
                            'status' => $data['status'],
                            'google_drive_returned_file_id' => $data['google_drive_returned_file_id'],
                            'ai_executive_summary' => $data['ai_executive_summary'],
                            'metadata' => $metadata,
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->title('Processo Despachado')
                            ->success()
                            ->body("O processo {$record->process_id} foi despachado e o aluno foi notificado por e-mail/Telegram.")
                            ->send();
                    }),
                Tables\Actions\EditAction::make()
                    ->label('Editar'),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make()
                        ->label('Visualizar'),
                    Tables\Actions\Action::make('timeline')
                        ->label('Ver Histórico (Linha do Tempo)')
                        ->icon('heroicon-o-clock')
                        ->color('info')
                        ->url(fn ($record) => route('documento.timeline', ['id' => $record->id]))
                        ->openUrlInNewTab(),
                    Tables\Actions\Action::make('recibo')
                        ->label('Baixar Recibo Forense')
                        ->icon('heroicon-o-document-check')
                        ->color('success')
                        ->url(fn ($record) => url("/documento/recibo/{$record->id}"))
                        ->openUrlInNewTab()
                        ->visible(fn ($record) => isset($record->metadata['forensic_hash'])),
                    Tables\Actions\Action::make('certificado')
                        ->label('Ver Certificado Acadêmico')
                        ->icon('heroicon-o-academic-cap')
                        ->color('success')
                        ->url(fn ($record) => route('documento.certificado', $record->id))
                        ->openUrlInNewTab()
                        ->visible(fn ($record) => str_starts_with($record->status, 'Processo Concluído')
                            && in_array($record->document_type, [
                                'Artigo Científico',
                                'Trabalho de Congresso',
                                'Relato de Caso',
                                'Relatório de Aula',
                                'Relatório de Necropsia',
                            ])),
                    Tables\Actions\Action::make('verificarAssinatura')
                        ->label('Validar Assinatura Digital')
                        ->icon('heroicon-o-shield-check')
                        ->color('info')
                        ->action(function ($record) {
                            $student = $record->student;
                            $studentName = $student && $student->user ? $student->user->name : 'N/A';
                            $rgm = $student ? $student->registration : 'N/A';
                            $ip = $record->metadata['forensic_ip'] ?? '';
                            $timestamp = $record->metadata['forensic_timestamp'] ?? '';
                            $userAgent = $record->metadata['forensic_user_agent'] ?? '';
                            
                            $termsText = "Declaro que aceito as cláusulas de relatórios periódicos, entrega de resultados (FAPESP), dedicação/exclusividade de dados e boas práticas científicas.";
                            $dataToHash = $termsText . '|' . $studentName . '|' . $rgm . '|' . $ip . '|' . $timestamp . '|' . $userAgent;
                            $calculatedHash = hash('sha256', $dataToHash);
                            
                            $savedHash = $record->metadata['forensic_hash'] ?? '';
                            
                            if ($calculatedHash === $savedHash) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Assinatura Íntegra')
                                    ->success()
                                    ->body("A assinatura digital do aluno foi verificada com sucesso! O hash recalculado coincide perfeitamente com o registro permanente:\n\n{$savedHash}")
                                    ->send();
                            } else {
                                \Filament\Notifications\Notification::make()
                                    ->title('Assinatura Adulterada ou Inválida')
                                    ->danger()
                                    ->body('Atenção: Os dados do processo ou auditoria foram modificados após o aceite! O hash recalculado não coincide com o registro original.')
                                    ->send();
                            }
                        })
                        ->visible(fn ($record) => $record->document_type === 'Termo de Aceite de IC' && isset($record->metadata['forensic_hash']) && auth()->user()->role === 'admin'),
                    Tables\Actions\Action::make('reauditar')
                        ->label('Re-auditar com IA')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->action(function ($record) {
                            $record->update(['status' => 'pending']);
                            dispatch(new \App\Jobs\AuditDocumentWithAI($record));
                            
                            \Filament\Notifications\Notification::make()
                                ->title('Auditoria Reiniciada')
                                ->success()
                                ->body('O documento foi colocado na fila para re-auditoria com a IA Gemini.')
                                ->send();
                        })
                        ->visible(false),
                    Tables\Actions\Action::make('assinarGovBr')
                        ->label('Assinar via Gov.br')
                        ->icon('heroicon-o-pencil-square')
                        ->color('success')
                        ->url(fn ($record) => route('documento.govbr.redirect', ['id' => $record->id]))
                        ->visible(false), // Desativado (Abordado Gov.br)
                    Tables\Actions\DeleteAction::make()
                        ->label('Excluir'),
                ])
                ->label('Mais Opções')
                ->icon('heroicon-m-ellipsis-vertical')
                ->color('gray')
                ->button(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('assinarLoteGovBr')
                        ->label('Assinar via Gov.br (Lote)')
                        ->icon('heroicon-o-pencil-square')
                        ->color('success')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            // Desativado
                        })
                        ->visible(false), // Desativado (Abordado Gov.br)
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->recordUrl(function ($record) {
                if (auth()->user()->role === 'admin') {
                    return static::getUrl('edit', ['record' => $record]);
                }
                if ($record->canStudentEdit()) {
                    return static::getUrl('edit', ['record' => $record]);
                }
                return static::getUrl('view', ['record' => $record]);
            });

        // Para o aluno, agrupa as etapas de um mesmo processo (Concepção → Objetivos
        // → TCC, ou Termo de Aceite de IC → IC → Relatórios) — que hoje compartilham
        // o mesmo academic_title_id assim que uma etapa avança — em um único bloco
        // visual, em vez de linhas soltas e desconectadas na lista. Processos sem
        // título vinculado (ainda) continuam aparecendo cada um como seu próprio
        // grupo, nunca misturados entre si.
        if (auth()->user()->role === 'student') {
            $table
                ->groups([
                    Tables\Grouping\Group::make('group_key')
                        ->label('Processo')
                        ->getTitleFromRecordUsing(fn ($record) => $record->academicTitle?->title ?? $record->title ?? $record->document_type)
                        ->collapsible(),
                ])
                ->defaultGroup('group_key')
                // Dentro de cada grupo, a etapa mais recente aparece primeiro —
                // o aluno sempre encontra o último retorno assim que abre.
                ->defaultSort('created_at', 'desc');
        }

        return $table;
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery()->with(['student.user', 'academicTitle']);

        if (auth()->user()->role === 'student') {
            // group_key agrupa etapas do mesmo processo (mesmo academic_title_id)
            // na listagem do aluno. Processos sem título vinculado usam um offset
            // grande sobre o próprio id para nunca colidir com um academic_title_id
            // real e sempre ficarem isolados em seu próprio grupo.
            return $query
                ->whereHas('student', function ($q) {
                    $q->where('user_id', auth()->id());
                })
                ->selectRaw('document_submissions.*, COALESCE(academic_title_id, id + 1000000000) as group_key');
        }

        return $query;
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();

        // Aluno que já declarou conclusão de curso não pode abrir novos processos
        if ($user->role === 'student' && $user->student?->course_concluded_at) {
            return false;
        }

        // Alunos e admins podem criar submissões
        return in_array($user->role, ['admin', 'student']);
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        // Apenas o admin pode excluir submissões
        return auth()->user()->role === 'admin';
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDocumentSubmissions::route('/'),
            'create' => Pages\CreateDocumentSubmission::route('/create'),
            'view' => Pages\ViewDocumentSubmission::route('/{record}'),
            'edit' => Pages\EditDocumentSubmission::route('/{record}/edit'),
        ];
    }
}
