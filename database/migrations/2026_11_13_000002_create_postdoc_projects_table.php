<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Painel privado do pós-doutorado do professor (um registro por estágio).
        Schema::create('postdoc_projects', function (Blueprint $table) {
            $table->id();
            $table->string('title', 500);
            $table->string('institution')->nullable();
            $table->string('program')->nullable();
            $table->string('research_line')->nullable();
            $table->string('supervisor')->nullable();
            $table->string('supervisor_email')->nullable();
            $table->string('modality')->nullable();
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->string('status', 30)->default('preparacao');
            $table->string('protocol_code', 10)->nullable(); // revisão do programa ligada ao pós-doc (P19)
            $table->string('registration_doi')->nullable();
            $table->json('checklist')->nullable();   // [{item, done_at, note}]
            $table->json('timeline')->nullable();    // [{activity, months, status}]
            $table->json('documents')->nullable();   // caminhos no disco local (privado)
            $table->json('deliverables')->nullable(); // [{title, due_at, done_at, link}]
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('postdoc_projects');
    }
};
