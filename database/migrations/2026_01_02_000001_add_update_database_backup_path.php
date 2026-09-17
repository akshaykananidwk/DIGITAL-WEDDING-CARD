<?php

/**
 * Record the pre-update database backup alongside the file backup.
 *
 * A rollback has to restore both halves. Before this column only the file
 * archive path was stored, so a rollback could put the old code back but
 * left the database as the failed update had left it.
 */

declare(strict_types=1);

use App\Core\Blueprint;
use App\Core\Database;
use App\Core\Schema;

return new class {
    public function up(Database $db): void
    {
        Schema::use($db);

        Schema::addColumn('update_logs', 'database_backup_path', static function (Blueprint $t): void {
            $t->string('database_backup_path', 255)->nullable();
        });
    }

    public function down(Database $db): void
    {
        Schema::use($db);

        // SQLite before 3.35 cannot drop a column, and leaving a spare
        // nullable column behind is harmless either way.
        if (!$db->isSqlite() && $db->columnExists('update_logs', 'database_backup_path')) {
            $db->pdo()->exec(
                'ALTER TABLE ' . $db->wrap($db->table('update_logs')) . ' DROP COLUMN '
                . $db->wrap('database_backup_path')
            );
        }
    }
};
