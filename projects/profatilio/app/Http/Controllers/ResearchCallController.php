<?php

namespace App\Http\Controllers;

use App\Models\ResearchApplication;
use App\Models\ResearchCall;
use App\Services\ResearchRecruitmentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Página pública da seleção de alunos (/selecao/{slug}), inscrição e
 * acompanhamento pelo link pessoal.
 */
class ResearchCallController extends Controller
{
    public function __construct(protected ResearchRecruitmentService $service) {}

    /** /selecao → a chamada aberta mais recente. */
    public function index()
    {
        $call = ResearchCall::where('status', ResearchCall::STATUS_OPEN)->latest('id')->first()
            ?? ResearchCall::where('status', ResearchCall::STATUS_CLOSED)->latest('id')->first();

        if (!$call) {
            return $this->unavailable('Nenhuma seleção aberta no momento', 'Ainda não há inscrições abertas para os projetos de pesquisa. Volte em breve ou acompanhe os avisos do Prof. Atilio.');
        }

        return redirect()->route('research-call.show', $call->slug);
    }

    public function show(string $slug)
    {
        $call = $this->findPublic($slug);
        if (!$call) {
            return $this->unavailable('Seleção não encontrada', 'Esta página de seleção não existe ou ainda não foi publicada. Confira o link que você recebeu.');
        }

        return view('research-call.show', ['call' => $call]);
    }

    public function store(Request $request, string $slug)
    {
        $call = $this->findPublic($slug);
        abort_unless($call, 404);

        // Honeypot: robô preenche o campo escondido.
        if ($request->filled('website')) {
            return view('research-call.done', ['call' => $call, 'application' => null, 'duplicate' => false]);
        }

        if (!$call->isOpen()) {
            return back()->withErrors(['call' => 'As inscrições desta chamada estão encerradas.']);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'min:5', 'max:255', function ($attribute, $value, $fail) use ($request) {
                if ($problem = self::fullNameProblem($value)) {
                    $fail($problem);
                } elseif (\Illuminate\Support\Str::squish($value) !== \Illuminate\Support\Str::squish((string) $request->input('name_confirmation'))) {
                    $fail('Os dois campos de nome completo estão diferentes. Digite exatamente o mesmo nome nos dois.');
                }
            }],
            'email' => ['required', 'email', 'max:255', function ($attribute, $value, $fail) use ($request) {
                if (mb_strtolower(trim($value)) !== mb_strtolower(trim((string) $request->input('email_confirmation')))) {
                    $fail('Os dois campos de e-mail estão diferentes. Confira o seu e-mail: todos os avisos chegam nele.');
                }
            }],
            'adhesion' => 'accepted',
            'phone' => 'required|string|min:8|max:40',
            'course' => ['required', 'string', Rule::in(ResearchCall::COURSES)],
            'institution' => 'required|string|max:255',
            'semester' => 'required|integer|min:1|max:12',
            'purpose' => ['required', Rule::in(array_keys(ResearchApplication::PURPOSES))],
            'axes' => 'required|array|min:1',
            'axes.*' => ['string', Rule::in($call->axisNames())],
            'projects' => 'nullable|array|max:3',
            'projects.*' => ['string', Rule::in(array_keys($call->openProjectsByCode()))],
            'weekly_hours' => 'required|integer|min:1|max:40',
            'english_level' => ['required', Rule::in(array_keys(ResearchApplication::ENGLISH_LEVELS))],
            'lattes_url' => 'nullable|url|max:255',
            'orcid' => ['nullable', 'regex:/^(https?:\/\/orcid\.org\/)?\d{4}-\d{4}-\d{4}-\d{3}[\dX]$/'],
            'motivation' => 'required|string|min:30|max:2000',
            'experience' => 'nullable|string|max:2000',
            'commitments' => 'nullable|array',
        ], [
            'axes.required' => 'Escolha pelo menos um eixo de interesse.',
            'projects.max' => 'Escolha no máximo 3 projetos de preferência.',
            'motivation.min' => 'Conte em pelo menos 30 caracteres por que você quer participar.',
            'orcid.regex' => 'ORCID no formato 0000-0000-0000-0000.',
            'adhesion.accepted' => 'Para se inscrever, leia e aceite o termo de adesão.',
        ], [
            'name' => 'nome', 'phone' => 'telefone', 'course' => 'curso', 'institution' => 'instituição',
            'semester' => 'semestre', 'purpose' => 'objetivo', 'weekly_hours' => 'horas por semana',
            'english_level' => 'leitura em inglês', 'motivation' => 'motivação',
        ]);

        if ($problems = $this->service->eligibilityProblems($call, $data)) {
            return back()->withInput()->withErrors($problems);
        }

        [$application, $duplicate] = $this->service->submit($call, $data, $request->ip(), $request->userAgent());

        return view('research-call.done', ['call' => $call, 'application' => $application, 'duplicate' => $duplicate]);
    }

    public function track(Request $request, string $token)
    {
        $application = ResearchApplication::findByToken($token);
        abort_unless($application, 404);
        $this->service->confirmEmail($application, $request->ip());

        return view('research-call.track', [
            'application' => $application,
            'call' => $application->researchCall,
            'current' => $this->service->currentStep($application),
        ]);
    }

    /** Aceite do termo de adesão pela página de acompanhamento (inscrições antigas). */
    public function acceptAdhesion(Request $request, string $token)
    {
        $application = ResearchApplication::findByToken($token);
        abort_unless($application, 404);
        $request->validate(['adhesion' => 'accepted'], ['adhesion.accepted' => 'Marque a caixa para aceitar o termo de adesão.']);

        $this->service->confirmEmail($application, $request->ip());
        $this->service->acceptAdhesion($application, $request->ip(), $request->userAgent());

        return redirect()->route('research-call.track', $token)->with('adhesion_ok', true);
    }

    /**
     * Nome completo exato (vai para o termo, a autoria e os certificados):
     * pelo menos nome e sobrenome, sem números e sem abreviações.
     */
    public static function fullNameProblem(string $name): ?string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name));
        if (preg_match('/\d/u', $name)) {
            return 'O nome completo não pode ter números.';
        }
        $words = explode(' ', $name);
        if (count($words) < 2) {
            return 'Digite o seu nome completo, com sobrenome, exatamente como nos seus documentos.';
        }
        // "e"/"y" sozinhos são conectivos (ex.: "Silva e Souza"); outras letras soltas ou com ponto são abreviações.
        foreach ($words as $w) {
            if (str_contains($w, '.') || (mb_strlen($w) === 1 && !in_array(mb_strtolower($w), ['e', 'y'], true))) {
                return 'Escreva o nome completo sem abreviações (ex.: "Maria Aparecida da Silva", e não "Maria A. Silva").';
            }
        }

        return null;
    }

    /** Chamada visível: rascunho só aparece para o professor logado (pré-visualização). */
    protected function findPublic(string $slug): ?ResearchCall
    {
        $call = ResearchCall::where('slug', $slug)->first();
        if (!$call || ($call->status === ResearchCall::STATUS_DRAFT && auth()->user()?->role !== 'admin')) {
            return null;
        }

        return $call;
    }

    /** Página amigável (status 404) quando não há chamada publicada. */
    protected function unavailable(string $title, string $message)
    {
        return response()->view('research-call.unavailable', ['title' => $title, 'message' => $message], 404);
    }
}
