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
        Schema::table('crm_companies', function (Blueprint $table) {
            $table->string('trading_name')->nullable()->after('name');
            $table->string('registration_number')->nullable()->after('trading_name');
            $table->string('tax_number')->nullable()->after('registration_number');
            $table->string('industry_classification')->nullable()->after('industry_id');
            $table->string('company_size')->nullable()->after('industry_classification');
            $table->decimal('annual_revenue', 15, 2)->nullable()->after('company_size');
            $table->integer('employees_count')->nullable()->after('annual_revenue');
            $table->uuid('parent_id')->nullable()->after('organization_id');
            $table->string('status')->default('Prospect')->after('address'); // Prospect, Customer, Partner, Vendor, Inactive
            $table->uuid('user_id')->nullable()->after('parent_id'); // Account owner
            $table->string('territory')->nullable()->after('status');
            $table->string('timezone')->nullable()->after('territory');
            $table->string('preferred_language')->nullable()->after('timezone');
            $table->string('currency')->nullable()->after('preferred_language');
            $table->jsonb('social_media_profiles')->nullable()->after('custom_fields');
            $table->integer('health_score')->default(100)->after('status');
            $table->string('health_status')->default('Healthy')->after('health_score'); // Healthy, Warning, Critical
            $table->jsonb('health_breakdown')->nullable()->after('health_status');

            $table->foreign('parent_id')
                ->references('id')
                ->on('crm_companies')
                ->onDelete('set null');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->index('parent_id');
            $table->index('user_id');
            $table->index('status');
            $table->index('health_score');
        });

        Schema::create('crm_company_locations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('company_id');
            $table->string('type'); // headquarters, branch, warehouse, billing, shipping
            $table->string('name');
            $table->text('address')->nullable();
            $table->string('country')->nullable();
            $table->string('state')->nullable();
            $table->string('county')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('gps_coordinates')->nullable();
            $table->string('timezone')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');

            $table->foreign('company_id')
                ->references('id')
                ->on('crm_companies')
                ->onDelete('cascade');

            $table->index('organization_id');
            $table->index('company_id');
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_company_locations');

        Schema::table('crm_companies', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropForeign(['user_id']);
            $table->dropColumn([
                'trading_name',
                'registration_number',
                'tax_number',
                'industry_classification',
                'company_size',
                'annual_revenue',
                'employees_count',
                'parent_id',
                'status',
                'user_id',
                'territory',
                'timezone',
                'preferred_language',
                'currency',
                'social_media_profiles',
                'health_score',
                'health_status',
                'health_breakdown'
            ]);
        });
    }
};
