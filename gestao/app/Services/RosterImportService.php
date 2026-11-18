<?php

namespace App\Services;

use App\Models\ExternalClassGroup;
use App\Models\ExternalClassParticipant;
use PhpOffice\PhpSpreadsheet\IOFactory;

class RosterImportService
{
    /**
     * Lê e valida a planilha SEM gravar nada no banco — usado pra mostrar
     * uma pré-visualização (linha a linha: novo/atualiza/erro) antes do
     * professor confirmar o import de fato.
     *
     * @return array{rows: array<int, array{line: int, name: string, rgm: string, email: string, status: string, reason: ?string}>, importCount: int, updateCount: int, errorCount: int}
     */
    public function analyze(ExternalClassGroup $group, string $absolutePath, string $extension): array
    {
        $extension = strtolower($extension);

        $rawRows = $extension === 'xlsx'
            ? $this->readXlsx($absolutePath)
            : $this->readDelimited($absolutePath);

        $rows = [];
        $importCount = 0;
        $updateCount = 0;
        $errorCount = 0;
        $seenRgms = [];

        foreach ($rawRows as $lineNumber => $row) {
            [$name, $rgm, $email] = $row;

            $name = trim((string) $name);
            $rgm = trim((string) $rgm);
            $email = trim((string) $email);

            // Pula linha de cabeçalho (nome/rgm/email como texto literal)
            if ($lineNumber === 0 && $this->looksLikeHeader($name, $rgm, $email)) {
                continue;
            }

            // Pula linha totalmente vazia (comum no fim de planilhas exportadas)
            if ($name === '' && $rgm === '' && $email === '') {
                continue;
            }

            $line = $lineNumber + 1;

            if ($name === '' || $rgm === '') {
                $rows[] = ['line' => $line, 'name' => $name, 'rgm' => $rgm, 'email' => $email, 'status' => 'error', 'reason' => 'Nome ou RGM em branco.'];
                $errorCount++;
                continue;
            }

            if ($email === '') {
                $rows[] = ['line' => $line, 'name' => $name, 'rgm' => $rgm, 'email' => $email, 'status' => 'error', 'reason' => 'E-mail em branco — sem ele o aluno não consegue acessar.'];
                $errorCount++;
                continue;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $rows[] = ['line' => $line, 'name' => $name, 'rgm' => $rgm, 'email' => $email, 'status' => 'error', 'reason' => "E-mail inválido ({$email})."];
                $errorCount++;
                continue;
            }

            $rgmKey = mb_strtolower($rgm);
            if (isset($seenRgms[$rgmKey])) {
                $rows[] = ['line' => $line, 'name' => $name, 'rgm' => $rgm, 'email' => $email, 'status' => 'error', 'reason' => "RGM duplicado na própria planilha (já aparece na linha {$seenRgms[$rgmKey]})."];
                $errorCount++;
                continue;
            }
            $seenRgms[$rgmKey] = $line;

            $existing = ExternalClassParticipant::where('external_class_group_id', $group->id)
                ->whereRaw('LOWER(rgm) = ?', [$rgmKey])
                ->first();

            $status = $existing ? 'update' : 'new';
            $status === 'update' ? $updateCount++ : $importCount++;

            $rows[] = ['line' => $line, 'name' => $name, 'rgm' => $rgm, 'email' => $email, 'status' => $status, 'reason' => null];
        }

        return ['rows' => $rows, 'importCount' => $importCount, 'updateCount' => $updateCount, 'errorCount' => $errorCount];
    }

    /**
     * Grava no banco as linhas já validadas por analyze() — chamado depois
     * que o professor confirma a pré-visualização. Não relê o arquivo:
     * recebe a mesma lista de linhas já analisada para não haver
     * divergência entre o que foi mostrado e o que foi gravado.
     *
     * @param array<int, array{line: int, name: string, rgm: string, email: string, status: string, reason: ?string}> $rows
     * @return array{imported: int, updated: int}
     */
    public function commit(ExternalClassGroup $group, array $rows): array
    {
        $imported = 0;
        $updated = 0;

        foreach ($rows as $row) {
            if ($row['status'] === 'error') {
                continue;
            }

            ExternalClassParticipant::updateOrCreate(
                ['external_class_group_id' => $group->id, 'rgm' => $row['rgm']],
                ['name' => $row['name'], 'email' => $row['email']]
            );

            $row['status'] === 'update' ? $updated++ : $imported++;
        }

        return ['imported' => $imported, 'updated' => $updated];
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
