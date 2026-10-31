<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R-30 Phase 4: the response desk. A copy is worked, not just stored — it carries a
 * status, can be assigned to a person on the team, and holds an internal note that
 * respondents never see.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_submissions', function (Blueprint $table) {
            $table->string('status', 20)->default('new')->after('data');
            $table->foreignId('assigned_to')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->text('internal_note')->nullable()->after('assigned_to');

            $table->index(['form_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('form_submissions', function (Blueprint $table) {
            $table->dropIndex(['form_id', 'status']);
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropColumn(['status', 'internal_note']);
        });
    }
};
