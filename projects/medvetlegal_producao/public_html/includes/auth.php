<?php
// Proteção de página - Middleware Simples
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function verificarAcesso() {
    if (!isset($_SESSION['usuario_id'])) {
        // Redireciona para o login se não houver sessão
        header("Location: ../plataforma/login.php");
        exit();
    }
}

// Função para saudar o usuário pelo nome (Atilio, Adriana, etc.)
function saudarUsuario() {
    return $_SESSION['usuario_nome'] ?? 'Usuário';
}
?>