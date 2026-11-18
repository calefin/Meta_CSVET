<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CourseResource\Pages;
use App\Models\Course;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CourseResource extends Resource
{
    protected static ?string $model = Course::class;
    protected static ?string $navigationIcon  = 'heroicon-o-academic-cap';
    protected static ?string $navigationLabel = 'Cursos';
    protected static ?string $navigationGroup = 'Certificados';
    protected static ?int    $navigationSort  = 10;
    protected static ?string $modelLabel      = 'Curso';
    protected static ?string $pluralModelLabel = 'Cursos';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Dados do Curso')->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nome do Curso')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('workload')
                    ->label('Carga Horária (horas)')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->suffix('h'),

                Forms\Components\Toggle::make('active')
                    ->label('Disponível para Solicitação')
                    ->default(true),

                Forms\Components\DatePicker::make('start_date')
                    ->label('Data de Início')
                    ->required()
                    ->displayFormat('d/m/Y'),

                Forms\Components\DatePicker::make('end_date')
                    ->label('Data de Encerramento')
                    ->required()
                    ->displayFormat('d/m/Y')
                    ->afterOrEqual('start_date'),

                Forms\Components\TextInput::make('isbn')
                    ->label('ISBN (Registro CBL)')
                    ->helperText('Preencha quando o registro de ISBN for concedido pela CBL. Aparece no rodapé do PDF das etapas e do certificado.')
                    ->maxLength(255),

                Forms\Components\RichEditor::make('syllabus')
                    ->label('Conteúdo Programático')
                    ->required()
                    ->columnSpanFull()
                    ->toolbarButtons([
                        'bold', 'italic', 'underline',
                        'bulletList', 'orderedList',
                        'h2', 'h3',
                        'undo', 'redo',
                    ])
                    ->helperText('Este conteúdo será impresso na segunda página do certificado. Use listas e negrito para organizar os tópicos.'),
            ])->columns(['default' => 1, 'md' => 2, 'lg' => 3]),

            Forms\Components\Section::make('Etapas da Trilha')
                ->description('Organize o curso em etapas sequenciais. O aluno só avança para a próxima etapa depois de concluir a atual. Deixe em branco se este curso não tem conteúdo interativo (ex.: cursos externos só para emissão de certificado).')
                ->schema([
                    Forms\Components\Repeater::make('modules')
                        ->relationship('modules')
                        ->label('')
                        ->schema([
                            Forms\Components\TextInput::make('title')
                                ->label('Título da Etapa')
                                ->required()
                                ->columnSpan(2),
                            Forms\Components\Select::make('type')
                                ->label('Tipo')
                                ->options([
                                    'instrucao' => '📖 Instrução / Conteúdo',
                                    'avaliacao' => '✅ Avaliação (Quiz)',
                                ])
                                ->default('instrucao')
                                ->required()
                                ->live()
                                ->columnSpan(1),
                            Forms\Components\RichEditor::make('content')
                                ->label('Conteúdo / Instruções')
                                ->toolbarButtons(['bold', 'italic', 'underline', 'bulletList', 'orderedList', 'h2', 'h3', 'link', 'codeBlock', 'undo', 'redo'])
                                ->columnSpanFull(),
                            Forms\Components\TextInput::make('video_url')
                                ->label('Vídeo Complementar (YouTube)')
                                ->url()
                                ->placeholder('https://www.youtube.com/watch?v=...')
                                ->helperText('Opcional. Aceita links do seu canal ou de terceiros para complementar a etapa.')
                                ->columnSpanFull(),
                            Forms\Components\Repeater::make('practice_exercises')
                                ->label('Exercícios Práticos (autoverificáveis, não valem nota)')
                                ->helperText('O aluno roda o código sugerido, digita o que obteve e vê na hora se acertou. Se errar, pode revelar a explicação de como resolver.')
                                ->schema([
                                    Forms\Components\Textarea::make('prompt')
                                        ->label('Enunciado do Exercício')
                                        ->required()
                                        ->rows(2)
                                        ->columnSpanFull(),
                                    Forms\Components\Textarea::make('code_starter')
                                        ->label('Código Sugerido para o Aluno Rodar')
                                        ->rows(3)
                                        ->extraInputAttributes(['style' => 'font-family: monospace; font-size: 0.85rem;'])
                                        ->columnSpanFull(),
                                    Forms\Components\TextInput::make('expected_output')
                                        ->label('Resultado Esperado')
                                        ->required()
                                        ->helperText('Comparação exata, ignorando maiúsculas/minúsculas e espaços nas pontas.')
                                        ->columnSpan(1),
                                    Forms\Components\Textarea::make('solution_explanation')
                                        ->label('Explicação de Como Resolver (aparece se o aluno pedir a solução)')
                                        ->rows(2)
                                        ->columnSpanFull(),
                                ])
                                ->columns(2)
                                ->itemLabel(fn (array $state): ?string => $state['prompt'] ?? null)
                                ->addActionLabel('+ Adicionar Exercício Prático')
                                ->collapsible()
                                ->columnSpanFull(),
                            Forms\Components\TextInput::make('passing_score')
                                ->label('Nota de Corte (%)')
                                ->helperText('Aprovação exige nota MAIOR que este valor.')
                                ->numeric()
                                ->minValue(1)
                                ->maxValue(100)
                                ->default(70)
                                ->visible(fn (Forms\Get $get) => $get('type') === 'avaliacao')
                                ->columnSpan(1),
                            Forms\Components\Repeater::make('quiz_questions')
                                ->label('Perguntas do Quiz')
                                ->visible(fn (Forms\Get $get) => $get('type') === 'avaliacao')
                                ->schema([
                                    Forms\Components\Select::make('type')
                                        ->label('Tipo de Pergunta')
                                        ->options([
                                            'multipla_escolha' => '🔘 Múltipla Escolha',
                                            'codigo' => '💻 Exercício de Código (o aluno roda e digita a resposta)',
                                        ])
                                        ->default('multipla_escolha')
                                        ->required()
                                        ->live()
                                        ->columnSpanFull(),
                                    Forms\Components\TextInput::make('question')
                                        ->label('Pergunta / Enunciado')
                                        ->required()
                                        ->columnSpanFull(),
                                    Forms\Components\Repeater::make('options')
                                        ->label('Alternativas')
                                        ->visible(fn (Forms\Get $get) => ($get('type') ?? 'multipla_escolha') === 'multipla_escolha')
                                        ->simple(
                                            Forms\Components\TextInput::make('option')->required()
                                        )
                                        ->live()
                                        ->minItems(2)
                                        ->maxItems(6)
                                        ->required(fn (Forms\Get $get) => ($get('type') ?? 'multipla_escolha') === 'multipla_escolha')
                                        ->columnSpanFull(),
                                    Forms\Components\Select::make('correct_index')
                                        ->label('Alternativa Correta')
                                        ->visible(fn (Forms\Get $get) => ($get('type') ?? 'multipla_escolha') === 'multipla_escolha')
                                        ->options(fn (Forms\Get $get) => collect($get('options') ?? [])
                                            ->mapWithKeys(fn ($opt, $i) => [$i => (filled($opt) ? $opt : ('Opção ' . ($i + 1)))]))
                                        ->required(fn (Forms\Get $get) => ($get('type') ?? 'multipla_escolha') === 'multipla_escolha')
                                        ->columnSpanFull(),
                                    Forms\Components\Textarea::make('code_snippet')
                                        ->label('Código para o Aluno Rodar')
                                        ->visible(fn (Forms\Get $get) => $get('type') === 'codigo')
                                        ->required(fn (Forms\Get $get) => $get('type') === 'codigo')
                                        ->rows(4)
                                        ->extraInputAttributes(['style' => 'font-family: monospace; font-size: 0.85rem;'])
                                        ->columnSpanFull(),
                                    Forms\Components\TextInput::make('expected_answer')
                                        ->label('Resposta Esperada')
                                        ->visible(fn (Forms\Get $get) => $get('type') === 'codigo')
                                        ->required(fn (Forms\Get $get) => $get('type') === 'codigo')
                                        ->helperText('Comparação exata, ignorando maiúsculas/minúsculas e espaços nas pontas. Ex.: 4500, TRUE, "Canino".')
                                        ->columnSpanFull(),
                                ])
                                ->columns(1)
                                ->itemLabel(fn (array $state): ?string => $state['question'] ?? null)
                                ->addActionLabel('+ Adicionar Pergunta')
                                ->columnSpanFull(),
                        ])
                        ->columns(3)
                        ->orderColumn('order')
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => ($state['title'] ?? 'Nova Etapa') . ((($state['type'] ?? '') === 'avaliacao') ? ' — Quiz' : ''))
                        ->addActionLabel('+ Adicionar Etapa')
                        ->reorderableWithButtons(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome do Curso')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('workload')
                    ->label('C.H.')
                    ->suffix('h')
                    ->sortable(),

                Tables\Columns\TextColumn::make('start_date')
                    ->label('Início')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('end_date')
                    ->label('Encerramento')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\IconColumn::make('active')
                    ->label('Ativo')
                    ->boolean(),

                Tables\Columns\TextColumn::make('modules_count')
                    ->label('Etapas')
                    ->counts('modules')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('enrollments_count')
                    ->label('Matriculados')
                    ->counts('enrollments')
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('certificate_requests_count')
                    ->label('Solicitações')
                    ->counts('certificateRequests')
                    ->badge()
                    ->color('gray'),
            ])
            ->defaultSort('end_date', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('active')->label('Ativo'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelationsManagers(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCourses::route('/'),
            'create' => Pages\CreateCourse::route('/create'),
            'edit'   => Pages\EditCourse::route('/{record}/edit'),
        ];
    }
}
