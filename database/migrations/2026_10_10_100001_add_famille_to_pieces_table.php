<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pieces', function (Blueprint $table) {
            // Les pièces existantes restent des pièces ; les chaises, matelas, vélos... sont des « articles divers »
            $table->enum('famille', ['piece', 'divers'])->default('piece')->after('reference');
            $table->index('famille');
        });
    }

    public function down(): void
    {
        Schema::table('pieces', function (Blueprint $table) {
            $table->dropIndex(['famille']);
            $table->dropColumn('famille');
        });
    }
};