<?php

namespace Tests\Feature;

use App\Livewire\Admin\Courses\CourseBuilder;
use App\Livewire\Admin\Courses\CourseList;
use App\Livewire\Student\CourseShow;
use App\Livewire\Student\CoursesList;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CoursesTest extends TestCase
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

    public function test_admin_can_create_a_course_with_module_and_lesson(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(CourseList::class)
            ->call('create')
            ->set('title', 'Fundamentos de Ifa')
            ->set('target', 'iniciado')
            ->set('price', '0')
            ->call('save');

        $course = Course::first();
        $this->assertNotNull($course);

        Livewire::actingAs($admin)
            ->test(CourseBuilder::class, ['course' => $course])
            ->set('newModuleTitle', 'Modulo 1')
            ->call('addModule');

        $module = $course->modules()->first();
        $this->assertNotNull($module);

        Livewire::actingAs($admin)
            ->test(CourseBuilder::class, ['course' => $course])
            ->call('showLessonForm', $module->id)
            ->set('lessonTitle', 'Introducao')
            ->set('lessonType', 'text')
            ->set('lessonContent', 'Conteudo da aula')
            ->set('lessonAudio', UploadedFile::fake()->create('aula.mp3', 100, 'audio/mpeg'))
            ->call('saveLesson');

        $this->assertDatabaseHas('lessons', [
            'module_id' => $module->id,
            'title' => 'Introducao',
            'type' => 'text',
        ]);

        $lesson = \App\Models\Lesson::where('title', 'Introducao')->first();
        $this->assertNotNull($lesson->audio_path);
        Storage::disk('local')->assertExists($lesson->audio_path);
    }

    public function test_admin_can_edit_existing_lesson_content(): void
    {
        $admin = $this->admin();
        $course = Course::create(['title' => 'Curso', 'target' => 'iniciado', 'price' => 0, 'active' => true]);
        $module = $course->modules()->create(['title' => 'Modulo 1', 'order' => 0]);
        $lesson = $module->lessons()->create(['title' => 'Aula 1', 'type' => 'text', 'content' => '<p>Original</p>', 'order' => 0]);

        Livewire::actingAs($admin)
            ->test(CourseBuilder::class, ['course' => $course])
            ->call('editLesson', $lesson->id)
            ->assertSet('lessonTitle', 'Aula 1')
            ->set('lessonContent', '<p>Atualizado</p>')
            ->call('saveLesson');

        $this->assertDatabaseHas('lessons', [
            'id' => $lesson->id,
            'title' => 'Aula 1',
        ]);
        $this->assertStringContainsString('Atualizado', $lesson->refresh()->content);
    }

    public function test_lesson_content_html_is_sanitized_on_save(): void
    {
        $admin = $this->admin();
        $course = Course::create(['title' => 'Curso', 'target' => 'iniciado', 'price' => 0, 'active' => true]);
        $module = $course->modules()->create(['title' => 'Modulo 1', 'order' => 0]);

        Livewire::actingAs($admin)
            ->test(CourseBuilder::class, ['course' => $course])
            ->call('showLessonForm', $module->id)
            ->set('lessonTitle', 'Aula com script')
            ->set('lessonType', 'text')
            ->set('lessonContent', '<p>Texto</p><script>alert(1)</script>')
            ->call('saveLesson');

        $lesson = $module->lessons()->first();
        $this->assertNotNull($lesson);
        $this->assertStringNotContainsString('<script>', $lesson->content);
        $this->assertStringContainsString('Texto', $lesson->content);
    }

    public function test_iniciado_sees_free_course_matching_target_without_explicit_enrollment(): void
    {
        $admin = $this->admin();
        $iniciado = $this->iniciado();

        $course = Course::create(['title' => 'Curso Livre', 'target' => 'iniciado', 'price' => 0, 'active' => true]);
        $module = $course->modules()->create(['title' => 'M1', 'order' => 0]);
        $module->lessons()->create(['title' => 'Aula 1', 'type' => 'text', 'content' => 'oi', 'order' => 0]);

        Livewire::actingAs($iniciado)
            ->test(CoursesList::class)
            ->assertSee('Curso Livre');
    }

    public function test_completing_all_lessons_marks_enrollment_completed_and_unlocks_certificate(): void
    {
        $iniciado = $this->iniciado();
        $course = Course::create(['title' => 'Curso Curto', 'target' => 'iniciado', 'price' => 0, 'active' => true]);
        $module = $course->modules()->create(['title' => 'M1', 'order' => 0]);
        $lesson = $module->lessons()->create(['title' => 'Unica aula', 'type' => 'text', 'content' => 'conteudo', 'order' => 0]);

        Livewire::actingAs($iniciado)
            ->test(CourseShow::class, ['course' => $course])
            ->call('openLesson', $lesson->id);

        $this->assertDatabaseHas('lesson_progress', [
            'lesson_id' => $lesson->id,
            'user_id' => $iniciado->id,
        ]);

        $enrollment = $course->enrollments()->where('user_id', $iniciado->id)->first();
        $this->assertNotNull($enrollment->completed_at);

        $this->actingAs($iniciado)->get(route('student.courses.certificate', $course))->assertOk();
    }

    public function test_paid_course_requires_purchase_before_access_and_admin_can_confirm_payment(): void
    {
        $admin = $this->admin();
        $iniciado = $this->iniciado();
        $course = Course::create(['title' => 'Curso Pago', 'target' => 'iniciado', 'price' => 50, 'active' => true]);

        Livewire::actingAs($iniciado)
            ->test(CoursesList::class)
            ->call('buy', $course->id);

        $payment = \App\Models\Payment::first();
        $this->assertNotNull($payment);
        $this->assertSame('pending', $payment->status);

        Livewire::actingAs($admin)
            ->test(CourseBuilder::class, ['course' => $course])
            ->call('confirmPayment', $payment->id);

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'paid']);
        $this->assertDatabaseHas('course_enrollments', ['course_id' => $course->id, 'user_id' => $iniciado->id]);
    }

    public function test_consulente_can_never_access_any_course_even_free_or_enrolled(): void
    {
        $consulente = User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'consulente', 'status' => 'active']);
        $freeCourse = Course::create(['title' => 'Curso Gratuito', 'target' => 'both', 'price' => 0, 'active' => true]);
        $paidCourse = Course::create(['title' => 'Curso Pago', 'target' => 'both', 'price' => 50, 'active' => true]);

        // Even with a manually-created enrollment row, access must stay denied.
        $paidCourse->enrollments()->create(['user_id' => $consulente->id, 'source' => 'manual', 'enrolled_at' => now()]);

        $this->assertFalse($freeCourse->userCanAccess($consulente));
        $this->assertFalse($paidCourse->userCanAccess($consulente));

        Livewire::actingAs($consulente)
            ->test(CoursesList::class)
            ->call('buy', $freeCourse->id)
            ->assertForbidden();

        $this->actingAs($consulente)->get(route('student.courses.show', $freeCourse))->assertForbidden();
    }

    public function test_iniciado_with_matching_orisa_record_unlocks_paid_course_for_free(): void
    {
        $admin = $this->admin();
        $iniciado = $this->iniciado();
        $course = Course::create([
            'title' => 'Curso de Yemanja',
            'target' => 'iniciado',
            'price' => 80,
            'required_orisa_name' => 'Yemanjá',
            'active' => true,
        ]);
        $module = $course->modules()->create(['title' => 'M1', 'order' => 0]);
        $lesson = $module->lessons()->create(['title' => 'Aula 1', 'type' => 'text', 'content' => 'oi', 'order' => 0]);

        $iniciado->orisaRecords()->create([
            'orisa_name' => 'Yemanjá',
            'performed_at' => now()->subYear(),
            'created_by' => $admin->id,
        ]);

        $this->assertTrue($course->userCanAccess($iniciado));

        Livewire::actingAs($iniciado)
            ->test(CourseShow::class, ['course' => $course])
            ->call('openLesson', $lesson->id);

        $this->assertDatabaseHas('course_enrollments', [
            'course_id' => $course->id,
            'user_id' => $iniciado->id,
            'source' => 'initiation_criteria',
        ]);

        $this->assertDatabaseMissing('payments', ['payable_type' => Course::class, 'payable_id' => $course->id]);
    }

    public function test_buying_the_same_course_twice_does_not_duplicate_the_payment(): void
    {
        $iniciado = $this->iniciado();
        $course = Course::create(['title' => 'Curso Pago', 'target' => 'iniciado', 'price' => 150, 'active' => true]);

        Livewire::actingAs($iniciado)->test(CoursesList::class)->call('buy', $course->id);
        Livewire::actingAs($iniciado)->test(CoursesList::class)->call('buy', $course->id);

        $this->assertSame(1, \App\Models\Payment::where('payable_type', Course::class)
            ->where('payable_id', $course->id)
            ->where('user_id', $iniciado->id)
            ->count());
    }

    public function test_iniciado_without_matching_criteria_cannot_open_paid_course(): void
    {
        $iniciado = $this->iniciado();
        $course = Course::create([
            'title' => 'Curso de Yemanja',
            'target' => 'iniciado',
            'price' => 80,
            'required_orisa_name' => 'Yemanjá',
            'active' => true,
        ]);

        $this->assertFalse($course->userCanAccess($iniciado));
        $this->actingAs($iniciado)->get(route('student.courses.show', $course))->assertForbidden();
    }
}
