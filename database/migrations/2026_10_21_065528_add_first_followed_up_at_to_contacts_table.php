<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            // Set once, the first time you mark them followed up (last_contacted_at keeps moving).
            // Powers "average days to first follow-up" on Insights.
            $table->timestamp('first_followed_up_at')->nullable()->after('last_contacted_at');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn('first_followed_up_at');
        });
    }
};
