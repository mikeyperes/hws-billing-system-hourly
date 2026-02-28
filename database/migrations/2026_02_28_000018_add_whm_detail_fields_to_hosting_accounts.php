<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add WHM detail fields to hosting_accounts.
 * Stores reseller owner, contact email, suspension reason, and shell/theme info.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hosting_accounts', function (Blueprint $table) {
            $table->string('owner')->default('root')->after('domain');
            $table->string('email')->nullable()->after('owner');
            $table->string('suspend_reason')->nullable()->after('status');
            $table->string('shell_access')->nullable()->after('bandwidth_used_mb');
            $table->string('theme')->nullable()->after('shell_access');
        });
    }

    public function down(): void
    {
        Schema::table('hosting_accounts', function (Blueprint $table) {
            $table->dropColumn(['owner', 'email', 'suspend_reason', 'shell_access', 'theme']);
        });
    }
};
