<?php

namespace App\Http\Controllers;

use App\Models\ArticleReview;
use App\Models\ArticleReviewer;
use App\Models\ArticleReviewFile;
use App\Models\User;
use App\Services\ArticleReviewService;
use App\Services\DocxInspector;
use Filament\Facades\Filament;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Página do participante de uma revisão de artigo/livro. Acesso por link
 * pessoal (token de 64 caracteres; no banco, hash + cópia criptografada) ou
 * pela conta com senha (Área do Revisor). Só o participante da vez baixa e
 * envia arquivo; todos veem a fila e o histórico público e preenchem a ficha.
 */
class ArticleReviewController extends Controller
{
    // ------------------------------------------------------------------
    // Página do participante
    // ------------------------------------------------------------------

    public function show(Request $request)
    {
        [$reviewer, $token] = $this->context($request);
        $review = $reviewer->articleReview;
        $gate = $this->needsLogin($reviewer, $token);

        if (!$gate && $reviewer->status === ArticleReviewer::STATUS_IN_REVIEW && !$reviewer->first_opened_at) {
            $reviewer->forceFill(['first_opened_at' => now()])->save();
            $review->logEvent("{$reviewer->name} abriu o processo", null, true);
        }

        $queue = $review->reviewers()->get();
        $active = $queue->firstWhere('status', ArticleReviewer::STATUS_IN_REVIEW);

        return $this->noCache(response()->view('article-review.show', [
            'reviewer' => $reviewer,
            'review' => $review,
            'gate' => $gate,
            'isOpen' => !$gate && $this->isOpen($reviewer),
            'canEditProfile' => !$gate && $this->canEditProfile($reviewer),
            'statement' => ArticleReviewService::participationStatement($review, $reviewer),
            'current' => $review->currentVersion(),
            'queue' => $queue,
            'active' => $active,
            'order' => app(ArticleReviewService::class)->queueOrder($reviewer),
            'events' => collect($review->history ?? [])->where('public', true)->reverse()->values(),
            'files' => $gate ? collect() : $review->files()->get(),
            'urls' => $this->urls($reviewer, $token),
            'viaAccount' => $token === null,
            'loggedAsOwner' => Auth::check() && $reviewer->user_id && Auth::id() === $reviewer->user_id,
            'existingUser' => $reviewer->user_id ? $reviewer->user : ($reviewer->email ? User::where('email', $reviewer->email)->first() : null),
        ]));
    }

    public function download(Request $request)
    {
        [$reviewer, $token] = $this->context($request);
        abort_if($this->needsLogin($reviewer, $token), 403);
        abort_unless($this->isOpen($reviewer), 403, 'Só quem está na vez pode baixar o arquivo.');

        $current = $reviewer->articleReview->currentVersion();
        abort_unless(Storage::disk(ArticleReviewService::DISK)->exists($current['path']), 404);

        $reviewer->articleReview->logEvent("{$reviewer->name} baixou a versão v{$current['version']}", $current['filename']);

        return Storage::disk(ArticleReviewService::DISK)->download($current['path'], $current['filename'], [
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function downloadFile(Request $request)
    {
        [$reviewer, $token] = $this->context($request);
        abort_if($this->needsLogin($reviewer, $token), 403);
        abort_unless($this->isOpen($reviewer), 403, 'Só quem está na vez pode baixar os documentos.');

        $file = ArticleReviewFile::where('article_review_id', $reviewer->article_review_id)
            ->whereKey((int) $request->route('file'))
            ->firstOrFail();

        abort_unless(Storage::disk(ArticleReviewService::DISK)->exists($file->path), 404);

        return Storage::disk(ArticleReviewService::DISK)->download($file->path, $file->filename, [
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function saveProfile(Request $request, ArticleReviewService $service)
    {
        [$reviewer, $token] = $this->context($request);
        $back = $this->urls($reviewer, $token)['show'] . '#ficha';

        if ($request->filled('website') || $this->needsLogin($reviewer, $token)) {
            return redirect($back);
        }

        if (!$this->canEditProfile($reviewer)) {
            return redirect($back)->withErrors(['ficha' => 'A ficha não pode mais ser alterada neste processo.']);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'citation_name' => 'required|string|max:120',
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('article_reviewers', 'email')
                    ->where('article_review_id', $reviewer->article_review_id)
                    ->whereNotIn('status', ArticleReviewer::INACTIVE_STATUSES)
                    ->ignore($reviewer->id),
            ],
            'phone' => ['required', 'string', 'max:40', 'regex:/^[\d\s()+.-]{10,}$/'],
            'institution' => 'required|string|max:255',
            'department' => 'nullable|string|max:255',
            'city' => 'required|string|max:120',
            'state' => 'required|string|max:120',
            'country' => 'required|string|max:120',
            'no_orcid' => 'nullable|boolean',
            'orcid' => 'nullable|string|max:60',
            'roles' => 'required|array|min:1',
            'roles.*' => ['string', Rule::in(array_keys(ArticleReviewer::CREDIT_ROLES))],
            'contribution_other' => 'nullable|string|max:255',
            'profile_notes' => 'nullable|string|max:3000',
            'checklist' => 'required|array',
            'checklist.*' => ['string', Rule::in(array_keys(ArticleReviewer::CHECKLIST))],
            'accept_participation' => 'accepted',
            'aware_content' => 'accepted',
        ], [
            'name.required' => 'Informe seu nome completo.',
            'citation_name.required' => 'Informe o nome para citação (ex.: SILVA, M. A.).',
            'email.required' => 'Informe seu e-mail.',
            'email.unique' => 'Este e-mail já está cadastrado para outro participante deste processo.',
            'phone.required' => 'Informe seu telefone (WhatsApp).',
            'phone.regex' => 'Telefone inválido. Use DDD + número, ex.: (11) 98765-4321.',
            'institution.required' => 'Informe a instituição da sua filiação.',
            'city.required' => 'Informe a cidade da filiação.',
            'state.required' => 'Informe o estado da filiação.',
            'country.required' => 'Informe o país da filiação.',
            'roles.required' => 'Marque ao menos um tipo de participação (CRediT).',
            'checklist.required' => 'Marque todos os itens da lista de verificação.',
            'accept_participation.accepted' => 'Confirme o aceite em participar do trabalho.',
            'aware_content.accepted' => 'Confirme que está ciente do conteúdo do trabalho.',
        ]);

        $missing = array_diff(array_keys(ArticleReviewer::CHECKLIST), $data['checklist']);
        if ($missing) {
            return redirect($back)->withInput()->withErrors(['checklist' => 'Confira e marque todos os itens da lista de verificação.']);
        }

        $data['no_orcid'] = (bool) ($data['no_orcid'] ?? false);
        if (!$data['no_orcid']) {
            $orcid = ArticleReviewer::normalizeOrcid($data['orcid'] ?? null);
            if (!$orcid) {
                return redirect($back)->withInput()->withErrors(['orcid' => 'ORCID inválido. Use o formato 0000-0000-0000-0000 (confira o último dígito) ou marque "Não possuo ORCID".']);
            }
            $data['orcid'] = $orcid;
        }

        $data['email'] = mb_strtolower(trim($data['email']));
        if ($reviewer->user_id && $reviewer->email && $data['email'] !== $reviewer->email) {
            return redirect($back)->withInput()->withErrors(['email' => 'Seu e-mail está vinculado a uma conta com senha. Para trocá-lo, fale com o Prof. Atilio.']);
        }

        $service->saveProfile($reviewer, $data, $request->ip() ?? '0.0.0.0');

        return redirect($back)->with('success', 'Ficha confirmada. Obrigado! Você pode corrigi-la enquanto o processo estiver aberto.');
    }

    public function store(Request $request, ArticleReviewService $service)
    {
        [$reviewer, $token] = $this->context($request);
        $back = $this->urls($reviewer, $token)['show'];

        if ($request->filled('website') || $this->needsLogin($reviewer, $token)) {
            return redirect($back);
        }

        if (!$this->isOpen($reviewer)) {
            return redirect($back)->withErrors(['document' => 'Esta etapa de revisão não está mais aberta para envio.']);
        }

        if (!$reviewer->isProfileComplete()) {
            return redirect($back . '#ficha')->withErrors(['ficha' => 'Antes de enviar o arquivo, preencha e confirme a sua ficha de participante.']);
        }

        $request->validate([
            'document' => 'required|file|max:' . ArticleReviewService::MAX_FILE_KB,
            'extras' => 'nullable|array|max:5',
            'extras.*' => 'file|max:' . ArticleReviewService::MAX_SUPPLEMENTARY_KB,
            'comments' => 'nullable|string|max:20000',
            'suggestions' => 'nullable|string|max:20000',
            'accept_participation' => 'accepted',
        ], [
            'document.required' => 'Selecione o arquivo revisado (.docx).',
            'document.max' => 'O arquivo não pode passar de 30MB.',
            'document.uploaded' => 'Falha no envio do arquivo. Tente novamente.',
            'extras.max' => 'Envie no máximo 5 documentos complementares.',
            'extras.*.max' => 'Cada documento complementar pode ter até 20MB.',
            'accept_participation.accepted' => 'Para concluir, confirme a declaração de participação.',
            'comments.max' => 'Os comentários passaram do limite de 20.000 caracteres. Coloque-os no próprio documento.',
            'suggestions.max' => 'As sugestões passaram do limite de 20.000 caracteres.',
        ]);

        $keep = fn () => redirect($back)->withInput($request->only('comments', 'suggestions'));

        $file = $request->file('document');
        $extension = strtolower($file->getClientOriginalExtension());
        $content = file_get_contents($file->getRealPath());

        [$ok, $error] = ArticleReviewService::validateDocument($content, $extension);
        if (!$ok) {
            return $keep()->withErrors(['document' => $error]);
        }

        $extras = [];
        foreach ($request->file('extras', []) as $extra) {
            $extContent = file_get_contents($extra->getRealPath());
            [$ok, $error] = ArticleReviewService::validateSupplementary($extContent, $extra->getClientOriginalExtension());
            if (!$ok) {
                return $keep()->withErrors(['extras' => $extra->getClientOriginalName() . ': ' . $error]);
            }
            $extras[] = ['content' => $extContent, 'filename' => $extra->getClientOriginalName()];
        }

        // Acusa envio provavelmente errado e pede confirmação explícita.
        $changes = $service->inspectUpload($reviewer->articleReview, $content, $extension);
        $blocking = array_values(array_intersect($changes['alerts'] ?? [], ['identical', 'different_document', 'much_shorter']));
        if ($blocking && !$request->boolean('confirm_file')) {
            return $keep()
                ->with('needs_confirm', array_map(fn ($a) => DocxInspector::ALERT_LABELS[$a], $blocking))
                ->withErrors(['document' => 'Confira o arquivo antes de enviar (veja o aviso abaixo). Se for o arquivo certo, marque a confirmação e selecione-o de novo.']);
        }

        try {
            $service->submitReview(
                $reviewer,
                $content,
                $extension,
                $request->input('comments'),
                $request->input('suggestions'),
                $request->ip() ?? '0.0.0.0',
                (string) $request->userAgent(),
                $changes ? $changes + ['confirmed_alerts' => $blocking] : null,
                $extras
            );
        } catch (\RuntimeException $e) {
            return $keep()->withErrors(['document' => $e->getMessage()]);
        } catch (\Throwable $e) {
            Log::error('Revisão de artigo: erro ao gravar devolução do participante ' . $reviewer->id . ': ' . $e->getMessage());

            return $keep()->withErrors(['document' => 'Não foi possível registrar o envio agora. Tente novamente em alguns minutos.']);
        }

        return redirect($back)->with('success', 'Revisão recebida! Você receberá um comprovante por e-mail. Obrigado pela colaboração.');
    }

    /**
     * Criar senha (conta de revisor) ou entrar com a senha, a partir do link.
     */
    public function access(Request $request)
    {
        [$reviewer, $token] = $this->context($request);
        abort_if($token === null, 404);
        $back = $this->urls($reviewer, $token)['show'];

        if ($request->filled('website')) {
            return redirect($back);
        }

        $review = $reviewer->articleReview;

        if ($request->input('mode') === 'login') {
            $request->validate(['password' => 'required|string']);
            $user = $reviewer->user ?: ($reviewer->email ? User::where('email', $reviewer->email)->first() : null);

            if (!$user || !Auth::attempt(['email' => $user->email, 'password' => $request->input('password')])) {
                return redirect($back . '#conta')->withErrors(['password' => 'Senha incorreta. Use "Esqueci minha senha" se precisar.']);
            }

            $request->session()->regenerate();
            if (!$reviewer->user_id) {
                $reviewer->forceFill(['user_id' => $user->id])->save();
            }
            $review->logEvent("{$reviewer->name} entrou com senha");

            return redirect($back)->with('success', 'Você entrou na sua conta.');
        }

        if ($reviewer->user_id) {
            return redirect($back . '#conta')->withErrors(['password' => 'Você já tem conta. Entre com sua senha.']);
        }

        $request->validate([
            'email' => [$reviewer->email ? 'nullable' : 'required', 'email', 'max:255'],
            'password' => 'required|string|min:8|confirmed',
        ], [
            'email.required' => 'Informe seu e-mail.',
            'password.min' => 'A senha precisa ter pelo menos 8 caracteres.',
            'password.confirmed' => 'As senhas não conferem.',
        ]);

        $email = mb_strtolower(trim($reviewer->email ?: $request->input('email')));

        if (User::where('email', $email)->exists()) {
            return redirect($back . '#conta')->withErrors(['password' => 'Já existe uma conta com este e-mail. Entre com a sua senha (ou use "Esqueci minha senha").']);
        }

        $user = User::create([
            'name' => $reviewer->name,
            'email' => $email,
            'password' => $request->input('password'),
            'role' => 'reviewer',
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        if (!$reviewer->email) {
            $reviewer->email = $email;
        }
        $reviewer->user_id = $user->id;
        $reviewer->save();

        // Outros processos com o mesmo e-mail também ficam na conta.
        ArticleReviewer::where('email', $email)->whereNull('user_id')->update(['user_id' => $user->id]);

        Auth::login($user);
        $request->session()->regenerate();
        $review->logEvent("{$reviewer->name} criou conta com senha", $email);

        return redirect($back)->with('success', 'Conta criada! Você também pode acessar seus processos pela Área do Revisor.');
    }

    // ------------------------------------------------------------------
    // Link de cadastro do processo
    // ------------------------------------------------------------------

    public function signupForm(string $token)
    {
        $review = $this->resolveSignup($token);

        return $this->noCache(response()->view('article-review.signup', [
            'review' => $review,
            'token' => $token,
            'claimable' => $review->participants()->whereNull('email')->get(),
        ]));
    }

    public function signupStore(Request $request, string $token, ArticleReviewService $service)
    {
        $review = $this->resolveSignup($token);
        $back = route('article-review.signup', ['token' => $token]);

        if ($request->filled('website')) {
            return redirect($back);
        }

        $data = $request->validate([
            'claim_id' => 'nullable|integer',
            'name' => 'required_without:claim_id|nullable|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => ['required', 'string', 'max:40', 'regex:/^[\d\s()+.-]{10,}$/'],
            'agree' => 'accepted',
        ], [
            'name.required_without' => 'Informe seu nome completo ou selecione seu nome na lista.',
            'email.required' => 'Informe seu e-mail.',
            'phone.required' => 'Informe seu telefone (WhatsApp).',
            'phone.regex' => 'Telefone inválido. Use DDD + número.',
            'agree.accepted' => 'Confirme que deseja participar deste processo.',
        ]);

        $data['email'] = mb_strtolower(trim($data['email']));

        if ($review->participants()->where('email', $data['email'])->exists()) {
            return redirect($back)->withInput()->withErrors(['email' => 'Este e-mail já está no processo. Use o link que você recebeu por e-mail ou peça ao Prof. Atilio para reenviá-lo.']);
        }

        try {
            $reviewer = $service->registerFromSignup($review, $data['claim_id'] ?? null, $data);
        } catch (\RuntimeException $e) {
            return redirect($back)->withInput()->withErrors(['claim_id' => $e->getMessage()]);
        }

        return redirect($reviewer->accessUrl() . '#ficha')
            ->with('success', 'Cadastro registrado! Agora complete e confirme a sua ficha abaixo. Guarde este link (também foi enviado por e-mail).');
    }

    // ------------------------------------------------------------------
    // Auxiliares
    // ------------------------------------------------------------------

    /**
     * @return array{0: ArticleReviewer, 1: ?string}
     */
    protected function context(Request $request): array
    {
        $token = $request->route('token');

        if ($token !== null) {
            $reviewer = ArticleReviewer::findByToken((string) $token);
            abort_if(!$reviewer, 404);
        } else {
            if (!Auth::check()) {
                throw new HttpResponseException(redirect()->guest(Filament::getPanel('revisor')->getLoginUrl()));
            }
            $reviewer = ArticleReviewer::whereKey((int) $request->route('articleReviewer'))->first();
            abort_if(!$reviewer || $reviewer->user_id !== Auth::id() || $reviewer->status === ArticleReviewer::STATUS_REMOVED, 404);
        }

        $review = $reviewer->articleReview;
        abort_if(!$review || $review->status === ArticleReview::STATUS_CANCELLED, 404);

        return [$reviewer, $token];
    }

    protected function urls(ArticleReviewer $reviewer, ?string $token): array
    {
        if ($token !== null) {
            $p = ['token' => $token];

            return [
                'show' => route('article-review.show', $p),
                'download' => route('article-review.download', $p),
                'store' => route('article-review.store', $p),
                'profile' => route('article-review.profile', $p),
                'access' => route('article-review.access', $p),
                'file' => fn (int $id) => route('article-review.file', $p + ['file' => $id]),
            ];
        }

        $p = ['articleReviewer' => $reviewer->id];

        return [
            'show' => route('article-review.account.show', $p),
            'download' => route('article-review.account.download', $p),
            'store' => route('article-review.account.store', $p),
            'profile' => route('article-review.account.profile', $p),
            'access' => null,
            'file' => fn (int $id) => route('article-review.account.file', $p + ['file' => $id]),
        ];
    }

    /**
     * Processo com "exigir senha": o link sozinho não basta, a pessoa precisa
     * estar logada na conta vinculada.
     */
    protected function needsLogin(ArticleReviewer $reviewer, ?string $token): bool
    {
        if ($token === null || !$reviewer->articleReview->require_login) {
            return false;
        }

        return !(Auth::check() && $reviewer->user_id && Auth::id() === $reviewer->user_id);
    }

    protected function isOpen(ArticleReviewer $reviewer): bool
    {
        return $reviewer->status === ArticleReviewer::STATUS_IN_REVIEW
            && $reviewer->articleReview->status === ArticleReview::STATUS_IN_REVIEW;
    }

    protected function canEditProfile(ArticleReviewer $reviewer): bool
    {
        return $reviewer->isActive() && !$reviewer->articleReview->isClosed();
    }

    protected function resolveSignup(string $token): ArticleReview
    {
        $review = ArticleReview::findBySignupToken($token);
        abort_if(!$review || !$review->signup_enabled || $review->isClosed(), 404);

        return $review;
    }

    protected function noCache($response)
    {
        return $response
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('Cache-Control', 'no-store, private');
    }
}
