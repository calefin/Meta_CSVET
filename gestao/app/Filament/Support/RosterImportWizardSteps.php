<?php

namespace App\Filament\Support;

use App\Models\ExternalClassGroup;
use App\Services\RosterImportService;
use Filament\Forms;
use Filament\Forms\Get;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

/**
 * Compartilhado entre ExternalClassGroupResource (Nova Turma + Importar
 * Planilha) e ParticipantsRelationManager (Importar Planilha numa turma já
 * existente) — os dois passos são idênticos, só muda se o grupo já existe
 * na hora de pré-visualizar (ver previewStep).
 */
class RosterImportWizardSteps
{
    public static function uploadStep(): Forms\Components\Wizard\Step
    {
        return Forms\Components\Wizard\Step::make('Planilha')
            ->schema([
                Forms\Components\Placeholder::make('roster_instructions')
                    ->label('Como montar a planilha')
                    ->content(new HtmlString(
                        '<div class="text-sm text-gray-600 dark:text-gray-400 space-y-1.5">'
                        . '<p>Arquivo <strong>.xlsx</strong>, <strong>.csv</strong> ou <strong>.txt</strong> com exatamente 3 colunas, nessa ordem: <strong>Nome</strong>, <strong>RGM</strong>, <strong>E-mail</strong>.</p>'
                        . '<p>A primeira linha pode ser um cabeçalho (é detectado e ignorado automaticamente) ou já a primeira aluna/aluno.</p>'
                        . '<p>O e-mail é obrigatório — é ele + o RGM que o aluno usa pra entrar no portal, sem precisar criar conta.</p>'
                        . '<p>Reimportar a mesma planilha depois só atualiza quem já existe (por RGM), não duplica ninguém.</p>'
                        . '<p><a href="/templates/modelo_turma_estagio.csv" download class="text-primary-600 dark:text-primary-400 font-semibold underline">Baixar planilha modelo</a></p>'
                        . '</div>'
                    )),
                Forms\Components\FileUpload::make('roster_file')
                    ->label('Planilha')
                    ->required()
                    ->disk('local')
                    ->directory('temp_roster_imports')
                    ->visibility('private'),
            ]);
    }

    /**
     * $ownerRecord: null quando a turma ainda não existe (fluxo "Nova Turma
     * + Importar Planilha") — nesse caso tudo aparece como "novo", já que
     * não há como já existir participante numa turma que ainda não foi
     * criada.
     */
    public static function previewStep(?ExternalClassGroup $ownerRecord): Forms\Components\Wizard\Step
    {
        return Forms\Components\Wizard\Step::make('Pré-visualização')
            ->schema([
                Forms\Components\Placeholder::make('roster_preview')
                    ->label('')
                    ->content(function (Get $get) use ($ownerRecord) {
                        $relativePath = $get('roster_file');
                        $relativePath = is_array($relativePath) ? reset($relativePath) : $relativePath;

                        if (!$relativePath) {
                            return new HtmlString('<p class="text-sm text-gray-500 dark:text-gray-400">Envie a planilha na etapa anterior pra ver a pré-visualização aqui.</p>');
                        }

                        $absolutePath = Storage::disk('local')->path($relativePath);
                        if (!file_exists($absolutePath)) {
                            return new HtmlString('<p class="text-sm text-danger-600 dark:text-danger-400">Não foi possível ler o arquivo enviado. Volte e envie novamente.</p>');
                        }

                        $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
                        $group = $ownerRecord ?? new ExternalClassGroup();
                        $analysis = app(RosterImportService::class)->analyze($group, $absolutePath, $extension);

                        return new HtmlString(view('filament.partials.roster-preview', ['analysis' => $analysis])->render());
                    }),
            ]);
    }
}
