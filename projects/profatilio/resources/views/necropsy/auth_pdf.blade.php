<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Autorizacao para Exame Necroscopico - {{ $necropsyRequest->id }}</title>
    <style>
        @page {
            margin: 40px;
        }
        body {
            font-family: 'Helvetica Neue', 'Helvetica', 'Arial', sans-serif;
            color: #1e293b;
            font-size: 11px;
            line-height: 1.4;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #0d9488;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .header h1 {
            font-size: 16px;
            margin: 0;
            color: #0d9488;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .header p {
            font-size: 10px;
            margin: 3px 0 0 0;
            color: #64748b;
            text-transform: uppercase;
        }
        .section-title {
            font-size: 11px;
            color: #0d9488;
            font-weight: bold;
            text-transform: uppercase;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 3px;
            margin-top: 15px;
            margin-bottom: 8px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        table td {
            padding: 4px 6px;
            vertical-align: top;
            border: 1px solid #e2e8f0;
        }
        table td.label {
            font-weight: bold;
            color: #475569;
            width: 25%;
            background-color: #f8fafc;
        }
        .clauses {
            text-align: justify;
            font-size: 8px;
            color: #334155;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 10px;
            border-radius: 6px;
            margin-top: 15px;
            line-height: 1.3;
        }
        .clauses p {
            margin: 0 0 6px 0;
        }
        .clauses p:last-child {
            margin: 0;
        }
        .signatures-table {
            margin-top: 25px;
            border: none;
        }
        .signatures-table td {
            border: none;
            text-align: center;
            width: 50%;
            padding: 10px;
        }
        .signature-line {
            border-top: 1px solid #94a3b8;
            margin-top: 40px;
            padding-top: 5px;
            font-size: 10px;
        }
        .signature-img {
            max-height: 50px;
            max-width: 200px;
            display: block;
            margin: 0 auto;
        }
        .certificate-box {
            margin-top: 30px;
            border: 2px solid #0d9488;
            background-color: #f0fdfa;
            padding: 12px;
            border-radius: 8px;
        }
        .certificate-title {
            font-size: 10px;
            color: #0d9488;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 6px;
        }
        .certificate-grid {
            width: 100%;
        }
        .certificate-grid td {
            border: none;
            padding: 2px 4px;
            font-size: 9px;
        }
        .certificate-grid td.c-label {
            font-weight: bold;
            color: #0d9488;
            width: 20%;
        }
        .hash-code {
            font-family: monospace;
            background-color: #ffffff;
            border: 1px solid #ccfbf1;
            padding: 4px;
            border-radius: 4px;
            font-size: 9px;
            color: #0d9488;
            word-break: break-all;
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>Autorização para Exame Necroscópico</h1>
        <p>Plataforma Prof. Dr. Atilio Sersun Calefi — profatilio.com.br</p>
    </div>

    <!-- Seção 1: Dados do Animal -->
    <div class="section-title">1. Identificação do Animal</div>
    <table>
        <tr>
            <td class="label">Nome do Animal:</td>
            <td>{{ $necropsyRequest->animal_name }}</td>
            <td class="label">Espécie:</td>
            <td>{{ $necropsyRequest->species }}</td>
        </tr>
        <tr>
            <td class="label">Raça:</td>
            <td>{{ $necropsyRequest->breed ?: 'N/A' }}</td>
            <td class="label">Idade Estimada:</td>
            <td>{{ $necropsyRequest->age ?: 'N/A' }}</td>
        </tr>
        <tr>
            <td class="label">Sexo:</td>
            <td>{{ $necropsyRequest->sex ?: 'N/A' }}</td>
            <td class="label">Pelagem:</td>
            <td>{{ $necropsyRequest->pelage ?: 'N/A' }}</td>
        </tr>
        <tr>
            <td class="label">Peso (kg):</td>
            <td>{{ number_format($necropsyRequest->weight, 2, ',', '.') }} kg</td>
            <td class="label">Eutanásia?</td>
            <td>{{ $necropsyRequest->is_euthanasia ? 'Sim' : 'Não' }}</td>
        </tr>
    </table>

    <!-- Seção 2: Dados do Requisitante -->
    <div class="section-title">2. Requisitante (Responsável Legal)</div>
    <table>
        <tr>
            <td class="label">Nome Completo:</td>
            <td colspan="3">{{ $necropsyRequest->requester_name }}</td>
        </tr>
        <tr>
            <td class="label">CPF:</td>
            <td>{{ $necropsyRequest->requester_cpf }}</td>
            <td class="label">RG:</td>
            <td>{{ $necropsyRequest->requester_rg }}</td>
        </tr>
        @if($necropsyRequest->requester_cnpj)
        <tr>
            <td class="label">CNPJ:</td>
            <td colspan="3">{{ $necropsyRequest->requester_cnpj }}</td>
        </tr>
        @endif
        <tr>
            <td class="label">E-mail:</td>
            <td>{{ $necropsyRequest->requester_email }}</td>
            <td class="label">Telefone:</td>
            <td>{{ $necropsyRequest->requester_phone }}</td>
        </tr>
        <tr>
            <td class="label">Endereço:</td>
            <td colspan="3">
                {{ $necropsyRequest->requester_address }}
                @if($necropsyRequest->requester_complement) ({{ $necropsyRequest->requester_complement }}) @endif
            </td>
        </tr>
        <tr>
            <td class="label">Bairro:</td>
            <td>{{ $necropsyRequest->requester_neighborhood }}</td>
            <td class="label">CEP:</td>
            <td>{{ $necropsyRequest->requester_zip }}</td>
        </tr>
        <tr>
            <td class="label">Cidade:</td>
            <td>{{ $necropsyRequest->requester_city }}</td>
            <td class="label">Estado (UF):</td>
            <td>{{ $necropsyRequest->requester_state }}</td>
        </tr>
    </table>

    <!-- Seção 3: Informações do Veterinário -->
    @if($necropsyRequest->requested_by_vet)
    <div class="section-title">3. Solicitação por Médico Veterinário</div>
    <table>
        <tr>
            <td class="label">CRMV:</td>
            <td>{{ $necropsyRequest->vet_crmv }} - {{ $necropsyRequest->vet_state }}</td>
            <td class="label">Estabelecimento:</td>
            <td>{{ $necropsyRequest->vet_establishment ?: 'N/A' }}</td>
        </tr>
    </table>
    @endif

    <!-- Seção 4: Detalhes Adicionais -->
    <div class="section-title">4. Informações Adicionais do Exame</div>
    <table>
        <tr>
            <td class="label">Plano de Cremação?</td>
            <td>{{ $necropsyRequest->has_cremation_plan ? 'Sim (' . $necropsyRequest->cremation_company . ')' : 'Não' }}</td>
            <td class="label">Próteses?</td>
            <td>{{ $necropsyRequest->has_prosthesis ? 'Sim (' . $necropsyRequest->prosthesis_details . ')' : 'Não' }}</td>
        </tr>
        <tr>
            <td class="label">Cirurgia Prévia?</td>
            <td colspan="3">{{ $necropsyRequest->had_surgery ? 'Sim (' . $necropsyRequest->surgery_details . ')' : 'Não' }}</td>
        </tr>
        <tr>
            <td class="label">Preço Calculado:</td>
            <td>R$ {{ number_format($necropsyRequest->price, 2, ',', '.') }}</td>
            <td class="label">Valor Final Cobrado:</td>
            <td><strong>R$ {{ number_format($necropsyRequest->final_price, 2, ',', '.') }}</strong></td>
        </tr>
    </table>

    <!-- Seção 5: Termo -->
    <div class="clauses">
        <p><strong>DECLARAÇÕES E CONSENTIMENTO LEGAL:</strong></p>
        <p>
            1. Como requisitante do exame necroscópico do animal acima identificado, autorizo a realização de necropsia compreendendo a emissão de relatório macroscópico, realização de exame histopatológico e fotodocumentação.
        </p>
        <p>
            2. Declaro estar ciente que, no caso da necropsia cosmética, devo retirar o cadáver até 5 dias úteis após o exame. No caso de este prazo ter sido ultrapassado, e na necropsia documentada ou diagnóstica, o cadáver receberá a destinação estipulada pelas normas sanitárias vigentes no município, estando ciente que o mesmo não poderá ser recuperado posteriormente.
        </p>
        <p>
            3. Em caso de cremação por empresa externa, a remoção e procedimentos são de responsabilidade de contrato entre o solicitante e empresa terceirizada.
        </p>
        <p>
            4. Declaro estar ciente que: o prazo para entrega do relatório final é de 30 dias após a realização da necropsia, podendo ser estendido devido à complexidade do caso e necessidade de testes complementares.
        </p>
        <p>
            5. Para manutenção de matrizes biológicas destinadas a outros exames, que não solicitadas pelo patologista responsável, será cobrada taxa adicional para a sua manutenção.
        </p>
        <p>
            6. O histórico clínico fornecido é de responsabilidade do Médico Veterinário e Responsável Legal, sendo o mesmo transcrito tal como está na requisição de exame, não sendo passível de alteração posterior.
        </p>
        <p>
            7. As alterações decorrentes do processo de congelamento ou acondicionamento anterior a entrega do animal ao patologista responsável são de responsabilidade do Responsável Legal e/ou Médico Veterinário.
        </p>
        <p>
            8. Não é de responsabilidade do patologista responsável a ausência de informações no histórico clínico encaminhado.
        </p>
        <p>
            9. O esclarecimento de dúvidas somente será realizado mediante agendamento prévio via email (ascalefi@gmail.com), comunicação por outras vias serão desconsideradas devido a necessidade de agendamento de horário condizente com a agenda do patologista.
        </p>
        <p>
            10. A realização de assessoria técnica Médico-Veterinária destinada a propositura judicial não está contemplada no exame necroscópico, para tal deve ser realizada solicitação para encaminhamento de orçamento.
        </p>
        <p>
            11. No caso de o requisitante ser médico veterinário, este declara que o proprietário autorizou este exame e descarte no estabelecimento médico-veterinário de origem.
        </p>
    </div>

    <!-- Seção 6: Assinaturas -->
    <table class="signatures-table">
        <tr>
            <td>
                @if($necropsyRequest->signature_image)
                    <img class="signature-img" src="{{ $necropsyRequest->signature_image }}" alt="Assinatura">
                @endif
                <div class="signature-line">
                    <strong>{{ $necropsyRequest->signature_name }}</strong><br>
                    Requisitante (Assinatura Digital)
                </div>
            </td>
            <td>
                <div style="height: 50px;"></div>
                <div class="signature-line">
                    <strong>Prof. Dr. Atilio Sersun Calefi</strong><br>
                    Patologista Responsável
                </div>
            </td>
        </tr>
    </table>

    <!-- Testemunhas se preenchidas -->
    @if($necropsyRequest->witness1_name || $necropsyRequest->witness2_name)
    <div class="section-title">Testemunhas</div>
    <table class="signatures-table" style="margin-top: 10px;">
        <tr>
            @if($necropsyRequest->witness1_name)
            <td>
                <div class="signature-line" style="margin-top: 20px;">
                    <strong>{{ $necropsyRequest->witness1_name }}</strong><br>
                    CPF: {{ $necropsyRequest->witness1_cpf }}
                </div>
            </td>
            @endif
            @if($necropsyRequest->witness2_name)
            <td>
                <div class="signature-line" style="margin-top: 20px;">
                    <strong>{{ $necropsyRequest->witness2_name }}</strong><br>
                    CPF: {{ $necropsyRequest->witness2_cpf }}
                </div>
            </td>
            @endif
        </tr>
    </table>
    @endif

    <!-- Seção 7: Certificado Forense -->
    <div class="certificate-box">
        <div class="certificate-title">Certificado de Assinatura e Auditoria Forense</div>
        <table class="certificate-grid">
            <tr>
                <td class="c-label">Hash SHA-256:</td>
                <td colspan="3">
                    <div class="hash-code">{{ $necropsyRequest->forensic_hash }}</div>
                </td>
            </tr>
            <tr>
                <td class="c-label">IP do Assinante:</td>
                <td>{{ $necropsyRequest->forensic_ip }}</td>
                <td class="c-label">Data e Hora:</td>
                <td>{{ $necropsyRequest->forensic_timestamp }}</td>
            </tr>
            <tr>
                <td class="c-label">Navegador:</td>
                <td colspan="3" style="color: #64748b; font-size: 8px;">
                    {{ $necropsyRequest->forensic_user_agent }}
                </td>
            </tr>
        </table>
    </div>

</body>
</html>
