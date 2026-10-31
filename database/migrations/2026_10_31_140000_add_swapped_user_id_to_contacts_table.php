<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            // Set when two MetMemo users swap cards: the other person's account. One swap contact per pair, so
            // swapping again updates it instead of adding a duplicate.
            $table->foreignUuid('swapped_user_id')->nullable()->after('card_id')->constrained('users')->nullOnDelete();
            $table->unique(['user_id', 'swapped_user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'swapped_user_id']);
            $table->dropConstrainedForeignId('swapped_user_id');
        });
    }
};
