<?php

namespace App\Http\Controllers;

use App\Models\NecropsyClassAttendance;
use App\Models\NecropsyClassParticipant;
use App\Models\NecropsyClassSession;
use Illuminate\Http\Request;

class NecropsyClassCheckInController extends Controller
{
    /**
     * Tela pública exibida ao escanear o QR Code da tarde de necropsia.
     */
    public function show(Request $request, string $token)
    {
        $session = NecropsyClassSession::where('token', $token)->firstOrFail();

        $rgm = trim((string) $request->query('rgm', ''));
        $participant = null;
        $alreadyCheckedIn = false;

        if ($rgm !== '') {
            $participant = NecropsyClassParticipant::whereRaw('LOWER(rgm) = ?', [mb_strtolower($rgm)])->first();

            if ($participant) {
                $alreadyCheckedIn = NecropsyClassAttendance::where('necropsy_class_session_id', $session->id)
                    ->where('necropsy_class_participant_id', $participant->id)
                    ->exists();
            }
        }

        return view('necropsy_class.checkin', [
            'session' => $session,
            'rgm' => $rgm,
            'participant' => $participant,
            'alreadyCheckedIn' => $alreadyCheckedIn,
        ]);
    }

    /**
     * Confirma a presença: localiza o participante pelo RGM (criando um
     * cadastro mínimo se necessário) e registra a presença na tarde.
     */
    public function store(Request $request, string $token)
    {
        $session = NecropsyClassSession::where('token', $token)->firstOrFail();

        if ($session->isCancelled()) {
            return redirect()->route('necropsy.class.checkin', ['token' => $token])
                ->withErrors(['rgm' => 'Esta atividade foi cancelada/dispensada e não aceita mais presenças.']);
        }

        $validated = $request->validate([
            'rgm' => 'required|string|max:255',
            'name' => 'nullable|string|max:255',
            'institution' => 'nullable|string|max:255',
        ], [
            'rgm.required' => 'Informe seu RGM/matrícula.',
        ]);

        $existing = NecropsyClassParticipant::whereRaw('LOWER(rgm) = ?', [mb_strtolower(trim($validated['rgm']))])->first();

        if (!$existing && empty($validated['name'])) {
            return redirect()->route('necropsy.class.checkin', ['token' => $token, 'rgm' => $validated['rgm']])
                ->withErrors(['name' => 'RGM não encontrado. Preencha nome e instituição para o seu cadastro rápido.']);
        }

        $participant = NecropsyClassParticipant::findOrCreateByRgm(
            $validated['rgm'],
            $validated['name'] ?? null,
            $validated['institution'] ?? null
        );

        $attendance = NecropsyClassAttendance::firstOrCreate(
            [
                'necropsy_class_session_id' => $session->id,
                'necropsy_class_participant_id' => $participant->id,
            ],
            [
                'checked_in_at' => now(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]
        );

        return view('necropsy_class.success', [
            'session' => $session,
            'participant' => $participant,
            'attendance' => $attendance,
        ]);
    }
}
