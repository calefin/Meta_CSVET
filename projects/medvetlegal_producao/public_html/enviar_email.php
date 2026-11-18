<?php
// Configurações de destino
$para_principal = "patoforense.adriana@gmail.com";
$para_copia = "calefi@csvet.com.br";

// Verifica se o formulário foi submetido via POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // --- INÍCIO DA PROTEÇÃO ANTI-SPAM ---
    
    // 1. Verifica Honeypot (Campo oculto)
    $honeypot = $_POST['url_spam_check'] ?? '';
    
    // 2. Verifica Desafio Matemático
    $desafio = trim($_POST['desafio_humano'] ?? '');

    if (!empty($honeypot)) {
        // Se o robô preencheu o campo oculto, fingimos que enviou (Silent Drop)
        // Isso impede que o bot tente outra estratégia
        echo "<script>alert('Mensagem enviada com sucesso!'); window.location.href='index.php';</script>";
        exit;
    }

    if ($desafio != '7') {
        // Se errou a conta matemática
        echo "<script>alert('Erro na verificação de segurança. A resposta da soma está incorreta.'); window.history.back();</script>";
        exit;
    }
    // --- FIM DA PROTEÇÃO ---

    
    // Sanitização simples dos dados
    $nome = strip_tags(trim($_POST["nome"]));
    $email = filter_var(trim($_POST["email"]), FILTER_SANITIZE_EMAIL);
    $telefone = strip_tags(trim($_POST["telefone"]));
    $assunto_tipo = strip_tags(trim($_POST["assunto"]));
    $mensagem_usuario = strip_tags(trim($_POST["mensagem"]));

    // Validação básica dos campos obrigatórios
    if (empty($nome) || empty($mensagem_usuario) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "<script>alert('Por favor, preencha os campos corretamente.'); window.history.back();</script>";
        exit;
    }

    // Assunto do E-mail (interno)
    $subject = "Novo Contato MedVetLegal: $assunto_tipo - $nome";

    // Corpo do E-mail (HTML)
    $body = "
    <html>
    <head>
      <title>Novo Contato via Site</title>
    </head>
    <body style='font-family: Arial, sans-serif;'>
      <h2 style='color: #0e2a47;'>Novo contato recebido pelo site MedVetLegal</h2>
      <p><strong>Nome:</strong> $nome</p>
      <p><strong>E-mail:</strong> $email</p>
      <p><strong>Telefone:</strong> $telefone</p>
      <p><strong>Tipo de Assunto:</strong> $assunto_tipo</p>
      <hr>
      <p><strong>Mensagem:</strong><br> " . nl2br($mensagem_usuario) . "</p>
      <hr>
      <p><small>Enviado em: " . date("d/m/Y H:i") . " via IP: " . $_SERVER['REMOTE_ADDR'] . "</small></p>
    </body>
    </html>
    ";

    // Headers para envio HTML e Cópia
    // Dica: O ideal no 'From' é usar um e-mail do próprio domínio (ex: nao-responda@medvetlegal.com.br)
    // para evitar cair na caixa de spam do Gmail.
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: no-reply@medvetlegal.com.br" . "\r\n"; 
    $headers .= "Reply-To: $email" . "\r\n";
    $headers .= "Cc: $para_copia, contato@medvetlegal.com.br" . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    // Tenta enviar
    if (mail($para_principal, $subject, $body, $headers)) {
        echo "<script>alert('Mensagem enviada com sucesso! Entraremos em contato em breve.'); window.location.href='index.php';</script>";
    } else {
        echo "<script>alert('Houve um erro ao enviar a mensagem. Tente novamente mais tarde ou contate-nos pelo e-mail direto.'); window.history.back();</script>";
    }

} else {
    // Se tentar acessar o arquivo diretamente sem POST
    header("Location: index.php");
    exit;
}
?>