<?php
require('fpdf/fpdf.php');

// Simulando dados que virão do DB futuramente
$nomeAluno = "NOME DO ALUNO"; // Será substituído pelo Responsável Legal
$nomeCurso = "Curso de Patologia Experimental"; // Baseado na sua especialidade
$data = date('d/m/Y');

$pdf = new FPDF('L', 'mm', 'A4'); // 'L' para modo Paisagem
$pdf->AddPage();

// Inserir a imagem de fundo (seu template)
// O arquivo hero-bg.jpg já existe na sua pasta assets, mas aqui usaremos o template
$pdf->Image('../assets/template.jpg', 0, 0, 297, 210); 

// Configurar Fonte
$pdf->SetFont('Arial', 'B', 30);
$pdf->SetTextColor(0, 0, 0);

// Escrever o nome do Responsável Legal (Aluno)[cite: 1]
$pdf->Ln(80); // Pular linhas para centralizar no template
$pdf->Cell(0, 10, utf8_decode($nomeAluno), 0, 1, 'C');

$pdf->SetFont('Arial', '', 20);
$pdf->Ln(20);
$pdf->MultiCell(0, 10, utf8_decode("Concluiu com êxito o curso de $nomeCurso\n emitido por CSVET Editora."), 0, 'C');[cite: 1]

// Saída do arquivo
$pdf->Output('D', 'Certificado_MedVetLegal.pdf'); 
?>