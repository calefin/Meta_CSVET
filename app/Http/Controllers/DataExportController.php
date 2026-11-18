<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DataExportController extends Controller
{
    /**
     * LGPD portability (spec sec. 12): the user's own data only - never
     * the admin-only life areas of a consultation, which stay internal
     * even in the user's own export.
     */
    private function buildData(Request $request): array
    {
        $user = $request->user()->load(
            'consultationRequests.consultation.oduMain',
            'consultationRequests.consultation.ireIbiType',
            'appointments',
            'sentMessages',
            'receivedMessages',
            'consentRecords',
            'courseEnrollments.course'
        );

        return [
            'perfil' => [
                'nome' => $user->name,
                'email' => $user->email,
                'telefone' => $user->phone,
                'pais' => $user->country,
                'data_nascimento' => $user->birthdate?->toDateString(),
                'cadastrado_em' => $user->created_at->toDateString(),
            ],
            'consultas' => $user->consultationRequests->map(fn ($r) => [
                'modalidade' => $r->modality,
                'status' => $r->status,
                'solicitada_em' => $r->created_at->toDateString(),
                'odu_principal' => $r->consultation?->oduMain?->name,
                'classificacao' => $r->consultation?->ire_ibi,
                'tipo' => $r->consultation?->ireIbiType?->name,
                'orientacoes_gerais' => $r->consultation?->general_guidance,
            ]),
            'agendamentos' => $user->appointments->map(fn ($a) => [
                'data' => $a->scheduled_at->toDateTimeString(),
                'modalidade' => $a->modality,
                'status' => $a->status,
            ]),
            'mensagens_enviadas' => $user->sentMessages->count(),
            'mensagens_recebidas' => $user->receivedMessages->count(),
            'consentimentos' => $user->consentRecords->map(fn ($c) => [
                'tipo' => $c->type,
                'aceito_em' => $c->accepted_at->toDateTimeString(),
            ]),
            'cursos' => $user->courseEnrollments->map(fn ($e) => [
                'curso' => $e->course->title,
                'matriculado_em' => $e->enrolled_at?->toDateString(),
                'concluido_em' => $e->completed_at?->toDateString(),
            ]),
        ];
    }

    public function json(Request $request): JsonResponse
    {
        return response()->json($this->buildData($request), 200, [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    public function pdf(Request $request)
    {
        $pdf = Pdf::loadView('pdf.data-export', ['data' => $this->buildData($request), 'user' => $request->user()]);

        return $pdf->stream('meus-dados.pdf');
    }
}
