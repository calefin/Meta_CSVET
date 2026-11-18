<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\TicketController;
use App\Http\Controllers\Admin\PostController as AdminPostController;
use App\Http\Controllers\Admin\ProfileController as AdminProfileController;
use App\Http\Controllers\Admin\ClienteController as AdminClienteController;
use App\Http\Middleware\AdminMiddleware;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\ContactController;
use App\Http\Controllers\BlogController;

use App\Http\Controllers\ClientTicketController;

Route::get('/', function () {
    $challengeA = random_int(2, 9);
    $challengeB = random_int(2, 9);

    session([
        'contact_challenge_sum' => $challengeA + $challengeB,
        'contact_form_started_at' => now()->timestamp,
    ]);

    return view('welcome', compact('challengeA', 'challengeB'));
});

Route::post('/contato/enviar', [ContactController::class, 'submit'])
    ->name('contact.submit')
    ->middleware('throttle:5,10');

Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('blog.show');

// Public Client Tracking Portal
Route::get('/tickets/acompanhar/{token}', [ClientTicketController::class, 'show'])->name('tickets.show_public');
Route::post('/tickets/acompanhar/{token}/mensagem', [ClientTicketController::class, 'mensagem'])
    ->name('tickets.mensagem_public')
    ->middleware('throttle:10,10');
Route::post('/tickets/acompanhar/{token}/status', [ClientTicketController::class, 'updateStatus'])
    ->name('tickets.status_public')
    ->middleware('throttle:10,10');

// Dynamic Sitemap
use App\Http\Controllers\SitemapController;
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');

// Terms of Use & LGPD Policy
Route::get('/termos', function () {
    return view('public.terms');
})->name('terms');

Auth::routes();

Route::middleware(['auth', AdminMiddleware::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Perfil do administrador
    Route::get('/perfil', [AdminProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/perfil', [AdminProfileController::class, 'update'])->name('profile.update');

    // Tickets
    Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/create', [TicketController::class, 'create'])->name('tickets.create');
    Route::post('/tickets', [TicketController::class, 'store'])->name('tickets.store');
    Route::post('/tickets/reenviar-notificacoes-pendentes', [TicketController::class, 'reenviarNotificacoesPendentes'])->name('tickets.reenviar-notificacoes-pendentes');
    Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
    Route::put('/tickets/{ticket}', [TicketController::class, 'update'])->name('tickets.update');
    Route::post('/tickets/{ticket}/mensagem', [TicketController::class, 'mensagem'])->name('tickets.mensagem');
    Route::post('/tickets/{ticket}/reenviar-notificacao', [TicketController::class, 'reenviarNotificacao'])->name('tickets.reenviar-notificacao');
    Route::post('/tickets/{ticket}/close', [TicketController::class, 'close'])->name('tickets.close');
    Route::post('/tickets/{ticket}/reopen', [TicketController::class, 'reopen'])->name('tickets.reopen');
    Route::post('/tickets/{ticket}/archive', [TicketController::class, 'archive'])->name('tickets.archive');
    Route::post('/tickets/{ticket}/unarchive', [TicketController::class, 'unarchive'])->name('tickets.unarchive');
    Route::delete('/tickets/{ticket}', [TicketController::class, 'destroy'])->name('tickets.destroy');
    Route::post('/tickets/{ticket}/restore', [TicketController::class, 'restore'])->name('tickets.restore')->withTrashed();
    Route::delete('/tickets/{ticket}/force', [TicketController::class, 'forceDelete'])->name('tickets.force-delete')->withTrashed();

    // Blog CRUD
    Route::resource('posts', AdminPostController::class);

    // Clientes CRUD
    Route::resource('clientes', AdminClienteController::class);
});

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');