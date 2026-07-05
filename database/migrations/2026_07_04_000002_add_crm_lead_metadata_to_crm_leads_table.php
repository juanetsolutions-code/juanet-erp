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
        Schema::table('crm_leads', function (Blueprint $table) {
            if (!Schema::hasColumn('crm_leads', 'crm_lead_metadata')) {
                $table->jsonb('crm_lead_metadata')->nullable()->after('custom_fields');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crm_leads', function (Blueprint $table) {
            if (Schema::hasColumn('crm_leads', 'crm_lead_metadata')) {
                $table->dropColumn('crm_lead_metadata');
            }
        });
    }
};
