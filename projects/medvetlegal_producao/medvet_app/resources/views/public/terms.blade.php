@extends('layouts.public')

@section('title', 'Termos de Uso e Política de Privacidade (LGPD) | MedVetLegal')
@section('meta_description', 'Política de privacidade de dados e termos de uso do site MedVetLegal de acordo com a Lei Geral de Proteção de Dados (LGPD).')

@section('content')

    <!-- Banner Header -->
    <header class="blog-header" style="background: linear-gradient(rgba(7, 23, 41, 0.9), rgba(14, 42, 71, 0.85)), url('{{ asset('assets/hero-bg.jpg') }}'); background-size: cover; background-position: center; padding: 80px 0 50px; text-align: center; color: white;">
        <div class="container">
            <h1 class="display-5 text-white font-playfair">Termos de Uso & Privacidade</h1>
            <p class="lead opacity-75 fs-6">Conformidade com a Lei Geral de Proteção de Dados (Lei nº 13.709/18 - LGPD)</p>
        </div>
    </header>

    <!-- Content Section -->
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10 col-xl-9">
                <div class="bg-white p-5 rounded shadow-sm" style="box-shadow: 0 10px 30px rgba(0,0,0,0.02) !important; color: #4a5568; line-height: 1.8;">
                    
                    <span class="text-muted small d-block mb-2">Última atualização: {{ date('d/m/Y') }}</span>
                    <h2 class="font-playfair text-primary mb-4">1. Introdução e Compromisso</h2>
                    <p>
                        A <strong>MedVetLegal</strong>, sob a coordenação técnica e científica da perita <strong>Dra. Adriana de Siqueira</strong>, valoriza a segurança, o sigilo e a privacidade dos dados de seus usuários, tutores de animais, advogados e profissionais da medicina veterinária.
                    </p>
                    <p>
                        Esta Política de Privacidade descreve como coletamos, usamos, armazenamos e protegemos as informações fornecidas por você através do nosso formulário de contato e portal de acompanhamento de casos, em conformidade com a <strong>Lei Geral de Proteção de Dados Pessoais (LGPD - Lei nº 13.709/2018)</strong> e sob os preceitos de segredo e sigilo profissional aplicados às perícias médico-legais.
                    </p>

                    <h2 class="font-playfair text-primary mt-5 mb-4">2. Dados Coletados e Finalidade</h2>
                    <p>
                        Coletamos informações em duas etapas no nosso site:
                    </p>
                    <ul class="list-group list-group-flush mb-4">
                        <li class="list-group-item bg-transparent px-0">
                            <strong>Formulário de Contato Inicial:</strong> Coletamos seu <em>Nome Completo</em>, <em>E-mail</em>, <em>Telefone / WhatsApp</em> e o <em>Breve relato do caso</em>. Esses dados são coletados sob a base legal do consentimento e execução pré-contratual, com a única finalidade de avaliar a viabilidade técnica do caso e propor orçamentos de honorários periciais.
                        </li>
                        <li class="list-group-item bg-transparent px-0">
                            <strong>Portal de Acompanhamento (Área de Tickets):</strong> Caso haja avanço na solicitação, você poderá enviar arquivos em PDF (tais como prontuários clínicos, laudos de exames, receitas e fotos de necropsia). Esses documentos são confidenciais e destinam-se unicamente ao embasamento de pareceres técnicos, contestações ou assistências técnicas judiciais solicitadas por você.
                        </li>
                    </ul>

                    <h2 class="font-playfair text-primary mt-5 mb-4">3. Sigilo e Compartilhamento de Informações</h2>
                    <p>
                        A <strong>MedVetLegal não comercializa, não aluga e não compartilha</strong> seus dados pessoais ou arquivos técnicos com terceiros para fins publicitários ou comerciais.
                    </p>
                    <p>
                        O acesso aos relatos e documentos enviados é restrito exclusivamente aos peritos responsáveis pela elaboração dos laudos: a <strong>Dra. Adriana de Siqueira</strong> (Perita Principal) e o <strong>Dr. Atilio Sersun Calefi</strong> (Consultor Técnico). Os dados poderão ser compartilhados apenas com autoridades judiciais caso haja determinação de ordem legal em processos nos quais atuamos oficialmente como assistentes ou peritos indicados pelo juiz.
                    </p>

                    <h2 class="font-playfair text-primary mt-5 mb-4">4. Segurança do Armazenamento</h2>
                    <p>
                        Adotamos medidas técnicas administrativas rígidas de segurança para proteger seus dados contra acessos não autorizados, perdas ou adulterações:
                    </p>
                    <ul class="list-group list-group-flush mb-4">
                        <li class="list-group-item bg-transparent px-0">
                            Os links de acompanhamento do portal do cliente são tokenizados com criptografia aleatória de 256 bits, impedindo a listagem pública de tickets ou descoberta por robôs.
                        </li>
                        <li class="list-group-item bg-transparent px-0">
                            Os arquivos anexados (PDFs) são criptografados no servidor de arquivos do site.
                        </li>
                        <li class="list-group-item bg-transparent px-0">
                            O acesso administrativo ao painel de gerenciamento de tickets é protegido por senha forte e restrito a usuários previamente autorizados pelo diretor do site.
                        </li>
                    </ul>

                    <h2 class="font-playfair text-primary mt-5 mb-4">5. Direitos do Titular de Dados</h2>
                    <p>
                        Em conformidade com o Artigo 18 da LGPD, você possui os seguintes direitos em relação aos seus dados pessoais, exercíveis a qualquer momento mediante requisição por escrito para o e-mail <a href="mailto:contato@medvetlegal.com.br">contato@medvetlegal.com.br</a>:
                    </p>
                    <ul class="list-group list-group-flush mb-4">
                        <li class="list-group-item bg-transparent px-0"><i class="fas fa-check text-warning me-2"></i>Confirmação da existência de tratamento dos dados;</li>
                        <li class="list-group-item bg-transparent px-0"><i class="fas fa-check text-warning me-2"></i>Acesso aos dados pessoais armazenados;</li>
                        <li class="list-group-item bg-transparent px-0"><i class="fas fa-check text-warning me-2"></i>Correção de dados incompletos, inexatos ou desatualizados;</li>
                        <li class="list-group-item bg-transparent px-0"><i class="fas fa-check text-warning me-2"></i>Eliminação dos dados pessoais tratados com o seu consentimento (exceto quando o armazenamento for exigido por lei ou para a defesa judicial de direitos da MedVetLegal).</li>
                    </ul>

                    <h2 class="font-playfair text-primary mt-5 mb-4">6. Retenção e Descarte de Dados</h2>
                    <p>
                        Os dados fornecidos serão mantidos em nosso sistema enquanto perdurar a prestação dos serviços de assistência técnica ou análise de viabilidade, ou até que você solicite sua exclusão. 
                    </p>
                    <p>
                        Documentos periciais, laudos e pareceres que façam parte de processos judiciais encerrados ou em andamento poderão ser mantidos arquivados sob segredo de justiça pelo tempo necessário para cumprimento de obrigações legais de cadeia de custódia e responsabilidade profissional.
                    </p>

                    <h2 class="font-playfair text-primary mt-5 mb-4">7. Contato e Canal LGPD</h2>
                    <p>
                        Para dirimir qualquer dúvida, solicitar exclusão de dados ou exercer seus direitos de titular previstos na LGPD, entre em contato diretamente com o nosso Canal de Proteção de Dados:
                    </p>
                    <div class="p-4 rounded mt-3" style="background-color: var(--bg-light); border-left: 4px solid var(--accent-color);">
                        <p class="mb-1"><strong>Encarregado de Dados (DPO):</strong> Dra. Adriana de Siqueira</p>
                        <p class="mb-1"><strong>E-mail direto:</strong> <a href="mailto:patoforense.adriana@gmail.com">patoforense.adriana@gmail.com</a></p>
                        <p class="mb-0"><strong>E-mail geral de contato:</strong> <a href="mailto:contato@medvetlegal.com.br">contato@medvetlegal.com.br</a></p>
                    </div>

                </div>
            </div>
        </div>
    </div>

@endsection
