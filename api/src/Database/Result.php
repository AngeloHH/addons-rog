<?php

/**
 * Addons ROG - Buffered query results
 *
 * - Provide the fetch operations used by portable RETURNING emulation.
 * - Keep cursor position separate from the number of affected rows.
 */

declare(strict_types=1);

namespace Rog\Database;

final class Result {
    private int $offset = 0;

    public function __construct(private array $rows, private int $affected = 0) {}
    public function fetch(): array|false { return $this->rows[$this->offset++] ?? false; }

    /**
     * Return the remaining rows and advance the cursor to the end.
     */
    public function fetchAll(): array {
        $rows = array_slice($this->rows, $this->offset); $this->offset = count($this->rows);
        return $rows;
    }

    public function fetchColumn(int $column = 0): mixed {
        $row = $this->fetch(); return $row === false ? false : (array_values($row)[$column] ?? false);
    }

    // Affected rows describe the mutation, not the remaining buffered results.
    public function rowCount(): int { return $this->affected; }
}
