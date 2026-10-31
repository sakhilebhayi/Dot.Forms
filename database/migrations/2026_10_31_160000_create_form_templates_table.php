<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R-30 Phase 3: an organisation's own reusable masters. Built-in templates live in
 * code (App\Support\FormTemplates); this table holds only what a team saved itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('description', 500)->nullable();
            $table->json('payload');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['team_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_templates');
    }
};
