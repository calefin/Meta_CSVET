<?php
// Configurações do Banco de Dados na HostGator
define('DB_HOST', 'localhost');
define('DB_NAME', 'medvet_sistema');
define('DB_USER', 'usuario_criado_no_cpanel');
define('DB_PASS', 'sua_senha_segura');

// Configurações Gerais
define('SITE_NAME', 'MedVet Legal');
define('DIRETOR', 'Atilio Sersun Calefi'); // Seu nome conforme currículo[cite: 1]
define('EMPRESA', 'CSVET Editora, Cursos e Treinamentos'); //[cite: 1]

// Iniciar sessão para login de alunos
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>