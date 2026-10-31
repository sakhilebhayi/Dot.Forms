<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R-30 leftovers: the funnel's starts counter, the duplicate-submit guard,
 * and the desk's approval stamp.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->unsignedInteger('starts_count')->default(0)->after('views_count');
        });

        Schema::table('form_submissions', function (Blueprint $table) {
            // Set by the respondent's browser session; a retried submit with the
            // same token is answered with the receipt instead of a second copy.
            $table->string('client_token', 64)->nullable()->after('user_agent');
            $table->index(['form_id', 'client_token']);

            // The approval stamp: a decision on the copy, by whom, and when.
            $table->string('decision', 16)->nullable()->after('internal_note');
            $table->foreignId('decided_by')->nullable()->after('decision')->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable()->after('decided_by');
        });
    }

    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->dropColumn('starts_count');
        });

        Schema::table('form_submissions', function (Blueprint $table) {
            $table->dropIndex(['form_id', 'client_token']);
            $table->dropConstrainedForeignId('decided_by');
            $table->dropColumn(['client_token', 'decision', 'decided_at']);
        });
    }
};
