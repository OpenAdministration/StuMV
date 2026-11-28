<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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

        $this->convertToString($connection, 'oauth_clients', 'id');

        foreach ($this->clientIdColumns as $table => $column) {
            if (
                Schema::connection($connection)->hasTable($table)
                && $this->isNumericColumn($connection, $table, $column)
            ) {
                $this->convertToString($connection, $table, $column);
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
        $type = strtolower(Schema::connection($connection)->getColumnType($table, $column));

        // MySQL/MariaDB type names, then PostgreSQL's
        return in_array($type, ['tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint', 'int2', 'int4', 'int8'], true);
    }

    /**
     * Turn an integer column into a non-null 36-char string, keeping its values.
     */
    protected function convertToString(?string $connection, string $table, string $column): void
    {
        Schema::connection($connection)->table($table, function (Blueprint $blueprint) use ($column): void {
            // using() only matters for PostgreSQL, which won't cast integer to varchar implicitly
            $blueprint->string($column, 36)->using("{$column}::varchar(36)")->change();
        });
    }

    /**
     * Get the migration connection name.
     */
    public function getConnection(): ?string
    {
        return $this->connection ?? config('passport.connection');
    }
};
