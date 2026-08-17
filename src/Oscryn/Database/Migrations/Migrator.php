<?php

namespace Oscryn\Database\Migrations;

use Oscryn\Database\DBConnector;
use PDO;
use RuntimeException;

class Migrator
{
    protected PDO $pdo;
    protected string $path;
    protected string $table = 'migrations';
    protected array $loaded = [];

    public function __construct(string $path, ?PDO $pdo = null)
    {
        if ($pdo === null) {
            if (DBConnector::ensureDatabaseExists()) {
                $this->write('Created database: '.env('DB_NAME'), 'green');
            }

            $pdo = DBConnector::connection();
        }

        $this->pdo = $pdo;
        $this->path = rtrim($path, '/');
    }

    public function migrate(): void
    {
        $this->ensureMigrationsTable();
        $applied = $this->getApplied();
        $batch = $this->nextBatch();
        $count = 0;

        foreach ($this->getMigrationFiles() as $file) {
            if (isset($applied[$file])) {
                continue;
            }

            $this->resolve($file)->up();
            $this->record($file, $batch);
            $this->write("Migrated: {$file}", 'green');
            $count++;
        }

        if ($count === 0) {
            $this->write('Nothing to migrate.');
        }
    }

    public function rollback(): void
    {
        $this->ensureMigrationsTable();
        $lastBatch = (int) $this->pdo->query(
            "SELECT MAX(batch) FROM `{$this->table}`"
        )->fetchColumn();

        if ($lastBatch === 0) {
            $this->write('Nothing to rollback.');
            return;
        }

        $stmt = $this->pdo->prepare(
            "SELECT migration FROM `{$this->table}` WHERE batch = ? ORDER BY id DESC"
        );
        $stmt->execute([$lastBatch]);

        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $file) {
            $this->resolve($file)->down();
            $delete = $this->pdo->prepare("DELETE FROM `{$this->table}` WHERE migration = ?");
            $delete->execute([$file]);
            $this->write("Rolled back: {$file}", 'yellow');
        }
    }

    public function fresh(): void
    {
        $this->rollbackAll();
        $this->migrate();
    }

    public function status(): void
    {
        $this->ensureMigrationsTable();
        $applied = $this->getApplied();
        $files = $this->getMigrationFiles();

        if ($files === []) {
            $this->write('No migrations found.');
            return;
        }

        foreach ($files as $file) {
            $state = isset($applied[$file]) ? 'APPLIED' : 'PENDING';
            $color = isset($applied[$file]) ? 'green' : 'yellow';
            $this->write(str_pad($file, 55, '.', STR_PAD_RIGHT).' '.$state, $color);
        }
    }

    public function make(string $name): void
    {
        $name = preg_replace('/[^a-z0-9_]+/', '_', strtolower(trim($name)));

        if ($name === '') {
            $this->write('Usage: php migrate.php make <migration_name>', 'red');
            exit(1);
        }

        $class = $this->studly($name);
        $file = date('Y_m_d_His').'_'.$name.'.php';
        $table = $this->tableNameFromMigration($name);

        $template = <<<PHP
<?php

use Oscryn\Database\Migrations\Migration;
use Oscryn\Database\Schema\Schema;
use Oscryn\Database\Schema\Table;

class {$class} extends Migration
{
    public function up(): void
    {
        Schema::create('{$table}', function (Table \$table) {
            \$table->id();
            \$table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{$table}');
    }
}

PHP;

        file_put_contents($this->path.'/'.$file, $template);
        $this->write("Created: {$this->path}/{$file}", 'green');
    }

    protected function rollbackAll(): void
    {
        $this->ensureMigrationsTable();
        $applied = $this->getApplied();

        foreach (array_keys(array_reverse($applied)) as $file) {
            $this->resolve($file)->down();
            $delete = $this->pdo->prepare("DELETE FROM `{$this->table}` WHERE migration = ?");
            $delete->execute([$file]);
            $this->write("Rolled back: {$file}", 'yellow');
        }
    }

    protected function ensureMigrationsTable(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS `'.$this->table.'` ('
            .'`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, '
            .'`migration` VARCHAR(255) NOT NULL, '
            .'`batch` INT NOT NULL, '
            .'UNIQUE KEY `migration_unique` (`migration`)'
            .') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    protected function getApplied(): array
    {
        $rows = $this->pdo->query("SELECT migration, batch FROM `{$this->table}`")->fetchAll(PDO::FETCH_KEY_PAIR);

        return $rows;
    }

    protected function record(string $file, int $batch): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO `{$this->table}` (migration, batch) VALUES (?, ?)");
        $stmt->execute([$file, $batch]);
    }

    protected function getMigrationFiles(): array
    {
        $files = glob($this->path.'/*.php') ?: [];

        return array_map(static fn (string $path) => basename($path, '.php'), $files);
    }

    protected function resolve(string $file): Migration
    {
        if (!isset($this->loaded[$file])) {
            require $this->path.'/'.$file.'.php';
            $this->loaded[$file] = true;
        }

        $class = $this->classFromFileName($file);

        if (!class_exists($class)) {
            throw new RuntimeException("Migration class \"{$class}\" not found in {$file}.");
        }

        return new $class();
    }

    protected function classFromFileName(string $file): string
    {
        $name = preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $file);

        return $this->studly($name);
    }

    protected function studly(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $value)));
    }

    protected function tableNameFromMigration(string $name): string
    {
        if (str_starts_with($name, 'create_')) {
            $name = substr($name, 7);
        }

        if (str_ends_with($name, '_table')) {
            $name = substr($name, 0, -6);
        }

        return $name;
    }

    protected function nextBatch(): int
    {
        return (int) $this->pdo->query("SELECT COALESCE(MAX(batch), 0) + 1 FROM `{$this->table}`")->fetchColumn();
    }

    protected function write(string $message, string $color = 'default'): void
    {
        $colors = [
            'green'   => "\033[32m",
            'yellow'  => "\033[33m",
            'red'     => "\033[31m",
            'default' => "\033[0m",
        ];

        $code = $colors[$color] ?? $colors['default'];
        echo $code.$message."\033[0m".PHP_EOL;
    }
}
