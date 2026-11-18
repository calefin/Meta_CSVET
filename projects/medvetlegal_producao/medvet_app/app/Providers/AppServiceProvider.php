<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Pagination\Paginator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // Garante que canonical/og:url/asset() sempre usem https em produção,
        // mesmo que o servidor não repasse corretamente o header de esquema.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Correção automática preventiva dos registros de imagens quebradas do Unsplash no banco de dados
        try {
            \Illuminate\Support\Facades\DB::table('posts')
                ->where('slug', 'necropsia-veterinaria-forense-quando-e-necessaria-como-solicitar')
                ->whereIn('image', [
                    'https://images.unsplash.com/photo-1579684389782-64d84b5e901a?q=80&w=600&auto=format&fit=crop',
                    'https://images.unsplash.com/photo-1530026405186-ed1ea0ac7a63?q=80&w=600&auto=format&fit=crop'
                ])
                ->update(['image' => 'https://images.unsplash.com/photo-1518152006812-edab29b069ac?q=80&w=600&auto=format&fit=crop']);

            \Illuminate\Support\Facades\DB::table('posts')
                ->where('slug', 'exumacao-de-animais-e-possivel-realizar-pericia-apos-o-enterro')
                ->where('image', 'https://images.unsplash.com/photo-1508197148814-68f6fc8bac3e?q=80&w=600&auto=format&fit=crop')
                ->update(['image' => 'https://images.unsplash.com/photo-1448375240586-882707db888b?q=80&w=600&auto=format&fit=crop']);
        } catch (\Throwable $e) {
            // Silencia erros caso o banco ainda não esteja conectado
        }
    }
}
