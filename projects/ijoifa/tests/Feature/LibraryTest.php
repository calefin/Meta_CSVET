<?php

namespace Tests\Feature;

use App\Livewire\Admin\Library\AccessManager;
use App\Livewire\Admin\Library\CategoryManager;
use App\Livewire\Admin\Library\DocumentManager;
use App\Livewire\Library\Browser;
use App\Models\LibraryCategory;
use App\Models\LibraryDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class LibraryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'admin', 'status' => 'active']);
    }

    private function iniciado(): User
    {
        return User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'iniciado', 'status' => 'active']);
    }

    public function test_admin_can_create_category_and_upload_document(): void
    {
        Storage::fake('local');
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(CategoryManager::class)
            ->set('name', 'Rezas diárias')
            ->call('save');

        $category = LibraryCategory::where('name', 'Rezas diárias')->first();
        $this->assertNotNull($category);
        $this->assertSame('rezas-diarias', $category->slug);

        Livewire::actingAs($admin)
            ->test(DocumentManager::class, ['category' => $category])
            ->set('title', 'Ìwúre Òwúró')
            ->set('language', 'pt-BR')
            ->set('content', '<p>Reza da manhã</p>')
            ->set('audio', UploadedFile::fake()->create('audio.mp3', 100, 'audio/mpeg'))
            ->call('save');

        $this->assertDatabaseHas('library_documents', [
            'category_id' => $category->id,
            'title' => 'Ìwúre Òwúró',
            'content' => '<p>Reza da manhã</p>',
        ]);
        
        $document = LibraryDocument::where('title', 'Ìwúre Òwúró')->first();
        $this->assertNotNull($document->audio_path);
        Storage::disk('local')->assertExists($document->audio_path);
    }

    public function test_category_access_is_granted_and_revoked_per_user(): void
    {
        $admin = $this->admin();
        $iniciado = $this->iniciado();
        $category = LibraryCategory::create(['name' => 'Incantations', 'slug' => 'incantations', 'order' => 0]);

        Livewire::actingAs($admin)
            ->test(AccessManager::class, ['user' => $iniciado])
            ->call('grant', $category->id);

        $this->assertDatabaseHas('library_category_access', [
            'user_id' => $iniciado->id,
            'category_id' => $category->id,
        ]);

        Livewire::actingAs($admin)
            ->test(AccessManager::class, ['user' => $iniciado])
            ->call('revoke', $category->id);

        $this->assertDatabaseMissing('library_category_access', [
            'user_id' => $iniciado->id,
            'category_id' => $category->id,
        ]);
    }

    public function test_user_only_sees_categories_granted_to_them(): void
    {
        $iniciado = $this->iniciado();
        $admin = $this->admin();
        $granted = LibraryCategory::create(['name' => 'Liberada', 'slug' => 'liberada', 'order' => 0]);
        $notGranted = LibraryCategory::create(['name' => 'Bloqueada', 'slug' => 'bloqueada', 'order' => 1]);

        $iniciado->libraryCategories()->attach($granted->id, ['granted_by' => $admin->id, 'granted_at' => now()]);

        Livewire::actingAs($iniciado)
            ->test(Browser::class)
            ->assertSee('Liberada')
            ->assertDontSee('Bloqueada');
    }

    public function test_user_cannot_open_viewer_for_ungranted_category(): void
    {
        $iniciado = $this->iniciado();
        $admin = $this->admin();
        $category = LibraryCategory::create(['name' => 'Bloqueada', 'slug' => 'bloqueada', 'order' => 0]);
        $document = LibraryDocument::create([
            'category_id' => $category->id,
            'title' => 'Segredo',
            'language' => 'pt-BR',
            'file_path' => 'library/segredo.pdf',
            'order' => 0,
            'uploaded_by' => $admin->id,
        ]);

        $this->actingAs($iniciado)
            ->get(route('library.show', $document))
            ->assertForbidden();
    }

    public function test_user_can_open_viewer_for_granted_category(): void
    {
        $iniciado = $this->iniciado();
        $admin = $this->admin();
        $category = LibraryCategory::create(['name' => 'Liberada', 'slug' => 'liberada', 'order' => 0]);
        $document = LibraryDocument::create([
            'category_id' => $category->id,
            'title' => 'Aberto',
            'language' => 'pt-BR',
            'file_path' => 'library/aberto.pdf',
            'order' => 0,
            'uploaded_by' => $admin->id,
        ]);

        $iniciado->libraryCategories()->attach($category->id, ['granted_by' => $admin->id, 'granted_at' => now()]);

        $this->actingAs($iniciado)
            ->get(route('library.show', $document))
            ->assertOk()
            ->assertSee('Aberto');
    }

    public function test_stream_route_rejects_user_without_access_to_documents_category(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('library/secreto.pdf', 'pdf-bytes');

        $iniciado = $this->iniciado();
        $admin = $this->admin();
        $category = LibraryCategory::create(['name' => 'Bloqueada', 'slug' => 'bloqueada', 'order' => 0]);
        $document = LibraryDocument::create([
            'category_id' => $category->id,
            'title' => 'Secreto',
            'language' => 'pt-BR',
            'file_path' => 'library/secreto.pdf',
            'order' => 0,
            'uploaded_by' => $admin->id,
        ]);

        $this->actingAs($iniciado)
            ->get(route('library.stream', $document))
            ->assertForbidden();
    }

    public function test_stream_route_serves_file_for_user_with_access(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('library/aberto.pdf', 'pdf-bytes');

        $iniciado = $this->iniciado();
        $admin = $this->admin();
        $category = LibraryCategory::create(['name' => 'Liberada', 'slug' => 'liberada', 'order' => 0]);
        $document = LibraryDocument::create([
            'category_id' => $category->id,
            'title' => 'Aberto',
            'language' => 'pt-BR',
            'file_path' => 'library/aberto.pdf',
            'order' => 0,
            'uploaded_by' => $admin->id,
        ]);

        $iniciado->libraryCategories()->attach($category->id, ['granted_by' => $admin->id, 'granted_at' => now()]);

        $this->actingAs($iniciado)
            ->get(route('library.stream', $document))
            ->assertOk();
    }

    public function test_consulente_role_cannot_reach_admin_library_routes(): void
    {
        $consulente = User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'consulente', 'status' => 'active']);

        $this->actingAs($consulente)
            ->get(route('admin.library.categories'))
            ->assertForbidden();
    }

    public function test_audio_stream_route_serves_audio_for_user_with_access(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('library-audios/test.mp3', 'mp3-bytes');

        $iniciado = $this->iniciado();
        $admin = $this->admin();
        $category = LibraryCategory::create(['name' => 'Liberada', 'slug' => 'liberada', 'order' => 0]);
        $document = LibraryDocument::create([
            'category_id' => $category->id,
            'title' => 'Aberto',
            'language' => 'pt-BR',
            'content' => 'Conteudo',
            'audio_path' => 'library-audios/test.mp3',
            'order' => 0,
            'uploaded_by' => $admin->id,
        ]);

        $iniciado->libraryCategories()->attach($category->id, ['granted_by' => $admin->id, 'granted_at' => now()]);

        $this->actingAs($iniciado)
            ->get(route('library.audio', $document))
            ->assertOk();
    }
}
