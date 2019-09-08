<?php

class mysqlDb
{
    public $conn = FALSE;

    public function connect() {
        $server = "mysql";
        $database = "test_db";
        $user = "root";
        $password = 'hartolanMarkkinat'; // Test password

        $this->conn = mysqli_connect($server, $user, $password, $database);

        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }
    }

    public function insert() {
        $sql = "
        INSERT INTO test_table 
        (id, hash, timestamp)
        VALUES
        (1, 'hash', 2);
        ";

        if ($this->conn->query($sql)) {
            echo "New record created successfully";
        } else {
            echo "Error: " . $this->conn->error;
        }
    }

    public function close() {
        mysqli_close($this->conn);
    }
    
}

