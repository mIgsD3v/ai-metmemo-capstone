<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A landline next to the mobile number ("phone"), on cards and contacts. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['cards', 'contacts'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('telephone')->nullable()->after('phone');
            });
        }
    }

    public function down(): void
    {
        foreach (['cards', 'contacts'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('telephone');
            });
        }
    }
};
