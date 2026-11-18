<?php

namespace App\Livewire\Admin\Courses;

use App\Models\Course;
use App\Models\Odu;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.admin')]
class CourseList extends Component
{
    use WithPagination;

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $title = '';

    public string $description = '';

    public string $target = 'both';

    public string $price = '0';

    public string $required_orisa_name = '';

    public ?int $required_odu_id = null;

    public bool $active = true;

    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'target' => ['required', 'in:iniciado,consulente,both'],
            'price' => ['required', 'numeric', 'min:0'],
            'required_orisa_name' => ['nullable', 'string', 'max:100'],
            'required_odu_id' => ['nullable', 'exists:odus,id'],
            'active' => ['boolean'],
        ];
    }

    public function create(): void
    {
        $this->reset(['editingId', 'title', 'description', 'required_orisa_name', 'required_odu_id']);
        $this->target = 'both';
        $this->price = '0';
        $this->active = true;
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $course = Course::findOrFail($id);
        $this->editingId = $course->id;
        $this->title = $course->title;
        $this->description = (string) $course->description;
        $this->target = $course->target;
        $this->price = (string) $course->price;
        $this->required_orisa_name = (string) $course->required_orisa_name;
        $this->required_odu_id = $course->required_odu_id;
        $this->active = $course->active;
        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate();
        $validated['required_orisa_name'] = $validated['required_orisa_name'] ?: null;

        $course = Course::updateOrCreate(['id' => $this->editingId], $validated);

        $this->showModal = false;
        $this->redirectRoute('admin.courses.show', $course);
    }

    public function delete(int $id): void
    {
        Course::destroy($id);
    }

    public function render()
    {
        return view('livewire.admin.courses.course-list', [
            'courses' => Course::withCount('modules')->orderBy('title')->paginate(25),
            'odus' => Odu::orderBy('number')->get(),
        ]);
    }
}
