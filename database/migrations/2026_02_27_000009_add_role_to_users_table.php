<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add role column to the users table.
 * Currently single-admin system, but structured for future multi-user expansion.
 * Role field is present but not enforced in middleware yet.
 */
return new class extends Migration
{
    /**
     * Run the migration — add role column to users table.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // User role for future RBAC — defaults to 'admin' since current system is single-user
            // Placed after 'email' column for logical ordering
            $table->string('role')->default('admin')->after('email');
        });
    }

    /**
     * Reverse the migration — remove the role column.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop the role column when rolling back
            $table->dropColumn('role');
        });
    }
};
