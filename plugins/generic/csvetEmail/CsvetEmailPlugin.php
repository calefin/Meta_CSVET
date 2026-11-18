<?php

/**
 * @file plugins/generic/csvetEmail/CsvetEmailPlugin.php
 *
 * @class CsvetEmailPlugin
 *
 * @brief Envolve o HTML de todos os e-mails do OJS (convites, avaliação, senha, decisões)
 *  no layout da revista: cabeçalho navy com o logo, corpo branco e rodapé institucional
 *  da CSVET Editora. Também reescreve os blocos com classes CSS dos modelos padrão do OJS
 *  (botões verde/vermelho, caixas de papel numeradas), que os clientes de e-mail mostram
 *  sem estilo, no padrão sóbrio da revista. A faixa de credenciais do layout (trust.html)
 *  fica só nos e-mails de captação; em mensagens de serviço (senha, convite, decisão) ela
 *  soaria como propaganda. Põe em cópia oculta (bccEmails) os e-mails do fluxo editorial,
 *  nunca os da conta pessoal (senha, cadastro).
 *
 *  É um plugin genérico, e não parte do tema, porque o tema só carrega quando uma página
 *  é desenhada; os e-mails também saem pela fila (cron) e pela API, onde só os plugins
 *  genéricos estão ativos. Não é lazy-load: fica sempre ligado depois de registrado
 *  (scripts/configure-journal.php faz o registro).
 *
 *  O layout fica em layout.html e os textos fixos em strings.json, compartilhados com
 *  content/nvas/emails/build.py (e-mails enviados fora do OJS, pelo Gmail).
 */

namespace APP\plugins\generic\csvetEmail;

use APP\core\Application;
use APP\facades\Repo;
use APP\file\PublicFileManager;
use PKP\config\Config;
use PKP\mail\Mailable;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Email;

class CsvetEmailPlugin extends GenericPlugin
{
    public function register($category, $path, $mainContextId = null)
    {
        if (!parent::register($category, $path, $mainContextId)) {
            return false;
        }
        if (!Application::isUnderMaintenance()) {
            Hook::add('Email::send::before', $this->helo(...));
            Hook::add('Email::send::before', $this->wrap(...));
            Hook::add('Mailable::build', $this->addBcc(...));
        }
        return true;
    }

    public function getDisplayName()
    {
        return 'CSVET: layout dos e-mails';
    }

    public function getDescription()
    {
        return 'Aplica o layout da revista (logo, credenciais e rodapé da CSVET Editora) a todos os e-mails enviados pelo OJS.';
    }

    /**
     * Apresenta a conexão SMTP com o nome real do servidor. Sem isso o Symfony diz
     * "EHLO [127.0.0.1]" e o filtro antispam da HostGator (router fightspamHG) descarta
     * em silêncio todos os e-mails do OJS, até os de texto puro.
     */
    public function helo(string $hookName, array $args): bool
    {
        try {
            $transport = $args['mailer']->getSymfonyTransport();
            $host = self::serverHostname();
            if ($host && $transport instanceof EsmtpTransport && $transport->getLocalDomain() !== $host) {
                $transport->setLocalDomain($host);
            }
        } catch (\Throwable $e) {
            error_log('csvetEmail helo: ' . $e->getMessage());
        }
        return Hook::CONTINUE;
    }

    /** Nome completo do servidor (sh00122.hostgator.com.br), ou null se não for um nome com domínio */
    public static function serverHostname(): ?string
    {
        $host = (string) gethostname();
        return str_contains($host, '.') ? $host : null;
    }

    public function wrap(string $hookName, array $args): bool
    {
        /** @var Email $message */
        $message = $args['message'];
        $html = $message->getHtmlBody();
        if (!is_string($html) || trim($html) === '' || str_contains($html, 'data-csvet-layout') || preg_match('/<html[\s>]/i', $html)) {
            return Hook::CONTINUE;
        }

        try {
            // Estrangeiros recebem só a parte em inglês dos modelos bilíngues
            if ($this->allInternational($message)) {
                $html = $this->englishOnly($html);
                $message->subject($this->englishSubject((string) $message->getSubject()));
            }
        } catch (\Throwable $e) {
            error_log('csvetEmail english: ' . $e->getMessage());
        }

        try {
            $message->html($this->render($html));
        } catch (\Throwable $e) {
            // Sem layout é melhor que sem e-mail
            error_log('csvetEmail: ' . $e->getMessage());
        }
        return Hook::CONTINUE;
    }

    /** Rótulos bilíngues do rodapé dos modelos da revista e a assinatura do editor */
    private const EN_LABELS = [
        'Atenciosamente / Kind regards' => 'Kind regards',
        'Equipe editorial / Editorial office' => 'Editorial office',
        'Manuscrito / Manuscript' => 'Manuscript',
        'Resumo / Abstract' => 'Abstract',
        'Decisão / Decision' => 'Decision',
        'Kind regards / Atenciosamente' => 'Kind regards',
        'Editorial office / Equipe editorial' => 'Editorial office',
        'Editor-chefe' => 'Editor-in-Chief',
        'Coeditora-chefe' => 'Co-Editor-in-Chief',
        'Coeditor-chefe' => 'Co-Editor-in-Chief',
    ];

    /**
     * Verdadeiro quando todos os destinatários (Para) são usuários de fora do Brasil: país do
     * cadastro diferente de BR ou, sem país, idioma preferido só em inglês. Quem não tem
     * cadastro ou é do Brasil recebe o e-mail como foi escrito.
     */
    private function allInternational(Email $message): bool
    {
        $to = $message->getTo();
        if (!$to) {
            return false;
        }
        foreach ($to as $address) {
            $user = Repo::user()->getByEmail($address->getAddress(), true);
            if (!$user) {
                return false;
            }
            $country = strtoupper(trim((string) $user->getCountry()));
            $locales = array_filter((array) $user->getLocales());
            $international = $country !== ''
                ? $country !== 'BR'
                : ($locales && !in_array('pt_BR', $locales, true));
            if (!$international) {
                return false;
            }
        }
        return true;
    }

    /**
     * Modelos bilíngues da revista (content/nvas/ojs-emails): tira o aviso de idioma, o bloco
     * em português (o que começa com Prezado/Prezada/Olá entre as divisórias) e os rótulos
     * duplos do rodapé. E-mails só em português (decisões redigidas pelo editor) ficam como
     * estão: para estrangeiros, o editor troca o idioma no próprio compositor.
     */
    private function englishOnly(string $html): string
    {
        if (!str_contains($html, 'csvet-langnote')) {
            return strtr($html, self::EN_LABELS);
        }
        $html = preg_replace("#<p class=['\"]csvet-langnote['\"]>.*?</p>\s*#s", '', $html, 1);
        $parts = preg_split('#<hr\s*/?>#i', $html);
        $keep = [];
        foreach ($parts as $part) {
            $text = ltrim(html_entity_decode(strip_tags($part), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if (preg_match('/^(?:Prezad[oa]|Olá)\b/u', $text)) {
                continue;
            }
            $keep[] = $part;
        }
        return strtr(implode('<hr>', $keep), self::EN_LABELS);
    }

    /** Assunto "[NVAS] Português · English" (ou ao contrário) → "[NVAS] English" */
    private function englishSubject(string $subject): string
    {
        if (!str_contains($subject, ' · ')) {
            return $subject;
        }
        $prefix = '';
        if (preg_match('/^(\[[^\]]+\]\s*)(.*)$/su', $subject, $m)) {
            [$prefix, $subject] = [$m[1], $m[2]];
        }
        $pt = '/[ãõçáéíóúâêô]|\b(?:sua|seu|para|nova|convite|lembrete|aguardamos|ainda|obrigad[oa]|prazos|reconsideraria|decisão|submissão)\b/iu';
        foreach (explode(' · ', $subject) as $piece) {
            if (!preg_match($pt, $piece)) {
                return $prefix . trim($piece);
            }
        }
        return $prefix . $subject;
    }

    /**
     * E-mails da conta de cada pessoa, que nunca vão em cópia: senha, cadastro (alguns
     * trazem a senha provisória), confirmação de e-mail, troca de e-mail e ORCID.
     */
    private const PERSONAL_MAILABLES = [
        \PKP\mail\mailables\PasswordResetRequested::class,
        \PKP\mail\mailables\UserCreated::class,
        \PKP\mail\mailables\ReviewerRegister::class,
        \PKP\mail\mailables\ValidateEmailContext::class,
        \PKP\mail\mailables\ValidateEmailSite::class,
        \PKP\mail\mailables\ChangeProfileEmailInvitationNotify::class,
        \PKP\mail\mailables\OrcidCollectAuthorId::class,
        \PKP\mail\mailables\OrcidRequestAuthorAuthorization::class,
        \PKP\mail\mailables\OrcidRequestUpdateScope::class,
    ];

    /**
     * Cópia oculta dos e-mails do fluxo editorial (envio, convites, avaliação, decisões)
     * para os endereços do ajuste bccEmails, para o editor acompanhar o fluxo. Ficam de
     * fora os e-mails da conta pessoal (PERSONAL_MAILABLES) e quem já é destinatário.
     * Roda ao montar o e-mail (Mailable::build, que passa o argumento nomeado $mailable),
     * antes de os destinatários irem para a mensagem.
     */
    public function addBcc(string $hookName, Mailable $mailable): bool
    {
        try {
            foreach (self::PERSONAL_MAILABLES as $class) {
                if ($mailable instanceof $class) {
                    return Hook::CONTINUE;
                }
            }
            $context = $this->getContext();
            if (!$context) {
                return Hook::CONTINUE;
            }
            foreach ((array) $this->getSetting($context->getId(), 'bccEmails') as $email) {
                $email = strtolower(trim((string) $email));
                if (filter_var($email, FILTER_VALIDATE_EMAIL)
                    && !$mailable->hasTo($email) && !$mailable->hasCc($email) && !$mailable->hasBcc($email)) {
                    $mailable->bcc($email);
                }
            }
        } catch (\Throwable $e) {
            // Sem cópia é melhor que sem e-mail
            error_log('csvetEmail bcc: ' . $e->getMessage());
        }
        return Hook::CONTINUE;
    }

    private function render(string $content): string
    {
        $context = $this->getContext();
        $baseUrl = rtrim((string) Config::getVar('general', 'base_url'), '/');
        $journalName = $context?->getLocalizedName() ?? 'Neotropical Veterinary and Animal Science';
        $journalUrl = $context ? "{$baseUrl}/{$context->getPath()}" : $baseUrl;

        // O e-mail não diz o idioma; o texto do próprio corpo diz. Nos bilíngues (avaliadores),
        // vale a saudação que vem primeiro
        $text = strip_tags($content);
        $pt = preg_match('/\b(?:Prezad[oa]|Olá|Car[oa]\b)/u', $text, $m, PREG_OFFSET_CAPTURE) ? $m[0][1] : PHP_INT_MAX;
        $en = preg_match('/\b(?:Dear|Hello)\b/', $text, $m, PREG_OFFSET_CAPTURE) ? $m[0][1] : PHP_INT_MAX;
        if ($pt !== $en) {
            $lang = $pt < $en ? 'pt_BR' : 'en';
        } else {
            $lang = preg_match('/ção|ções|você|Prezad|Olá|Atenciosamente|senha|avaliação|submissão/iu', $content) ? 'pt_BR' : 'en';
        }

        $strings = json_decode(file_get_contents(__DIR__ . '/strings.json'), true, 512, JSON_THROW_ON_ERROR);
        $vars = array_map('htmlspecialchars', $strings[$lang]) + [
            'lang' => $lang === 'pt_BR' ? 'pt-BR' : 'en',
            'journalName' => htmlspecialchars($journalName),
            'journalUrl' => htmlspecialchars($journalUrl),
            'journalHost' => htmlspecialchars(preg_replace('#^https?://#', '', $journalUrl)),
            'logo' => $this->logoTag($context, $baseUrl, $journalName),
            'trustBand' => '',
        ];

        $layout = file_get_contents(__DIR__ . '/layout.html');
        $replacements = ['{{content}}' => $this->tidy($content, $lang)];
        foreach ($vars as $key => $value) {
            $replacements['{{' . $key . '}}'] = $value;
        }
        return strtr($layout, $replacements);
    }

    /**
     * Reescreve com estilo em linha os blocos dos modelos padrão do OJS 3.5 que só têm
     * classes CSS (email-header, section, btn-wrap). O que não casar fica como veio.
     */
    private function tidy(string $html, string $lang): string
    {
        $serif = "font-family:Georgia,'Times New Roman',serif;";
        $q = '[\'"]';

        // Título do modelo (<div class='email-header'><h2>Convite para nova função</h2></div>)
        $html = preg_replace(
            "#<div class={$q}email-header{$q}>\s*<h2>(.*?)</h2>\s*</div>#s",
            '<h1 style="' . $serif . 'font-size:22px;font-weight:normal;line-height:1.3;color:#000067;margin:0 0 24px;">$1</h1>',
            $html
        );

        // Rótulo antes da lista de papéis (<p><h2>Novas funções atribuídas</h2></p>)
        $html = preg_replace(
            '#<p>\s*<h2>(.*?)</h2>\s*</p>#s',
            '<p style="margin:24px 0 8px;font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:#4a5168;">$1</p>',
            $html
        );

        // Papel (número, nome, início, fim, expediente) → caixa com filete navy
        $html = preg_replace_callback(
            "#<div class={$q}section{$q}>\s*<div class={$q}section-number{$q}>.*?</div>\s*<div class={$q}section-content{$q}>(.*?)</div>\s*</div>#s",
            function ($m) use ($serif, $lang) {
                preg_match('#<h2>(.*?)</h2>#s', $m[1], $name);
                preg_match_all('#<p>(.*?)</p>#s', $m[1], $lines);
                $lines = array_filter(array_map('trim', $lines[1]), fn ($l) => strip_tags($l) !== '');
                $lines = array_map(fn ($l) => $this->formatDates($l, $lang), $lines);
                return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 20px;">'
                    . '<tr><td style="background:#f4f6fb;border-left:3px solid #000067;padding:14px 18px;">'
                    . '<div style="' . $serif . 'font-size:18px;line-height:1.3;color:#000067;margin:0 0 6px;">' . ($name[1] ?? '') . '</div>'
                    . '<div style="font-size:13px;line-height:1.55;color:#3b4358;">' . implode('<br>', $lines) . '</div>'
                    . '</td></tr></table>';
            },
            $html
        );

        // Botões (aceitar/recusar) → um botão navy para a ação principal, as demais como link discreto
        $html = preg_replace_callback(
            "#<table class={$q}btn-wrap{$q}.*?</table>#s",
            function ($m) use ($q) {
                preg_match_all("#<a [^>]*href={$q}([^'\"]*){$q}[^>]*>(.*?)</a>#s", $m[0], $links, PREG_SET_ORDER);
                if (!$links) {
                    return $m[0];
                }
                [, $url, $text] = array_shift($links);
                $out = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 16px;">'
                    . '<tr><td bgcolor="#000080" style="background:#000080;border-radius:6px;">'
                    . '<a href="' . $url . '" style="display:inline-block;padding:12px 28px;font-weight:bold;color:#ffffff;text-decoration:none;">' . $text . '</a>'
                    . '</td></tr></table>';
                foreach ($links as [, $url, $text]) {
                    $out .= '<p style="margin:0 0 24px;font-size:13px;"><a href="' . $url . '" style="color:#4a5168;">' . $text . '</a></p>';
                }
                return $out;
            },
            $html
        );

        // Modelos da revista (content/nvas/ojs-emails/build_reviewer_emails.py): botão da ação
        // principal, aviso "versão em ... abaixo" e divisória entre os idiomas
        $html = preg_replace(
            "#<p class={$q}csvet-cta{$q}>\s*<a [^>]*href={$q}([^'\"]*){$q}[^>]*>(.*?)</a>\s*</p>#s",
            '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 16px;">'
                . '<tr><td bgcolor="#000080" style="background:#000080;border-radius:6px;">'
                . '<a href="$1" style="display:inline-block;padding:12px 28px;font-weight:bold;color:#ffffff;text-decoration:none;">$2</a>'
                . '</td></tr></table>',
            $html
        );
        $html = preg_replace(
            "#<p class={$q}csvet-langnote{$q}>(.*?)</p>#s",
            '<p style="margin:0 0 16px;font-size:13px;color:#4a5168;"><em>$1</em></p>',
            $html
        );
        $html = preg_replace(
            '#<hr\s*/?>#',
            '<hr style="border:0;border-top:1px solid #dde1ee;margin:28px 0;">',
            $html
        );

        return $html;
    }

    /** Datas ISO dos blocos de papel (2026-10-13) no formato do idioma do e-mail. */
    private function formatDates(string $text, string $lang): string
    {
        return preg_replace_callback('/\b(\d{4})-(\d{2})-(\d{2})\b/', function ($m) use ($lang) {
            if ($lang === 'pt_BR') {
                return "{$m[3]}/{$m[2]}/{$m[1]}";
            }
            $months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
            return (int) $m[3] . ' ' . $months[(int) $m[2] - 1] . ' ' . $m[1];
        }, $text);
    }

    /**
     * Logo branco da revista (o mesmo do cabeçalho do site). Se o leitor bloquear imagens,
     * o texto alternativo aparece em serifa branca, então o cabeçalho continua legível.
     */
    private function logoTag($context, string $baseUrl, string $journalName): string
    {
        $alt = htmlspecialchars($journalName);
        $altStyle = "font-family:Georgia,'Times New Roman',serif;font-size:22px;line-height:1.3;color:#ffffff;";
        $logo = $context?->getData('pageHeaderLogoImage');
        $image = is_array($logo) ? ($logo[$context->getPrimaryLocale()] ?? reset($logo)) : null;
        if (!$image || empty($image['uploadName'])) {
            return "<span style=\"{$altStyle}\">{$alt}</span>";
        }
        $src = $baseUrl . '/' . (new PublicFileManager())->getContextFilesPath($context->getId()) . '/' . rawurlencode($image['uploadName']);
        $height = !empty($image['width']) && !empty($image['height']) ? (int) round(300 * $image['height'] / $image['width']) : 65;
        return '<img class="csvet-logo" src="' . htmlspecialchars($src) . '" width="300" height="' . $height . '" alt="' . $alt . '"'
            . ' style="display:block;border:0;outline:none;width:300px;max-width:100%;height:auto;' . $altStyle . '">';
    }

    private function getContext()
    {
        // Fora de uma página (cron, fila no terminal) não há router e getContext() quebraria
        $request = Application::get()->getRequest();
        $context = $request->getRouter() ? $request->getContext() : null;
        if ($context) {
            return $context;
        }
        // Fila/cron: sem requisição, usa a primeira revista (o portal tem uma só)
        return Application::getContextDAO()->getAll(true)->next() ?: null;
    }
}
