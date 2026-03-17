<?php

require_once __DIR__ . '/../../config/database.php';

class BaseRepository
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    protected function fetchOne($sql, $params = [])
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch();
    }

    protected function fetchAll($sql, $params = [])
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    protected function execute($sql, $params = [])
    {
        $stmt = $this->db->prepare($sql);

        return $stmt->execute($params);
    }

    protected function lastInsertId()
    {
        return $this->db->lastInsertId();
    }

    public function beginTransaction()
    {
        return $this->db->beginTransaction();
    }

    public function commit()
    {
        return $this->db->commit();
    }

    public function rollback()
    {
        return $this->db->rollBack();
    }
}