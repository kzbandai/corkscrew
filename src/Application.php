<?php
declare(strict_types = 1);

namespace Corkscrew;

use PDO;
use PDOStatement;
use PDOException;
use InvalidArgumentException;

class Application
{
    /* @var PDO $pdo */
    private $pdo;

    /* @var array $statements */
    private $statements;

    const SELECT = 'select';
    const INSERT = 'insert';
    const UPDATE = 'update';
    const DELETE = 'delete';

    public function __construct(array $config)
    {
        if ($this->pdo && $this->pdo instanceof PDO) {
            return;
        }

        $config['dns'] = $this->getDns($config['dns']);
        $option = array_merge($config['option'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        try {
            $this->pdo = new PDO($config['dns'], $config['user'], $config['password'], $option);
        } catch (PDOException $e) {
            throw new CorkscrewException('Database Error');
        }
    }

    public function getStatement(string $name): PDOStatement
    {
        if ($name || $this->statements[$name]) {
            return $this->statements[$name];
        }

        throw new InvalidArgumentException();
    }

    public function prepareStatement(string $name, string $sql): Application
    {
        try {
            $this->statements[$name] = $this->pdo->prepare($sql);
        } catch (PDOException $e) {
            throw new CorkscrewException('Database Error');
        }

        return $this;
    }

    public function setParams(string $name, array $params): Application
    {
        if (!$name || !$this->statements[$name]) {
            throw new InvalidArgumentException();
        }

        /* @var PDOStatement $statement */
        $statement = $this->statements[$name];

        try {
            foreach ($params as $k => $v) {
                if (ctype_digit((string)$v) || is_numeric($v)) {
                    $statement->bindValue(":${k}", (int)$v, PDO::PARAM_INT);
                } else {
                    $statement->bindValue(":${k}", (string)$v, PDO::PARAM_STR);
                }
            }
        } catch (PDOException $e) {
            throw new CorkscrewException('Database Error');
        }

        return $this;
    }

    /**
     * @param string $name
     * @return array|bool
     * @throws CorkscrewException
     */
    public function exec(string $name)
    {
        if (!$name || !$this->statements[$name]) {
            throw new InvalidArgumentException();
        }

        /* @var PDOStatement $statement */
        $statement = $this->statements[$name];

        try {
            /* @var PDOStatement $statement */
            $this->pdo->beginTransaction();
            $exec_result = $statement->execute();

            if ($this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw new CorkscrewException('Database Error');
        }

        if ($this->validateQuery($statement->queryString, self::SELECT)) {
            $data = $statement->fetchAll(PDO::FETCH_ASSOC);
            if (!$data) {
                throw new InvalidArgumentException();
            }

            return $data;
        }

        return $exec_result;
    }

    public function select(string $query): array
    {
        if ($this->validateQuery($query, self::SELECT)) {
            try {
                $statement = $this->pdo->query($query);
            } catch (PDOException $e) {
                throw new CorkscrewException('Database Error');
            }

            return $statement->fetch(PDO::FETCH_ASSOC);
        }

        throw new InvalidArgumentException();
    }

    public function insert(string $query): PDOStatement
    {
        if ($this->validateQuery($query, self::INSERT)) {
            try {
                $statement = $this->pdo->query($query);
            } catch (PDOException $e) {
                throw new CorkscrewException('Database Error');
            }

            return $statement->fetch(PDO::FETCH_ASSOC);
        }

        throw new InvalidArgumentException();
    }

    public function update(string $query): PDOStatement
    {
        if ($this->validateQuery($query, self::UPDATE)) {
            try {
                $statement = $this->pdo->query($query);
            } catch (PDOException $e) {
                throw new CorkscrewException('Database Error');
            }

            return $statement->fetch(PDO::FETCH_ASSOC);
        }

        throw new InvalidArgumentException();
    }

    public function delete(string $query): PDOStatement
    {
        if ($this->validateQuery($query, self::DELETE)) {
            try {
                $statement = $this->pdo->query($query);
            } catch (PDOException $e) {
                throw new CorkscrewException('Database Error');
            }

            return $statement->fetchAll(PDO::FETCH_ASSOC);
        }

        throw new InvalidArgumentException();
    }

    public function getAttribute($attribute): string
    {
        return $this->pdo->getAttribute($attribute);
    }

    public function getErrorCode(): string
    {
        return (string)$this->pdo->errorCode();
    }

    public function getErrorInfo(): array
    {
        return $this->pdo->errorInfo();
    }

    private function getDns(array $config): string
    {
        if (!array_key_exists('driver', $config) || !array_key_exists('db_name', $config) || !array_key_exists('host', $config)) {
            throw new InvalidArgumentException();
        }

        return sprintf('%s:dbname=%s;host=%s;charset=utf8', $config['driver'], $config['db_name'], $config['host']);
    }

    private function validateQuery(string $query, string $kind): bool
    {
        switch ($kind) {
            case self::SELECT:
                if (!preg_match('/^SELECT|select.*/', $query)) {
                    throw new InvalidArgumentException('Not Select Sql');
                }
                break;

            case self::INSERT:
                if (!preg_match('/^INSERT|insert.*/', $query)) {
                    throw new InvalidArgumentException('Not Insert Sql');
                }
                break;

            case self::UPDATE:
                if (!preg_match('/^UPDATE|update.*/', $query)) {
                    throw new InvalidArgumentException('Not Update Sql');
                }
                break;

            case self::DELETE:
                if (!preg_match('/^DELETE|delete.*/', $query)) {
                    throw new InvalidArgumentException('Not Delete Sql');
                }
                break;

            default:
        }

        return true;
    }
}
