<?php

namespace Tests\Unit;

use App\Models\DocumentSubmission;
use PHPUnit\Framework\TestCase;

class ReturnedFileSetterTest extends TestCase
{
    private function submissionWithReturned(?string $current): DocumentSubmission
    {
        $submission = new DocumentSubmission();
        $submission->setRawAttributes(['google_drive_returned_file_id' => $current]);

        return $submission;
    }

    public function test_new_upload_wins_over_prefilled_old_file(): void
    {
        $submission = $this->submissionWithReturned('returns/antigo.pdf');
        $submission->google_drive_returned_file_id = ['a' => 'returns/antigo.pdf', 'b' => 'returns/novo-assinado.pdf'];

        $this->assertSame('returns/novo-assinado.pdf', $submission->getAttributes()['google_drive_returned_file_id']);
    }

    public function test_unchanged_prefilled_file_is_kept(): void
    {
        $submission = $this->submissionWithReturned('returns/antigo.pdf');
        $submission->google_drive_returned_file_id = ['a' => 'returns/antigo.pdf'];

        $this->assertSame('returns/antigo.pdf', $submission->getAttributes()['google_drive_returned_file_id']);
    }

    public function test_empty_array_preserves_existing_value(): void
    {
        $submission = $this->submissionWithReturned('returns/antigo.pdf');
        $submission->google_drive_returned_file_id = [];

        $this->assertSame('returns/antigo.pdf', $submission->getAttributes()['google_drive_returned_file_id']);
    }

    public function test_first_upload_is_stored(): void
    {
        $submission = $this->submissionWithReturned(null);
        $submission->google_drive_returned_file_id = ['x' => 'returns/primeiro.pdf'];

        $this->assertSame('returns/primeiro.pdf', $submission->getAttributes()['google_drive_returned_file_id']);
    }
}
