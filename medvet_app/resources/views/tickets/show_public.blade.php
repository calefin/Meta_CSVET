@extends('layouts.public')

@section('title', 'Acompanhamento do Caso #' . $ticket->id . ' | MedVetLegal')

@section('content')
<div class="bg-light py-5">
    <div class="container py-4">
        
        <!-- Header status banner -->
        <div class="row mb-5">
            <div class="col-12">
                <div class="card border-0 shadow-sm p-4" style="border-radius: 8px;">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                        <div>
                            <span class="text-muted small">ACOMPANHAMENTO DE CASO</span>
                            <h2 class="font-playfair text-primary mb-0 mt-1">Caso #{{ $ticket->id }}: {{ $ticket->titulo }}</h2>
                        </div>
                        <div class="d-flex gap-2">
                            <!-- Status Badge -->
                            <span class="badge px-3 py-2 text-uppercase" style="
                                background-color: {{ 
                                    $ticket->status == 'novo' ? '#17a2b8' : 
                                    ($ticket->status == 'analise' ? '#ffc107' : 
                                    ($ticket->status == 'orcado' ? '#007bff' : 
                                    ($ticket->status == 'aceito' ? '#28a745' : 
                                    ($ticket->status == 'recusado' ? '#dc3545' : '#6c757d')))) 
                                }}; color: {{ $ticket->status == 'analise' ? '#212529' : '#fff' }}; font-weight: bold; border-radius: 4px; font-size: 0.85rem;
                            ">
                                {{ 
                                    $ticket->status == 'novo' ? 'Novo' : 
                                    ($ticket->status == 'analise' ? 'Em Análise' : 
                                    ($ticket->status == 'orcado' ? 'Orçamento Proposto' : 
                                    ($ticket->status == 'aceito' ? 'Orçamento Aceito' : 
                                    ($ticket->status == 'recusado' ? 'Orçamento Recusado' : 'Fechado')))) 
                                }}
                            </span>
                            
                            <!-- Priority Badge -->
                            <span class="badge px-3 py-2 text-uppercase bg-secondary" style="font-weight: bold; border-radius: 4px; font-size: 0.85rem;">
                                Prioridade: {{ ucfirst($ticket->prioridade) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            
            <!-- Principal: Detalhes e Chat (Esquerda) -->
            <div class="col-lg-8">
                
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                        <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                <!-- Relato Inicial -->
                <div class="card border-0 shadow-sm mb-4" style="border-radius: 8px;">
                    <div class="card-header bg-white py-3 border-0">
                        <h5 class="font-playfair text-primary mb-0 font-weight-bold">Descrição da Solicitação</h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-0 text-muted" style="white-space: pre-line;">{{ $ticket->descricao }}</p>
                    </div>
                    <div class="card-footer bg-white border-0 text-muted small">
                        Iniciado em: {{ $ticket->created_at->format('d/m/Y \à\s H:i') }}
                    </div>
                </div>

                <!-- Histórico de Mensagens / Linha do tempo -->
                <div class="card border-0 shadow-sm mb-4" style="border-radius: 8px;">
                    <div class="card-header bg-white py-3 border-0">
                        <h5 class="font-playfair text-primary mb-0 font-weight-bold">Linha do Tempo & Mensagens</h5>
                    </div>
                    <div class="card-body" style="max-height: 550px; overflow-y: auto; background-color: #f8f9fa;">
                        
                        @forelse($ticket->mensagens as $msg)
                            <!-- Mensagem individual -->
                            <div class="mb-4 p-3 shadow-sm border" style="
                                border-radius: 12px;
                                border-left-width: 4px !important;
                                border-left-color: {{ $msg->enviado_por_admin ? 'var(--accent-color)' : 'var(--primary-color)' }} !important;
                                background-color: {{ $msg->enviado_por_admin ? '#ffffff' : '#fdfdfd' }};
                            ">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <strong style="color: {{ $msg->enviado_por_admin ? 'var(--primary-color)' : '#4a5568' }};">
                                        @if($msg->enviado_por_admin)
                                            <i class="fas fa-user-shield me-2 text-warning"></i>Dra. Adriana (Perita Responsável)
                                        @else
                                            <i class="fas fa-user me-2"></i>Você (Cliente)
                                        @endif
                                    </strong>
                                    <small class="text-muted">{{ $msg->created_at->format('d/m/Y H:i') }}</small>
                                </div>
                                @if($msg->is_html)
                                    <div class="mb-2 text-muted ticket-rich-content" style="font-size: 0.95rem;">{!! $msg->conteudo !!}</div>
                                @else
                                    <p class="mb-2 text-muted" style="font-size: 0.95rem; white-space: pre-line;">{{ $msg->conteudo }}</p>
                                @endif
                                
                                @if($msg->anexo)
                                    <div class="mt-2 pt-2 border-top">
                                        <a href="{{ asset($msg->anexo) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-file-pdf me-2"></i>Ver PDF Anexo
                                        </a>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <p class="text-center text-muted my-4">Nenhuma mensagem registrada.</p>
                        @endforelse
                        
                    </div>
                </div>

                <!-- Formulário de Resposta -->
                <div class="card border-0 shadow-sm" style="border-radius: 8px;">
                    <div class="card-header bg-white py-3 border-0">
                        <h5 class="font-playfair text-primary mb-0 font-weight-bold">Enviar Mensagem ou Documentos</h5>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('tickets.mensagem_public', $ticket->token) }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="mb-3">
                                <label for="conteudo" class="form-label">Sua Mensagem *</label>
                                <textarea class="form-control" id="conteudo" name="conteudo" rows="4" placeholder="Digite sua dúvida, observação ou esclarecimento aqui..."></textarea>
                            </div>
                            
                            <div class="mb-4">
                                <label for="anexo_file" class="form-label">Anexar Documento PDF (Exame, Laudo Clínico, Processo) - Opcional</label>
                                <input class="form-control" type="file" id="anexo_file" name="anexo_file" accept=".pdf">
                                <span class="form-text text-muted small">Apenas arquivos no formato PDF de até 5MB.</span>
                            </div>
                            
                            <button type="submit" class="btn btn-gold w-100">
                                <i class="fas fa-paper-plane me-2"></i>Enviar Resposta
                            </button>
                        </form>
                    </div>
                </div>

            </div>

            <!-- Lateral: Informações Orçamento (Direita) -->
            <div class="col-lg-4">
                
                <!-- Card de Orçamento -->
                <div class="card border-0 shadow-sm text-center mb-4 overflow-hidden" style="border-radius: 8px;">
                    <div class="bg-primary text-white py-3">
                        <h5 class="font-playfair mb-0">Proposta de Orçamento</h5>
                    </div>
                    <div class="card-body p-4">
                        
                        @if($ticket->valor_orcado)
                            <div class="mb-3">
                                <span class="text-muted small d-block">VALOR DO PARECER TÉCNICO</span>
                                <h3 class="font-playfair text-primary my-2" style="font-size: 2.2rem; font-weight: 700;">
                                    R$ {{ number_format($ticket->valor_orcado, 2, ',', '.') }}
                                </h3>
                            </div>
                            
                            @if($ticket->status == 'orcado')
                                <div class="alert alert-warning small border-0 mb-4" style="background-color: var(--accent-light); color: var(--primary-color);">
                                    <i class="fas fa-info-circle me-2"></i>Temos uma proposta de honorários pronta para o seu caso. Revise a proposta técnica e tome sua decisão abaixo.
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <!-- Form Aceitar -->
                                    <form action="{{ route('tickets.status_public', $ticket->token) }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="status" value="aceito">
                                        <button type="submit" class="btn btn-success w-100 py-3 font-weight-bold">
                                            <i class="fas fa-check me-2"></i>Aceitar Proposta
                                        </button>
                                    </form>
                                    
                                    <!-- Form Recusar -->
                                    <form action="{{ route('tickets.status_public', $ticket->token) }}" method="POST" onsubmit="return confirm('Deseja realmente recusar esta proposta de orçamento?');">
                                        @csrf
                                        <input type="hidden" name="status" value="recusado">
                                        <button type="submit" class="btn btn-outline-danger w-100 py-2">
                                            <i class="fas fa-times me-2"></i>Recusar Proposta
                                        </button>
                                    </form>
                                </div>
                            @elseif($ticket->status == 'aceito')
                                <div class="p-3 bg-success-light text-success rounded mb-2" style="background-color: #d4edda;">
                                    <i class="fas fa-check-circle fa-2x mb-2"></i>
                                    <h6 class="font-weight-bold mb-1">Orçamento Aceito!</h6>
                                    <p class="small mb-0">Nossos especialistas estão trabalhando na redação do seu parecer/laudo. Entraremos em contato em breve.</p>
                                </div>
                            @elseif($ticket->status == 'recusado')
                                <div class="p-3 bg-danger-light text-danger rounded mb-2" style="background-color: #f8d7da;">
                                    <i class="fas fa-times-circle fa-2x mb-2"></i>
                                    <h6 class="font-weight-bold mb-1">Proposta Recusada</h6>
                                    <p class="small mb-0">Caso queira renegociar o valor ou alterar o escopo técnico do caso, entre em contato enviando uma mensagem ao lado.</p>
                                </div>
                            @endif
                        @else
                            <div class="py-4">
                                <i class="fas fa-hourglass-half fa-3x text-muted mb-3"></i>
                                <h6 class="font-weight-bold text-primary">Orçamento em Elaboração</h6>
                                <p class="small text-muted mb-0">
                                    A perita principal <strong>Dra. Adriana de Siqueira</strong> está analisando a documentação e o relato enviado para avaliar a viabilidade e formular a proposta técnica de honorários periciais.
                                </p>
                            </div>
                        @endif

                    </div>
                </div>



                <!-- Aviso de Confidencialidade -->
                <div class="card border-0 shadow-sm p-4 text-center bg-primary text-white" style="border-radius: 8px;">
                    <i class="fas fa-user-shield fa-2x mb-3 text-warning"></i>
                    <h6 class="font-playfair mb-2 text-white">Segurança & Sigilo</h6>
                    <p class="small mb-0 opacity-75">
                        Esta página de acompanhamento é protegida por token de criptografia de 256 bits. O compartilhamento deste link é de inteira responsabilidade do cliente. Todas as informações trafegadas seguem a LGPD e o segredo profissional de perícia médica legal.
                    </p>
                </div>

            </div>

        </div>
    </div>
</div>

<style>
    .ticket-rich-content p:last-child { margin-bottom: 0; }
    .ticket-rich-content ul, .ticket-rich-content ol { padding-left: 1.25rem; margin-bottom: 0.5rem; }
    .ticket-rich-content blockquote { border-left: 3px solid #ccc; padding-left: 0.75rem; color: #666; margin: 0.5rem 0; }
</style>
@endsection

@push('scripts')
<script src="https://cdn.ckeditor.com/ckeditor5/36.0.1/classic/ckeditor.js"></script>
<script>
    ClassicEditor
        .create(document.querySelector('#conteudo'), {
            toolbar: ['bold', 'italic', 'bulletedList', 'numberedList', 'blockQuote', 'link', 'undo', 'redo']
        })
        .then(editor => {
            const form = editor.sourceElement.closest('form');
            form.addEventListener('submit', function (e) {
                editor.updateSourceElement();
                if (!editor.getData().trim()) {
                    e.preventDefault();
                    alert('Digite uma mensagem antes de enviar.');
                }
            });
        })
        .catch(error => console.error(error));
</script>
@endpush
