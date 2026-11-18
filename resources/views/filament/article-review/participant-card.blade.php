{{-- Cartão compacto do participante (largura total; TextEntry limitava a max-content). --}}
<div style="width:100%">{!! \App\Filament\Resources\ArticleReviewResource::participantCardHtml($getRecord())->toHtml() !!}</div>
