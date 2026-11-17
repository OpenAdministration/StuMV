<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const array LETTER_COLUMNS = [
        'letter_enabled' => 'with_letter',
        'letter_recipient' => 'recipient',
        'letter_header_title' => 'header_title',
        'letter_header_subtitle' => 'header_subtitle',
        'letter_sender_name' => 'sender_name',
        'letter_sender_unit' => 'sender_unit',
        'letter_sender_building' => 'sender_building',
        'letter_sender_room' => 'sender_room',
        'letter_sender_email' => 'sender_email',
        'letter_sender_phone' => 'sender_phone',
        'letter_sender_website' => 'sender_website',
        'letter_subject' => 'subject',
        'letter_body' => 'body',
    ];

    /** Replaces the single set of letter defaults per realm with any number of templates. */
    public function up(): void
    {
        Schema::create('membership_export_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('realm')->index();
            $table->string('name');
            $table->boolean('with_letter')->default(false);
            $table->string('sender')->default('realm');
            $table->string('date_format')->default('Y-m-d');
            $table->json('recipient')->nullable();
            $table->string('header_title')->nullable();
            $table->string('header_subtitle')->nullable();
            $table->string('sender_name')->nullable();
            $table->string('sender_unit')->nullable();
            $table->string('sender_building')->nullable();
            $table->string('sender_room')->nullable();
            $table->string('sender_email')->nullable();
            $table->string('sender_phone')->nullable();
            $table->string('sender_website')->nullable();
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->timestamps();
        });

        foreach (DB::table('realm_contacts')->get() as $contact) {
            $values = [];
            foreach (self::LETTER_COLUMNS as $old => $new) {
                $values[$new] = $contact->{$old};
            }

            if (array_filter($values, fn (mixed $value): bool => $value !== null && $value !== false && $value !== 0) === []) {
                continue;
            }

            DB::table('membership_export_templates')->insert([
                ...$values,
                'realm' => $contact->realm,
                'name' => $contact->letter_subject ?? 'Anschreiben',
                'with_letter' => (bool) $contact->letter_enabled,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('realm_contacts', function (Blueprint $table): void {
            $table->dropColumn([...array_keys(self::LETTER_COLUMNS), 'letter_signer_function']);
        });
    }

    public function down(): void
    {
        Schema::table('realm_contacts', function (Blueprint $table): void {
            $table->boolean('letter_enabled')->default(false);
            $table->json('letter_recipient')->nullable();
            $table->text('letter_body')->nullable();
            foreach ([...array_keys(self::LETTER_COLUMNS), 'letter_signer_function'] as $column) {
                if (! in_array($column, ['letter_enabled', 'letter_recipient', 'letter_body'], true)) {
                    $table->string($column)->nullable();
                }
            }
        });

        Schema::dropIfExists('membership_export_templates');
    }
};
