@extends('layouts.admin')

@section('title', 'Ticket #' . $ticket->id)
@section('header')
    <h1>
        Ticket #{{ $ticket->id }}
        <div class="float-right">
            @php
                $whatsappUrl = $ticket->cliente->whatsappUrl("Olá, {$ticket->cliente->nome}! Aqui é a equipe da MedVetLegal sobre o Ticket #{$ticket->id}. Já retornamos sua solicitação pelo sistema/e-mail — poderia verificar, por favor?");
            @endphp
            @if($whatsappUrl)
                <a href="{{ $whatsappUrl }}" target="_blank" class="btn btn-sm" style="background-color: #25D366; color: #fff;"><i class="fab fa-whatsapp"></i> Responder no WhatsApp</a>
            @endif

            @if($ticket->ultimaMensagem && $ticket->ultimaMensagem->enviado_por_admin)
                <form action="{{ route('admin.tickets.reenviar-notificacao', $ticket) }}" method="POST" class="d-inline" onsubmit="return confirm('Reenviar o e-mail de aviso de resposta para {{ $ticket->cliente->email }}?');">
                    @csrf
                    <button type="submit" class="btn btn-outline-warning btn-sm"><i class="fas fa-paper-plane"></i> Reenviar Notificação por E-mail</button>
                </form>
            @endif

            @if($ticket->status !== 'fechado')
                <form action="{{ route('admin.tickets.close', $ticket) }}" method="POST" class="d-inline" onsubmit="return confirm('Encerrar este ticket?');">
                    @csrf
                    <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="fas fa-lock"></i> Encerrar Ticket</button>
                </form>
            @else
                <form action="{{ route('admin.tickets.reopen', $ticket) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-outline-success btn-sm"><i class="fas fa-lock-open"></i> Reabrir Ticket</button>
                </form>
            @endif

            @if($ticket->archived_at)
                <form action="{{ route('admin.tickets.unarchive', $ticket) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-outline-primary btn-sm"><i class="fas fa-box-open"></i> Desarquivar</button>
                </form>
            @else
                <form action="{{ route('admin.tickets.archive', $ticket) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="fas fa-archive"></i> Arquivar</button>
                </form>
            @endif

            <form action="{{ route('admin.tickets.destroy', $ticket) }}" method="POST" class="d-inline" onsubmit="return confirm('Mover este ticket para a lixeira?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash"></i> Excluir</button>
            </form>

            <a href="{{ route('admin.tickets.index') }}" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Voltar</a>
        </div>
    </h1>
@endsection

@section('content')
<div class="row">
    <div class="col-md-8">
        <!-- Mensagens -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">{{ $ticket->titulo }}</h3>
            </div>
            <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                @foreach($ticket->mensagens as $msg)
                <div class="mb-3 p-3 {{ $msg->enviado_por_admin ? 'bg-light' : 'bg-info-light' }}" style="border-radius: 10px;">
                    <strong>{{ $msg->enviado_por_admin ? ($msg->user->name ?? 'Admin') : $ticket->cliente->nome }}</strong>
                    <small class="text-muted float-right">{{ $msg->created_at->format('d/m/Y H:i') }}</small>
                    @if($msg->is_html)
                        <div class="mt-2 ticket-rich-content">{!! $msg->conteudo !!}</div>
                    @else
                        <p class="mt-2" style="white-space: pre-line;">{{ $msg->conteudo }}</p>
                    @endif
                    @if($msg->anexo)
                        <a href="{{ asset('storage/' . $msg->anexo) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-file-pdf"></i> Ver Anexo
                        </a>
                    @endif
                </div>
                @endforeach
            </div>
            <div class="card-footer" id="responder">
                <form action="{{ route('admin.tickets.mensagem', $ticket) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="form-group">
                        <label class="mb-1">Resposta</label>
                        <div class="mb-2 d-flex flex-wrap" style="gap: 6px;">
                            <span class="text-muted small align-self-center mr-1">Modelos:</span>
                            <button type="button" class="btn btn-outline-secondary btn-sm modelo-resposta-btn" data-modelo="proposta-consulta">Proposta de Consulta</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm modelo-resposta-btn" data-modelo="solicitacao-documentos">Solicitar Documentos</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm modelo-resposta-btn" data-modelo="confirmacao-recebimento">Confirmar Recebimento</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm modelo-resposta-btn" data-modelo="orcamento-enviado">Orçamento Enviado</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm modelo-resposta-btn" data-modelo="follow-up-sem-resposta">Acompanhamento</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm modelo-resposta-btn" data-modelo="parecer-concluido">Parecer Concluído</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm modelo-resposta-btn" data-modelo="encerramento-agradecimento">Encerramento</button>
                        </div>
                        <textarea name="conteudo" id="conteudo-admin" class="form-control" rows="3" placeholder="Digite sua resposta..."></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Anexar PDF</label>
                                <input type="file" name="anexo" class="form-control-file" accept=".pdf">
                            </div>
                        </div>
                        <div class="col-md-6 text-right">
                            <button type="submit" class="btn btn-primary mt-4"><i class="fas fa-paper-plane"></i> Enviar Resposta</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <!-- Informações do Ticket -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Informações</h3>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.tickets.update', $ticket) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="form-control">
                            <option value="novo" {{ $ticket->status == 'novo' ? 'selected' : '' }}>Novo</option>
                            <option value="analise" {{ $ticket->status == 'analise' ? 'selected' : '' }}>Em Análise</option>
                            <option value="orcado" {{ $ticket->status == 'orcado' ? 'selected' : '' }}>Orçado</option>
                            <option value="aceito" {{ $ticket->status == 'aceito' ? 'selected' : '' }}>Aceito</option>
                            <option value="recusado" {{ $ticket->status == 'recusado' ? 'selected' : '' }}>Recusado</option>
                            <option value="fechado" {{ $ticket->status == 'fechado' ? 'selected' : '' }}>Fechado</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Prioridade</label>
                        <select name="prioridade" class="form-control">
                            <option value="baixa" {{ $ticket->prioridade == 'baixa' ? 'selected' : '' }}>Baixa</option>
                            <option value="media" {{ $ticket->prioridade == 'media' ? 'selected' : '' }}>Média</option>
                            <option value="alta" {{ $ticket->prioridade == 'alta' ? 'selected' : '' }}>Alta</option>
                            <option value="urgente" {{ $ticket->prioridade == 'urgente' ? 'selected' : '' }}>Urgente</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Valor Orçado (R$)</label>
                        <input type="number" name="valor_orcado" class="form-control" step="0.01" value="{{ $ticket->valor_orcado }}">
                    </div>
                    <button type="submit" class="btn btn-success btn-block"><i class="fas fa-save"></i> Atualizar</button>
                </form>
            </div>
        </div>
        
        <!-- Dados do Cliente -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Cliente</h3>
            </div>
            <div class="card-body">
                <p><strong>Nome:</strong> {{ $ticket->cliente->nome }}</p>
                <p><strong>Email:</strong> {{ $ticket->cliente->email }}</p>
                <p><strong>Telefone:</strong> {{ $ticket->cliente->telefone ?? 'Não informado' }}</p>
                <p><strong>CPF/CNPJ:</strong> {{ $ticket->cliente->cpf_cnpj ?? 'Não informado' }}</p>
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
    const nomeClienteTicket = @json($ticket->cliente->nome);

    const modelosResposta = {
        'proposta-consulta': `<p>Prezado(a) [NOME DO CLIENTE], boa tarde.</p><p>Primeiramente, agradeço pelo seu contato.</p><p>Com o objetivo de alinharmos a estratégia referente ao parecer técnico, bem como para nos conhecermos, proponho o agendamento de uma reunião online. O investimento para a consulta inicial possui o valor de R$ 300,00 por hora.</p><p>A partir das informações apresentadas durante essa conversa, será possível estruturar a linha do tempo do caso e estabelecer o nexo causal necessário. Cumpre destacar que o valor médio para a elaboração do parecer técnico é de R$ 4.000,00, quantia que já engloba a formulação dos quesitos ao perito judicial.</p><p>Caso seja de seu interesse dar andamento ao processo, permaneço à disposição para agendarmos o nosso encontro.</p><p>Atenciosamente,</p><p>Dra. Adriana de Siqueira</p>`,

        'solicitacao-documentos': `<p>Prezado(a) [NOME DO CLIENTE], boa tarde.</p><p>Para darmos continuidade à análise técnica do seu caso, precisamos que nos envie a documentação abaixo (em formato PDF, pelo próprio portal de acompanhamento):</p><ul><li>Prontuário veterinário completo;</li><li>Exames complementares realizados (imagem, laboratoriais, necroscópicos, conforme aplicável);</li><li>Laudos ou pareceres anteriores, se houver.</li></ul><p>Assim que recebermos os documentos, retomamos a análise e retornamos com os próximos passos.</p><p>Atenciosamente,</p><p>Dra. Adriana de Siqueira</p>`,

        'confirmacao-recebimento': `<p>Prezado(a) [NOME DO CLIENTE], boa tarde.</p><p>Confirmamos o recebimento dos documentos enviados. Nossa equipe técnica iniciará a análise do material e retornaremos em breve com uma atualização sobre o caso.</p><p>Qualquer novidade, avisaremos por aqui.</p><p>Atenciosamente,</p><p>Dra. Adriana de Siqueira</p>`,

        'orcamento-enviado': `<p>Prezado(a) [NOME DO CLIENTE], boa tarde.</p><p>Concluímos a análise preliminar do seu caso e disponibilizamos a proposta de honorários no seu portal de acompanhamento.</p><p>O valor contempla a elaboração do parecer técnico completo, incluindo a formulação dos quesitos ao perito judicial quando aplicável.</p><p>Fico à disposição para esclarecer qualquer dúvida sobre o escopo do trabalho antes da sua decisão.</p><p>Atenciosamente,</p><p>Dra. Adriana de Siqueira</p>`,

        'follow-up-sem-resposta': `<p>Prezado(a) [NOME DO CLIENTE], boa tarde.</p><p>Notamos que ainda não tivemos um retorno sobre [o orçamento enviado / a solicitação de documentos] referente ao seu caso.</p><p>Continuamos à disposição para prosseguir assim que for conveniente para você. Caso tenha alguma dúvida ou precise de mais informações antes de decidir, é só nos avisar por aqui.</p><p>Atenciosamente,</p><p>Dra. Adriana de Siqueira</p>`,

        'parecer-concluido': `<p>Prezado(a) [NOME DO CLIENTE], boa tarde.</p><p>Informamos que o parecer técnico referente ao seu caso foi concluído e está disponível para download no seu portal de acompanhamento.</p><p>Ficamos à disposição para esclarecer qualquer ponto do documento ou auxiliar nos desdobramentos processuais.</p><p>Atenciosamente,</p><p>Dra. Adriana de Siqueira</p>`,

        'encerramento-agradecimento': `<p>Prezado(a) [NOME DO CLIENTE], boa tarde.</p><p>Encerramos o atendimento referente ao seu caso. Agradecemos a confiança em nosso trabalho e ficamos à disposição para futuras necessidades.</p><p>Caso surja alguma dúvida posterior sobre o parecer entregue, é só nos procurar.</p><p>Atenciosamente,</p><p>Dra. Adriana de Siqueira</p>`,
    };

    let ticketEditor = null;

    ClassicEditor
        .create(document.querySelector('#conteudo-admin'), {
            toolbar: ['bold', 'italic', 'bulletedList', 'numberedList', 'blockQuote', 'link', 'undo', 'redo']
        })
        .then(editor => {
            ticketEditor = editor;

            const form = editor.sourceElement.closest('form');
            form.addEventListener('submit', function (e) {
                editor.updateSourceElement();
                if (!editor.getData().trim()) {
                    e.preventDefault();
                    alert('Digite uma mensagem antes de enviar.');
                }
            });

            if (window.location.hash === '#responder') {
                document.getElementById('responder').scrollIntoView({ behavior: 'smooth', block: 'start' });
                editor.editing.view.focus();
            }
        })
        .catch(error => console.error(error));

    document.querySelectorAll('.modelo-resposta-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const chave = this.dataset.modelo;

            if (!chave || !ticketEditor) {
                return;
            }

            if (ticketEditor.getData().trim() && !confirm('Isso vai substituir o texto atual da resposta. Continuar?')) {
                return;
            }

            const conteudoModelo = modelosResposta[chave].replace(/\[NOME DO CLIENTE\]/g, nomeClienteTicket);
            ticketEditor.setData(conteudoModelo);
            ticketEditor.editing.view.focus();
        });
    });
</script>
@endpush