<?php
require_once __DIR__ . '/data.php';
$page = [
    'title' => 'Termos de Uso',
    'description' => 'Termos de uso do site institucional da CSVET Editora: propriedade intelectual, natureza informativa do conteúdo, links externos e legislação aplicável.',
    'path' => '/termos',
    'heading' => 'Termos de Uso',
    'lead' => 'Última atualização: ' . $legal['updated'] . '.',
];
$e = fn($v) => htmlspecialchars($v);
include __DIR__ . '/partials/page-start.php';
?>
        <h2 id="aceitacao">1. Aceitação</h2>
        <p>Estes termos regem o uso do site <strong>csvet.com.br</strong>, mantido pela <strong><?php echo $e($legal['controller']); ?></strong> (<?php echo $e($legal['responsible']); ?><?php if ($legal['cnpj']): ?>, CNPJ <?php echo $e($legal['cnpj']); ?><?php endif; ?>, <?php echo $e($legal['address']); ?>). Ao navegar pelo site, você concorda com eles. O tratamento de dados pessoais está descrito na <a href="/privacidade">Política de Privacidade</a>.</p>

        <h2 id="natureza">2. Natureza do site</h2>
        <p>Este é um site institucional e informativo. Ele apresenta a editora, seus periódicos científicos e as plataformas do ecossistema CSVET. Não há venda de produtos ou serviços neste domínio: preços, condições e contratação, quando existirem, estão nas respectivas plataformas e seguem os termos de cada uma, inclusive o Código de Defesa do Consumidor.</p>

        <h2 id="conteudo">3. Conteúdo técnico e científico</h2>
        <p>O conteúdo tem finalidade educacional e informativa. Ele <strong>não substitui</strong> o atendimento por médico-veterinário, a assistência técnica de um profissional habilitado nem orientação jurídica sobre um caso concreto.</p>
        <p>Os artigos publicados nos periódicos da editora passam pelo processo de avaliação descrito nas políticas editoriais de cada revista, em <a href="https://journals.csvet.com.br">journals.csvet.com.br</a>. As opiniões expressas nos artigos são de responsabilidade de seus autores.</p>

        <h2 id="propriedade">4. Propriedade intelectual</h2>
        <p>A marca CSVET, os logotipos, o layout e os textos deste site pertencem à <?php echo $e($legal['controller']); ?> e são protegidos pela Lei de Direitos Autorais (Lei 9.610/1998) e pela Lei de Propriedade Industrial (Lei 9.279/1996). Não é permitido reproduzi-los para fins comerciais sem autorização por escrito.</p>
        <p>Os artigos científicos publicados em acesso aberto seguem a licença indicada em cada publicação, que define como podem ser compartilhados e citados.</p>

        <h2 id="uso">5. Uso adequado</h2>
        <p>Ao usar o site, você se compromete a não:</p>
        <ul>
            <li>tentar obter acesso não autorizado ao site, ao servidor ou a dados de terceiros (Lei 12.737/2012);</li>
            <li>sobrecarregar o site com acessos automatizados em volume abusivo ou interferir no seu funcionamento;</li>
            <li>usar o nome ou a marca CSVET de forma que sugira vínculo, patrocínio ou endosso inexistente.</li>
        </ul>

        <h2 id="links">6. Links para outros sites</h2>
        <p>O site leva a plataformas do ecossistema CSVET e a serviços de terceiros, como YouTube e Instagram. Cada um tem termos e política de privacidade próprios. Os serviços de terceiros não são controlados pela editora, que não responde pelo conteúdo ou pelas práticas deles.</p>

        <h2 id="disponibilidade">7. Disponibilidade</h2>
        <p>Trabalhamos para manter o site no ar e com informações corretas, mas ele pode passar por manutenção, interrupções ou atualizações sem aviso prévio. O conteúdo pode ser alterado a qualquer momento.</p>

        <h2 id="lei">8. Legislação aplicável</h2>
        <p>Estes termos são regidos pelas leis da República Federativa do Brasil, em especial o Marco Civil da Internet (Lei 12.965/2014), a Lei Geral de Proteção de Dados (Lei 13.709/2018) e, quando houver relação de consumo, o Código de Defesa do Consumidor (Lei 8.078/1990).<?php if ($legal['forum']): ?> Fica eleito o foro da comarca de <?php echo $e($legal['forum']); ?>, ressalvado o direito do consumidor de propor ação no foro de seu domicílio.<?php endif; ?></p>

        <h2 id="contato">9. Contato</h2>
        <p>Dúvidas, pedidos de autorização de uso ou comunicações sobre estes termos: <a href="mailto:<?php echo $e($organization['email']); ?>"><?php echo $e($organization['email']); ?></a>.</p>
<?php include __DIR__ . '/partials/page-end.php'; ?>
