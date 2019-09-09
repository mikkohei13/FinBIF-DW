<?php

class mysqlDb
{
    public $conn = FALSE;

    public function connect($database) {
        $server = "mysql";
        $user = "root";
        $password = 'hartolanMarkkinat'; // Test password

        $this->conn = mysqli_connect($server, $user, $password, $database);

        if ($this->conn->connect_error) {
            return "Database connection failed: " . $this->conn->connect_error;
        }
        else {
            return TRUE;
        }
    }

    public function doesIDExist($id) {
        $sql = "
        SELECT id FROM observations 
        WHERE id = $id;
        ";

        echo $sql;

        $result = $this->conn->query($sql);
        echo "Rows: " . mysqli_num_rows($result);

    }

    public function insert($id, $hash, $timestamp) {
        // todo: data security / prepared statements
        $sql = "
        INSERT INTO observations 
        (id, hash, timestamp)
        VALUES
        ($id, $hash, $timestamp);
        ";

        if ($this->conn->query($sql)) {
            // todo: log
            return TRUE;
        } else {
            return "Error inserting record: " . $this->conn->error;
        }
    }

    public function close() {
        mysqli_close($this->conn);
    }
    
}

