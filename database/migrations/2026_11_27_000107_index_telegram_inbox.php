<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mini_requests', function (Blueprint $table) {
            $table->index(['user_id', 'updated_at', 'id'], 'mini_requests_owner_recent');
            $table->index(['user_id', 'visitor', 'updated_at', 'id'], 'mini_requests_visitor_recent');
        });

        // MySQL already indexes the foreign key on mini_request_id.
        if (DB::connection()->getDriverName() === 'sqlite') {
            Schema::table('mini_messages', fn (Blueprint $table) => $table->index('mini_request_id', 'mini_messages_request_index'));
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            Schema::table('mini_messages', fn (Blueprint $table) => $table->dropIndex('mini_messages_request_index'));
        }

        Schema::table('mini_requests', function (Blueprint $table) {
            $table->dropIndex('mini_requests_visitor_recent');
            $table->dropIndex('mini_requests_owner_recent');
        });
    }
};
