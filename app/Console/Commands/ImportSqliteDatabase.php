<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ImportSqliteDatabase extends Command
{
    protected $signature = 'db:import-sqlite
                            {path : Path to the SQLite database file}
                            {--connection= : Destination connection (defaults to DB_CONNECTION)}
                            {--force : Do not ask for confirmation}';

    protected $description = 'Copy application data from a SQLite file into the current Postgres database';

    /**
     * Laravel internals that should not be copied from SQLite.
     *
     * @var list<string>
     */
    private array $skipTables = [
        'migrations',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'sessions',
        'password_reset_tokens',
        'sqlite_sequence',
    ];

    public function handle(): int
    {
        $path = $this->argument('path');
        $absolutePath = str_starts_with($path, DIRECTORY_SEPARATOR)
            ? $path
            : base_path($path);

        if (! is_file($absolutePath)) {
            $this->error("SQLite file not found: {$absolutePath}");

            return self::FAILURE;
        }

        $destination = $this->option('connection') ?: config('database.default');
        $destinationConfig = config("database.connections.{$destination}");

        if (! is_array($destinationConfig) || ($destinationConfig['driver'] ?? null) !== 'pgsql') {
            $this->error("Destination connection [{$destination}] must be pgsql.");

            return self::FAILURE;
        }

        config([
            'database.connections.sqlite_import' => [
                'driver' => 'sqlite',
                'database' => $absolutePath,
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
        ]);

        $sourceTables = $this->listTables('sqlite_import');
        $destinationTables = $this->listTables($destination);
        $tables = array_values(array_diff(
            array_intersect($sourceTables, $destinationTables),
            $this->skipTables
        ));
        $skippedMissing = array_values(array_diff($sourceTables, $destinationTables, $this->skipTables));

        if ($skippedMissing !== []) {
            $this->warn('Skipping SQLite tables that do not exist in Postgres: '.implode(', ', $skippedMissing));
        }

        if ($tables === []) {
            $this->error('No shared application tables to copy. Run migrations on Postgres first.');

            return self::FAILURE;
        }

        $this->info("Source: {$absolutePath}");
        $this->info("Destination: {$destination} (".config("database.connections.{$destination}.database").')');
        $this->table(
            ['Table', 'SQLite rows'],
            array_map(fn (string $table) => [$table, DB::connection('sqlite_import')->table($table)->count()], $tables)
        );

        if (! $this->option('force') && ! $this->confirm('This truncates those Postgres tables and replaces them with SQLite data. Continue?')) {
            return self::SUCCESS;
        }

        $destinationConnection = DB::connection($destination);
        $disabledTriggers = false;

        try {
            $destinationConnection->statement('SET session_replication_role = replica');
            $disabledTriggers = true;
        } catch (Throwable) {
            $this->warn('Could not disable foreign-key triggers (needs a superuser). Inserting in dependency order instead.');
        }

        try {
            $destinationConnection->transaction(function () use ($destinationConnection, $tables, $disabledTriggers) {
                $this->truncateTables($destinationConnection, $tables);

                $insertOrder = $disabledTriggers
                    ? $tables
                    : $this->sortedInsertOrder($destinationConnection, $tables);

                foreach ($insertOrder as $table) {
                    $this->copyTable($destinationConnection, $table);
                }

                $this->restoreSelfReferences($destinationConnection, $insertOrder);
                $this->resetSequences($destinationConnection, $tables);
            });
        } finally {
            if ($disabledTriggers) {
                $destinationConnection->statement('SET session_replication_role = origin');
            }
        }

        $this->info('Import finished.');
        $this->table(
            ['Table', 'SQLite', 'Postgres'],
            array_map(fn (string $table) => [
                $table,
                DB::connection('sqlite_import')->table($table)->count(),
                DB::connection($destination)->table($table)->count(),
            ], $tables)
        );

        $mismatched = array_values(array_filter($tables, function (string $table) use ($destination) {
            return DB::connection('sqlite_import')->table($table)->count()
                !== DB::connection($destination)->table($table)->count();
        }));

        if ($mismatched !== []) {
            $this->error('Row counts differ for: '.implode(', ', $mismatched));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function listTables(string $connection): array
    {
        return collect(Schema::connection($connection)->getTableListing())
            ->map(function (string $table) {
                return str_contains($table, '.') ? substr($table, strrpos($table, '.') + 1) : $table;
            })
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $tables
     */
    private function truncateTables($connection, array $tables): void
    {
        $quoted = implode(', ', array_map(
            fn (string $table) => $connection->getQueryGrammar()->wrapTable($table),
            $tables
        ));

        $connection->statement("TRUNCATE {$quoted} RESTART IDENTITY CASCADE");
    }

    /**
     * @param  list<string>  $tables
     * @return list<string>
     */
    private function sortedInsertOrder($connection, array $tables): array
    {
        $remaining = array_fill_keys($tables, true);
        $parentsByChild = array_fill_keys($tables, []);

        foreach ($this->foreignKeys($connection) as $foreignKey) {
            $child = $foreignKey['child'];
            $parent = $foreignKey['parent'];

            if ($child === $parent || ! isset($remaining[$child], $remaining[$parent])) {
                continue;
            }

            $parentsByChild[$child][$parent] = true;
        }

        $ordered = [];

        while ($remaining !== []) {
            $ready = [];

            foreach ($remaining as $table => $_) {
                $unresolved = array_filter(
                    array_keys($parentsByChild[$table]),
                    fn (string $parent) => isset($remaining[$parent])
                );

                if ($unresolved === []) {
                    $ready[] = $table;
                }
            }

            if ($ready === []) {
                $ready = [array_key_first($remaining)];
            }

            sort($ready);

            foreach ($ready as $table) {
                $ordered[] = $table;
                unset($remaining[$table]);
            }
        }

        return $ordered;
    }

    /**
     * @return list<array{child: string, parent: string, columns: list<string>}>
     */
    private function foreignKeys($connection): array
    {
        $rows = $connection->select("
            SELECT
                tc.table_name AS child_table,
                kcu.column_name AS child_column,
                ccu.table_name AS parent_table
            FROM information_schema.table_constraints AS tc
            JOIN information_schema.key_column_usage AS kcu
                ON tc.constraint_name = kcu.constraint_name
                AND tc.table_schema = kcu.table_schema
            JOIN information_schema.constraint_column_usage AS ccu
                ON ccu.constraint_name = tc.constraint_name
                AND ccu.table_schema = tc.table_schema
            WHERE tc.constraint_type = 'FOREIGN KEY'
                AND tc.table_schema = current_schema()
            ORDER BY tc.table_name, kcu.ordinal_position
        ");

        $grouped = [];

        foreach ($rows as $row) {
            $key = $row->child_table.'|'.$row->parent_table;
            $grouped[$key]['child'] = $row->child_table;
            $grouped[$key]['parent'] = $row->parent_table;
            $grouped[$key]['columns'][] = $row->child_column;
        }

        return array_values($grouped);
    }

    private function copyTable($connection, string $table): void
    {
        $columns = collect(Schema::connection($connection->getName())->getColumns($table))
            ->keyBy('name');
        $selfReferences = $this->selfReferenceColumns($connection, $table);
        $source = DB::connection('sqlite_import')->table($table);
        $rows = $columns->has('id')
            ? $source->orderBy('id')->get()
            : $source->get();

        $payload = [];

        foreach ($rows as $row) {
            $payload[] = $this->castRow((array) $row, $columns, $selfReferences);
        }

        foreach (array_chunk($payload, 250) as $chunk) {
            $connection->table($table)->insert($chunk);
        }

        $this->line("  {$table}: ".count($payload).' rows');
    }

    /**
     * @return list<string>
     */
    private function selfReferenceColumns($connection, string $table): array
    {
        $columns = [];

        foreach ($this->foreignKeys($connection) as $foreignKey) {
            if ($foreignKey['child'] === $table && $foreignKey['parent'] === $table) {
                $columns = array_merge($columns, $foreignKey['columns']);
            }
        }

        return array_values(array_unique($columns));
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  Collection<string, array<string, mixed>>  $columns
     * @param  list<string>  $selfReferences
     * @return array<string, mixed>
     */
    private function castRow(array $row, Collection $columns, array $selfReferences): array
    {
        $cast = [];

        foreach ($row as $name => $value) {
            if (! $columns->has($name)) {
                continue;
            }

            if (in_array($name, $selfReferences, true)) {
                $cast[$name] = null;

                continue;
            }

            $type = strtolower((string) ($columns[$name]['type_name'] ?? $columns[$name]['type'] ?? ''));

            if (in_array($type, ['bool', 'boolean'], true)) {
                $cast[$name] = $value === null ? null : (bool) (int) $value;

                continue;
            }

            if (in_array($type, ['json', 'jsonb'], true)) {
                if ($value === null || $value === '') {
                    $cast[$name] = null;

                    continue;
                }

                $cast[$name] = is_string($value) ? $value : json_encode($value);

                continue;
            }

            $cast[$name] = $value;
        }

        return $cast;
    }

    /**
     * @param  list<string>  $tables
     */
    private function restoreSelfReferences($connection, array $tables): void
    {
        foreach ($tables as $table) {
            $selfReferences = $this->selfReferenceColumns($connection, $table);

            if ($selfReferences === []) {
                continue;
            }

            foreach (DB::connection('sqlite_import')->table($table)->get() as $row) {
                $row = (array) $row;
                $updates = [];

                foreach ($selfReferences as $column) {
                    if (array_key_exists($column, $row)) {
                        $updates[$column] = $row[$column];
                    }
                }

                if ($updates === [] || ! isset($row['id'])) {
                    continue;
                }

                $connection->table($table)->where('id', $row['id'])->update($updates);
            }
        }
    }

    /**
     * @param  list<string>  $tables
     */
    private function resetSequences($connection, array $tables): void
    {
        foreach ($tables as $table) {
            $columns = collect(Schema::connection($connection->getName())->getColumns($table));

            if ($columns->firstWhere('name', 'id') === null) {
                continue;
            }

            $quotedTable = $connection->getQueryGrammar()->wrapTable($table);

            $connection->statement(
                'SELECT setval(pg_get_serial_sequence(?, ?), COALESCE((SELECT MAX(id) FROM '.$quotedTable.'), 1), true)',
                [$table, 'id']
            );
        }
    }
}
