<?php
require_once __DIR__ . '/data.php';
$page = [
    'title' => 'Política de Privacidade',
    'description' => 'Como a CSVET Editora trata dados pessoais em csvet.com.br, em conformidade com a LGPD (Lei 13.709/2018) e o Marco Civil da Internet.',
    'path' => '/privacidade',
    'heading' => 'Política de Privacidade',
    'lead' => 'Última atualização: ' . $legal['updated'] . '. Em conformidade com a Lei Geral de Proteção de Dados (Lei 13.709/2018).',
];
$e = fn($v) => htmlspecialchars($v);
include __DIR__ . '/partials/page-start.php';
?>
        <nav class="legal-toc" aria-label="Sumário">
            <ol>
                <li><a href="#controlador">Quem é o controlador</a></li>
                <li><a href="#abrangencia">Abrangência</a></li>
                <li><a href="#dados">Dados tratados</a></li>
                <li><a href="#cookies">Cookies e rastreamento</a></li>
                <li><a href="#compartilhamento">Compartilhamento</a></li>
                <li><a href="#retencao">Retenção</a></li>
                <li><a href="#seguranca">Segurança</a></li>
                <li><a href="#direitos">Seus direitos</a></li>
                <li><a href="#criancas">Crianças e adolescentes</a></li>
                <li><a href="#alteracoes">Alterações</a></li>
            </ol>
        </nav>

        <h2 id="controlador">1. Quem é o controlador</h2>
        <p>O controlador dos dados pessoais tratados neste site é a <strong><?php echo $e($legal['controller']); ?></strong>, mantida por <?php echo $e($legal['responsible']); ?><?php if ($legal['cnpj']): ?>, CNPJ <?php echo $e($legal['cnpj']); ?><?php endif; ?><?php if ($legal['address']): ?>, com sede em <?php echo $e($legal['address']); ?><?php endif; ?>.</p>
        <p><strong>Encarregado pelo tratamento de dados pessoais (art. 41 da LGPD):</strong> <?php echo $e($legal['dpo_name']); ?>, e-mail <a href="mailto:<?php echo $e($legal['dpo_email']); ?>"><?php echo $e($legal['dpo_email']); ?></a>. É o canal para qualquer pedido ou dúvida sobre seus dados.</p>

        <h2 id="abrangencia">2. Abrangência</h2>
        <p>Esta política vale para o site institucional <strong>csvet.com.br</strong>. As plataformas ligadas a partir dele (por exemplo csvet.app, vetrank.com.br, prof-aid.com e plumoragen.com) são serviços distintos, com termos e políticas próprios, que devem ser consultados em cada uma.</p>
        <p>O portal de periódicos <a href="https://journals.csvet.com.br">journals.csvet.com.br</a> trata os dados de autores, avaliadores e leitores cadastrados conforme a declaração de privacidade publicada em cada revista, por exemplo a do <a href="<?php echo $e($journals['nvas']['url']); ?>/about/privacy">Neotropical Veterinary and Animal Science</a>.</p>

        <h2 id="dados">3. Dados tratados, finalidades e bases legais</h2>
        <p>Este site é apenas informativo: <strong>não tem cadastro, formulário, área de login, venda nem publicidade</strong>. Os únicos tratamentos de dados pessoais são estes:</p>
        <div class="legal-table-wrap">
            <table>
                <thead>
                    <tr><th>Dados</th><th>Finalidade</th><th>Base legal (LGPD)</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Registros de acesso gerados automaticamente pelo servidor: endereço IP, data e hora, página solicitada, navegador e página de origem.</td>
                        <td>Funcionamento do site, segurança, prevenção de fraudes e ataques, diagnóstico de erros.</td>
                        <td>Cumprimento de obrigação legal (art. 7º, II, c/c art. 15 do Marco Civil da Internet, quando aplicável) e legítimo interesse (art. 7º, IX).</td>
                    </tr>
                    <tr>
                        <td>Dados que você decidir enviar por e-mail: nome, endereço de e-mail e o conteúdo da mensagem.</td>
                        <td>Responder ao seu contato e dar andamento ao que foi solicitado.</td>
                        <td>Procedimentos preliminares a um contrato, a seu pedido (art. 7º, V), ou legítimo interesse (art. 7º, IX).</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p>Não tratamos dados pessoais sensíveis, não fazemos perfis de comportamento e não tomamos decisões automatizadas sobre você.</p>

        <h2 id="cookies">4. Cookies e rastreamento</h2>
        <p>Este site <strong>não usa cookies</strong>, pixels, ferramentas de análise de audiência nem de publicidade. As fontes e imagens são servidas pelo próprio site, sem chamadas a serviços de terceiros. Por isso não há banner de consentimento de cookies.</p>
        <p>Ao clicar em um link para outro site ou rede social (como YouTube e Instagram), você passa a ser regido pela política de privacidade daquele serviço.</p>

        <h2 id="compartilhamento">5. Compartilhamento</h2>
        <p>Não vendemos nem cedemos dados pessoais. Eles só são acessados por:</p>
        <ul>
            <li><strong>Empresa de hospedagem do site</strong>, que atua como operadora, apenas para manter o site no ar;</li>
            <li><strong>Google (Google Workspace)</strong>, que hospeda o e-mail @csvet.com.br e atua como operador das mensagens que você nos envia. <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">Política do Google</a>;</li>
            <li><strong>Autoridades públicas</strong>, quando houver ordem judicial ou requisição legal válida.</li>
        </ul>
        <p>O Google pode armazenar as mensagens de e-mail fora do Brasil, principalmente nos Estados Unidos. Essa transferência internacional ocorre nas hipóteses do art. 33 da LGPD, com as garantias contratuais adotadas pelo Google para o Workspace.</p>

        <h2 id="retencao">6. Retenção</h2>
        <ul>
            <li><strong>Registros de acesso:</strong> mantidos pelo período necessário à segurança do site e, quando exigido pelo art. 15 do Marco Civil da Internet, por no mínimo 6 meses. Depois disso são eliminados.</li>
            <li><strong>Mensagens de e-mail:</strong> pelo tempo necessário para atender ao pedido e cumprir obrigações legais ou o exercício regular de direitos (art. 16 da LGPD).</li>
        </ul>

        <h2 id="seguranca">7. Segurança</h2>
        <p>O site funciona apenas com conexão criptografada (HTTPS), o acesso administrativo é restrito e os arquivos internos não ficam expostos. Em caso de incidente de segurança que possa causar risco ou dano relevante, a Autoridade Nacional de Proteção de Dados (ANPD) e os titulares afetados serão comunicados, conforme o art. 48 da LGPD.</p>

        <h2 id="direitos">8. Seus direitos</h2>
        <p>Pelo art. 18 da LGPD, você pode pedir a qualquer momento, sem custo:</p>
        <ul>
            <li>confirmação de que tratamos dados seus e acesso a eles;</li>
            <li>correção de dados incompletos, inexatos ou desatualizados;</li>
            <li>anonimização, bloqueio ou eliminação de dados desnecessários, excessivos ou tratados em desconformidade com a lei;</li>
            <li>portabilidade dos dados a outro fornecedor;</li>
            <li>eliminação dos dados tratados com base no seu consentimento;</li>
            <li>informação sobre com quem seus dados foram compartilhados;</li>
            <li>revisão de decisões tomadas unicamente com base em tratamento automatizado;</li>
            <li>oposição a tratamento feito em desconformidade com a lei.</li>
        </ul>
        <p>Para exercer qualquer desses direitos, escreva para <a href="mailto:<?php echo $e($legal['dpo_email']); ?>"><?php echo $e($legal['dpo_email']); ?></a>. Podemos pedir informações para confirmar sua identidade. A resposta completa sobre acesso aos dados é enviada em até 15 dias (art. 19, II).</p>
        <p>Você também pode apresentar reclamação à <a href="https://www.gov.br/anpd" target="_blank" rel="noopener">Autoridade Nacional de Proteção de Dados (ANPD)</a>.</p>

        <h2 id="criancas">9. Crianças e adolescentes</h2>
        <p>O site é voltado a estudantes, profissionais e pesquisadores e não coleta intencionalmente dados de crianças. Se você for responsável legal e souber de algum dado de criança ou adolescente enviado a nós, entre em contato para que seja eliminado (art. 14 da LGPD).</p>

        <h2 id="alteracoes">10. Alterações</h2>
        <p>Esta política pode ser atualizada para refletir mudanças no site ou na legislação. A data da última atualização fica sempre no topo desta página.</p>
<?php include __DIR__ . '/partials/page-end.php'; ?>
