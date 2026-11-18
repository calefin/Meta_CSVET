<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Student;
use App\Models\DocumentSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentDownloadFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_download_by_path_matches_json_encoded_path_in_db(): void
    {
        // 1. Create a student user and student record
        $user = User::factory()->create([
            'role' => 'student',
            'name' => 'João Student',
        ]);

        $student = Student::create([
            'user_id' => $user->id,
            'institution' => 'Universidade Vet',
            'academic_level' => 'Graduação',
            'registration' => '12345',
            'research_topic' => 'Não informado',
        ]);

        $this->actingAs($user);

        // 2. Create a submission with a path containing special characters (like slashes and accentuation)
        $filePath = "submissions/PROJ-IC-2026-0001 - João Student - Iniciação Científica.pdf";
        
        $submission = DocumentSubmission::create([
            'student_id' => $student->id,
            'title' => 'Trabalho de IC',
            'document_type' => 'Iniciação Científica',
            'status' => 'Aguardando Triagem',
        ]);

        // Manually force the JSON-encoded value in the database representing an array of files
        $submission->setGoogleDriveFileIdAttribute([$filePath]);
        $submission->save();

        // Put the dummy file on public disk
        Storage::fake('public');
        Storage::fake('local');
        Storage::fake('google');

        // We place the file in the public disk instead of the google disk
        Storage::disk('public')->put($filePath, 'Dummy PDF content');

        // 3. Make a request to the download-path route using the raw path
        // The controller should query the DB, locate the submission even though it is JSON array encoded with slashes/unicode,
        // fallback from google disk to public disk, and return the file!
        $response = $this->get(route('documento.download-path', ['path' => $filePath]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Disposition');
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertEquals('Dummy PDF content', $response->streamedContent());
    }

    public function test_download_file_by_id_and_type_with_disk_fallback(): void
    {
        // 1. Create a student user and student record
        $user = User::factory()->create([
            'role' => 'student',
            'name' => 'João Student',
        ]);

        $student = Student::create([
            'user_id' => $user->id,
            'institution' => 'Universidade Vet',
            'academic_level' => 'Graduação',
            'registration' => '12345',
            'research_topic' => 'Não informado',
        ]);

        $this->actingAs($user);

        $filePath = "submissions/TCC-2026-0001 - João Student - TCC.pdf";

        $submission = DocumentSubmission::create([
            'student_id' => $student->id,
            'title' => 'Trabalho de TCC',
            'document_type' => 'TCC',
            'status' => 'Aguardando Triagem',
        ]);

        $submission->setGoogleDriveFileIdAttribute([$filePath]);
        $submission->save();

        // Setup fakes
        Storage::fake('public');
        Storage::fake('local');
        Storage::fake('google');

        // Place file in local storage instead of google drive
        Storage::disk('local')->put($filePath, 'Dummy TCC PDF content');

        // 2. Request download by ID and type
        $response = $this->get(route('documento.download', ['id' => $submission->id, 'type' => 'original']));

        $response->assertStatus(200);
        $response->assertHeader('Content-Disposition');
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertEquals('Dummy TCC PDF content', $response->streamedContent());
    }
}
