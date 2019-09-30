<?php

// todo: test that all methods return proper error data
// todo: Try/Catch error handling

class mysqlDb
{
    public $conn = FALSE;
    public $error = FALSE;

    public function __construct($database) {
        return $this->connect($database);
    }

/*
    // Wrapper functions which allows the object to work without a logger, if not set
    private function log2($type, $message, $filename) {
        if ($this->logger) {
            $this->logger($type, $message, $filename);
            return TRUE;
        }
        return FALSE; // If no logger set
    }
*/

    // Note: this is temporarily copied to mysql class.
    private function log2($type, $message, $filename = "log/log.txt") {

        // https://stackoverflow.com/questions/1252529/get-code-line-and-file-thats-executing-the-current-function-in-php
        $bt = debug_backtrace();
        $caller = array_shift($bt);
        // echo $caller['file'];
        // echo $caller['line'];

        $message = date("Y-m-d H:i:s") . "\t" . $type . "\t" . $caller['file'] . "\t" . $caller['line'] . "\t" . $message . "\n";

        $bytes = file_put_contents($filename, $message, FILE_APPEND);

        if ("ERROR" == $type) {
            exit("Exited through logger");
        }
        
        return $bytes;
    }

    public function connect($database) {
        $server = "mysql";
        $user = "root";
        $password = 'hartolanMarkkinat'; // Test password

        $this->conn = mysqli_connect($server, $user, $password, $database);

        if ($this->conn->connect_error) {
            $this->error = "Database connection failed: " . $this->conn->connect_error;
            $this->log2("ERROR", "Database connection failed: " . $database, "log/inat-obs-log.log"); 
            return FALSE;
        }
        else {
            $this->log2("NOTICE", "Connected to database: " . $database, "log/inat-obs-log.log"); 
            return TRUE;
        }
    }

    public function setLatestUpdate($idAbove, $updateStartedTime) {

        // todo: this expects that there is entry with id = 1, and silently fails if there is not.
        $sql = "
        UPDATE latest_update
        SET latest_update = '$updateStartedTime',
            observation_id = $idAbove
        WHERE id = 1;
        ";

//        echo $sql;

        if ($this->conn->query($sql)) {
            $this->log2("NOTICE", "Logged to database: latest_update $updateStartedTime, observation_id = $idAbove", "log/inat-obs-log.log");
            return TRUE;
        }
        else {
            $this->error = "Error updating latest_update: " . $this->conn->error;
            $this->log2("D", $this->error, "log/inat-obs-log.log");
            return FALSE;
        }       
    }

    public function getLatestUpdate() {

        // todo: data security / prepared statements
        $sql = "
        SELECT latest_update,
                observation_id
        FROM latest_update 
        WHERE id = 1;
        ";

        $result = $this->conn->query($sql);

        if ($result) {
            $arr = mysqli_fetch_assoc($result);
            if (empty($arr['latest_update'])) {
                $this->log2("ERROR", "Error gerring latest update time: time is empty", "log/inat-obs-log.log");
            }
            $this->log2("NOTICE", "Got latest update from db: latest_update " . $arr['latest_update'] . ", observation_id: " . $arr['observation_id'], "log/inat-obs-log.log");
            return $arr['latest_update'];
        }
        else {
            $this->log2("ERROR", "Error getting latest update time: " . $this->error, "log/inat-obs-log.log");
        }
    }

    public function push($id, $hash = "", $status = 0) {
        /*
        Statuses:
         0 = default
         1 = monthly update done, no need to delete
         -1 = deleted
        */
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

    private function doesIDExist($id) {
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
        $this->log2("D", "Searching for id $id, hash $hash", "log/inat-obs-log.log");

        $sql = "
        SELECT id FROM observations 
        WHERE
            id = $id 
            AND
            hash = '$hash';
        ";

        $result = $this->conn->query($sql);

        if ($result) {
            $rowCount = mysqli_num_rows($result);
            if ($rowCount > 0) {
//                $this->log2("D", "FOUND", "log/inat-obs-log.log");
                return TRUE;
            }
            else {
//                $this->log2("D", "NOT FOUND", "log/inat-obs-log.log");
                return FALSE;
            }
        }
        else {
//            $this->log2("D", "ERROR", "log/inat-obs-log.log");
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

    public function updateStatus($id, $status) {
        $timestamp = time();

        // todo: data security / prepared statements
        $sql = "
        UPDATE observations
        SET
            timestamp = $timestamp,
            status = $status
        WHERE id = $id;
        ";

        if ($this->conn->query($sql)) {
            $this->log2("NOTICE", "Trashed $id from database", "log/inat-obs-log.log"); 
            return TRUE;
        }
        else {
            $this->error = "Error updating record: " . $this->conn->error;
            $this->log2("ERROR", "Trashing $id from database failed", "log/inat-obs-log.log"); 
            return FALSE;
        }
    }

    public function set0to2() {
        $timestamp = time();

        // todo: data security / prepared statements
        $sql = "
        UPDATE observations
        SET status = 2
        WHERE status = 0;
        ";

        if ($this->conn->query($sql)) {
            $this->log2("NOTICE", "Set 0 to 2 in database", "log/inat-obs-log.log"); 
            return TRUE;
        }
        else {
            $this->error = "Error updating record: " . $this->conn->error;
            $this->log2("NOTICE", "Setting 0 to 2 in failed", "log/inat-obs-log.log"); 
            return FALSE;
        }
    }

    public function set1to0() {
        $timestamp = time();

        // todo: data security / prepared statements
        $sql = "
        UPDATE observations
        SET status = 0
        WHERE status = 1;
        ";

        if ($this->conn->query($sql)) {
            $this->log2("NOTICE", "Set 1 to 0 in database", "log/inat-obs-log.log"); 
            return TRUE;
        }
        else {
            $this->error = "Error updating record: " . $this->conn->error;
            $this->log2("NOTICE", "Setting 1 to 0 in failed", "log/inat-obs-log.log"); 
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

