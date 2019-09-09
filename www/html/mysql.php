<?php

// todo: test that all methods return proper error data
// todo: Try/Catch error handling

class mysqlDb
{
    public $conn = FALSE;
    public $error = FALSE;

    public function connect($database) {
        $server = "mysql";
        $user = "root";
        $password = 'hartolanMarkkinat'; // Test password

        $this->conn = mysqli_connect($server, $user, $password, $database);

        if ($this->conn->connect_error) {
            $this->error = "Database connection failed: " . $this->conn->connect_error;
            return FALSE;
        }
        else {
            return TRUE;
        }
    }

    public function set_latest_update() {
        $timestamp = time();

        // todo: data security / prepared statements
        $sql = "
        UPDATE latest_update
        SET timestamp = $timestamp
        WHERE id = 1;
        ";

//f        echo $sql;

        if ($this->conn->query($sql)) {
            // todo: log
            return TRUE;
        }
        else {
            $this->error = "Error updating latest_update time: " . $this->conn->error;
            return FALSE;
        }
    }

    public function get_latest_update() {
        $timestamp = time();

        // todo: data security / prepared statements
        $sql = "
        SELECT timestamp FROM latest_update 
        WHERE id = 1;
        ";

        $result = $this->conn->query($sql);

        if ($result) {
            // todo: log
            $arr = mysqli_fetch_assoc($result);
            return $arr['timestamp'];
        }
        else {
            $this->error = "Error getting latest_update time: " . $this->conn->error;
            return FALSE;
        }
    }

    public function push($id, $hash = "", $status = 0) {
        $IDExists = $this->doesIDExist($id); 
        if (TRUE === $IDExists) {
            // UPDATE
            return $this->update($id, $hash, $status);
        }
        elseif (NULL === $IDExists) {
            // INSERT
            return $this->insert($id, $hash, $status);
        }
        else {
            return FALSE;
        }
    }

    public function doesIDExist($id) {
        $sql = "
        SELECT id FROM observations 
        WHERE id = $id;
        ";

        $result = $this->conn->query($sql);

        if ($result) {
            $rowCount = mysqli_num_rows($result);
            if ($rowCount > 0) {
                return TRUE;
            }
            else {
                return NULL;
            }
        }
        else {
            $this->error = "Error finding id: " . $this->conn->error;
            return FALSE;
        }
    }

    public function doesHashExist($id, $hash) {
        $sql = "
        SELECT id FROM observations 
        WHERE
            id = $id 
            AND
            hash = $hash;
        ";

        $result = $this->conn->query($sql);

        if ($result) {
            $rowCount = mysqli_num_rows($result);
            if ($rowCount > 0) {
                return TRUE;
            }
            else {
                return NULL;
            }
        }
        else {
            $this->error = "Error finding hash: " . $this->conn->error;
            return FALSE;
        }
    }

    public function update($id, $hash = "", $status = "") {
        $timestamp = time();

        // todo: data security / prepared statements
        $sql = "
        UPDATE observations
        SET
            hash = '$hash',
            timestamp = $timestamp,
            status = $status
        WHERE id = $id;
        ";

        if ($this->conn->query($sql)) {
            // todo: log
            return TRUE;
        }
        else {
            $this->error = "Error updating record: " . $this->conn->error;
            return FALSE;
        }
    }

    public function insert($id, $hash = "", $status = "") {
        $timestamp = time();

        // todo: data security / prepared statements
        $sql = "
        INSERT INTO observations 
            (id, hash, timestamp, status)
        VALUES
            ($id, '$hash', $timestamp, $status);
        ";

//f        echo $sql;

        if ($this->conn->query($sql)) {
            // todo: log
            return TRUE;
        }
        else {
            $this->error = "Error inserting record: " . $this->conn->error;
            return FALSE;
        }
    }

    public function close() {
        mysqli_close($this->conn);
    }
    
}

