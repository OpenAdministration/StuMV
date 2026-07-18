<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables (besides oauth_clients itself) whose column stores a client id.
     *
     * @var array<string, string>
     */
    protected array $clientIdColumns = [
        'oauth_access_tokens' => 'client_id',
        'oauth_auth_codes' => 'client_id',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $connection = $this->getConnection();

        // Laravel\Passport\Client always generates a UUID for `id` (it uses
        // the HasUuids trait), but some deployments' oauth_clients table
        // predates Passport's switch to UUID client ids and still has a
        // legacy auto-incrementing integer `id`. Inserting a real UUID into
        // an int column truncates it down to its leading digits, so both
        // the primary key and every column that stores a client id need to
        // become a 36-char string. Existing integer ids (e.g. 5) convert to
        // their string form (e.g. "5") and keep matching each other.
        if (! $this->isNumericColumn($connection, 'oauth_clients', 'id')) {
            return;
        }

        DB::connection($connection)->statement('ALTER TABLE oauth_clients MODIFY id CHAR(36) NOT NULL');

        foreach ($this->clientIdColumns as $table => $column) {
            if (
                Schema::connection($connection)->hasTable($table)
                && $this->isNumericColumn($connection, $table, $column)
            ) {
                DB::connection($connection)->statement("ALTER TABLE {$table} MODIFY {$column} CHAR(36) NOT NULL");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Not reversible: once new UUID client ids exist alongside the
        // converted legacy integer ones, there's no lossless way back to an
        // auto-incrementing integer column.
    }

    /**
     * Determine whether a column currently has an integer type.
     */
    protected function isNumericColumn(?string $connection, string $table, string $column): bool
    {
        $result = DB::connection($connection)->selectOne(
            'select DATA_TYPE as `type` from information_schema.COLUMNS
                where TABLE_SCHEMA = DATABASE() and TABLE_NAME = ? and COLUMN_NAME = ?',
            [$table, $column]
        );

        return $result && in_array(strtolower($result->type), ['tinyint', 'smallint', 'mediumint', 'int', 'bigint'], true);
    }

    /**
     * Get the migration connection name.
     */
    public function getConnection(): ?string
    {
        return $this->connection ?? config('passport.connection');
    }
};
