<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('depenses', function (Blueprint $table) {
            $table->enum('statut', ['valide', 'annulee'])->default('valide')->after('notes');
            $table->string('motif_annulation', 255)->nullable()->after('statut');
            $table->index('statut');
        });
    }

    public function down(): void
    {
        Schema::table('depenses', function (Blueprint $table) {
            $table->dropIndex(['statut']);
            $table->dropColumn(['statut', 'motif_annulation']);
        });
    }
};