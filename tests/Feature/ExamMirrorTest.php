<?php

namespace Tests\Feature;

use App\Filament\Pages\GerenciarEspelhosProva;
use App\Http\Controllers\ExamMirrorController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ExamMirrorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
        // Garante que o semestre 2026.2 está aberto para os testes padrão
        $cfg = ExamMirrorController::getSystemConfig();
        $cfg['active_semester'] = '2026.2';
        $cfg['semesters_status']['2026.2'] = 'aberto';
        ExamMirrorController::saveSystemConfig($cfg);
    }

    public function test_public_exam_mirror_page_loads_successfully_with_years_and_disciplines(): void
    {
        $response = $this->get('/consulta-avaliacao');

        $response->assertStatus(200);
        $response->assertSee('Devolutivas de Trabalhos &amp; Avaliações', false);
        $response->assertSee('Medicina dos Animais Silvestres');
        $response->assertSee('Empreendedorismo e Inovação');
        $response->assertSee('2026.2');
        // Campus claro
        $response->assertSee('PTA — Paulista');
        // Disclaimer oficial obrigatório
        $response->assertSee('Área do Aluno');
        $response->assertSee('meramente educacional');
        // Nenhum número técnico de oferta é vazado no HTML
        $response->assertDontSee('2276204');
        $response->assertDontSee('2280372');
    }

    public function test_valid_student_credentials_decrypt_exam_mirror_in_memory(): void
    {
        // Exemplo real: P2 Paulista Manhã - RGM 34096167 (Q1=A, Q2=A, Q3=D -> Nota 2,30)
        $response = $this->post('/consulta-avaliacao', [
            'package_id' => '2026_2__SILVESTRES__P2',
            'oferta'     => 'PTA_MAT',
            'versao'     => 'B',
            'rgm'        => '34096167',
            'q1'         => 'A',
            'q2'         => 'A',
            'q3'         => 'D',
        ]);

        $response->assertStatus(200);
        $response->assertSee('2,30');
        $response->assertSee('Clamidiose');
        $response->assertSee('PTA — Paulista · Matutino');
        // Disclaimer visível no resultado
        $response->assertSee('Área do Aluno');
        $response->assertSee('meramente educacional');
        // Garante que o nome civil e oferta NÃO estão no HTML (LGPD Art. 12)
        $response->assertDontSee('FRANCESCA');
        $response->assertDontSee('2280372');
    }

    public function test_valid_student_credentials_decrypt_project_work_in_memory(): void
    {
        // Exemplo real: Trabalho Empreendedorismo - Matutino - RGM 38258439 (ABIGAIL CARVALHO DO NASCIMENTO)
        // Nota Trabalho 4.00, P-BB 1.00, Total A2 5.00, Projeto REABILITAR
        $response = $this->post('/consulta-avaliacao', [
            'package_id'    => '2026_2__EMPREENDEDORISMO__TRABALHO',
            'oferta'        => 'PTA_MAT',
            'rgm'           => '38258439',
            'primeiro_nome' => 'Abigail',
        ]);

        $response->assertStatus(200);
        $response->assertSee('5,00');
        $response->assertSee('REABILITAR');
        $response->assertSee('Business Model Canvas');
        $response->assertSee('5W2H');
        $response->assertSee('PTA — Paulista · Matutino');
        // Disclaimer visível no resultado
        $response->assertSee('Área do Aluno');
        $response->assertSee('meramente educacional');
        // Nome civil não pode constar desnecessariamente no corpo
        $response->assertDontSee('ABIGAIL CARVALHO');
    }

    public function test_invalid_project_credentials_reject_access(): void
    {
        $response = $this->from('/consulta-avaliacao')->post('/consulta-avaliacao', [
            'package_id'    => '2026_2__EMPREENDEDORISMO__TRABALHO',
            'oferta'        => 'Matutino',
            'rgm'           => '38258439',
            'primeiro_nome' => 'NomeIncorreto',
        ]);

        $response->assertRedirect('/consulta-avaliacao');
        $response->assertSessionHasErrors('consulta');
    }

    public function test_invalid_exam_answers_reject_access(): void
    {
        $response = $this->from('/consulta-avaliacao')->post('/consulta-avaliacao', [
            'package_id' => '2026_2__SILVESTRES__P2',
            'oferta'     => '2280372',
            'versao'     => 'B',
            'rgm'        => '34096167',
            'q1'         => 'E',
            'q2'         => 'E',
            'q3'         => 'E',
        ]);

        $response->assertRedirect('/consulta-avaliacao');
        $response->assertSessionHasErrors('consulta');
    }

    public function test_closed_semester_blocks_consultation(): void
    {
        // Encerra o semestre 2026.2
        $cfg = ExamMirrorController::getSystemConfig();
        $cfg['semesters_status']['2026.2'] = 'encerrado';
        ExamMirrorController::saveSystemConfig($cfg);

        $response = $this->from('/consulta-avaliacao')->post('/consulta-avaliacao', [
            'package_id'    => '2026_2__EMPREENDEDORISMO__TRABALHO',
            'oferta'        => 'Matutino',
            'rgm'           => '38258439',
            'primeiro_nome' => 'Abigail',
        ]);

        $response->assertRedirect('/consulta-avaliacao');
        $response->assertSessionHasErrors('consulta');

        // Reabre para não afetar outros testes
        $cfg['semesters_status']['2026.2'] = 'aberto';
        ExamMirrorController::saveSystemConfig($cfg);
    }

    public function test_admin_can_access_and_manage_exam_mirror_packages_with_crud(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@profatilio.com.br']);

        Livewire::actingAs($admin)
            ->test(GerenciarEspelhosProva::class)
            ->assertOk()
            ->assertSee('Gestão de Devolutivas &amp; Espelhos de Prova', false)
            ->assertSee('Empreendedorismo e Inovação')
            ->assertSee('Medicina dos Animais Silvestres')
            ->assertSee('PTA — Paulista')
            // Abre o formulário de inicialização pré-configurado para Fisiologia Animal II
            ->call('openCreatePackage', 'Fisiologia Animal II')
            ->assertSet('isCreating', true)
            ->assertSet('createForm.disciplina', 'Fisiologia Animal II')
            ->call('cancelCreate')
            ->assertSet('isCreating', false);
    }

    public function test_admin_can_import_student_grades_via_csv_without_scripts(): void
    {
        $pkgPath = storage_path('app/exam_mirrors/2026_2_empreendedorismo_trabalho.json');
        $originalContent = file_get_contents($pkgPath);

        try {
            $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin_csv@profatilio.com.br']);

            $csvData = "RGM,Nome,Nota_Trabalho,Nota_PBB,Projeto\n99999991,Aluno Teste Import,3.8,1.0,PROJETO ALPHA";

            Livewire::actingAs($admin)
                ->test(GerenciarEspelhosProva::class)
                ->call('openImport', '2026_2_empreendedorismo_trabalho.json', 'PTA_MAT')
                ->set('importRawText', $csvData)
                ->call('runImport')
                ->assertSet('isImporting', false);

            // Testa se o aluno inserido agora consegue consultar a nota no site público com Zero-Knowledge
            $response = $this->post('/consulta-avaliacao', [
                'package_id'    => '2026_2__EMPREENDEDORISMO__TRABALHO',
                'oferta'        => 'PTA_MAT',
                'rgm'           => '99999991',
                'primeiro_nome' => 'Aluno',
            ]);

            $response->assertStatus(200);
            $response->assertSee('4,80');
            $response->assertSee('PROJETO ALPHA');
        } finally {
            file_put_contents($pkgPath, $originalContent);
        }
    }

    public function test_admin_can_insert_single_student_manually_without_scripts(): void
    {
        $pkgPath = storage_path('app/exam_mirrors/2026_2_silvestres_p2.json');
        $originalContent = file_get_contents($pkgPath);

        try {
            $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin_single@profatilio.com.br']);

            Livewire::actingAs($admin)
                ->test(GerenciarEspelhosProva::class)
                ->call('openAddStudent', '2026_2_silvestres_p2.json', 'PTA_MAT')
                ->set('studentData.rgm', '88888882')
                ->set('studentData.versao', 'A')
                ->set('studentData.q1', 'B')
                ->set('studentData.q2', 'C')
                ->set('studentData.q3', 'D')
                ->set('studentData.acertos_obj', 14)
                ->set('studentData.total_obj', 15)
                ->set('studentData.nota_final', 2.80)
                ->set('studentData.feedback', 'Excelente desempenho no exame de silvestres')
                ->call('saveSingleStudent')
                ->assertSet('isAddingStudent', false);

            // Testa consulta pública do aluno avulso recém-criptografado
            $response = $this->post('/consulta-avaliacao', [
                'package_id' => '2026_2__SILVESTRES__P2',
                'oferta'     => 'PTA_MAT',
                'versao'     => 'A',
                'rgm'        => '88888882',
                'q1'         => 'B',
                'q2'         => 'C',
                'q3'         => 'D',
            ]);

            $response->assertStatus(200);
            $response->assertSee('2,80');
            $response->assertSee('Excelente desempenho no exame de silvestres');
        } finally {
            file_put_contents($pkgPath, $originalContent);
        }
    }

    public function test_admin_can_merge_turma_json_directly_into_parent_package(): void
    {
        $pkgPath = storage_path('app/exam_mirrors/2026_2_silvestres_p2.json');
        $originalContent = file_get_contents($pkgPath);

        try {
            $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin_turma_merge@profatilio.com.br']);

            // Simula um JSON de turma com 1 aluno
            $simulatedTurmaJson = [
                'package_id' => '2026_2__SILVESTRES__P2__2278834',
                'turmas' => [
                    'SM_MAT' => [
                        'turma' => 'SM — São Miguel · Matutino',
                        'status' => 'publicada',
                        'total_provas' => 1,
                        'versoes' => ['A' => ['versao' => 'A', 'label' => 'Versão A Teste', 'q_hints' => []]],
                    ],
                ],
                'vaults' => [],
            ];

            [$h, $c] = ExamMirrorController::encryptProvaVault(
                '2026_2__SILVESTRES__P2',
                '99999991',
                'A',
                'B',
                'C',
                [
                    'tipo' => 'avaliacao',
                    'turma' => 'SM — São Miguel · Matutino',
                    'nota_final' => 2.45,
                    'discursiva' => ['nota_total' => 0.95, 'feedback_professor' => 'Caso clínico perfeito.'],
                ]
            );
            $simulatedTurmaJson['vaults'][$h] = $c;

            $res = ExamMirrorController::mergeTurmaJson('2026_2_silvestres_p2.json', 'SM_MAT', $simulatedTurmaJson);
            $this->assertTrue($res['success']);

            // Verifica que o cofre foi mesclado no pacote principal
            $parentData = json_decode(file_get_contents($pkgPath), true);
            $this->assertArrayHasKey($h, $parentData['vaults']);
            $status = $parentData['turmas']['2278834']['status'] ?? ($parentData['turmas']['SM_MAT']['status'] ?? null);
            $this->assertEquals('publicada', $status);
        } finally {
            file_put_contents($pkgPath, $originalContent);
        }
    }

    public function test_admin_can_perform_vista_de_prova_and_update_student_feedback(): void
    {
        $pkgPath = storage_path('app/exam_mirrors/2026_2_silvestres_p2.json');
        $originalContent = file_get_contents($pkgPath);

        try {
            $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin_vista@profatilio.com.br']);

            // Aluno conhecido: 34096167
            $found = ExamMirrorController::findAndDecryptStudent('2026_2_silvestres_p2.json', '34096167');
            $this->assertNotNull($found);
            $this->assertEquals('avaliacao', $found['type']);
            $this->assertEquals('34096167', $found['rgm']);

            // Executa ajuste na vista de prova via Livewire
            Livewire::actingAs($admin)
                ->test(GerenciarEspelhosProva::class)
                ->call('openVista', '2026_2_silvestres_p2.json')
                ->set('vistaRgm', '34096167')
                ->call('searchStudentVista')
                ->assertSet('vistaStudent.nota_final', 2.3)
                ->set('vistaForm.nota_final', 2.5)
                ->set('vistaForm.feedback', 'Nota revisada e elevada para 2,50 durante a vista de prova.')
                ->call('saveStudentVista');

            // Consulta como aluno para certificar que a nota mudou no portal público
            $response = $this->post('/consulta-avaliacao', [
                'package_id' => '2026_2__SILVESTRES__P2',
                'oferta'     => 'PTA_MAT',
                'versao'     => 'B',
                'rgm'        => '34096167',
                'q1'         => 'A',
                'q2'         => 'A',
                'q3'         => 'D',
            ]);

            $response->assertStatus(200);
            $response->assertSee('2,50');
            $response->assertSee('Nota revisada e elevada para 2,50 durante a vista de prova.');
        } finally {
            file_put_contents($pkgPath, $originalContent);
        }
    }

    public function test_admin_must_confirm_before_deleting_package(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin_del@profatilio.com.br']);

        Livewire::actingAs($admin)
            ->test(GerenciarEspelhosProva::class)
            ->call('confirmDelete', 'pacote_teste_seguranca.json', 'Pacote Teste')
            ->assertSet('confirmingDeletion', true)
            ->assertSet('deletingFilename', 'pacote_teste_seguranca.json')
            ->call('cancelDelete')
            ->assertSet('confirmingDeletion', false);
    }
}
