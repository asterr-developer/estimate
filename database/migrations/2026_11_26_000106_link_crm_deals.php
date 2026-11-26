<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('workspace_records', function (Blueprint $table) {
            $table->string('crm_deal_key', 80)->nullable()->unique();
        });
        Schema::table('proposals', function (Blueprint $table) {
            $table->string('crm_deal_key', 80)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->dropIndex(['crm_deal_key']);
            $table->dropColumn('crm_deal_key');
        });
        Schema::table('workspace_records', function (Blueprint $table) {
            $table->dropUnique(['crm_deal_key']);
            $table->dropColumn('crm_deal_key');
        });
    }
};
