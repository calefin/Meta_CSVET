<?php

namespace App\Livewire\Admin;

use App\Mail\TelegramCoursePaymentReminderMail;
use App\Models\Payment;
use App\Models\TelegramCourseAttendance;
use App\Models\TelegramCourseLesson;
use App\Models\TelegramCourseSubscription;
use App\Services\MercadoPagoService;
use App\Services\TelegramBotService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class TelegramCourseManager extends Component
{
    use WithPagination;
    use WithFileUploads;

    public string $activeTab = 'subscribers'; // subscribers, attendance, lessons, broadcast

    public string $search = '';

    public string $statusFilter = 'all';

    public ?int $editingSubId = null;

    public string $notes = '';

    public ?string $generatedLink = null;

    public ?string $generatedLinkUser = null;

    public ?string $generatedInviteLink = null;

    public ?string $generatedInviteLinkUser = null;

    // Attendance Tab
    public string $meetingDate = '';

    public array $attendanceStates = []; // subId => bool

    // Lessons Tab
    public ?int $editingLessonId = null;

    public string $lessonTitle = '';

    public string $lessonDescription = '';

    public string $lessonType = 'weekly_telegram';

    public string $lessonChecklist = '';

    public string $lessonContent = '';

    public $lessonDocument = null;

    public ?string $currentDocumentPath = null;

    // Broadcast Tab
    public string $broadcastText = '';

    public function mount(): void
    {
        $this->meetingDate = Carbon::today()->format('Y-m-d');
        $this->loadAttendanceStates();
    }

    public function loadAttendanceStates(): void
    {
        $date = $this->meetingDate ?: Carbon::today()->format('Y-m-d');
        $records = TelegramCourseAttendance::where('meeting_date', $date)->get()->keyBy('telegram_course_subscription_id');

        $subs = TelegramCourseSubscription::where('status', 'active')->get();
        $states = [];
        foreach ($subs as $sub) {
            $states[$sub->id] = isset($records[$sub->id]) ? (bool) $records[$sub->id]->attended : true;
        }
        $this->attendanceStates = $states;
    }

    public function toggleAttendance(int $subId): void
    {
        $date = $this->meetingDate ?: Carbon::today()->format('Y-m-d');
        $current = $this->attendanceStates[$subId] ?? true;
        $newStatus = ! $current;

        TelegramCourseAttendance::updateOrCreate(
            [
                'telegram_course_subscription_id' => $subId,
                'meeting_date' => $date,
            ],
            [
                'attended' => $newStatus,
            ]
        );

        $this->attendanceStates[$subId] = $newStatus;
        session()->flash('success', 'Frequência do encontro presencial atualizada.');
    }

    public function editLesson(int $id): void
    {
        $lesson = TelegramCourseLesson::findOrFail($id);

        $this->editingLessonId = $lesson->id;
        $this->lessonTitle = $lesson->title;
        $this->lessonDescription = (string) $lesson->description;
        $this->lessonType = $lesson->type;
        $this->lessonChecklist = (string) $lesson->checklist_materials;
        $this->lessonContent = (string) $lesson->content;
        $this->currentDocumentPath = $lesson->document_path;
        $this->lessonDocument = null;
    }

    public function cancelEditLesson(): void
    {
        $this->editingLessonId = null;
        $this->lessonTitle = '';
        $this->lessonDescription = '';
        $this->lessonType = 'weekly_telegram';
        $this->lessonChecklist = '';
        $this->lessonContent = '';
        $this->lessonDocument = null;
        $this->currentDocumentPath = null;
    }

    public function saveLesson(): void
    {
        $validated = $this->validate([
            'lessonTitle' => 'required|string|max:255',
            'lessonDescription' => 'nullable|string',
            'lessonType' => 'required|in:weekly_telegram,presential_meeting',
            'lessonChecklist' => 'nullable|string',
            'lessonContent' => 'nullable|string',
            'lessonDocument' => 'nullable|file|mimes:pdf|max:51200',
        ]);

        $documentPath = $this->currentDocumentPath;

        if ($this->lessonDocument) {
            if ($this->currentDocumentPath) {
                Storage::disk('local')->delete($this->currentDocumentPath);
            }
            $documentPath = $this->lessonDocument->store('telegram-course/materials', 'local');
        }

        $data = [
            'title' => trim($validated['lessonTitle']),
            'description' => trim($validated['lessonDescription'] ?? ''),
            'type' => $validated['lessonType'],
            'checklist_materials' => trim($validated['lessonChecklist'] ?? ''),
            'content' => $validated['lessonContent'] ? clean($validated['lessonContent'], 'lesson_content') : null,
            'document_path' => $documentPath,
        ];

        if ($this->editingLessonId) {
            TelegramCourseLesson::findOrFail($this->editingLessonId)->update($data);
            session()->flash('success', 'Conteúdo atualizado com sucesso!');
        } else {
            $data['published_at'] = now();
            TelegramCourseLesson::create($data);
            session()->flash('success', 'Conteúdo / Aula do curso publicado com sucesso!');
        }

        $this->cancelEditLesson();
    }

    public function removeLessonDocument(): void
    {
        if (! $this->editingLessonId || ! $this->currentDocumentPath) {
            return;
        }

        Storage::disk('local')->delete($this->currentDocumentPath);

        TelegramCourseLesson::findOrFail($this->editingLessonId)->update(['document_path' => null]);
        $this->currentDocumentPath = null;
        session()->flash('info', 'Apostila em PDF removida desta aula.');
    }

    public function deleteLesson(int $id): void
    {
        $lesson = TelegramCourseLesson::findOrFail($id);

        if ($lesson->document_path) {
            Storage::disk('local')->delete($lesson->document_path);
        }

        $lesson->delete();

        if ($this->editingLessonId === $id) {
            $this->cancelEditLesson();
        }

        session()->flash('info', 'Aula removida.');
    }

    public function sendBroadcast(): void
    {
        $this->validate([
            'broadcastText' => 'required|string|min:5',
        ]);

        $success = TelegramBotService::notifyAdmin("📢 <b>COMUNICADO CURSO TELEGRAM</b>\n\n".htmlspecialchars($this->broadcastText, ENT_QUOTES));

        if ($success) {
            $this->broadcastText = '';
            session()->flash('success', 'Comunicado enviado com sucesso para o Telegram do Admin / Grupo!');
        } else {
            session()->flash('error', 'Não foi possível enviar via Telegram Bot. Verifique as credenciais do bot nas configurações.');
        }
    }

    public function generateUniqueInviteLink(): void
    {
        $groupChatId = config('services.telegram.course_group_chat_id');
        if (! $groupChatId) {
            session()->flash('error', 'Configure a variável TELEGRAM_COURSE_GROUP_CHAT_ID para gerar links de convite via API.');

            return;
        }

        $link = TelegramBotService::createChatInviteLink($groupChatId, 86400, 1);

        if ($link) {
            $this->generatedInviteLink = $link;
            $this->generatedInviteLinkUser = null;
            session()->flash('success', 'Link de convite temporário exclusivo de 1 uso (24h) gerado com sucesso!');
        } else {
            session()->flash('error', 'Não foi possível gerar o link de convite. Certifique-se de que o bot possui permissão de administrador no grupo.');
        }
    }

    public function generateInviteLinkForSub(int $subId): void
    {
        $sub = TelegramCourseSubscription::findOrFail($subId);

        $groupChatId = config('services.telegram.course_group_chat_id');
        if (! $groupChatId) {
            session()->flash('error', 'Configure a variável TELEGRAM_COURSE_GROUP_CHAT_ID para gerar links de convite via API.');

            return;
        }

        $link = TelegramBotService::createChatInviteLink($groupChatId, 86400, 1);

        if ($link) {
            $this->generatedInviteLink = $link;
            $this->generatedInviteLinkUser = $sub->full_name.' ('.$sub->telegram_username.')';
            session()->flash('success', 'Link de convite exclusivo gerado para '.$sub->full_name.'!');
        } else {
            session()->flash('error', 'Não foi possível gerar o link de convite. Certifique-se de que o bot possui permissão de administrador no grupo.');
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function markAsPaid(int $subId): void
    {
        $sub = TelegramCourseSubscription::findOrFail($subId);

        $newExpiry = TelegramCourseSubscription::nextDueDate($sub->expires_at);

        $sub->update([
            'status' => 'active',
            'expires_at' => $newExpiry,
        ]);

        Payment::create([
            'user_id' => $sub->user_id ?? auth()->id(),
            'payable_type' => TelegramCourseSubscription::class,
            'payable_id' => $sub->id,
            'amount' => 180.00,
            'status' => 'paid',
            'paid_at' => now(),
            'mp_payment_type' => 'manual',
            'mp_payment_method' => 'Dinheiro / Pix direto',
        ]);

        session()->flash('success', 'Pagamento manual registrado e assinatura de '.$sub->full_name.' estendida até '.$newExpiry->format('d/m/Y').'.');
    }

    public function generatePaymentLink(int $subId): void
    {
        $sub = TelegramCourseSubscription::findOrFail($subId);

        $payment = Payment::findOrCreatePending(
            payableType: TelegramCourseSubscription::class,
            payableId: $sub->id,
            userId: $sub->user_id ?? auth()->id(),
            amount: 180.00,
            scopeToUser: false
        );

        $mpService = new MercadoPagoService;
        $url = $mpService->createPreference($payment, 'Mensalidade Curso Telegram — '.$sub->full_name, maxInstallments: 1);

        $this->generatedLink = $url ?? $payment->checkout_url ?? '#';
        $this->generatedLinkUser = $sub->full_name.' ('.$sub->telegram_username.')';

        session()->flash('success', 'Link de pagamento do Mercado Pago gerado com sucesso!');
    }

    public function removeFromGroup(int $subId): void
    {
        $sub = TelegramCourseSubscription::findOrFail($subId);

        if (! $sub->telegram_user_id) {
            session()->flash('error', 'Ainda não identificamos o ID numérico do Telegram de '.$sub->full_name.' — isso é capturado automaticamente na primeira mensagem que a pessoa mandar pro bot ou no grupo. Peça pra ela mandar qualquer mensagem, ou remova manualmente pelo próprio Telegram.');

            return;
        }

        $groupChatId = config('services.telegram.course_group_chat_id');
        if (! $groupChatId) {
            session()->flash('error', 'Configure a variável TELEGRAM_COURSE_GROUP_CHAT_ID para remover alunos do grupo via API.');

            return;
        }

        $banned = TelegramBotService::banChatMember($groupChatId, $sub->telegram_user_id);

        if ($banned) {
            // Unban right after so the removal is a one-time kick, not a
            // permanent block - the person can rejoin with a new invite
            // link once they pay again.
            TelegramBotService::unbanChatMember($groupChatId, $sub->telegram_user_id);
            session()->flash('success', $sub->full_name.' foi removido(a) do grupo do Telegram.');
        } else {
            session()->flash('error', 'Não foi possível remover '.$sub->full_name.' do grupo. Certifique-se de que o bot é administrador do grupo com permissão de banir membros.');
        }
    }

    public function cancelSubscription(int $subId): void
    {
        $sub = TelegramCourseSubscription::findOrFail($subId);
        $sub->update(['status' => 'cancelled']);
        session()->flash('info', 'Inscrição de '.$sub->full_name.' foi marcada como cancelada.');
    }

    public function sendReminderEmail(int $subId): void
    {
        $sub = TelegramCourseSubscription::findOrFail($subId);

        $payment = Payment::findOrCreatePending(
            payableType: TelegramCourseSubscription::class,
            payableId: $sub->id,
            userId: $sub->user_id ?? auth()->id(),
            amount: 180.00,
            scopeToUser: false
        );

        $mpService = new MercadoPagoService;
        $url = $mpService->createPreference($payment, 'Mensalidade Curso Telegram — '.$sub->full_name, maxInstallments: 1);
        $paymentUrl = $url ?? $payment->checkout_url ?? route('public-telegram-course-registration');

        try {
            Mail::to($sub->email)->send(
                new TelegramCoursePaymentReminderMail($sub, $paymentUrl)
            );
            session()->flash('success', 'E-mail de lembrete de renovação enviado com sucesso para '.$sub->email.'!');
        } catch (\Throwable $e) {
            session()->flash('error', 'Não foi possível enviar o e-mail: '.$e->getMessage());
        }
    }

    public function saveNotes(int $subId): void
    {
        $sub = TelegramCourseSubscription::findOrFail($subId);
        $sub->update(['notes' => $this->notes]);
        $this->editingSubId = null;
        session()->flash('success', 'Anotações atualizadas com sucesso.');
    }

    public function editNotes(int $subId): void
    {
        $sub = TelegramCourseSubscription::findOrFail($subId);
        $this->editingSubId = $subId;
        $this->notes = $sub->notes ?? '';
    }

    public function cancelEditNotes(): void
    {
        $this->editingSubId = null;
        $this->notes = '';
    }

    public function getFormattedTelegramReportProperty(): string
    {
        return TelegramBotService::generateCourseStatusReport();
    }

    public function render()
    {
        $query = TelegramCourseSubscription::query();

        if ($this->search) {
            $term = '%'.$this->search.'%';
            $query->where(function ($q) use ($term) {
                $q->where('full_name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('telegram_username', 'like', $term)
                    ->orWhere('phone', 'like', $term);
            });
        }

        if ($this->statusFilter === 'active') {
            $query->where('status', 'active')->where('expires_at', '>', now());
        } elseif ($this->statusFilter === 'overdue') {
            $query->where(function ($q) {
                $q->where('status', 'overdue')
                    ->orWhere(function ($q2) {
                        $q2->where('status', 'active')->where('expires_at', '<=', now());
                    });
            });
        } elseif ($this->statusFilter === 'pending_payment') {
            $query->where('status', 'pending_payment')->whereNull('expires_at');
        } elseif ($this->statusFilter === 'cancelled') {
            $query->where('status', 'cancelled');
        }

        $subscriptions = $query->orderByDesc('updated_at')->paginate(15);

        $totalSubscribers = TelegramCourseSubscription::count();
        $allSubs = TelegramCourseSubscription::all();
        $activeCount = $allSubs->filter(fn ($s) => $s->isActive())->count();
        $overdueCount = $allSubs->filter(fn ($s) => $s->isOverdue())->count();
        $estimatedMonthlyRevenue = $activeCount * 180.00;

        $activeSubscribers = TelegramCourseSubscription::where('status', 'active')->orderBy('full_name')->get();
        $lessons = TelegramCourseLesson::orderByDesc('published_at')->get();

        return view('livewire.admin.telegram-course-manager', [
            'subscriptions' => $subscriptions,
            'activeSubscribers' => $activeSubscribers,
            'lessons' => $lessons,
            'totalSubscribers' => $totalSubscribers,
            'activeCount' => $activeCount,
            'overdueCount' => $overdueCount,
            'estimatedMonthlyRevenue' => $estimatedMonthlyRevenue,
            'formattedReport' => $this->formattedTelegramReport,
        ])->layout('components.layouts.admin', [
            'title' => 'Gestão do Curso Telegram — Ìjọ Ifá Èjìogbè',
        ]);
    }
}
