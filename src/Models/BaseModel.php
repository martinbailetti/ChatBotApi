<?php
declare(strict_types=1);

/**
 * Modelo base.
 *
 * Proporciona acceso al PDO singleton y helpers para
 * operaciones comunes (find, findAll, insert, update, delete).
 * Todos los métodos usan prepared statements.
 */
abstract class BaseModel
{
    /** @var PDO */
    protected $db;

    /** @var string Nombre de la tabla, definido en la subclase. */
    protected $table = '';

    /** @var string Nombre de la clave primaria. */
    protected $primaryKey = 'Id';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ── Helpers de consulta ────────────────────────────────────────────────────

    /**
     * Busca un registro por su clave primaria.
     *
     * @param int|string $id
     * @return array|null
     */
    public function findById($id): ?array
    {
        $sql  = 'SELECT * FROM `' . $this->table . '` WHERE `' . $this->primaryKey . '` = ? LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /**
     * Devuelve todos los registros de la tabla.
     *
     * @return array
     */
    public function findAll(): array
    {
        $sql  = 'SELECT * FROM `' . $this->table . '`';
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Inserta un registro y devuelve el ID generado.
     *
     * @param array $data array asociativo columna => valor
     * @return int
     */
    protected function insert(array $data): int
    {
        $columns      = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');

        $sql = 'INSERT INTO `' . $this->table . '` '
             . '(`' . implode('`, `', $columns) . '`) '
             . 'VALUES (' . implode(', ', $placeholders) . ')';

        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_values($data));
        return (int)$this->db->lastInsertId();
    }

    /**
     * Actualiza un registro por su clave primaria.
     *
     * @param int|string $id
     * @param array      $data array asociativo columna => valor
     * @return int Filas afectadas
     */
    protected function update($id, array $data): int
    {
        $sets = [];
        foreach (array_keys($data) as $col) {
            $sets[] = '`' . $col . '` = ?';
        }

        $sql = 'UPDATE `' . $this->table . '` '
             . 'SET ' . implode(', ', $sets) . ' '
             . 'WHERE `' . $this->primaryKey . '` = ?';

        $values   = array_values($data);
        $values[] = $id;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($values);
        return $stmt->rowCount();
    }

    /**
     * Elimina un registro por su clave primaria.
     *
     * @param int|string $id
     * @return int Filas afectadas
     */
    protected function delete($id): int
    {
        $sql  = 'DELETE FROM `' . $this->table . '` WHERE `' . $this->primaryKey . '` = ?';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->rowCount();
    }

    /**
     * Cuenta el número de registros de la tabla.
     *
     * @return int
     */
    public function count(): int
    {
        $sql  = 'SELECT COUNT(*) FROM `' . $this->table . '`';
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }
}
