<?php
// Política de Privacidade do app Odù Journey em pt / en / es (mesmos idiomas do app).
// Idioma: ?lang=pt|en|es; sem parâmetro, segue o idioma do navegador.
$langs = ['pt', 'en', 'es'];
$lang = isset($_GET['lang']) ? strtolower(substr($_GET['lang'], 0, 2)) : '';
if (!in_array($lang, $langs, true)) {
    $accept = strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
    // Português para quem não informa idioma; inglês para idiomas que o app não tem
    $lang = $accept === '' ? 'pt' : 'en';
    if (preg_match('/^(pt|en|es)/', $accept, $m)) {
        $lang = $m[1];
    }
}
header('Vary: Accept-Language');
header('Content-Language: ' . ['pt' => 'pt-BR', 'en' => 'en', 'es' => 'es'][$lang]);
$base = 'https://www.csvet.app/privacidade.php';
$meta = [
    'pt' => ['html' => 'pt-BR', 'title' => 'Política de Privacidade | Odù Journey', 'desc' => 'Política de Privacidade do aplicativo Odù Journey (Jornada Odù), da CSVET Editora, em conformidade com a LGPD (Lei 13.709/2018).', 'nav' => ['Sobre', 'Apps', 'Contato']],
    'en' => ['html' => 'en', 'title' => 'Privacy Policy | Odù Journey', 'desc' => 'Privacy Policy of the Odù Journey app by CSVET Editora, in compliance with the Brazilian General Data Protection Law (LGPD).', 'nav' => ['About', 'Apps', 'Contact']],
    'es' => ['html' => 'es', 'title' => 'Política de Privacidad | Odù Journey', 'desc' => 'Política de Privacidad de la aplicación Odù Journey de CSVET Editora, conforme a la Ley General de Protección de Datos de Brasil (LGPD).', 'nav' => ['Acerca de', 'Apps', 'Contacto']],
][$lang];
?>
<!DOCTYPE html>
<html lang="<?php echo $meta['html']; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $meta['title']; ?></title>
    <meta name="description" content="<?php echo $meta['desc']; ?>">

    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <link rel="canonical" href="<?php echo $base . ($lang === 'pt' ? '' : '?lang=' . $lang); ?>">
    <link rel="alternate" hreflang="pt-BR" href="<?php echo $base; ?>">
    <link rel="alternate" hreflang="en" href="<?php echo $base; ?>?lang=en">
    <link rel="alternate" hreflang="es" href="<?php echo $base; ?>?lang=es">
    <link rel="alternate" hreflang="x-default" href="<?php echo $base; ?>">

    <!-- Sem Google Fonts nem Font Awesome nesta página: nenhum dado do visitante vai a terceiros -->
    <link rel="stylesheet" href="style.css">

    <style>
        .policy-content {
            padding: 40px 0;
            text-align: left;
            max-width: 820px;
            margin: 0 auto;
            font-family: Roboto, -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
        }
        .policy-content h1 { color: #000080; margin-bottom: 8px; text-align: center; }
        .policy-content .updated { text-align: center; color: #777; margin-bottom: 18px; }
        .lang-switch { display: flex; justify-content: center; gap: 8px; margin-bottom: 28px; }
        .lang-switch a { padding: 5px 14px; border: 1px solid #c6c6c6; border-radius: 20px; color: #000080; text-decoration: none; font-size: 0.9rem; font-weight: 600; }
        .lang-switch a[aria-current="page"] { background: #000080; border-color: #000080; color: #fff; }
        .policy-content h2 { color: #000080; margin-top: 34px; margin-bottom: 10px; font-size: 1.35rem; scroll-margin-top: 90px; }
        .policy-content h3 { color: #222; margin-top: 18px; margin-bottom: 6px; font-size: 1.05rem; }
        .policy-content p, .policy-content li { color: #444; line-height: 1.65; margin-bottom: 10px; font-size: 1rem; }
        .policy-content ul { margin-left: 22px; list-style-type: disc; }
        .policy-content a { color: #0088cc; }
        .policy-summary { background: #f4f6fb; border: 1px solid #e3e6ef; border-left: 4px solid #00aaff; border-radius: 8px; padding: 16px 20px; margin-bottom: 26px; }
        .policy-summary li { margin-bottom: 6px; }
        .table-wrap { overflow-x: auto; margin: 12px 0 16px; }
        .policy-content table { width: 100%; border-collapse: collapse; font-size: 0.94rem; }
        .policy-content th, .policy-content td { text-align: left; vertical-align: top; padding: 10px 12px; border-bottom: 1px solid #e3e6ef; color: #444; line-height: 1.5; }
        .policy-content th { background: #f4f6fb; color: #000080; font-weight: 600; }
    </style>
</head>
<body>

    <header>
        <div class="container">
            <div class="logo">
                <a href="index.php"> <img src="logo-branco.png" alt="Logo CSVET" class="header-logo">
                </a>
            </div>

            <nav>
                <ul>
                    <li><a href="index.php#sobre"><?php echo $meta['nav'][0]; ?></a></li>
                    <li><a href="index.php#apps"><?php echo $meta['nav'][1]; ?></a></li>
                    <li><a href="index.php#contato" class="btn-nav"><?php echo $meta['nav'][2]; ?></a></li>
                </ul>
            </nav>
        </div>
    </header>

    <section class="section-light">
        <div class="container policy-content">

            <nav class="lang-switch" aria-label="Idioma / Language / Idioma">
                <a href="?lang=pt" hreflang="pt-BR" <?php if ($lang === 'pt') echo 'aria-current="page"'; ?>>PT</a>
                <a href="?lang=en" hreflang="en" <?php if ($lang === 'en') echo 'aria-current="page"'; ?>>EN</a>
                <a href="?lang=es" hreflang="es" <?php if ($lang === 'es') echo 'aria-current="page"'; ?>>ES</a>
            </nav>

<?php if ($lang === 'pt'): ?>
            <h1>Política de Privacidade: Odù Journey</h1>
            <p class="updated">Aplicativo Odù Journey (Jornada Odù), Android <code>app.csvet.odujourney</code>. Última atualização: 07/10/2026.</p>

            <div class="policy-summary">
                <p><strong>Resumo</strong></p>
                <ul>
                    <li>Você pode usar o app <strong>sem cadastro</strong>. Nesse caso, seu progresso fica só no seu aparelho.</li>
                    <li>Nome e e-mail são <strong>opcionais</strong>. Se você informar o e-mail, guardamos seu progresso no nosso servidor e podemos enviar novidades do app.</li>
                    <li>Assinaturas são processadas pelo <strong>Google Play</strong> e pela <strong>RevenueCat</strong>. Não recebemos dados do seu cartão.</li>
                    <li>Não há anúncios, não vendemos dados e não usamos ferramentas de rastreamento ou análise de comportamento.</li>
                    <li>Para excluir seus dados, escreva para <a href="mailto:calefi@csvet.com.br">calefi@csvet.com.br</a>.</li>
                </ul>
            </div>

            <h2 id="controlador">1. Quem somos</h2>
            <p>O controlador dos dados pessoais é a <strong>CSVET Editora, Cursos e Treinamentos</strong>, mantida por 32.645.724 Atilio Sersun Calefi, CNPJ 32.645.724/0001-89, com sede em Americana/SP, Brasil.</p>
            <p><strong>Encarregado pelo tratamento de dados pessoais (art. 41 da LGPD):</strong> Atilio Sersun Calefi, e-mail <a href="mailto:calefi@csvet.com.br">calefi@csvet.com.br</a>. Suporte do aplicativo: <a href="mailto:contato@csvet.com.br">contato@csvet.com.br</a>.</p>

            <h2 id="abrangencia">2. Abrangência</h2>
            <p>Esta política vale para o aplicativo Odù Journey e para o servidor que ele usa em odujourney.com. Ela segue a Lei Geral de Proteção de Dados (Lei 13.709/2018, LGPD) e o Marco Civil da Internet (Lei 12.965/2014).</p>

            <h2 id="dados">3. Dados tratados, finalidades e bases legais</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>Dados</th><th>Quando e para quê</th><th>Base legal (LGPD)</th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Progresso (XP, nível), favoritos, idioma e preferências</td>
                            <td>Ficam <strong>somente no seu aparelho</strong> para o app funcionar. Não são enviados a nós se você não informar e-mail.</td>
                            <td>Execução do serviço que você pediu (art. 7º, V)</td>
                        </tr>
                        <tr>
                            <td>E-mail, nome ou apelido, idioma e um identificador aleatório da instalação (opcionais)</td>
                            <td>Enviados ao nosso servidor <strong>apenas se você preencher o e-mail</strong>, para salvar seu progresso e enviar novidades do app.</td>
                            <td>Consentimento (art. 7º, I), que você pode revogar a qualquer momento</td>
                        </tr>
                        <tr>
                            <td>XP, nível e se a assinatura Premium está ativa, ligados ao identificador da instalação</td>
                            <td>Sincronizados com o servidor <strong>apenas para quem informou e-mail</strong>, para guardar o progresso.</td>
                            <td>Consentimento (art. 7º, I)</td>
                        </tr>
                        <tr>
                            <td>Dados da compra: identificador anônimo de cliente, produto, data, recibo da transação, país e moeda, modelo do aparelho e versão do sistema</td>
                            <td>Processar e validar a assinatura Premium, restaurar compras e atender pedidos de suporte. Tratados pelo Google Play e pela RevenueCat.</td>
                            <td>Execução de contrato (art. 7º, V) e obrigações legais e fiscais (art. 7º, II)</td>
                        </tr>
                        <tr>
                            <td>Registros de acesso ao servidor: endereço IP, data e hora</td>
                            <td>Segurança, prevenção de abuso e diagnóstico de erros.</td>
                            <td>Obrigação legal (art. 7º, II, c/c art. 15 do Marco Civil) e legítimo interesse (art. 7º, IX)</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p>O identificador da instalação é um código aleatório criado pelo próprio app. Ele não é o número do aparelho, o IMEI nem o ID de publicidade.</p>

            <h3>Notificações</h3>
            <p>O lembrete diário ("Odù do dia") é agendado <strong>dentro do seu aparelho</strong>. Não usamos servidor de notificações e nada é enviado para isso. Você pode desativá-lo nas configurações do Android.</p>

            <h3>O que não fazemos</h3>
            <ul>
                <li>Não exibimos anúncios e não vendemos nem alugamos dados pessoais.</li>
                <li>Não usamos ferramentas de análise de comportamento, rastreamento ou publicidade.</li>
                <li>Não acessamos localização, contatos, câmera, microfone, fotos ou arquivos.</li>
                <li>Não pedimos nem registramos religião ou crença. O app é uma ferramenta de estudo. Como um cadastro em um app sobre a tradição de Ifá pode sugerir interesse no tema, tratamos esses dados com acesso restrito e nunca os usamos para publicidade nem os compartilhamos para esse fim.</li>
            </ul>

            <h2 id="terceiros">4. Com quem compartilhamos</h2>
            <ul>
                <li><strong>Google (Google Play):</strong> distribuição do app e cobrança da assinatura. O pagamento é feito na sua conta Google; não recebemos dados do cartão. <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">Política do Google</a>.</li>
                <li><strong>RevenueCat, Inc. (EUA):</strong> gestão e validação das assinaturas, com identificador anônimo. <a href="https://www.revenuecat.com/privacy" target="_blank" rel="noopener">Política da RevenueCat</a>.</li>
                <li><strong>Google Fonts:</strong> o app pode baixar as fontes tipográficas dos servidores do Google, que recebem o endereço IP do aparelho.</li>
                <li><strong>Empresa de hospedagem</strong> do servidor odujourney.com, como operadora, só para manter o serviço no ar.</li>
                <li><strong>Google (Google Workspace):</strong> hospeda nosso e-mail @csvet.com.br; as mensagens que você nos envia, inclusive pedidos de exclusão, ficam nele.</li>
                <li><strong>Autoridades públicas</strong>, quando houver ordem judicial ou requisição legal válida.</li>
            </ul>

            <h3>Transferência internacional</h3>
            <p>Google (Play, Fonts e Workspace) e RevenueCat podem processar dados fora do Brasil, principalmente nos Estados Unidos. Essa transferência é necessária para executar a assinatura que você contratou e ocorre nas hipóteses do art. 33 da LGPD, com as garantias contratuais adotadas por esses fornecedores.</p>

            <h2 id="retencao">5. Por quanto tempo guardamos</h2>
            <ul>
                <li><strong>E-mail, nome e progresso sincronizado:</strong> até você pedir a exclusão ou revogar o consentimento.</li>
                <li><strong>Registros de compras:</strong> pelo prazo exigido pela legislação fiscal e de defesa do consumidor.</li>
                <li><strong>Registros de acesso ao servidor:</strong> 6 meses (art. 15 do Marco Civil da Internet), depois eliminados.</li>
                <li><strong>Dados no aparelho:</strong> apagados quando você desinstala o app ou limpa os dados dele nas configurações do Android.</li>
            </ul>

            <h2 id="direitos">6. Seus direitos e como excluir seus dados</h2>
            <p>Pelo art. 18 da LGPD, você pode pedir, sem custo: confirmação e acesso aos seus dados, correção, anonimização, bloqueio ou eliminação, portabilidade, informação sobre compartilhamento, revogação do consentimento e eliminação dos dados tratados com base nele.</p>
            <p><strong>Para excluir seus dados</strong>, envie um e-mail para <a href="mailto:calefi@csvet.com.br?subject=Excluir%20meus%20dados%20-%20Od%C3%B9%20Journey">calefi@csvet.com.br</a> com o assunto "Excluir meus dados - Odù Journey", a partir do e-mail que você cadastrou no app. Confirmamos a exclusão em até 15 dias. Guardamos apenas o que a lei obriga, como registros fiscais de compras.</p>
            <p>Excluir os dados <strong>não cancela a assinatura</strong>. Para cancelar, use o Google Play: Pagamentos e assinaturas › Assinaturas.</p>
            <p>Para deixar de receber novidades por e-mail, responda a qualquer mensagem pedindo o descadastro ou escreva para o mesmo endereço.</p>
            <p>Você também pode reclamar à <a href="https://www.gov.br/anpd" target="_blank" rel="noopener">Autoridade Nacional de Proteção de Dados (ANPD)</a>.</p>

            <h2 id="seguranca">7. Segurança</h2>
            <p>A comunicação entre o app e o servidor é criptografada (HTTPS) e o acesso aos dados é restrito. Nenhum sistema é totalmente imune a falhas. Se ocorrer um incidente de segurança que possa causar risco ou dano relevante, comunicaremos a ANPD e os usuários afetados, conforme o art. 48 da LGPD.</p>

            <h2 id="criancas">8. Crianças e adolescentes</h2>
            <p>O app não é direcionado a menores de 13 anos e não coleta intencionalmente dados de crianças. Adolescentes devem usá-lo com o conhecimento dos responsáveis. Se você for responsável e souber de dados de uma criança enviados a nós, escreva para <a href="mailto:calefi@csvet.com.br">calefi@csvet.com.br</a> que eles serão eliminados (art. 14 da LGPD).</p>

            <h2 id="alteracoes">9. Alterações</h2>
            <p>Esta política pode mudar quando o app ganhar novos recursos ou a legislação for alterada. Mudanças relevantes serão informadas no app ou por e-mail, para quem o cadastrou. A data da última atualização fica no topo desta página.</p>

            <h2 id="contato">10. Contato</h2>
            <p><strong>Privacidade e encarregado de dados:</strong> <a href="mailto:calefi@csvet.com.br">calefi@csvet.com.br</a><br>
            <strong>Suporte do app:</strong> <a href="mailto:contato@csvet.com.br">contato@csvet.com.br</a><br>
            CSVET Editora, Cursos e Treinamentos · CNPJ 32.645.724/0001-89 · Americana/SP</p>

<?php elseif ($lang === 'en'): ?>
            <h1>Privacy Policy: Odù Journey</h1>
            <p class="updated">Odù Journey app (Jornada Odù), Android <code>app.csvet.odujourney</code>. Last updated: October 7, 2026.</p>

            <div class="policy-summary">
                <p><strong>Summary</strong></p>
                <ul>
                    <li>You can use the app <strong>without signing up</strong>. In that case, your progress stays only on your device.</li>
                    <li>Name and email are <strong>optional</strong>. If you provide your email, we store your progress on our server and may send you app updates.</li>
                    <li>Subscriptions are processed by <strong>Google Play</strong> and <strong>RevenueCat</strong>. We never receive your card details.</li>
                    <li>There are no ads, we do not sell data, and we do not use tracking or behavioral analytics tools.</li>
                    <li>To delete your data, write to <a href="mailto:calefi@csvet.com.br">calefi@csvet.com.br</a>.</li>
                </ul>
            </div>

            <h2 id="controller">1. Who we are</h2>
            <p>The data controller is <strong>CSVET Editora, Cursos e Treinamentos</strong>, operated by 32.645.724 Atilio Sersun Calefi, Brazilian company registration (CNPJ) 32.645.724/0001-89, based in Americana, São Paulo, Brazil.</p>
            <p><strong>Data Protection Officer (art. 41 of the LGPD):</strong> Atilio Sersun Calefi, email <a href="mailto:calefi@csvet.com.br">calefi@csvet.com.br</a>. App support: <a href="mailto:contato@csvet.com.br">contato@csvet.com.br</a>.</p>

            <h2 id="scope">2. Scope</h2>
            <p>This policy applies to the Odù Journey app and to the server it uses at odujourney.com. It follows the Brazilian General Data Protection Law (Law 13,709/2018, "LGPD") and the Brazilian Internet Civil Framework (Law 12,965/2014). If you live in another country, you may also have rights under your local law, and you can exercise them through the same contact.</p>

            <h2 id="data">3. Data we process, purposes and legal bases</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>Data</th><th>When and why</th><th>Legal basis (LGPD)</th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Progress (XP, level), favorites, language and preferences</td>
                            <td>Stored <strong>only on your device</strong> so the app can work. Not sent to us unless you provide an email.</td>
                            <td>Provision of the service you requested (art. 7, V)</td>
                        </tr>
                        <tr>
                            <td>Email, name or nickname, language and a random installation identifier (optional)</td>
                            <td>Sent to our server <strong>only if you enter your email</strong>, to save your progress and send you app updates.</td>
                            <td>Consent (art. 7, I), which you may withdraw at any time</td>
                        </tr>
                        <tr>
                            <td>XP, level and whether a Premium subscription is active, linked to the installation identifier</td>
                            <td>Synced with the server <strong>only for users who provided an email</strong>, to keep their progress.</td>
                            <td>Consent (art. 7, I)</td>
                        </tr>
                        <tr>
                            <td>Purchase data: anonymous customer ID, product, date, transaction receipt, country and currency, device model and OS version</td>
                            <td>To process and validate the Premium subscription, restore purchases and handle support requests. Processed by Google Play and RevenueCat.</td>
                            <td>Performance of a contract (art. 7, V) and legal and tax obligations (art. 7, II)</td>
                        </tr>
                        <tr>
                            <td>Server access logs: IP address, date and time</td>
                            <td>Security, abuse prevention and error diagnosis.</td>
                            <td>Legal obligation (art. 7, II, with art. 15 of the Internet Civil Framework) and legitimate interest (art. 7, IX)</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p>The installation identifier is a random code generated by the app itself. It is not your device serial number, IMEI or advertising ID.</p>

            <h3>Notifications</h3>
            <p>The daily reminder ("Odù of the day") is scheduled <strong>on your device</strong>. We do not use a push notification server, and nothing is sent to us for this. You can turn it off in your Android settings.</p>

            <h3>What we do not do</h3>
            <ul>
                <li>We do not show ads, and we do not sell or rent personal data.</li>
                <li>We do not use behavioral analytics, tracking or advertising tools.</li>
                <li>We do not access your location, contacts, camera, microphone, photos or files.</li>
                <li>We do not ask for or record your religion or beliefs. The app is a study tool. Since signing up for an app about the Ifá tradition may suggest an interest in the subject, we keep this data under restricted access and never use or share it for advertising.</li>
            </ul>

            <h2 id="sharing">4. Who we share data with</h2>
            <ul>
                <li><strong>Google (Google Play):</strong> app distribution and subscription billing. Payment is made through your Google account; we never receive card details. <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">Google Privacy Policy</a>.</li>
                <li><strong>RevenueCat, Inc. (USA):</strong> subscription management and validation, using an anonymous ID. <a href="https://www.revenuecat.com/privacy" target="_blank" rel="noopener">RevenueCat Privacy Policy</a>.</li>
                <li><strong>Google Fonts:</strong> the app may download typefaces from Google's servers, which receive your device's IP address.</li>
                <li><strong>Hosting provider</strong> of the odujourney.com server, acting as a processor, only to keep the service running.</li>
                <li><strong>Google (Google Workspace):</strong> hosts our @csvet.com.br email; messages you send us, including deletion requests, are stored there.</li>
                <li><strong>Public authorities</strong>, when there is a court order or a valid legal request.</li>
            </ul>

            <h3>International transfers</h3>
            <p>Google (Play, Fonts and Workspace) and RevenueCat may process data outside Brazil, mainly in the United States. This transfer is necessary to perform the subscription you purchased and takes place under art. 33 of the LGPD, with the contractual safeguards adopted by these providers.</p>

            <h2 id="retention">5. How long we keep data</h2>
            <ul>
                <li><strong>Email, name and synced progress:</strong> until you request deletion or withdraw your consent.</li>
                <li><strong>Purchase records:</strong> for the period required by tax and consumer protection laws.</li>
                <li><strong>Server access logs:</strong> 6 months (art. 15 of the Internet Civil Framework), then deleted.</li>
                <li><strong>Data on your device:</strong> erased when you uninstall the app or clear its data in your Android settings.</li>
            </ul>

            <h2 id="rights">6. Your rights and how to delete your data</h2>
            <p>Under art. 18 of the LGPD, you may request, free of charge: confirmation of and access to your data, correction, anonymization, blocking or deletion, portability, information about sharing, withdrawal of consent and deletion of data processed on the basis of consent.</p>
            <p><strong>To delete your data</strong>, send an email to <a href="mailto:calefi@csvet.com.br?subject=Delete%20my%20data%20-%20Od%C3%B9%20Journey">calefi@csvet.com.br</a> with the subject "Delete my data - Odù Journey", from the email address you registered in the app. We will confirm the deletion within 15 days. We keep only what the law requires, such as tax records of purchases.</p>
            <p>Deleting your data <strong>does not cancel your subscription</strong>. To cancel, use Google Play: Payments &amp; subscriptions › Subscriptions.</p>
            <p>To stop receiving updates by email, reply to any message asking to unsubscribe or write to the same address.</p>
            <p>You may also file a complaint with the Brazilian <a href="https://www.gov.br/anpd" target="_blank" rel="noopener">National Data Protection Authority (ANPD)</a>.</p>

            <h2 id="security">7. Security</h2>
            <p>Communication between the app and the server is encrypted (HTTPS), and access to data is restricted. No system is completely immune to failures. If a security incident occurs that may cause significant risk or harm, we will notify the ANPD and the affected users, as required by art. 48 of the LGPD.</p>

            <h2 id="children">8. Children and teenagers</h2>
            <p>The app is not directed at children under 13 and does not knowingly collect data from children. Teenagers should use it with the knowledge of their parents or guardians. If you are a parent or guardian and become aware that a child's data has been sent to us, write to <a href="mailto:calefi@csvet.com.br">calefi@csvet.com.br</a> and it will be deleted (art. 14 of the LGPD).</p>

            <h2 id="changes">9. Changes</h2>
            <p>This policy may change when the app gains new features or the law changes. Significant changes will be announced in the app or by email to users who registered one. The date of the last update is always shown at the top of this page.</p>

            <h2 id="contact">10. Contact</h2>
            <p><strong>Privacy and Data Protection Officer:</strong> <a href="mailto:calefi@csvet.com.br">calefi@csvet.com.br</a><br>
            <strong>App support:</strong> <a href="mailto:contato@csvet.com.br">contato@csvet.com.br</a><br>
            CSVET Editora, Cursos e Treinamentos · CNPJ 32.645.724/0001-89 · Americana, SP, Brazil</p>
            <p><em>This is a translation. In case of any discrepancy, the Portuguese version prevails.</em></p>

<?php else: ?>
            <h1>Política de Privacidad: Odù Journey</h1>
            <p class="updated">Aplicación Odù Journey (Jornada Odù), Android <code>app.csvet.odujourney</code>. Última actualización: 7 de octubre de 2026.</p>

            <div class="policy-summary">
                <p><strong>Resumen</strong></p>
                <ul>
                    <li>Puede usar la aplicación <strong>sin registrarse</strong>. En ese caso, su progreso queda solo en su dispositivo.</li>
                    <li>El nombre y el correo electrónico son <strong>opcionales</strong>. Si informa su correo, guardamos su progreso en nuestro servidor y podemos enviarle novedades de la aplicación.</li>
                    <li>Las suscripciones las procesan <strong>Google Play</strong> y <strong>RevenueCat</strong>. Nunca recibimos los datos de su tarjeta.</li>
                    <li>No hay anuncios, no vendemos datos y no usamos herramientas de rastreo ni de análisis de comportamiento.</li>
                    <li>Para eliminar sus datos, escriba a <a href="mailto:calefi@csvet.com.br">calefi@csvet.com.br</a>.</li>
                </ul>
            </div>

            <h2 id="responsable">1. Quiénes somos</h2>
            <p>El responsable del tratamiento de los datos personales es <strong>CSVET Editora, Cursos e Treinamentos</strong>, gestionada por 32.645.724 Atilio Sersun Calefi, registro mercantil brasileño (CNPJ) 32.645.724/0001-89, con sede en Americana, São Paulo, Brasil.</p>
            <p><strong>Encargado de protección de datos (art. 41 de la LGPD):</strong> Atilio Sersun Calefi, correo <a href="mailto:calefi@csvet.com.br">calefi@csvet.com.br</a>. Soporte de la aplicación: <a href="mailto:contato@csvet.com.br">contato@csvet.com.br</a>.</p>

            <h2 id="alcance">2. Alcance</h2>
            <p>Esta política se aplica a la aplicación Odù Journey y al servidor que utiliza en odujourney.com. Sigue la Ley General de Protección de Datos de Brasil (Ley 13.709/2018, "LGPD") y el Marco Civil de Internet de Brasil (Ley 12.965/2014). Si vive en otro país, también puede tener derechos según su legislación local, que puede ejercer por el mismo contacto.</p>

            <h2 id="datos">3. Datos tratados, finalidades y bases legales</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>Datos</th><th>Cuándo y para qué</th><th>Base legal (LGPD)</th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Progreso (XP, nivel), favoritos, idioma y preferencias</td>
                            <td>Se guardan <strong>solo en su dispositivo</strong> para que la aplicación funcione. No se nos envían si no informa un correo.</td>
                            <td>Prestación del servicio que usted solicitó (art. 7, V)</td>
                        </tr>
                        <tr>
                            <td>Correo electrónico, nombre o apodo, idioma y un identificador aleatorio de la instalación (opcionales)</td>
                            <td>Se envían a nuestro servidor <strong>solo si completa el correo</strong>, para guardar su progreso y enviarle novedades de la aplicación.</td>
                            <td>Consentimiento (art. 7, I), que puede revocar en cualquier momento</td>
                        </tr>
                        <tr>
                            <td>XP, nivel y si la suscripción Premium está activa, vinculados al identificador de la instalación</td>
                            <td>Se sincronizan con el servidor <strong>solo para quienes informaron un correo</strong>, para conservar el progreso.</td>
                            <td>Consentimiento (art. 7, I)</td>
                        </tr>
                        <tr>
                            <td>Datos de compra: identificador anónimo de cliente, producto, fecha, recibo de la transacción, país y moneda, modelo del dispositivo y versión del sistema</td>
                            <td>Procesar y validar la suscripción Premium, restaurar compras y atender solicitudes de soporte. Los tratan Google Play y RevenueCat.</td>
                            <td>Ejecución de un contrato (art. 7, V) y obligaciones legales y fiscales (art. 7, II)</td>
                        </tr>
                        <tr>
                            <td>Registros de acceso al servidor: dirección IP, fecha y hora</td>
                            <td>Seguridad, prevención de abusos y diagnóstico de errores.</td>
                            <td>Obligación legal (art. 7, II, junto con el art. 15 del Marco Civil de Internet) e interés legítimo (art. 7, IX)</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p>El identificador de la instalación es un código aleatorio generado por la propia aplicación. No es el número de serie del dispositivo, el IMEI ni el ID de publicidad.</p>

            <h3>Notificaciones</h3>
            <p>El recordatorio diario ("Odù del día") se programa <strong>en su dispositivo</strong>. No usamos un servidor de notificaciones y no se nos envía nada para ello. Puede desactivarlo en los ajustes de Android.</p>

            <h3>Lo que no hacemos</h3>
            <ul>
                <li>No mostramos anuncios ni vendemos o alquilamos datos personales.</li>
                <li>No usamos herramientas de análisis de comportamiento, rastreo ni publicidad.</li>
                <li>No accedemos a su ubicación, contactos, cámara, micrófono, fotos ni archivos.</li>
                <li>No pedimos ni registramos su religión o creencias. La aplicación es una herramienta de estudio. Como registrarse en una aplicación sobre la tradición de Ifá puede sugerir interés en el tema, tratamos estos datos con acceso restringido y nunca los usamos ni compartimos con fines publicitarios.</li>
            </ul>

            <h2 id="compartir">4. Con quién compartimos datos</h2>
            <ul>
                <li><strong>Google (Google Play):</strong> distribución de la aplicación y cobro de la suscripción. El pago se hace con su cuenta de Google; nunca recibimos datos de la tarjeta. <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">Política de privacidad de Google</a>.</li>
                <li><strong>RevenueCat, Inc. (EE. UU.):</strong> gestión y validación de las suscripciones, con un identificador anónimo. <a href="https://www.revenuecat.com/privacy" target="_blank" rel="noopener">Política de privacidad de RevenueCat</a>.</li>
                <li><strong>Google Fonts:</strong> la aplicación puede descargar tipografías de los servidores de Google, que reciben la dirección IP del dispositivo.</li>
                <li><strong>Proveedor de alojamiento</strong> del servidor odujourney.com, como encargado del tratamiento, solo para mantener el servicio en funcionamiento.</li>
                <li><strong>Google (Google Workspace):</strong> aloja nuestro correo @csvet.com.br; los mensajes que nos envía, incluidas las solicitudes de eliminación, se guardan allí.</li>
                <li><strong>Autoridades públicas</strong>, cuando exista una orden judicial o un requerimiento legal válido.</li>
            </ul>

            <h3>Transferencias internacionales</h3>
            <p>Google (Play, Fonts y Workspace) y RevenueCat pueden tratar datos fuera de Brasil, principalmente en Estados Unidos. Esta transferencia es necesaria para ejecutar la suscripción que usted contrató y se realiza conforme al art. 33 de la LGPD, con las garantías contractuales adoptadas por estos proveedores.</p>

            <h2 id="conservacion">5. Cuánto tiempo conservamos los datos</h2>
            <ul>
                <li><strong>Correo, nombre y progreso sincronizado:</strong> hasta que solicite su eliminación o revoque su consentimiento.</li>
                <li><strong>Registros de compras:</strong> durante el plazo exigido por la legislación fiscal y de protección al consumidor.</li>
                <li><strong>Registros de acceso al servidor:</strong> 6 meses (art. 15 del Marco Civil de Internet); después se eliminan.</li>
                <li><strong>Datos en su dispositivo:</strong> se borran al desinstalar la aplicación o al borrar sus datos en los ajustes de Android.</li>
            </ul>

            <h2 id="derechos">6. Sus derechos y cómo eliminar sus datos</h2>
            <p>Según el art. 18 de la LGPD, puede solicitar sin costo: confirmación y acceso a sus datos, corrección, anonimización, bloqueo o eliminación, portabilidad, información sobre con quién se compartieron, revocación del consentimiento y eliminación de los datos tratados con base en él.</p>
            <p><strong>Para eliminar sus datos</strong>, envíe un correo a <a href="mailto:calefi@csvet.com.br?subject=Eliminar%20mis%20datos%20-%20Od%C3%B9%20Journey">calefi@csvet.com.br</a> con el asunto "Eliminar mis datos - Odù Journey", desde la dirección que registró en la aplicación. Confirmaremos la eliminación en un plazo de 15 días. Solo conservamos lo que exige la ley, como los registros fiscales de compras.</p>
            <p>Eliminar sus datos <strong>no cancela la suscripción</strong>. Para cancelarla, use Google Play: Pagos y suscripciones › Suscripciones.</p>
            <p>Para dejar de recibir novedades por correo, responda a cualquier mensaje pidiendo la baja o escriba a la misma dirección.</p>
            <p>También puede presentar una reclamación ante la <a href="https://www.gov.br/anpd" target="_blank" rel="noopener">Autoridad Nacional de Protección de Datos de Brasil (ANPD)</a>.</p>

            <h2 id="seguridad">7. Seguridad</h2>
            <p>La comunicación entre la aplicación y el servidor está cifrada (HTTPS) y el acceso a los datos está restringido. Ningún sistema es totalmente inmune a fallos. Si ocurre un incidente de seguridad que pueda causar un riesgo o daño relevante, lo comunicaremos a la ANPD y a los usuarios afectados, conforme al art. 48 de la LGPD.</p>

            <h2 id="menores">8. Niños y adolescentes</h2>
            <p>La aplicación no está dirigida a menores de 13 años y no recopila intencionalmente datos de niños. Los adolescentes deben usarla con conocimiento de sus padres o tutores. Si usted es padre, madre o tutor y sabe que se nos enviaron datos de un niño, escriba a <a href="mailto:calefi@csvet.com.br">calefi@csvet.com.br</a> y serán eliminados (art. 14 de la LGPD).</p>

            <h2 id="cambios">9. Cambios</h2>
            <p>Esta política puede cambiar cuando la aplicación incorpore nuevas funciones o cambie la legislación. Los cambios relevantes se informarán en la aplicación o por correo a quienes lo hayan registrado. La fecha de la última actualización aparece siempre al inicio de esta página.</p>

            <h2 id="contacto">10. Contacto</h2>
            <p><strong>Privacidad y encargado de protección de datos:</strong> <a href="mailto:calefi@csvet.com.br">calefi@csvet.com.br</a><br>
            <strong>Soporte de la aplicación:</strong> <a href="mailto:contato@csvet.com.br">contato@csvet.com.br</a><br>
            CSVET Editora, Cursos e Treinamentos · CNPJ 32.645.724/0001-89 · Americana, SP, Brasil</p>
            <p><em>Esta es una traducción. En caso de discrepancia, prevalece la versión en portugués.</em></p>
<?php endif; ?>
        </div>
    </section>

    <footer>
        <div class="container">
            <p>&copy; 2026 CSVET Editora, Cursos e Treinamentos · CNPJ 32.645.724/0001-89</p>
            <p>Desenvolvido por Prof. Atilio.</p>
            <div class="social-links">
                <a href="https://linktr.ee/profatilio" target="_blank" rel="noopener">Acesse meu Linktree Completo</a>
            </div>
        </div>
    </footer>

</body>
</html>
