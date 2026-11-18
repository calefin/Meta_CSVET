<?php

use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\BudgetPdfController;
use App\Http\Controllers\CourseCertificateController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DataExportController;
use App\Http\Controllers\LessonMaterialController;
use App\Livewire\Admin\AdminNotes;
use App\Livewire\Admin\Agenda;
use App\Livewire\Admin\Announcements;
use App\Livewire\Admin\Events;
use App\Livewire\Admin\AuditLogViewer;
use App\Livewire\Admin\Catalogs\Animals;
use App\Livewire\Admin\Catalogs\IreIbiTypes;
use App\Livewire\Admin\Catalogs\Odus;
use App\Livewire\Admin\Catalogs\RitualWorks;
use App\Livewire\Admin\Catalogs\Supplies;
use App\Livewire\Admin\Catalogs\Suppliers;
use App\Livewire\Admin\ClientProfile;
use App\Livewire\Admin\Courses\CourseBuilder;
use App\Livewire\Admin\Courses\CourseList;
use App\Livewire\Admin\Courses\QuizReview;
use App\Livewire\Admin\DeletionRequests;
use App\Livewire\Admin\Diary;
use App\Livewire\Admin\FinancialDashboard;
use App\Livewire\Admin\InitiationManager;
use App\Livewire\Admin\Library\AccessManager;
use App\Livewire\Admin\Library\CategoryManager;
use App\Livewire\Admin\Library\DocumentManager;
use App\Livewire\Admin\Oracular\BudgetBuilder;
use App\Livewire\Admin\Oracular\ConsultationDetail;
use App\Livewire\Admin\Oracular\NewWork;
use App\Livewire\Admin\Oracular\PendingFulfillments;
use App\Livewire\Admin\Oracular\RequestsQueue;
use App\Livewire\Admin\Oracular\WorksDashboard;
use App\Livewire\Admin\PendingApprovals;
use App\Livewire\Admin\PriceMonitor;
use App\Livewire\Admin\UserManagement;
use App\Livewire\Auth\CompleteGoogleRegistration;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Consulente\Consultations;
use App\Livewire\Consulente\MyAppointments;
use App\Livewire\Iniciado\MyInitiation;
use App\Livewire\Iniciado\SpiritualFamily;
use App\Livewire\Library\Browser as LibraryBrowser;
use App\Livewire\Library\Viewer as LibraryViewer;
use App\Livewire\Shared\Inbox;
use App\Livewire\Shared\MyData;
use App\Livewire\Shared\Profile;
use App\Livewire\Student\CourseShow;
use App\Livewire\Student\CoursesList;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('home'))->name('home');
Route::get('/en', fn () => view('home'))->name('home.en');
Route::get('/es', fn () => view('home'))->name('home.es');

Route::get('/politica-de-privacidade', fn () => view('privacy'))->name('privacy');
Route::get('/en/privacy-policy', fn () => view('privacy'))->name('privacy.en');
Route::get('/es/politica-de-privacidad', fn () => view('privacy'))->name('privacy.es');

Route::get('/blog', \App\Livewire\PublicArticlesList::class)->name('blog.index');
Route::get('/blog/{article:slug}', \App\Livewire\PublicArticleShow::class)->name('blog.show');

Route::get('/sitemap.xml', \App\Http\Controllers\SitemapController::class)->name('sitemap');

Route::middleware('guest')->group(function () {
    Route::get('/registro', Register::class)->name('register');
    Route::get('/registro/completar', CompleteGoogleRegistration::class)->name('register.complete');
    Route::get('/entrar', Login::class)->name('login');
    Route::get('/esqueci-senha', ForgotPassword::class)->name('password.request');
    Route::get('/redefinir-senha/{token}', ResetPassword::class)->name('password.reset');
    Route::get('/solicitar-consulta', \App\Livewire\PublicConsultationRequest::class)->name('public-consultation-request');
    Route::get('/en/request-consultation', \App\Livewire\PublicConsultationRequest::class)->name('public-consultation-request.en');
    Route::get('/es/solicitar-consulta', \App\Livewire\PublicConsultationRequest::class)->name('public-consultation-request.es');

    Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
});

// Path to bypass multi-views / ModSecurity "406 Not Acceptable" errors.
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])
    ->middleware('guest')
    ->name('auth.google.callback');

Route::get('/curso-telegram', \App\Livewire\PublicTelegramCourseRegistration::class)->name('public-telegram-course-registration');

Route::get('/pagar/{uuid}', \App\Livewire\PublicQuickCheckout::class)->name('quick-checkout');

// Alternative path for compatibility if needed.
Route::get('/api/auth/google/callback', [GoogleAuthController::class, 'callback'])
    ->middleware('guest')
    ->name('auth.google.callback.alt');

Route::get('/email/verificar/{id}/{hash}', EmailVerificationController::class)
    ->middleware('signed')
    ->name('verification.verify');

// Matches MERCADO_PAGO_WEBHOOK_URL already configured in production.
// Excluded from CSRF in bootstrap/app.php since Mercado Pago calls this
// directly with no session/token.
Route::post('/api/webhooks/mercadopago', \App\Http\Controllers\MercadoPagoWebhookController::class)
    ->middleware('throttle:60,1')
    ->name('webhooks.mercadopago');

// Excluded from CSRF — Telegram calls this directly with no session/token.
// Validated via X-Telegram-Bot-Api-Secret-Token header instead.
Route::post('/api/webhooks/telegram', \App\Http\Controllers\TelegramWebhookController::class)
    ->name('webhooks.telegram');

Route::post('/sair', LogoutController::class)->name('logout')->middleware('auth');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/painel', DashboardController::class)->name('dashboard');
    Route::get('/calendario-ose', \App\Livewire\Shared\OseCalendarView::class)->name('ose-calendar');
    Route::get('/orcamentos/{budget}/pdf', BudgetPdfController::class)->name('budgets.pdf');
    Route::get('/mensalidades/{payment}/recibo', \App\Http\Controllers\TelegramSubscriptionReceiptPdfController::class)->name('telegram-subscriptions.receipt');
    Route::get('/materiais-curso/{lesson}/pdf', \App\Http\Controllers\TelegramCourseLessonMaterialPdfController::class)->name('telegram-course.lessons.material-pdf');
    Route::get('/consultas/{consultationRequest}/anexo', \App\Http\Controllers\ConsultationAttachmentController::class)->name('consultation-requests.attachment');

    Route::get('/perfil', Profile::class)->name('profile');
    Route::get('/meus-dados', MyData::class)->name('my-data');
    Route::get('/meus-dados/exportar.json', [DataExportController::class, 'json'])->name('my-data.export.json');
    Route::get('/meus-dados/exportar.pdf', [DataExportController::class, 'pdf'])->name('my-data.export.pdf');

    Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function () {
        Route::get('/', \App\Livewire\Admin\Dashboard::class)->name('dashboard');
        Route::get('/aprovacoes', PendingApprovals::class)->name('approvals');
        Route::get('/usuarios', UserManagement::class)->name('users');

        Route::prefix('catalogos')->name('catalogs.')->group(function () {
            Route::get('/odus', Odus::class)->name('odus');
            Route::get('/ibi-ire', IreIbiTypes::class)->name('ibi-ire');
            Route::get('/trabalhos', RitualWorks::class)->name('ritual-works');
            Route::get('/fornecedores', Suppliers::class)->name('suppliers');
            Route::get('/insumos', Supplies::class)->name('supplies');
            Route::get('/animais', Animals::class)->name('animals');
        });

        Route::prefix('oracular')->name('oracular.')->group(function () {
            Route::get('/', RequestsQueue::class)->name('queue');
            Route::get('/trabalhos', WorksDashboard::class)->name('works');
            Route::get('/atendimentos-pendentes', PendingFulfillments::class)->name('pending-fulfillments');
            Route::get('/novo-trabalho', NewWork::class)->name('new-work');
            Route::get('/simulador-trabalhos', \App\Livewire\Admin\Oracular\WorkSimulator::class)->name('simulator');
            Route::get('/trabalhos/{recommendedWork}/orcamento', BudgetBuilder::class)->name('budget');
            Route::get('/link-pagamento', \App\Livewire\Admin\Oracular\QuickPaymentBuilder::class)->name('quick-payment');
            Route::get('/{consultationRequest}', ConsultationDetail::class)->name('show');
        });

        Route::get('/clientes/{user}', ClientProfile::class)->name('clients.show');

        Route::get('/agenda', Agenda::class)->name('agenda');
        Route::get('/mensagens', Inbox::class)->name('messages');
        Route::get('/avisos', Announcements::class)->name('announcements');
        Route::get('/eventos', Events::class)->name('events');
        Route::get('/enviar-email', \App\Livewire\Admin\EmailBroadcast::class)->name('email-broadcast');
        Route::get('/anotacoes', AdminNotes::class)->name('notes');
        Route::get('/anotacoes/{targetUserId}', AdminNotes::class)->name('notes.user');
        Route::get('/diario', Diary::class)->name('diary');
        Route::get('/exclusoes', DeletionRequests::class)->name('deletion-requests');
        Route::get('/auditoria', AuditLogViewer::class)->name('audit-log');
        Route::get('/backups', \App\Livewire\Admin\BackupLogViewer::class)->name('backups');

        Route::prefix('financeiro')->name('finance.')->group(function () {
            Route::get('/', FinancialDashboard::class)->name('index');
            Route::get('/precos', PriceMonitor::class)->name('prices');
            Route::get('/cobrancas', \App\Livewire\Admin\Payments::class)->name('payments');
        });

        Route::prefix('cursos')->name('courses.')->group(function () {
            Route::get('/', CourseList::class)->name('index');
            Route::get('/telegram', \App\Livewire\Admin\TelegramCourseManager::class)->name('telegram');
            Route::get('/quizzes', QuizReview::class)->name('quizzes');
            Route::get('/{course}', CourseBuilder::class)->name('show');
        });

        Route::get('/iniciacoes/{user}', InitiationManager::class)->name('initiations.show');

        Route::prefix('biblioteca')->name('library.')->group(function () {
            Route::get('/', CategoryManager::class)->name('categories');
            Route::get('/categorias/{category}', DocumentManager::class)->name('documents');
            Route::get('/acesso/{user}', AccessManager::class)->name('access');
        });

        Route::get('/artigos', \App\Livewire\Admin\ArticlesManager::class)->name('articles');
    });

    Route::prefix('iniciado')->name('iniciado.')->middleware('role:iniciado')->group(function () {
        Route::get('/', fn () => view('iniciado.dashboard'))->name('dashboard');
        Route::get('/mensagens', Inbox::class)->name('messages');
        Route::get('/minha-iniciacao', MyInitiation::class)->name('initiation');
        Route::get('/familia', SpiritualFamily::class)->name('family');
    });

    Route::prefix('consulente')->name('consulente.')->middleware('role:consulente')->group(function () {
        Route::get('/', fn () => view('consulente.dashboard'))->name('dashboard');
        Route::get('/consultas', Consultations::class)->name('consultations');
        Route::get('/agendamentos', MyAppointments::class)->name('appointments');
        Route::get('/mensagens', Inbox::class)->name('messages');
    });

    Route::middleware('role:iniciado,consulente')->prefix('cursos')->name('student.courses.')->group(function () {
        Route::get('/', CoursesList::class)->name('index');
        Route::get('/{course}', CourseShow::class)->name('show');
        Route::get('/{course}/certificado', CourseCertificateController::class)->name('certificate');
    });

    Route::middleware('role:iniciado,consulente,admin')->get('/aulas/{lesson}/material', LessonMaterialController::class)->name('student.lessons.material');
    Route::middleware('role:iniciado,consulente,admin')->get('/aulas/{lesson}/audio', \App\Http\Controllers\LessonAudioStreamController::class)->name('student.lessons.audio');

    Route::middleware('role:iniciado,consulente,admin')->prefix('biblioteca')->name('library.')->group(function () {
        Route::get('/', LibraryBrowser::class)->name('index');
        Route::get('/{document}', LibraryViewer::class)->name('show');
    });

    Route::middleware('role:iniciado,consulente,admin')
        ->get('/biblioteca/{document}/conteudo', \App\Http\Controllers\LibraryDocumentStreamController::class)
        ->name('library.stream');

    Route::middleware('role:iniciado,consulente,admin')
        ->get('/biblioteca/{document}/audio', \App\Http\Controllers\LibraryDocumentAudioStreamController::class)
        ->name('library.audio');
});
