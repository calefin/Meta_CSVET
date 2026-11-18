<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('necropsy_requests', function (Blueprint $table) {
            $table->id();
            $table->string('token')->unique();
            
            // Animal fields
            $table->string('animal_name')->nullable();
            $table->string('species');
            $table->string('breed')->nullable();
            $table->string('age')->nullable();
            $table->string('sex')->nullable();
            $table->string('pelage')->nullable();
            $table->decimal('weight', 8, 2);
            
            // Cremation & Euthanasia fields
            $table->boolean('has_cremation_plan')->default(false);
            $table->string('cremation_company')->nullable();
            $table->boolean('is_euthanasia')->default(false);
            
            // Vet info
            $table->boolean('requested_by_vet')->default(false);
            $table->string('vet_crmv')->nullable();
            $table->string('vet_state')->nullable();
            $table->string('vet_establishment')->nullable();
            
            // Requester details
            $table->string('requester_name')->nullable();
            $table->string('requester_email')->nullable();
            $table->string('requester_phone')->nullable();
            $table->string('requester_cpf')->nullable();
            $table->string('requester_rg')->nullable();
            $table->string('requester_cnpj')->nullable();
            $table->string('requester_address')->nullable();
            $table->string('requester_neighborhood')->nullable();
            $table->string('requester_complement')->nullable();
            $table->string('requester_city')->nullable();
            $table->string('requester_state')->nullable();
            $table->string('requester_zip')->nullable();
            
            // Animal history/details
            $table->boolean('has_prosthesis')->default(false);
            $table->string('prosthesis_details')->nullable();
            $table->boolean('had_surgery')->default(false);
            $table->string('surgery_details')->nullable();
            
            // Witnesses
            $table->string('witness1_name')->nullable();
            $table->string('witness1_cpf')->nullable();
            $table->string('witness2_name')->nullable();
            $table->string('witness2_cpf')->nullable();
            
            // Pricing
            $table->decimal('price', 8, 2);
            $table->decimal('discount', 8, 2)->default(0.00);
            $table->decimal('final_price', 8, 2);
            
            // Status and signatures
            $table->string('status')->default('quote_generated');
            $table->timestamp('signed_at')->nullable();
            $table->longText('signature_image')->nullable(); // Drawn signature (Base64)
            $table->string('signature_name')->nullable();    // Typed signature name
            
            // Forensic audit
            $table->string('forensic_hash')->nullable();
            $table->string('forensic_ip')->nullable();
            $table->string('forensic_timestamp')->nullable();
            $table->string('forensic_user_agent')->nullable();
            
            // Generated terms PDF
            $table->string('signed_pdf_path')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('necropsy_requests');
    }
};
