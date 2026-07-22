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
        Schema::create('group_mailman_lists', function (Blueprint $table): void {
            $table->id();
            // Stored alongside group_dn (which already encodes it) rather
            // than parsed back out of the DN at sync time - resolving a
            // member's LDAP mail attribute needs the realm's people DN
            // (see Community::peopleDnFor()), and every other sync command
            // already has the realm in hand as a plain uid string too.
            $table->string('realm');
            $table->string('group_dn');
            // Mailman 3's own list_id (e.g. "newsletter.lists.example.org"),
            // not the posting address - that's what every Mailman Core REST
            // endpoint addresses a list by.
            $table->string('mailman_list_id');
            $table->timestamps();
            $table->unique(['group_dn', 'mailman_list_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_mailman_lists');
    }
};
