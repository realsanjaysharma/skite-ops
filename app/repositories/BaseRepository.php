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
 * - Transaction methods are public so services can call them
 * - Should NOT be called directly from controllers
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
     * - null (if no result)
     */
    protected function fetchOne($sql, $params = [])
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result !== false ? $result : null;
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

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
     * - Public so SERVICE layer can control transaction boundaries
     * - Should NOT be called directly from controllers
     */
    public function beginTransaction()
    {
        return $this->db->beginTransaction();
    }

    /**
     * COMMIT TRANSACTION
     *
     * Called when all operations succeed
     */
    public function commit()
    {
        return $this->db->commit();
    }

    /**
     * ROLLBACK TRANSACTION
     *
     * Called when any operation fails
     */
    public function rollback()
    {
        return $this->db->rollBack();
    }

    /**
     * CHECK TRANSACTION STATE
     *
     * Returns true if a transaction is currently active.
     */
    public function inTransaction(): bool
    {
        return $this->db->inTransaction();
    }
}
