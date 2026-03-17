<?php

/**
 * BaseRepository
 *
 * Purpose:
 * Provides shared database access methods for all repositories.
 *
 * Why it exists:
 * - Avoid repeating PDO logic everywhere
 * - Standardize query execution
 * - Keep repositories clean and small
 *
 * IMPORTANT ARCHITECTURE RULES:
 * - ONLY database access (SQL)
 * - NO business logic here
 * - NO validation
 * - NO decision-making
 *
 * Architecture Flow:
 * Controller → Service → Repository → Database
 *
 * Transaction Rule:
 * - Transactions are controlled by SERVICE layer
 * - Repository should NOT expose transaction control publicly
 */

require_once __DIR__ . '/../../config/database.php';

class BaseRepository
{
    /**
     * Shared PDO connection
     * All repositories use same instance (Singleton)
     */
    protected $db;

    /**
     * Constructor
     *
     * Initializes database connection
     */
    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Fetch single row
     *
     * Use when:
     * - Expect exactly one result
     *
     * Returns:
     * - array (row data)
     * - false (if no result)
     */
    protected function fetchOne($sql, $params = [])
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch();
    }

    /**
     * Fetch multiple rows
     *
     * Use when:
     * - Expect list of records
     *
     * Returns:
     * - array of rows
     */
    protected function fetchAll($sql, $params = [])
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Execute query (INSERT / UPDATE / DELETE)
     *
     * Returns:
     * - true on success
     * - false on failure
     */
    protected function execute($sql, $params = [])
    {
        $stmt = $this->db->prepare($sql);

        return $stmt->execute($params);
    }

    /**
     * Get last inserted ID
     *
     * Use after INSERT queries
     */
    protected function lastInsertId()
    {
        return $this->db->lastInsertId();
    }

    /**
     * BEGIN TRANSACTION
     *
     * IMPORTANT:
     * - Protected: only used internally or via Service layer
     * - Should NOT be called directly from controllers
     */
    protected function beginTransaction()
    {
        return $this->db->beginTransaction();
    }

    /**
     * COMMIT TRANSACTION
     *
     * Called when all operations succeed
     */
    protected function commit()
    {
        return $this->db->commit();
    }

    /**
     * ROLLBACK TRANSACTION
     *
     * Called when any operation fails
     */
    protected function rollback()
    {
        return $this->db->rollBack();
    }
}