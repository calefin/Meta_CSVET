<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>gov.br - Identificação e Assinatura Eletrônica</title>
    <style>
        :root {
            --gov-blue: #004587;
            --gov-blue-hover: #003666;
            --gov-gray-bg: #f2f5f7;
            --gov-text-dark: #333333;
            --gov-text-muted: #666666;
            --gov-border: #cccccc;
            --gov-green: #138244;
        }

        body {
            font-family: "Rawline", "Segoe UI", -apple-system, BlinkMacSystemFont, Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--gov-gray-bg);
            color: var(--gov-text-dark);
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        header {
            background-color: #ffffff;
            border-bottom: 1px solid #e5e5e5;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-logo {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo-text {
            font-size: 26px;
            font-weight: 800;
            color: var(--gov-blue);
            letter-spacing: -1px;
        }

        .logo-badge {
            background-color: #e5f0fa;
            color: var(--gov-blue);
            font-size: 11px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 4px;
            text-transform: uppercase;
        }

        .flag-box {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            color: var(--gov-text-muted);
            font-weight: 500;
        }

        .flag-icon {
            width: 18px;
            height: 12px;
            background: linear-gradient(to bottom, #009c3b 40%, #ffdf00 40%, #ffdf00 60%, #002776 60%);
            border-radius: 1px;
        }

        main {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px 20px;
        }

        .login-card {
            background-color: #ffffff;
            width: 100%;
            max-width: 450px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            border: 1px solid #e0e4e8;
            padding: 35px 30px;
            box-sizing: border-box;
        }

        .card-title {
            font-size: 20px;
            font-weight: 700;
            margin-top: 0;
            margin-bottom: 8px;
            color: #1e293b;
        }

        .card-subtitle {
            font-size: 14px;
            color: var(--gov-text-muted);
            margin-bottom: 25px;
            line-height: 1.4;
        }

        .step-container {
            display: none;
        }

        .step-container.active {
            display: block;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #1e293b;
        }

        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 12px 14px;
            font-size: 15px;
            border: 1px solid var(--gov-border);
            border-radius: 4px;
            box-sizing: border-box;
            outline: none;
            transition: border-color 0.2s;
        }

        input[type="text"]:focus, input[type="password"]:focus {
            border-color: var(--gov-blue);
            box-shadow: 0 0 0 2px rgba(0, 69, 135, 0.1);
        }

        .btn {
            width: 100%;
            padding: 13px;
            font-size: 15px;
            font-weight: 700;
            border-radius: 24px;
            border: none;
            cursor: pointer;
            transition: background-color 0.2s, transform 0.1s;
            text-align: center;
            display: block;
            box-sizing: border-box;
            text-decoration: none;
        }

        .btn-primary {
            background-color: var(--gov-blue);
            color: #ffffff;
        }

        .btn-primary:hover {
            background-color: var(--gov-blue-hover);
        }

        .btn-success {
            background-color: var(--gov-green);
            color: #ffffff;
        }

        .btn-success:hover {
            background-color: #0f6c38;
        }

        .btn-secondary {
            background-color: #f1f5f9;
            color: #475569;
            border: 1px solid #cbd5e1;
            margin-top: 10px;
        }

        .btn-secondary:hover {
            background-color: #e2e8f0;
        }

        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 20px 0;
            color: var(--gov-text-muted);
            font-size: 13px;
        }

        .divider::before, .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #e2e8f0;
        }

        .divider:not(:empty)::before {
            margin-right: .5em;
        }

        .divider:not(:empty)::after {
            margin-left: .5em;
        }

        .other-login-options {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .option-button {
            display: flex;
            align-items: center;
            gap: 12px;
            background-color: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 10px 15px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
            text-align: left;
        }

        .option-button:hover {
            background-color: #f8fafc;
        }

        .sms-badge {
            background-color: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #15803d;
            padding: 12px;
            border-radius: 6px;
            font-size: 13px;
            margin-bottom: 20px;
            line-height: 1.4;
        }

        .consent-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 15px;
            font-size: 13.5px;
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .consent-box ul {
            margin: 8px 0 0 0;
            padding-left: 20px;
        }

        footer {
            background-color: #ffffff;
            border-top: 1px solid #e5e5e5;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: var(--gov-text-muted);
            font-weight: 500;
        }

        .security-seal {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--gov-green);
            font-weight: 700;
            font-size: 13px;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>

    <header>
        <div class="header-logo">
            <span class="logo-text">gov.br</span>
            <span class="logo-badge">Assinatura Digital</span>
        </div>
        <div class="flag-box">
            <div class="flag-icon"></div>
            <span>Brasil</span>
        </div>
    </header>

    <main>
        <div class="login-card">
            <!-- STEP 1: CPF IDENTIFICATION -->
            <div id="step-cpf" class="step-container active">
                <div class="security-seal">
                    <svg class="w-4 h-4" style="width:16px; height:16px;" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M2.166 4.9L10 1.154l7.834 3.746A1 1 0 0118.5 5.8v4.614a19.123 19.123 0 01-5.633 13.5A18.89 18.89 0 0110 19a18.89 18.89 0 01-2.867-5.086c-1.85-2.92-2.793-6.242-2.766-9.6H4.37c-.55 0-1-.45-1-1v-.5z" clip-rule="evenodd"></path></svg>
                    Ambiente de Simulação Seguro (Sandbox)
                </div>
                <h3 class="card-title">Identifique-se no gov.br</h3>
                <p class="card-subtitle">Digite seu CPF para iniciar a autenticação de assinatura do Prof. Atilio.</p>

                <div class="form-group">
                    <label for="cpf">CPF</label>
                    <input type="text" id="cpf" placeholder="000.000.000-00" value="000.000.000-00">
                </div>

                <button class="btn btn-primary" onclick="goToStep('password')">Continuar</button>

                <div class="divider">ou</div>

                <div class="other-login-options">
                    <button class="option-button" onclick="goToStep('password')">
                        <span style="font-size:18px;">🏦</span>
                        <span>Seu banco (acesso com credenciais bancárias)</span>
                    </button>
                    <button class="option-button" onclick="goToStep('password')">
                        <span style="font-size:18px;">🔑</span>
                        <span>Certificado digital (em nuvem ou token)</span>
                    </button>
                </div>
            </div>

            <!-- STEP 2: PASSWORD -->
            <div id="step-password" class="step-container">
                <h3 class="card-title">Insira sua senha</h3>
                <p class="card-subtitle">Conta vinculada ao CPF: <strong>000.***.***-00</strong></p>

                <div class="form-group">
                    <label for="password">Senha</label>
                    <input type="password" id="password" value="••••••••••••" placeholder="Sua senha gov.br">
                </div>

                <button class="btn btn-primary" onclick="goToStep('sms')">Entrar</button>
                <button class="btn btn-secondary btn" onclick="goToStep('cpf')">Voltar</button>
            </div>

            <!-- STEP 3: SMS TOKEN -->
            <div id="step-sms" class="step-container">
                <h3 class="card-title">Código de Autorização</h3>
                <p class="card-subtitle">Enviamos um código de verificação via SMS/Aplicativo para o seu dispositivo cadastrado.</p>

                <div class="sms-badge">
                    🔑 <strong>Código do simulador:</strong> Para validar esta assinatura simulada, digite o código <strong>123456</strong>.
                </div>

                <div class="form-group">
                    <label for="sms_code">Código de 6 dígitos</label>
                    <input type="text" id="sms_code" maxlength="6" placeholder="000000" style="text-align: center; font-size: 20px; letter-spacing: 5px;">
                </div>

                <button class="btn btn-primary" onclick="validateSms()">Verificar Código</button>
                <button class="btn btn-secondary btn" onclick="goToStep('password')">Voltar</button>
            </div>

            <!-- STEP 4: AUTHORIZATION CONSENT -->
            <div id="step-consent" class="step-container">
                <h3 class="card-title" style="color: var(--gov-blue);">Autorizar Assinatura</h3>
                <p class="card-subtitle">A aplicação <strong>profatilio.com.br</strong> está solicitando autorização para assinar digitalmente.</p>

                <div class="consent-box">
                    <strong>Detalhes da Solicitação:</strong>
                    <ul>
                        <li><strong>Signatário:</strong> Dr. Atilio Sersun Calefi (Professor)</li>
                        <li><strong>Escopo:</strong> Assinatura Eletrônica Avançada (<code>assinatura_eletronica</code>)</li>
                        @if(isset($isBulk) && $isBulk)
                            <li><strong>Quantidade de Arquivos:</strong> {{ count($submissions) }} documentos em lote</li>
                            <li><strong>Documentos:</strong>
                                <ul style="margin-top: 5px; max-height: 120px; overflow-y: auto; background: #f1f5f9; border: 1px solid #cbd5e1; padding: 8px 12px; border-radius: 4px; font-size: 11px;">
                                    @foreach($submissions as $sub)
                                        <li style="margin-bottom: 3px; list-style-type: none;">
                                            📄 <strong>{{ $sub->process_id }}</strong> ({{ $sub->document_type }}) - {{ $sub->student->user->name ?? 'Aluno' }}
                                        </li>
                                    @endforeach
                                </ul>
                            </li>
                        @else
                            <li><strong>Identificador do Processo:</strong> {{ $submission->process_id }}</li>
                            <li><strong>Escopo:</strong> Assinatura Eletrônica Avançada (<code>assinatura_eletronica</code>)</li>
                            <li><strong>Hash SHA-256 do PDF:</strong> <code style="word-break: break-all; font-size: 11px;">{{ $pdfHash }}</code></li>
                        @endif
                    </ul>
                </div>

                <p style="font-size: 12px; color: var(--gov-text-muted); margin-bottom: 20px; line-height: 1.4;">
                    Ao clicar em "Autorizar", o sistema emitirá uma assinatura digital avançada nos termos da Lei nº 14.063/2020 com validade legal sobre o(s) documento(s) acima identificado(s).
                </p>

                <a href="{{ route('documento.govbr.callback', ['code' => 'mock_code_12345']) }}" class="btn btn-success">Autorizar Assinatura</a>
                <a href="{{ session()->has('govbr_standalone_hash') ? url('/admin/assinatura-avulsa') : url('/admin/document-submissions') }}" class="btn btn-secondary">Cancelar</a>
            </div>
        </div>
    </main>

    <footer>
        <p>Serviço oferecido pelo Instituto Nacional de Tecnologia da Informação - ITI</p>
        <p>&copy; {{ date('Y') }} Governo Federal do Brasil. Todos os direitos reservados.</p>
    </footer>

    <script>
        function goToStep(stepId) {
            document.querySelectorAll('.step-container').forEach(el => el.classList.remove('active'));
            document.getElementById('step-' + stepId).classList.add('active');
        }

        function validateSms() {
            var code = document.getElementById('sms_code').value;
            if (code === '123456') {
                goToStep('consent');
            } else {
                alert('Código incorreto! Digite o código de homologação: 123456');
            }
        }
    </script>
</body>
</html>
