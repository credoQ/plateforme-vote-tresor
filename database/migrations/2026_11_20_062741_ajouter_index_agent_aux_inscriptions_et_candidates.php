<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L'espace électeur cherche l'inscription et les candidatures d'un agent par son seul identifiant : les index
 * existants commencent par la phase ou le scrutin et ne servent pas à cette recherche.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inscriptions', function (Blueprint $table): void {
            $table->index('agent_id');
        });

        Schema::table('candidates', function (Blueprint $table): void {
            $table->index('agent_id');
        });
    }

    public function down(): void
    {
        Schema::table('inscriptions', function (Blueprint $table): void {
            $table->dropIndex(['agent_id']);
        });

        Schema::table('candidates', function (Blueprint $table): void {
            $table->dropIndex(['agent_id']);
        });
    }
};
