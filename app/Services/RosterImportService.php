<?php

namespace App\Services;

use App\Models\ExternalClassGroup;
use App\Models\ExternalClassParticipant;
use PhpOffice\PhpSpreadsheet\IOFactory;

class RosterImportService
{
    /**
     * Importa nome/rgm/email de uma planilha (xlsx) ou arquivo delimitado
     * (csv/txt, vírgula ou ponto e vírgula) para dentro de uma turma,
     * fazendo upsert por RGM (escopado à turma).
     *
     * @return array{imported: int, updated: int, skipped: array<int, string>}
     */
    public function import(ExternalClassGroup $group, string $absolutePath, string $extension): array
    {
        $extension = strtolower($extension);

        $rows = $extension === 'xlsx'
            ? $this->readXlsx($absolutePath)
            : $this->readDelimited($absolutePath);

        $imported = 0;
        $updated = 0;
        $skipped = [];

        foreach ($rows as $lineNumber => $row) {
            [$name, $rgm, $email] = $row;

            $name = trim((string) $name);
            $rgm = trim((string) $rgm);
            $email = trim((string) $email);

            // Pula linha de cabeçalho (nome/rgm/email como texto literal)
            if ($lineNumber === 0 && $this->looksLikeHeader($name, $rgm, $email)) {
                continue;
            }

            if ($name === '' || $rgm === '') {
                if ($name !== '' || $rgm !== '' || $email !== '') {
                    $skipped[] = "Linha " . ($lineNumber + 1) . ": nome ou RGM em branco.";
                }
                continue;
            }

            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skipped[] = "Linha " . ($lineNumber + 1) . ": e-mail inválido ({$email}).";
                continue;
            }

            $existing = ExternalClassParticipant::where('external_class_group_id', $group->id)
                ->whereRaw('LOWER(rgm) = ?', [mb_strtolower($rgm)])
                ->first();

            ExternalClassParticipant::updateOrCreate(
                ['external_class_group_id' => $group->id, 'rgm' => $rgm],
                ['name' => $name, 'email' => $email]
            );

            $existing ? $updated++ : $imported++;
        }

        return ['imported' => $imported, 'updated' => $updated, 'skipped' => $skipped];
    }

    protected function looksLikeHeader(string $name, string $rgm, string $email): bool
    {
        $joined = mb_strtolower($name . '|' . $rgm . '|' . $email);

        return str_contains($joined, 'nome') || str_contains($joined, 'rgm') || str_contains($joined, 'email') || str_contains($joined, 'e-mail');
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: string}>
     */
    protected function readXlsx(string $absolutePath): array
    {
        $spreadsheet = IOFactory::load($absolutePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = [];

        foreach ($sheet->toArray(null, true, true, false) as $index => $cells) {
            $rows[$index] = [
                $cells[0] ?? '',
                $cells[1] ?? '',
                $cells[2] ?? '',
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: string}>
     */
    protected function readDelimited(string $absolutePath): array
    {
        $content = file_get_contents($absolutePath) ?: '';
        $firstLine = strtok($content, "\r\n") ?: '';

        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        $rows = [];
        $handle = fopen($absolutePath, 'r');
        $index = 0;

        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rows[$index] = [
                $data[0] ?? '',
                $data[1] ?? '',
                $data[2] ?? '',
            ];
            $index++;
        }

        fclose($handle);

        return $rows;
    }
}
