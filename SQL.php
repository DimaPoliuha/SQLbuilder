<?php
/**
 * Created by PhpStorm.
 * User: Dmitry Poliuha
 * Date: 25.04.2018
 * Time: 21:32
 */

class SQL {

    protected $commands = [];
    protected $params = [];
    protected $statement = "";
    protected $pdo;
    protected $expectedCommands =
        [
            "SELECT ",
            "INSERT ",
            "UPDATE ",
            "DELETE ",
            "DROP TABLE "
        ];
    protected $previousCommand = "";


    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    private function checkCommandOrder(string $function) {
        $function = strtoupper($function) . " ";

        $str = "";
        foreach ($this->expectedCommands as $value)
            $str .= "<{$value}>, ";

        if (!in_array($function, $this->expectedCommands))
            throw new Exception("BAD QUERY, EXPECTED " . $str . "but HAVE: <" . $function . ">");
    }

    private static function compoundName(string $str): string {
        if (preg_match("/^[a-z\_][a-z0-9\_]*\.[a-z\_][a-z0-9\_]*$/", $str))
        {
            $temp = explode('.', $str);
            return '`' . $temp[0] . '`.`' . $temp[1] . '`';
        }
        return '`' . $str . '`';
    }

    /* -------------------- SELECT -------------------- */

    public function select(string ... $fields): self {
        $this->checkCommandOrder(__FUNCTION__);

        $this->statement .= "SELECT ";

        if (empty($fields))
            $this->statement .= '* ';
        else {
            $i = 0;
            while ($i < count($fields) - 1) {
                $fields[$i] = self::compoundName($fields[$i]);
                $this->statement .= $fields[$i];
                $this->statement .= ', ';
                $i++;
            }
            $fields[$i] = self::compoundName($fields[$i]);
            $this->statement .= $fields[$i];
        }

        $this->statement .= ' ';
        $this->previousCommand = "SELECT ";
        $this->expectedCommands = ["FROM "];
        return $this;
    }

    /* -------------------- FROM -------------------- */

    public function from(string $firstTable, string ... $tables): self {
        $this->checkCommandOrder(__FUNCTION__);

        $this->statement .= "FROM ";
        $this->statement .= '`' . $firstTable . '`';

        foreach ($tables as $table)
        {
            $this->statement .= ', ';
            $this->statement .= '`' . $table . '`';
        }

        $this->statement .= ' ';
        $this->previousCommand = "FROM ";
        $this->expectedCommands = ["WHERE ", "WHERE NOT ", "ORDER BY ", "INNER JOIN ", "LEFT JOIN ", "RIGHT JOIN ", "FULL JOIN ", "LIMIT "];
        return $this;
    }

    /* -------------------- INSERT/UPDATE/DELETE -------------------- */

    public function insert(string $field1, string ... $otherFields): self {
        $this->checkCommandOrder(__FUNCTION__);

        $this->statement .= "INSERT ";
        $this->statement .= '(';

        $this->statement .= self::compoundName($field1);

        foreach ($otherFields as $field) {
            $this->statement .= ', ';
            $this->statement .= self::compoundName($field);
        }

        $this->statement .= ') ';

        $this->previousCommand = "INSERT ";
        $this->expectedCommands = ["INTO "];
        return $this;
    }

    public function into(string $table): self {
        $this->checkCommandOrder(__FUNCTION__);

        $after = substr($this->statement, strpos($this->statement, "INSERT ") + 7);
        $localQuery = "INTO " . self::compoundName($table) . ' ' . $after;
        $this->statement = substr_replace($this->statement, $localQuery, strpos($this->statement, "INSERT ") + 7);

        $this->previousCommand = "INTO ";
        $this->expectedCommands = ["VALUES "];
        return $this;
    }

    public function values(string $placeholder1, string ... $otherPlaceholders) {
        $this->checkCommandOrder(__FUNCTION__);

        if ($this->previousCommand == "VALUES ")
            $this->statement .= ', ';
        else
            $this->statement .= "VALUES ";

        $this->statement .= '(';

        $this->statement .= $placeholder1;

        foreach ($otherPlaceholders as $placeholder) {
            $this->statement .= ', ';
            $this->statement .= $placeholder;
        }
        $this->statement .= ') ';

        $this->previousCommand = "VALUES ";
        $this->expectedCommands = ["VALUES "];
        return $this;
    }

    public function update(string $firstTable, string ... $tables): self {
        $this->checkCommandOrder(__FUNCTION__);

        $this->statement .= "UPDATE ";
        $this->statement .= self::compoundName($firstTable);

        foreach ($tables as $table) {
            $this->statement .= ', ';
            $this->statement .= self::compoundName($table);
        }

        $this->statement .= ' ';

        $this->previousCommand = "UPDATE ";
        $this->expectedCommands = ["SET "];
        return $this;
    }

    public function set(string $field, string $valuePlaceholder): self {
        $this->checkCommandOrder(__FUNCTION__);

        if ($this->previousCommand != "SET ")
            $this->statement .= "SET ";
        else
            $this->statement .= ', ';
        $this->statement .= '`' . $field . '` ';
        $this->statement .= "= ";
        $this->statement .= $valuePlaceholder . ' ';

        $this->previousCommand = "SET ";
        $this->expectedCommands = ["SET ", "WHERE ", "WHERE NOT "];
        return $this;
    }

    public function delete(string $firstTable, string ... $tables): self {
        $this->checkCommandOrder(__FUNCTION__);

        $this->statement .= "DELETE ";

        $this->expectedCommands = ["FROM "];
        $this->from($firstTable, ... $tables);

        $this->previousCommand = "DELETE ";
        $this->expectedCommands = ["WHERE ", "WHERE NOT "];
        return $this;
    }

    /* -------------------- WHERE/WHERE NOT -------------------- */

    public function where(string $placeholder1, string $operator, string $placeholder2): self {
        $this->checkCommandOrder(__FUNCTION__);

        $this->statement .= "WHERE ";
        $this->statement .= $placeholder1 . ' ';
        $this->statement .= $operator . ' ';
        $this->statement .= $placeholder2 . ' ';
        $this->statement .= ' ';

        $this->previousCommand = "WHERE ";
        $this->expectedCommands = ["OR ", "AND ", "ORDER BY ", "LIMIT "];
        return $this;
    }

    public function whereNot(string $placeholder1, string $operator, string $placeholder2): self {
        $this->checkCommandOrder("WHERE NOT");

        $this->statement .= "WHERE NOT ";
        $this->statement .= $placeholder1 . ' ';
        $this->statement .= $operator . ' ';
        $this->statement .= $placeholder2 . ' ';
        $this->statement .= ' ';

        $this->previousCommand = "WHERE NOT ";
        $this->expectedCommands = ["OR ", "AND ", "ORDER BY ", "LIMIT "];
        return $this;
    }

    /* -------------------- OR/AND -------------------- */

    public function or (string $placeholder1, string $operator, string $placeholder2): self {
        $this->checkCommandOrder(__FUNCTION__);

        $this->statement .= "OR ";
        $this->statement .= $placeholder1 . ' ';
        $this->statement .= $operator . ' ';
        $this->statement .= $placeholder2 . ' ';
        $this->statement .= ' ';

        $this->previousCommand = "OR ";
        $this->expectedCommands = ["OR ", "AND ", "ORDER BY ", "LIMIT "];
        return $this;
    }

    public function and (string $placeholder1, string $operator, string $placeholder2): self {
        $this->checkCommandOrder(__FUNCTION__);

        $this->statement .= "AND ";
        $this->statement .= $placeholder1 . ' ';
        $this->statement .= $operator . ' ';
        $this->statement .= $placeholder2 . ' ';
        $this->statement .= ' ';

        $this->previousCommand = "AND ";
        $this->expectedCommands = ["OR ", "AND ", "ORDER BY ", "LIMIT "];
        return $this;
    }

    /* -------------------- ORDER BY -------------------- */

    public function orderby(string $firstField, string ... $otherFields): self {
        $this->checkCommandOrder("ORDER BY");

        $this->statement .= "ORDER BY ";
        $this->statement .= self::compoundName($firstField);

        foreach ($otherFields as $fields)
        {
            $this->statement .= ', ';
            $this->statement .= '`' . $fields . '`';
        }

        $this->previousCommand = "ORDER BY ";
        $this->expectedCommands = ["LIMIT "];
        return $this;
    }

    /* -------------------- LIMIT -------------------- */

    public function limit(string $limit): self {
        $this->checkCommandOrder(__FUNCTION__);

        $this->statement .= "LIMIT ";
        $this->statement .= $limit . ' ';

        $this->previousCommand = "LIMIT ";
        $this->expectedCommands = [];
        return $this;
    }

    /* -------------------- JOIN -------------------- */

    public function innerJoin(string $anotherTable): self {
        $this->checkCommandOrder("INNER JOIN");

        $this->statement .= "INNER JOIN ";
        $this->statement .= self::compoundName($anotherTable) . ' ';

        $this->previousCommand = "INNER JOIN ";
        $this->expectedCommands = ["ON ", "ORDER BY ", "LIMIT "];
        return $this;
    }

    public function leftJoin(string $anotherTable): self {
        $this->checkCommandOrder("LEFT JOIN");

        $this->statement .= "LEFT JOIN ";
        $this->statement .= self::compoundName($anotherTable) . ' ';

        $this->previousCommand = "LEFT JOIN ";
        $this->expectedCommands = ["ON ", "ORDER BY ", "LIMIT "];
        return $this;
    }

    public function rightJoin(string $anotherTable): self {
        $this->checkCommandOrder("RIGHT JOIN");

        $this->statement .= "RIGHT JOIN ";
        $this->statement .= self::compoundName($anotherTable) . ' ';

        $this->previousCommand = "RIGHT JOIN ";
        $this->expectedCommands = ["ON ", "ORDER BY ", "LIMIT "];
        return $this;
    }

    public function fullJoin(string $anotherTable): self {
        $this->checkCommandOrder("FULL JOIN");

        $this->statement .= "FULL JOIN ";
        $this->statement .= self::compoundName($anotherTable) . ' ';

        $this->previousCommand = "FULL JOIN ";
        $this->expectedCommands = ["ON ", "ORDER BY ", "LIMIT "];
        return $this;
    }

    public function on(string $placeholder1, string $condition, string $placeholder2): self {
        $this->checkCommandOrder(__FUNCTION__);

        if ($this->previousCommand == "ON ")
            $this->statement .= ', ';
        else
            $this->statement .= "ON ";
        $this->statement .= $placeholder1 . ' ';
        $this->statement .= $condition . ' ';
        $this->statement .= $placeholder2 . ' ';

        $this->previousCommand = "ON ";
        $this->expectedCommands = ["WHERE ", "WHERE NOT ", "ORDER BY ", "ON ", "INNER JOIN ", "LEFT JOIN ", "RIGHT JOIN ", "FULL JOIN ", "LIMIT "];
        return $this;
    }

    /* -------------------- DROP TABLE -------------------- */

    public function dropTable(string $table): self {
        $this->checkCommandOrder("DROP TABLE");

        $this->statement .= "DROP TABLE ";
        $this->statement .= self::compoundName($table);

        $this->previousCommand = "DROP TABLE ";
        $this->expectedCommands = [ ];
        return $this;
    }

    /* -------------------- EXECUTE -------------------- */

    public function execute(array $params = []) {
        $this->statement = trim($this->statement);

        $pdostatement = $this->pdo->prepare($this->statement);

        echo '<pre>' . $pdostatement->queryString . '</pre>' . '<br>';

        $command = explode(' ', trim($pdostatement->queryString));

        $pdostatement->execute($params);

        if ($command[0] == 'SELECT')
            return $pdostatement->fetchAll();

        return $pdostatement;
    }
}