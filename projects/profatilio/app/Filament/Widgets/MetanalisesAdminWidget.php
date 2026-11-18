<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\CentralMetanalises;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Resumo do Programa de Metanálises no Dashboard do professor; cada número abre a Central. */
class MetanalisesAdminWidget extends StatsOverviewWidget
{
    protected static ?int $sort = -5; // Acima da produtividade: é o acompanhamento do programa em andamento.

    protected ?string $heading = 'Programa de Metanálises';

    public static function canView(): bool
    {
        return auth()->user()?->role === 'admin' && CentralMetanalises::programQuery()->exists();
    }

    protected function getStats(): array
    {
        $s = CentralMetanalises::summary();
        $central = fn (string $tab = 'conversas') => CentralMetanalises::getUrl(['ver' => $tab]);

        return [
            Stat::make('Aguardando mensagem', $s['awaiting'])
                ->description($s['awaiting'] ? 'Responder na Central › Conversas' : 'Nenhuma conversa esperando')
                ->color($s['awaiting'] ? 'warning' : 'success')
                ->url($central('conversas')),
            Stat::make('Termos assinados', "{$s['signed']}/{$s['people']}")
                ->description($s['people'] - $s['signed'] ? ($s['people'] - $s['signed']) . ' pendente(s) · ver quem falta' : 'Todos assinados')
                ->color($s['signed'] < $s['people'] ? 'warning' : 'success')
                ->url($central('conversas')),
            Stat::make('Protocolos congelados', "{$s['frozen']}/{$s['projects']}")
                ->description('Congelar com o PDF libera o protocolo à dupla')
                ->color($s['frozen'] < $s['projects'] ? 'gray' : 'success')
                ->url($central('conversas')),
            Stat::make('Com link do OSF', "{$s['osf']}/{$s['projects']}")
                ->description('Registro do protocolo (P2)')
                ->color($s['osf'] < $s['projects'] ? 'gray' : 'success')
                ->url($central('conversas')),
        ];
    }
}
