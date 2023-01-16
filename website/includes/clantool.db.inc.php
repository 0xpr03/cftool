<?php
/*
 * !
 * Copyright 2018-2020 Aron Heinecke
 * aron.heinecke@t-online.de
 * All rights reserved.
 * Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:
 * 1. Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
 * 3. Neither the name of the copyright holder nor the names of its contributors may be used to endorse or promote products derived from this software without specific prior written permission.
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */
 
// This file relies also on the defines in clantool2.php !
define('DB_TS3_DATA','mDSStats2_6243');
define('DB_TS3_NAMES','ts_names');
define('DB_TS3_STATS','ModStats_6243');
// used to trick seconds -> datetime to display values as time
define('PLOTLY_START_DATE','1970-01-01 ');
define('ER_DUP_ENTRY',23000);
 
class dbException extends Exception {
    public function __construct($message, $code = 0, Exception $previous = null) {
        parent::__construct ( $message, $code, $previous );
    }
    public function __toString() {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }
    public function customFunction() {
        echo "Database exception\n";
    }
}
class clanDB extends dbException {
    private $db;
    private $name_default;
    public function __construct() {
        require 'includes/config.clantool.db.inc.php';
        $_access = getTS3Conf();
        date_default_timezone_set('Europe/Berlin');
        $this->db = new mysqli ( $_access ["host"], $_access ["user"], $_access ["pass"], $_access ["db"] );
        $this->name_default = NAME_MISSING_DEFAULT;
    }
    /**
     * Escape variable for DB statements
     * @param $data taken as pointer
     */
    private function escapeData(&$data) {
        $data = $this->db->real_escape_string ( $data );
    }
    
    /**
     * Copy ts relations from one account to another
     * @param $oldID old account ID
     * @param $newID new account ID
     * @throws dbException
     */
    public function copyTSRelation($oldID, $newID) {
        if($query = $this->db->prepare (
            'INSERT INTO `ts_relation` (`id`,`client_id`)
            (SELECT ?,tsr2.`client_id` FROM `ts_relation` tsr2
                WHERE tsr2.`id` = ?)
            ON DUPLICATE KEY UPDATE `ts_relation`.`client_id` = `ts_relation`.`client_id`')) {
            $query->bind_param('ii',$newID,$oldID);
            if(!$query->execute()){
                throw new dbException($this->db->error);
            }
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    function insertLog($message) {
        if($query = $this->db->prepare (
            'INSERT INTO `log` (`msg`,`date`) VALUES(?,CURRENT_TIMESTAMP)')) {
            $query->bind_param('s',$message);
            if(!$query->execute()){
                throw new dbException($this->db->error);
            }
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Load log entries in range
     * @param from
     * @param to
     * @return list of (date,msg)
     * @throws dbException
     */
    public function loadLog($from,$to) {
        $from .= '%';
        $to .= '%';
        if ($query = $this->db->prepare ( 'SELECT `date`,`msg` FROM `log`
        WHERE `date` >= ? AND `date` <= DATE_ADD(?, INTERVAL 1 DAY) ORDER BY `date` DESC')) {
            $query->bind_param('ss',$from,$to);
            if(!$query->execute()){
                throw new dbException($this->db->error);
            }
            $result = $query->get_result ();
            
            if (! $result) {
                throw new dbException ( $this->db->error, 500 );
            }
            
            $resultset = array ();
            while ( $row = $result->fetch_assoc () ) {
                $resultset[] = array(
                    'date' => $row['date'],
                    'msg' => $row['msg'],
                );
            }
            $result->close();
            
            return $resultset;
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Set settings value
     * @param key key
     * @param value value
     * @throws dbException
     */
    public function setSetting($key,$value) {
        if($query = $this->db->prepare (
            'INSERT INTO `settings` (`key`,`value`) VALUES(?,?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)')) {
            $query->bind_param('ss',$key,$value);
            if(!$query->execute()){
                throw new dbException($this->db->error);
            }
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Get settings value
     * @param key
     * @return value
     * @throws dbException
     */
    public function getSetting($key) {
        if ($query = $this->db->prepare ( 'SELECT `value` FROM `settings` WHERE `key` = ?' )) {
            $query->bind_param('s',$key);
            if(!$query->execute()){
                throw new dbException($this->db->error);
            }
            $result = $query->get_result ();
            
            if (! $result) {
                throw new dbException ( $this->db->error, 500 );
            }
            
            $resultvalue = null;
            if ($result->num_rows != 0) {
                if ( $row = $result->fetch_assoc () ) {
                    $resultvalue = $row['value'];
                }
            }
            $result->close();
            
            return $resultvalue;
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Get global notes for date range
     * @param string $date1
     * @param string $date2
     * @throws dbException
     */
    public function getGlobalNoteForRange($date1,$date2) {
        if ($query = $this->db->prepare ( 'SELECT `from`,`to`,`message` FROM `global_note` g
        WHERE `from` < ? AND `to` >= ?')) {
            $query->bind_param('ss',$date2,$date1);
            if(!$query->execute()){
                throw new dbException($this->db->error);
            }
            $result = $query->get_result ();
            
            if (! $result) {
                throw new dbException ( $this->db->error, 500 );
            }
            
            $resultset = array ();
            while ( $row = $result->fetch_assoc () ) {
                $resultset[] = array(
                    'from' => $row['from'],
                    'to' => $row['to'],
                    'message' => $row['message']
                );
            }
            $result->close();
            
            return $resultset;
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Get weekly difference table
     * @param string $date1
     * @param string $date2
     * @throws dbException
     */
    public function getDifferenceSum($date1,$date2) {
        $this->escapeData($date1);
        $this->escapeData($date2);
        if ($query = $this->db->prepare ( 'select ma.name as `vname`,
        ma.vip,ma.`diff_comment` as `comment`,
        afk.`from` IS NOT NULL as `is_afk`,
        caution.`from` IS NOT NULL as `is_caution`,
        trial.`from` IS NOT NULL as `is_trial`,
        names.name, m1.id, 
        (m1.exp-m2.exp) AS `EXP-Done`, 
        (`m1`.`cp`-`m2`.`cp`) AS `CP-Done`, DATEDIFF(m1.date,m2.date) AS `days`,
        cpdiff.`cp_by_exp`
        FROM member as m2 
        RIGHT JOIN member AS m1 ON m1.id = m2.id 
            AND m1.date LIKE "'.$date2.'%" 
        JOIN (SELECT n1.id,n1.`name` FROM 
                (SELECT id,MAX(updated) as maxdate 
                FROM member_names 
                GROUP BY id) as nEndDate 
                JOIN member_names AS n1 ON ( 
                    n1.id = nEndDate.id 
                    AND n1.updated = nEndDate.maxdate 
                ) 
        ) names ON m2.id = names.id 
        LEFT JOIN (SELECT id, SUM(CASE WHEN cpdiff2.`cp-exp` > '.MAX_CP_DAY.' THEN '.MAX_CP_DAY.' ELSE cpdiff2.`cp-exp` END) AS `cp_by_exp`
                FROM
                    (SELECT t1.id,(t1.exp - (SELECT t2.exp FROM member t2
                        WHERE t2.date < date_sub(t1.date, interval '.MAX_CP_DAY.' hour) AND t2.id=t1.id 
                        ORDER BY t2.date DESC LIMIT 1) ) DIV '.EXP_TO_CP.' AS `cp-exp`
                    FROM member t1 
                    WHERE t1.date between DATE_ADD("'.$date1.'%", INTERVAL 1 DAY) and DATE_ADD("'.$date2.'%", INTERVAL 1 DAY)
                ) cpdiff2
                GROUP BY id
        ) cpdiff ON cpdiff.id = m2.id
        LEFT JOIN `member_addition` AS ma ON m2.id = ma.id
        LEFT JOIN `afk` AS afk ON m2.id = afk.id AND /* from has date -1 day because of overlapping */
            afk.`from` < "'.$date2.'" AND afk.`to` >= "'.$date1.'"
        LEFT JOIN `caution` AS caution ON m2.id = caution.id AND 
            caution.`from` < "'.$date2.'" AND caution.`to` >= "'.$date1.'"
        LEFT JOIN `member_trial` trial ON 
            trial.id = m2.id AND (
                (trial.`to` IS NULL)
                OR 
                (trial.`from` < "'.$date2.'" AND trial.`to` >= "'.$date1.'")
            )
        WHERE m2.date LIKE "'.$date1.'%" OR ( m2.date >  "'.$date1.'%" AND m2.date < "'.$date2.'%" ) 
        GROUP BY `id` ORDER BY `cp_by_exp`,`CP-Done`, `EXP-Done`' )) { // Y-m-d G:i:s Y-m-d h:i:s
            $query->execute ();
            $result = $query->get_result ();
            
            if (! $result) {
                throw new dbException ( $this->db->error, 500 );
            }
            
            if ($result->num_rows == 0) {
                $resultset = null;
            } else {
                $resultset = array ();
                while ( $row = $result->fetch_assoc () ) {
                    $resultset [] = array(
                        'caution' => $row['is_caution'],
                        'afk' => $row['is_afk'],
                        'trial' => $row['is_trial'],
                        'vname' => $row['vname'],
                        'vip' => $row['vip'],
                        'name' => $row['name'],
                        'id' => $row['id'],
                        'cp' => $row['CP-Done'],
                        'exp' => $row['EXP-Done'],
                        'days' => $row['days'],
                        'cp_by_exp' => $row['cp_by_exp'],
                        'comment' => $row['comment']
                    );
                }
            }
            
            $result->close ();
            
            return $resultset;
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Get difference table
     * @param string $date1
     * @param string $date2
     * @throws dbException
     */
    public function getDifference($date1, $date2) {
        $this->escapeData($date1);
        $this->escapeData($date2);
        if ($query = $this->db->prepare ( 'select ma.name as `vname`, ma.vip,
        afk.`from` IS NOT NULL as `is_afk`,
        trial.`from` IS NOT NULL as `is_trial`,
        names.name, m1.id, m2.date as `Date1`, m1.date as  `Date2`,
        m2.exp as `Exp1`,m1.exp as `Exp2`, m2.cp as `CP1`, m1.cp as `CP2`, 
        (m1.exp - m2.exp) AS `EXP-Done`, 
        (`m1`.`cp`-`m2`.`cp`) AS `CP-Done`, DATEDIFF(m1.date,m2.date) AS `days`,
        cpdiff.`cp_by_exp`
        FROM member as m2 
        RIGHT JOIN member AS m1 ON m1.id = m2.id 
            AND m1.date LIKE "'.$date2.'%" 
        JOIN (SELECT n1.id,n1.`name` FROM 
                (SELECT id,MAX(updated) as maxdate 
                FROM member_names 
                GROUP BY id) as nEndDate 
                JOIN member_names AS n1 ON ( 
                    n1.id = nEndDate.id 
                    AND n1.updated = nEndDate.maxdate 
                ) 
        ) names ON m2.id = names.id 
        LEFT JOIN (SELECT id, SUM(CASE WHEN cpdiff2.`cp-exp` > '.MAX_CP_DAY.' THEN '.MAX_CP_DAY.' ELSE cpdiff2.`cp-exp` END) AS `cp_by_exp`
                FROM
                    (SELECT t1.id,(t1.exp - (SELECT t2.exp FROM member t2
                        WHERE t2.date < date_sub(t1.date, interval '.MAX_CP_DAY.' hour) AND t2.id=t1.id 
                        ORDER BY t2.date DESC LIMIT 1) ) DIV '.EXP_TO_CP.' AS `cp-exp`
                    FROM member t1 
                    WHERE t1.date between DATE_ADD("'.$date1.'%", INTERVAL 1 DAY) and DATE_ADD("'.$date2.'%", INTERVAL 1 DAY)
                ) cpdiff2
                GROUP BY id
        ) cpdiff ON cpdiff.id = m2.id
        LEFT JOIN `member_addition` AS ma ON m2.id = ma.id
        LEFT JOIN `afk` AS afk ON m2.id = afk.id AND
            afk.`from` < "'.$date2.'" AND afk.`to` >= "'.$date1.'"
        LEFT JOIN `member_trial` trial ON 
            trial.id = m2.id AND (
                (trial.`to` IS NULL)
                OR 
                (trial.`from` < "'.$date2.'" AND trial.`to` >= "'.$date1.'")
            )
        WHERE m2.date LIKE "'.$date1.'%" OR ( m2.date >  "'.$date1.'%" AND m2.date < "'.$date2.'%" ) 
        GROUP BY `id` ORDER BY `cp_by_exp`,`CP-Done`, `EXP-Done`' )) { // Y-m-d G:i:s Y-m-d h:i:s
            $query->execute ();
            $result = $query->get_result ();
            
            if (! $result) {
                throw new dbException ( $this->db->error, 500 );
            }
            
            if ($result->num_rows == 0) {
                $resultset = null;
            } else {
                $resultset = array ();
                while ( $row = $result->fetch_assoc () ) {
                    $resultset [] = array(
                                'afk' => $row['is_afk'],
                                'trial' => $row['is_trial'],
                                'vname' => $row['vname'],
                                'vip' => $row['vip'],
                                'name' => $row['name'],
                                'date1' => $row['Date1'],
                                'date2' => $row['Date2'],
                                'exp1' => $row['Exp1'],
                                'exp2' => $row['Exp2'],
                                'cp1' => $row['CP1'],
                                'cp2' => $row['CP2'],
                                'id' => $row['id'],
                                'cp' => $row['CP-Done'],
                                'exp' => $row['EXP-Done'],
                                'days' => $row['days'],
                                'cp_by_exp' => $row['cp_by_exp']
                            );
                }
            }
            
            $result->close ();
            
            return $resultset;
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Start transaction
     */
    public function startTransaction() {
        $this->db->autocommit(FALSE);
    }
    
    /**
     * End transaction & commit everything, reactivates autocommit
     */
    public function endTransaction() {
        $this->db->commit();
        $this->db->autocommit(TRUE);
    }
    
    /**
     * Get member caution entries
     * @param id Member ID
     * @return [{from,to,cause}]
     * @throws dbException
     */
    public function getCautions($id) {
        if ($query = $this->db->prepare ( 'SELECT `from`,`to`,`cause` FROM `caution`
        WHERE `id` = ? ORDER BY `from`')) {
            $query->bind_param('i',$id);
            if(!$query->execute()){
                throw new dbException($this->db->error);
            }
            $result = $query->get_result ();
            
            if (! $result) {
                throw new dbException ( $this->db->error, 500 );
            }
            
            $resultset = array ();
            while ( $row = $result->fetch_assoc () ) {
                $resultset[] = array(
                    'from' => $row['from'],
                    'to' => $row['to'],
                    'cause' => $row['cause']
                );
            }
            $result->close();
            
            return $resultset;
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Insert member caution entry
     * @param id account ID
     * @param from from date
     * @param to to date
     * @param cause cause of caution entry
     * @throws dbException
     */
    public function insertCaution($id,$from,$to,$cause) {
        if($query = $this->db->prepare (
            'INSERT INTO `caution` (`id`,`from`,`to`,`cause`,`added`) VALUES(?,?,?,?, CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE `cause` = VALUES(`cause`), `to` = VALUES(`to`)')) {
            $query->bind_param('isss',$id,$from,$to,$cause);
            if(!$query->execute()){
                throw new dbException($this->db->error);
            } else {
                $this->insertLog('Add Caution acc '.$id.' '.$from.'-'.$to.' '.$cause);
            }
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Delete member caution entry
     * @param id account ID
     * @param from from date
     * @param to to date
     * @throws dbException
     */
    public function deleteCaution($id,$from) {
        if($query = $this->db->prepare (
            'DELETE FROM `caution` WHERE `id` = ? AND `from` = ?')) {
            $query->bind_param('is',$id,$from);
            if(!$query->execute()){
                throw new dbException($this->db->error);
            } else {
                $this->insertLog('Delete Caution id '.$id.' from '.$from);
            }
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Get active/future afks for specified date
     * Shows only afks of members with active membership
     * @param date date for affected afks
     * @param current true to get currently active, false to get future AFKs
     * @return [{name,account name,id,from,to,cause}]
     * @throws dbException
     */
    public function getActiveFutureAFK($date,$current) {
        $whereClause = $current ? '(afk.`from` <= ? AND afk.`to` >= ?)' : 'afk.`from` > ?';
        
        if ($query = $this->db->prepare ( 'SELECT IFNULL(ad.name,?) as vname,IFNULL(names.name,?) as `name`,afk.`id`,afk.`from`,afk.`to`,`cause`
        FROM `afk` afk
        LEFT JOIN `member_names` names ON afk.id = names.id AND
            `names`.updated = (SELECT MAX(n2.updated) 
                FROM `member_names` n2 
                WHERE n2.id = afk.id
            )
        LEFT JOIN `member_addition` ad ON afk.id = ad.id
        JOIN `membership` ms ON ms.id = afk.id AND ms.to IS NULL
        WHERE '.$whereClause.'
        GROUP BY `id`
        ORDER BY afk.`from`,afk.`to`')) {
            if ($current) {
                $query->bind_param('ssss',$this->name_default,$this->name_default,$date,$date);
            } else {
                $query->bind_param('sss',$this->name_default,$this->name_default,$date);
            }
            if(!$query->execute()){
                throw new dbException($this->db->error);
            }
            $result = $query->get_result ();
            
            if (! $result) {
                throw new dbException ( $this->db->error, 500 );
            }
            
            $resultset = array ();
            while ( $row = $result->fetch_assoc () ) {
                $resultset[] = array(
                    'vname' => $row['vname'],
                    'name' => $row['name'],
                    'id' => $row['id'],
                    'from' => $row['from'],
                    'to' => $row['to'],
                    'cause' => $row['cause']
                );
            }
            $result->close();
            
            return $resultset;
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Get member afks
     * @param id Member ID
     * @return [{from,to,cause}]
     * @throws dbException
     */
    public function getAFKs($id) {
        if ($query = $this->db->prepare ( 'SELECT `from`,`to`,`cause` FROM `afk`
        WHERE `id` = ? ORDER BY `from`')) {
            $query->bind_param('i',$id);
            if(!$query->execute()){
                throw new dbException($this->db->error);
            }
            $result = $query->get_result ();
            
            if (! $result) {
                throw new dbException ( $this->db->error, 500 );
            }
            
            $resultset = array ();
            while ( $row = $result->fetch_assoc () ) {
                $resultset[] = array(
                    'from' => $row['from'],
                    'to' => $row['to'],
                    'cause' => $row['cause']
                );
            }
            $result->close();
            
            return $resultset;
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Edit member afk entry
     * @param $id
     * @param $from
     * @param $to
     * @param $fromNew
     * @param $toNew
     * @param $cause
     * @throws dbException
     */
    public function editAFK($id,$from,$to,$fromNew,$toNew,$cause) {
        if($query = $this->db->prepare(
            'UPDATE `afk` SET `from` = ?, `to` = ?, `cause` = ? WHERE `id` = ? AND `from` = ? AND `to` = ?')) {
            $query->bind_param('sssiss',$fromNew,$toNew,$cause,$id,$from,$to);
            if(!$query->execute()) {
                throw new dbException($this->db->error);
            } else {
                $this->insertLog('Edit AFK account '.$id.' '.$from.'->'.$fromNew.'-'.$to.'->'.$toNew.' '.$cause);
            }
        } else {
            throw new dbException($this->db->error);
        }
    }
    
    /**
     * Insert member afk entry
     * @param id account ID
     * @param from from date
     * @param to to date
     * @param cause cause of afk
     * @throws dbException
     */
    public function insertAFK($id,$from,$to,$cause) {
        if($query = $this->db->prepare (
            'INSERT INTO `afk` (`id`,`from`,`to`,`cause`,`added`) VALUES(?,?,?,?, CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE `cause` = VALUES(`cause`)')) {
            $query->bind_param('isss',$id,$from,$to,$cause);
            if(!$query->execute()){
                throw new dbException($this->db->error);
            } else {
                $this->insertLog('Insert AFK account '.$id.' '.$from.'-'.$to.' '.$cause);
            }
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Delete member afk entry
     * @param id account ID
     * @param from from date
     * @param to to date
     * @throws dbException
     */
    public function deleteAFK($id,$from,$to) {
        if($query = $this->db->prepare (
            'DELETE FROM `afk` WHERE `id` = ? AND `from` = ? AND `to` = ?')) {
            $query->bind_param('iss',$id,$from,$to);
            if(!$query->execute()){
                throw new dbException($this->db->error);
            } else {
                $this->insertLog('Delete AFK acc '.$id.' '.$from.'-'.$to);
            }
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Update member diff_comment
     * @param id account ID
     * @param comment new diff_comment value, set to null if == ''
     * @throws dbException
     */
    public function updateDiffComment($id,$comment) {
        if($comment == ''){
            $comment = null;
        }
        if($query = $this->db->prepare (
            'UPDATE `member_addition` SET `diff_comment` = ? WHERE `id` = ?')) {
            $query->bind_param('si',$comment,$id);
            if(!$query->execute()){
                throw new dbException($this->db->error);
            }
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Add a ts ID to the ignore table for unknown-id checks
     * @param ts_client_id ts client ID to link
     * @throws dbException
     */
    public function ignoreTS3Identity($ts_client_id) {
        if($query = $this->db->prepare (
            'INSERT INTO `ignore_ts_ids` (`client_id`) VALUES(?) ON DUPLICATE KEY UPDATE `client_id` = `client_id`')) {
            $query->bind_param('i',$ts_client_id);
            if(!$query->execute()){
                throw new dbException($this->db->error);
            } else {
                $this->insertLog('Add ignore_ts_ids client-id '.$ts_client_id);
            }
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Check if any unknown ts ids are existing
     * @throws dbException
     */
    public function hasUnknownTs3IDs() {
        if($query = $this->db->prepare (
            'SELECT EXISTS(SELECT 1 FROM `unknown_ts_unignored`) AS `has_ids`')) {
            if(!$query->execute()){
                throw new dbException($this->db->error);
            }
            
            $result = $query->get_result();
            if ($result->num_rows == 0) {
                throw new dbException ( 'Empty data set' );
            }
            return $nr = $result->fetch_assoc()['has_ids'] == 1;
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Insert member ts ID relation & remove from unknown ts list  
     * This function should be used with a transaction!
     * @param id account ID
     * @param ts_client_id ts client ID to link
     * @throws dbException
     */
    public function insertTSRelation($id,$ts_client_id) {
        $this->_insertTSRelation($id,$ts_client_id);
        $this->removeTSUnknownID($ts_client_id);
    }
    
    /**
     * Remove TS ID from unknown_ts_ids
     * @param ts_client_id ts client ID to link
     */
    private function removeTSUnknownID($ts_client_id) {
        if($query = $this->db->prepare (
            'DELETE FROM `unknown_ts_ids` WHERE `client_id` = ?')) {
            $query->bind_param('i',$ts_client_id);
            if(!$query->execute()){
                throw new dbException($this->db->error);
            }
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Insert ts-id<->account id relation
     * @param id account ID
     * @param ts_client_id ts client ID to link
     */
    private function _insertTSRelation($id,$ts_client_id) {
        if($query = $this->db->prepare (
            'INSERT INTO `ts_relation` (`id`,`client_id`) VALUES(?,?) ON DUPLICATE KEY UPDATE `client_id` = `client_id`')) {
            $query->bind_param('ii',$id,$ts_client_id);
            if(!$query->execute()){
                throw new dbException($this->db->error);
            }
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Remove member ts ID relation
     * @param id account ID
     * @param ts_client_id ts client ID to link
     * @throws dbException
     */
    public function removeTSRelation($id,$ts_client_id) {
        if($query = $this->db->prepare (
            'DELETE FROM `ts_relation` WHERE `id` = ? AND `client_id` = ?')) {
            $query->bind_param('ii',$id,$ts_client_id);
            if(!$query->execute()){
                throw new dbException($this->db->error);
            } else {
                $this->insertLog('Delete TS-Relation acc '.$id.' ts-client '.$ts_client_id);
            }
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Get member ts relations
     * @param id Member IDs
     * @return [{cID,name}]
     * @throws dbException
     */
    public function getMemberTSRelations($id) {
        if ($query = $this->db->prepare ( 'SELECT rel.`client_id`,
        IFNULL(names.`name`,?) as name FROM `ts_relation` rel 
        LEFT JOIN `'.DB_TS3_NAMES.'` names ON rel.`client_id` = names.`client_id` 
        WHERE `id` = ?')) {
            $query->bind_param('si',$this->name_default,$id);
            if(!$query->execute()){
                throw new dbException($this->db->error);
            }
            $result = $query->get_result ();
            
            if (! $result) {
                throw new dbException ( $this->db->error, 500 );
            }
            
            $resultset = array ();
            while ( $row = $result->fetch_assoc () ) {
                $resultset[] = array(
                    'cID' => $row['client_id'],
                    'name' => $row['name'] . ' ('.$row['client_id'].')'
                );
            }
            $result->close();
            
            return $resultset;
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Get second account relations
     * @param id ID of account
     * @return array of id,name/null
     * @throws dbException
     */
    public function getSecondAccounts($id){
        if ($query = $this->db->prepare ( 'SELECT sa.`id_sec`,`name`
        FROM `second_acc` sa 
        LEFT JOIN `member_names` names ON sa.`id_sec` = names.`id` 
        WHERE sa.`id` = ?
        GROUP BY `id_sec`')) {
            $query->bind_param('i',$id);
            if(!$query->execute()){
                throw new dbException($this->db->error);
            }
            $result = $query->get_result ();
            
            if (! $result) {
                throw new dbException ( $this->db->error, 500 );
            }
            
            $resultset = array ();
            while ( $row = $result->fetch_assoc () ) {
                $resultset[] = array(
                    'id_sec' => $row['id_sec'],
                    'name' => $row['name']
                );
            }
            $result->close();
            
            return $resultset;
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Add second account relation
     * @param id ID of account
     * @param secID ID of second account
     * @throws dbException
     */
    public function setSecondAccount($id,$secID){
        if($query = $this->db->prepare (
            'INSERT INTO `second_acc` (`id`,`id_sec`) VALUES(?,?) ON DUPLICATE KEY UPDATE `id_sec` = `id_sec`')) {
            $query->bind_param('ii',$id,$secID);
            if(!$query->execute()){
                throw new dbException($this->db->error);
            } else {
                $this->insertLog('Add SecondAccount id1 '.$id.' id2'.$secID);
            }
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Remove second account relation
     * @param id ID of account
     * @param secID ID of second account
     * @throws dbException
     */
    public function removeSecondAccount($id,$secID){
        if($query = $this->db->prepare (
            'DELETE FROM `second_acc` WHERE `id` = ? AND `id_sec`= ?')) {
            $query->bind_param('ii',$id, $secID);
            if(!$query->execute()){
                throw new dbException($this->db->error);
            } else {
                $this->insertLog('Delete SecondAcc id1 '.$id.' id2 '.$secID);
            }
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Get member name & comment
     * @param id ID
     * @return name,comment,vip/null
     * @throws dbException
     */
    public function getMemberAddition($id) {
        if ($query = $this->db->prepare ( 'SELECT `name`,`comment`,`vip` FROM `member_addition` WHERE `id` = ?' )) {
            $query->bind_param('i',$id);
            if(!$query->execute()){
                throw new dbException($this->db->error);
            }
            $result = $query->get_result ();
            
            if (! $result) {
                throw new dbException ( $this->db->error, 500 );
            }
            
            if ($result->num_rows == 0) {
                $addition = null;
            } else {
                $resultset = $result->fetch_assoc();
                $addition = array(
                    'name' => $resultset['name'],
                    'comment' => $resultset['comment'],
                    'vip' => $resultset['vip'],
                );
            }
            $result->close();
            
            return $addition;
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Set member name & comment
     * @param id account ID
     * @param name real name of member
     * @param comment can be null
     * @param vip boolean, account is VIP user
     * @throws dbException
     */
    public function setMemberAddition($id,$name,$comment,$vip) {
        if($query = $this->db->prepare (
            'INSERT INTO `member_addition` (`id`,`name`,`comment`,`vip`) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `comment` = VALUES(`comment`), `vip` = VALUES(`vip`)')) {
            $query->bind_param('issi',$id,$name,$comment,$vip);
            if(!$query->execute()){
                throw new dbException($this->db->error);
            } else {
                $this->insertLog('Change Member acc '.$id.' name '.$name.' comment '.$comment.' vip '.$vip);
            }
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Get membership changes for date
     * @param string $date
     * @return list of memberships
     * @throws dbException
     */
    public function getMembershipChanges($date) {
        $date = $date . '%';
        if ($query = $this->db->prepare ( 'SELECT ms.id,
            ms.`nr`,`from`,`to`,`kicked`,`cause`,
            IFNULL(ad.name,?) as vname,IFNULL(names.name,?) as `name`
        FROM `membership` ms 
        LEFT JOIN `membership_cause` mc ON ms.nr = mc.nr
        LEFT JOIN `member_names` names ON ms.id = names.id AND
            `names`.updated = (SELECT MAX(n2.updated) 
                FROM `member_names` n2 
                WHERE n2.id = ms.id 
            )
        LEFT JOIN `member_addition` ad ON ms.id = ad.id
        WHERE `from` >= ? OR (`to` IS NOT NULL AND `to` >= ?)
        ORDER BY `from`,`to`,`name`')) {
            $query->bind_param('ssss',$this->name_default,$this->name_default,$date,$date);
            if(!$query->execute()){
                throw new dbException($this->db->error);
            }
            $result = $query->get_result ();
            
            if (! $result) {
                throw new dbException ( $this->db->error, 500 );
            }
            
            $resultset = array ();
            while ( $row = $result->fetch_assoc () ) {
                $resultset[] = array(
                    'vname' => $row['vname'],
                    'name' => $row['name'],
                    'id' => $row['id'],
                    'nr' => $row['nr'],
                    'from' => $row['from'],
                    'to' => $row['to'],
                    'cause' => $row['cause'],
                    'kicked' => $row['kicked']
                );
            }
            $result->close();
            
            return $resultset;
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Get member joins
     * @param id account ID
     * @throws dbException
     * @return [nr,date]
     * @throws dbException
     */
    public function getMembershipData($id) {
        if ($query = $this->db->prepare ( 'SELECT ms.`nr`,`from`,`to`,`kicked`,`cause` FROM `membership` ms 
        LEFT JOIN `membership_cause` mc ON ms.nr = mc.nr
        WHERE `id` = ? 
        ORDER BY `from` ASC')) {
            $query->bind_param('i',$id);
            if(!$query->execute()){
                throw new dbException($this->db->error);
            }
            $result = $query->get_result ();
            
            if (! $result) {
                throw new dbException ( $this->db->error, 500 );
            }
            
            $resultset = array ();
            while ( $row = $result->fetch_assoc () ) {
                $resultset[] = array(
                    'nr' => $row['nr'],
                    'from' => $row['from'],
                    'to' => $row['to'],
                    'kicked' => $row['kicked'],
                    'cause' => $row['cause']
                );
            }
            $result->close();
            
            return $resultset;
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Insert member join
     * Retrieves existing entry ID if already existing
     * @param id account ID
     * @param from date of join
     * @throws dbException
     * @return row ID
     * @throws dbException
     */
    public function insertJoin($id,$from) {
        if($query = $this->db->prepare (
            'INSERT INTO `membership` (`id`,`from`,`to`) VALUES(?,?,NULL)')) {
            $query->bind_param('is',$id,$from);
            if(!$query->execute()){
                if($query->sqlstate == ER_DUP_ENTRY ){
                    $this->insertLog('Add membership acc '.$id.' '.$from);
                    return $this->getMembershipNr($id,$from);
                }else{
                    throw new dbException($this->db->error .' '.$query->sqlstate.' '.$this->db->sqlstate);
                }
            }
            
            return $this->db->insert_id;
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Checks for an existing membership entry for given data
     * @param id
     * @param from
     * @return ID or null
     * @throws dbException
     */
    public function checkForMemberShipNr($id,$from){
        return $this->getMembershipNr($id,$from,false);
    }
    
    /**
     * Get Membership NR by id & from-date
     * @param id
     * @param from
     * @param failure true on default, throw exception on empty result
     * @return row ID
     * @throws dbException
     */
    private function getMembershipNr($id,$from,$failure = true) {
        if ($query = $this->db->prepare ( 'SELECT `nr` FROM `membership` WHERE `id` = ? AND `from` = ?' )) {
            $query->bind_param('is',$id,$from);
            if(!$query->execute()){
                throw new dbException($this->db->error);
            }
            $result = $query->get_result ();
            
            if (! $result) {
                throw new dbException ( $this->db->error, 500 );
            }
            
            if ($result->num_rows == 0) {
                if($failure)
                    throw new dbException ( 'Empty data set' );
                else
                    $nr = NULL;
            } else {
                $nr = $result->fetch_assoc()['nr'];
            }
            $result->close();
            
            return $nr;
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Returns open memberships with no to date
     * @param id account ID
     * @return list of nr,from
     * @throws dbException
     */
    public function getOpenMembership($id) {
        if ($query = $this->db->prepare ( 'SELECT `nr`,`from` FROM `membership` WHERE `id` = ? AND `to` IS NULL')) {
            $query->bind_param('i',$id);
            if(!$query->execute()){
                throw new dbException($this->db->error);
            }
            $result = $query->get_result ();
            
            if (! $result) {
                throw new dbException ( $this->db->error, 500 );
            }
            
            $resultset = array ();
            while ( $row = $result->fetch_assoc () ) {
                $resultset[] = array(
                    'nr' => $row['nr'],
                    'from' => $row['from']
                );
            }
            $result->close();
            
            return $resultset;
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Remove leave from entry, uses a transaction
     * @param nr membership Nr
     * @throws dbException
     */
    public function deleteLeave($nr){
        $this->startTransaction();
        if($query = $this->db->prepare (
            'UPDATE `membership` SET `to` = NULL WHERE `nr` = ?')) {
            $query->bind_param('i',$nr);
            if(!$query->execute()){
                throw new dbException($this->db->error);
            }else{
                if($query = $this->db->prepare (
                    'DELETE FROM `membership_cause` WHERE `nr` = ?')) {
                    $query->bind_param('i',$nr);
                    if(!$query->execute()){
                        throw new dbException($this->db->error);
                    } else {
                        $this->endTransaction();
                        $this->insertLog('Delete Leave nr '.$nr);
                    }
                } else {
                    throw new dbException ( $this->db->error );
                }
            }
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Insert member leave
     * @param nr membership Nr
     * @param date date of leave
     * @param isKick member was kicked
     * @param cause cause of leave/kick, can be null
     * @throws dbException
     * @return row ID/null
     */
    public function insertLeave($nr,$date,$isKick,$cause) {
        if($query = $this->db->prepare (
            'UPDATE `membership` SET `to` = ? WHERE `nr` = ?')) {
            $query->bind_param('si',$date,$nr);
            if(!$query->execute()){
                throw new dbException($this->db->error);
            }else{
                $this->setMembershipCause($nr,$isKick,$cause);
                $this->insertLog('Change membership acc '.$id.' leave '.$date.' kick '.$isKick.' cause '.$cause);
            }
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Delete membership & _cause entry by nr
     * Uses an transaction for both tables
     * @param nr
     * @throws dbException
     */
    public function deleteMembershipEntry($nr) {
        $this->startTransaction();
        if($query = $this->db->prepare (
            'DELETE FROM `membership` WHERE `nr` = ?')) {
            $query->bind_param('i',$nr);
            if(!$query->execute()){
                throw new dbException($this->db->error);
            } else {
                if($query = $this->db->prepare (
                    'DELETE FROM `membership_cause` WHERE `nr` = ?')) {
                    $query->bind_param('i',$nr);
                    if(!$query->execute()){
                        throw new dbException($this->db->error);
                    } else {
                        $this->endTransaction();
                        $this->insertLog('Delete Membership nr '.$nr);
                    }
                } else {
                    throw new dbException ( $this->db->error );
                }
            }
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Update membership
     * @param nr membership Nr
     * @param $from member was kicked
     * @param $to cause of leave/kick, can be null
     * @throws dbException
     */
    public function updateMembership($nr,$from,$to) {
        if($query = $this->db->prepare (
            'UPDATE `membership` SET `from` = ?, `to` = ? WHERE `nr` = ?')) {
            $query->bind_param('ssi',$from,$to,$nr);
            if(!$query->execute()){
                throw new dbException($this->db->error);
            } else {
                $this->insertLog('Change Membership nr '.$nr.' '.$from.'-'.$to);
            }
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Insert membership cause
     * @param nr membership Nr
     * @param $isKick member was kicked
     * @param $cause cause of leave/kick, can be null
     * @throws dbException
     */
    public function setMembershipCause($nr,$isKick,$cause) {
        if($query = $this->db->prepare (
            'INSERT INTO `membership_cause` (`nr`,`kicked`,`cause`) VALUES(?,?,?) ON DUPLICATE KEY UPDATE `kicked` = VALUES(`kicked`), `cause` = VALUES(`cause`)')) {
            $query->bind_param('iis',$nr,$isKick,$cause);
            if(!$query->execute()){
                throw new dbException($this->db->error);
            } else {
                $this->insertLog('Add LeaveCause nr '.$nr.' kick '.$isKick.' '.$cause);
            }
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Get overview activity data
     * @param string $date1 !NOT escaped
     * @param string $date2 !NOT escaped
     * @throws dbException
     * @DEPRECATED
     */
    function getOverviewActivity($date1,$date2) {
        $this->escapeData($date1);
        $this->escapeData($date2);
        $ignoreid = TS_IGNORE_ID;
        $this->escapeData($ignoreid);
        // get clan stuff & get TS stuff, but
        if ($query = $this->db->prepare ( '
        SELECT m.date,COUNT(CASE WHEN exp_diff > 0 THEN 1 END) as member_online,
        COUNT(CASE WHEN exp_diff > 5000 THEN 1 END) as member_active,
        COUNT(CASE WHEN cp_diff > 100 THEN 1 END) as member_casher,
        AVG(exp_diff) as member_avg_exp 
        FROM (
            SELECT m1.date,m1.id,(m1.exp - m2.exp) as exp_diff,(m1.cp - m2.cp) as cp_diff FROM `member` as m1
            LEFT JOIN `member` m2
            ON m2.id = m1.id AND m2.date >= DATE_SUB(m1.date, INTERVAL 1 DAY)
                AND m2.date < m1.date
        ) m
        WHERE m.`date` BETWEEN "'.$date1.'%" AND DATE_ADD("'.$date2.'%" , INTERVAL 1 DAY)
        GROUP BY m.`date`
        ORDER BY m.`date`' )) { // Y-m-d G:i:s Y-m-d h:i:s
            $query->execute ();
            $result = $query->get_result ();
            
            if (! $result) {
                throw new dbException ( $this->db->error, 500 );
            }
            
            if ($result->num_rows == 0) {
                $resultset = null;
            } else {
                $resultset = array ();
                $dates = array();
                $active = array();
                $online = array();
                $exp_avg = array();
                $casher = array();
                while ( $row = $result->fetch_assoc () ) {
                    $dates[] = $row['date'];
                    $active[] = $row['member_active'];
                    $online[] = $row['member_online'];
                    $exp_avg[] = $row['member_avg_exp'];
                    $casher[] = $row['member_casher'];
                }
                $resultset['exp_avg'] = $exp_avg;
                $resultset['active'] = $active;
                $resultset['online'] = $online;
                $resultset['casher'] = $casher;
            }
            $result->close();
            
            return $resultset;
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Get overview data
     * @param string $date1
     * @param string $date2
     * @throws dbException
     * @DEPRECATED
     */
    public function getOverview($date1, $date2) {
        $this->escapeData($date1);
        $this->escapeData($date2);
        $ignoreid = TS_IGNORE_ID;
        $this->escapeData($ignoreid);
        // get clan stuff & get TS stuff, but
        if ($query = $this->db->prepare ( 'SELECT c.`date`,`wins`,`losses`,`draws`,`members`,
        COUNT(ts_data.`client_id`) as ts_count, SEC_TO_TIME(AVG(ts_data.time)) as ts_time_avg
        FROM `clan` as c
        LEFT JOIN `'.DB_TS3_DATA.'` ts_data
        ON ts_data.date = DATE(DATE_ADD(c.`date`, INTERVAL -1 DAY)) AND ts_data.client_id != 
        '.$ignoreid.'
        WHERE c.`date` BETWEEN "'.$date1.'%" AND DATE_ADD("'.$date2.'%" , INTERVAL 1 DAY)
        GROUP BY c.`date`
        ORDER BY c.`date`' )) { // Y-m-d G:i:s Y-m-d h:i:s
            $query->execute ();
            $result = $query->get_result ();
            
            if (! $result) {
                throw new dbException ( $this->db->error, 500 );
            }
            
            if ($result->num_rows == 0) {
                $resultset = null;
            } else {
                $resultset = array ();
                $dates = array();
                $wins = array();
                $losses = array();
                $draws = array();
                $member = array();
                $tsCount = array();
                while ( $row = $result->fetch_assoc () ) {
                    $dates[] = $row['date'];
                    $wins[] = $row['wins'];
                    $losses[] = $row['losses'];
                    $draws[] = $row['draws'];
                    $member[] = $row['members'];
                    $tsCount[] = $row['ts_count'];
                    $tsTimeAvg[] = PLOTLY_START_DATE.$row['ts_time_avg'];
                }
                $resultset['x'] = $dates;
                $resultset['wins'] = $wins;
                $resultset['losses'] = $losses;
                $resultset['draws'] = $draws;
                $resultset['member'] = $member;
                $resultset['ts_count'] = $tsCount;
                $resultset['ts_time_avg'] = $tsTimeAvg;
            }
            $result->close();
            
            $data = $this->getOverviewActivity($date1,$date2);
            $resultset['active'] = $data['active'];
            $resultset['exp_avg'] = $data['exp_avg'];
            $resultset['online'] = $data['online'];
            $resultset['casher'] = $data['casher'];
            
            return $resultset;
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Get difference of member from time to time
     * @param string $date1
     * @param string $date2
     * @param number $memberID
     * @throws dbException
     */
    public function getMemberChange($date1, $date2, $memberID) {
        $this->escapeData($date1);
        $this->escapeData($date2);
        if ($query = $this->db->prepare ( 'SELECT t1.id, t1.date,t1.exp,t1.cp,
        (t1.exp - (SELECT t2.exp FROM member t2
            WHERE t2.date < date_sub(t1.date, interval 10 hour) AND t2.id=t1.id 
            ORDER BY t2.date DESC LIMIT 1) ) AS `exp-diff`,
        (t1.cp - (SELECT t2.cp FROM member t2
            WHERE t2.date < date_sub(t1.date, interval 10 hour) AND t2.id=t1.id 
            ORDER BY t2.date DESC LIMIT 1) ) AS `cp-diff` 
        FROM member t1 
        WHERE t1.id = ? 
        AND t1.date BETWEEN DATE_ADD("'.$date1.'%", INTERVAL 1 DAY) AND DATE_ADD("'.$date2.'%", INTERVAL 1 DAY) 
        ORDER by id,date' )) { // Y-m-d G:i:s Y-m-d h:i:s
            $query->bind_param ( 'i', $memberID );
            $query->execute ();
            $result = $query->get_result ();
            
            if (! $result) {
                throw new dbException ( $this->db->error, 500 );
            }
            
            if ($result->num_rows == 0) {
                $resultset = null;
            } else {
                $resultset = array ();
                $exp = array();
                $cp = array();
                $exp_diff = array();
                $cp_diff = array();
                while ( $row = $result->fetch_assoc () ) {
                    $exp[] = array(
                        'x' => $row['date'],
                        'y' => $row['exp']
                    );
                    $cp[] = array(
                        'x' => $row['date'],
                        'y' => $row['cp']
                    );
                    $exp_diff[] = array(
                        'x' => $row['date'],
                        'y' => $row['exp-diff']
                    );
                    $cp_diff[] = array(
                        'x' => $row['date'],
                        'y' => $row['cp-diff']
                    );
                }
                $resultset['cp'] = $cp;
                $resultset['exp'] = $exp;
                $resultset['cp_diff'] = $cp_diff;
                $resultset['exp_diff'] = $exp_diff;
            }
            $result->close();
            
            return $resultset;
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Get member matching exact ID in membership, member_names, member_addition
     * @param id exact ID to match
     * @return select2 compatible list
     * @throws dbException
     */
    public function getMemberByExactID($id) {
        if ($query = $this->db->prepare ( 'SELECT `id`,`name` FROM `member_names` names WHERE `id` = ? AND
            `names`.updated = (SELECT MAX(n2.updated) 
                FROM `member_names` n2 
                WHERE n2.id = names.id
            )
        UNION DISTINCT 
        SELECT `id`, ? as `name` FROM `membership` 
        WHERE `id` = ? 
        UNION DISTINCT 
        SELECT `id`, ? as `name` FROM `member_addition`
        WHERE `id` = ?' )) {
            $query->bind_param ( 'isisi',$id,$this->name_default,$id,$this->name_default,$id );
            $query->execute();
            $result = $query->get_result ();
            
            if (! $result) {
                throw new dbException ( $this->db->error, 500 );
            }
            
            if ($result->num_rows == 0) {
                $resultset = array();
            } else {
                $resultset = array ();
                while ( $row = $result->fetch_assoc () ) {
                    $resultset[] = array(
                        'id' => $row['id'],
                        'text' => $row['name'] . ' (' . $row['id'] . ')',
                    );
                }
            }
            $result->close();
            
            return $resultset;
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Get member matching name in member_names
     * @param key Key to search, name like Key, id = key
     * @return Select2 compatible grouped list {with limit}
     * @throws dbException
     */
    public function getMemberByName($key) {
        $key_name = '%'.$key.'%';
        if ($query = $this->db->prepare ( 'SELECT mn.`name`,mn.`id`,ma.`name` as `vname` FROM `member_names` mn
        JOIN `member_addition` ma ON mn.id = ma.id 
        WHERE mn.`name` LIKE ? OR ma.`name` LIKE ? 
        ORDER BY `id`,`date`,`updated`,mn.`name` 
        LIMIT 100' )) {
            $query->bind_param ( 'ss', $key_name, $key_name );
            $query->execute();
            $result = $query->get_result ();
            
            if (! $result) {
                throw new dbException ( $this->db->error, 500 );
            }
            
            if ($result->num_rows == 0) {
                $resultset = array();
            } else {
                $resultset = array ();
                
                $groupID = -1;
                $groupArray = array();
                while ( $row = $result->fetch_assoc () ) {
                    if ($row['id'] !== $groupID) {
                        $resultset[] = $groupArray;
                        $groupID = $row['id'];
                        $groupArray = array(
                            'text' => $row['id'] .' '. $row['vname'],
                            'children' => array());
                    }
                    $groupArray['children'][] = array(
                        'id' => $row['id'],
                        'text' => $row['name'] . ' (' . $row['id'] . ') '.$row['vname'],
                    );
                }
                $resultset[] = $groupArray;
            }
            $result->close();
            
            return $resultset;
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Get account names
     * @param id Account id
     * @return returns list of name,date,updated
     * @throws dbException
     */
    public function getAccountNames($id) {
        if ($query = $this->db->prepare ( 'SELECT `name`,DATE(`date`) as `date`,`updated` FROM `member_names` WHERE `id` = ?
        ORDER BY `date`,`updated`,`name` DESC' )) { // Y-m-d G:i:s Y-m-d h:i:s
            $query->bind_param ( 'i', $id );
            $query->execute();
            $result = $query->get_result ();
            
            if (! $result) {
                throw new dbException ( $this->db->error, 500 );
            }
            
            if ($result->num_rows == 0) {
                $resultset = null;
            } else {
                $resultset = array ();
                while ( $row = $result->fetch_assoc () ) {
                    $resultset[] = array(
                        'name' => $row['name'],
                        'date' => $row['date'],
                        'updated' => $row['updated']
                    );
                }
            }
            $result->close();
            
            return $resultset;
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Get ongoing trial of member
     * @return id,from/null
     */
    public function getMemberTrialOpen($id) {
        if ($query = $this->db->prepare ( 'SELECT `id`,`from` FROM `member_trial` WHERE `id` = ? AND `to` IS NULL' )) {
            $query->bind_param('i',$id);
            if(!$query->execute()){
                throw new dbException($this->db->error);
            }
            $result = $query->get_result ();
            
            if (! $result) {
                throw new dbException ( $this->db->error, 500 );
            }
            
            if ($result->num_rows == 0) {
                $resultset = null;
            } else {
                $resultset = array ();
                $row = $result->fetch_assoc();
                $resultset = array(
                    'id' => $row['id'],
                    'from' => $row['from']
                );
            }
            $result->close();
            
            return $resultset;
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Receive member trials
     * @param id Member IDs
     * @param trial is Trial
     * @throws dbException
     */
    public function getMemberTrials($id) {
        if ($query = $this->db->prepare ( 'SELECT `id`,`from`,`to` FROM `member_trial` WHERE `id` = ?' )) {
            $query->bind_param('i',$id);
            if(!$query->execute()){
                throw new dbException($this->db->error);
            }
            $result = $query->get_result ();
            
            if (! $result) {
                throw new dbException ( $this->db->error, 500 );
            }
            
            if ($result->num_rows == 0) {
                $resultset = null;
            } else {
                $resultset = array ();
                while ( $row = $result->fetch_assoc () ) {
                    $resultset[] = array(
                        'id' => $row['id'],
                        'start' => $row['from'],
                        'end' => $row['to']
                    );
                }
            }
            $result->close();
            
            return $resultset;
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Set member trial, updates end on duplicate (id,start)
     * @param id Member IDs
     * @param start Start date
     * @param end End date, can be null
     * @throws dbException
     */
    public function setMemberTrial($id,$start,$end) {
        if($query = $this->db->prepare (
            'INSERT INTO `member_trial` (`id`,`from`,`to`) VALUES(?,?,?) ON DUPLICATE KEY UPDATE `to` = VALUES(`to`)')) {
            $query->bind_param('iss',$id,$start,$end);
            if(!$query->execute()){
                throw new dbException($this->db->error);
            } else {
                $this->insertLog('Add trial acc '.$id.' '.$start.'-'.$end);
            }
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Ends all open member trials
     * @param id Member IDs
     * @param end end date to value
     * @throws dbException
     */
    public function endMemberTrials($id,$end) {
        if($query = $this->db->prepare (
            'UPDATE `member_trial` SET `to` = ? WHERE `id` = ? AND `to` IS NULL')) {
            $query->bind_param('si',$end,$id);
            if(!$query->execute()){
                throw new dbException($this->db->error);
            }
            return $query->affected_rows;
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Delete member trial
     * @param id Id of member
     * @param from start date of entry
     * @throws dbException
     */
    public function deleteMemberTrial($id, $from) {
    if($query = $this->db->prepare (
            'DELETE FROM `member_trial` WHERE `id` = ? AND `from` = ?')) {
            $query->bind_param('is',$id,$from);
            if(!$query->execute()){
                throw new dbException($this->db->error);
            } else {
                $this->insertLog('Delete trial acc '.$id.' from '.$from);
            }
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Get amount of entries in member table
     * @param string $key
     * @throws dbException
     * @return integer/null
     */
    public function getMemberTableCount() {
        return $this->getCountByID('member');
    }
    
    /**
     * Get amount of entries in member_names table
     * @param string $key
     * @throws dbException
     * @return integer/null
     */
    public function getDBNameCount() {
        return $this->getCountByID('member_names');
    }
    
    /**
     * Get amount of users in member_names table
     * @param string $key
     * @throws dbException
     * @return integer/null
     */
    public function getDBIDCount() {
        if ($query = $this->db->prepare ( 'SELECT COUNT(DISTINCT `id`) as `amount` from member_names' )) {
            $query->execute();
            $result = $query->get_result ();
            
            if (! $result) {
                throw new dbException ( $this->db->error, 500 );
            }
            
            if ($result->num_rows == 0) {
                $amount = null;
            } else {
                $amount = $result->fetch_assoc()['amount'];
            }
            $result->close();
            
            return $amount;
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Get COUNT(`id`) value from specified table
     * @param table Table from which to count, NON SAFE
     * @return integer/exception
     * @throws dbException
     */
    private function getCountByID($table) {
        return $this->getCountByKey('id',$table);
    }
    
   /**
     * Get COUNT(`id`) value from specified table
     * @param table Table from which to count, NON SAFE
     * @param key Table key which is to count, NON SAFE
     * @return integer/exception
     * @throws dbException
     */
    private function getCountByKey($key,$table) {
        if ($query = $this->db->prepare ( 'SELECT COUNT(`'.$key.'`) AS `amount` FROM `'.$table.'`' )) {
            $query->execute();
            $result = $query->get_result ();
            
            if (! $result) {
                throw new dbException ( $this->db->error, 500 );
            }
            
            if ($result->num_rows == 0) {
                $amount = null;
            } else {
                $amount = $result->fetch_assoc()['amount'];
            }
            $result->close();
            
            return $amount;
        } else {
            throw new dbException ( '500' );
        }
    }
    
    /**
     * Get amount of real names
     * @throws dbException
     * @return integer/null
     */
    public function getRealNameCount() {
        return $this->getCountByID('member_addition');
    }
    
    /**
     * Get amount of afks
     * @throws dbException
     * @return integer/null
     */
    public function getAFKCount() {
        return $this->getCountByID('afk');
    }
    
    /**
     * Get amount of caution entries
     * @throws dbException
     * @return integer/null
     */
    public function getCautionCount() {
        return $this->getCountByID('caution');
    }
    
    /**
     * Get amount of joins
     * @throws dbException
     * @return integer/null
     */
    public function getJoinCount() {
        return $this->getCountByKey('nr','membership');
    }
    
    /**
     * Get amount of leaves
     * @throws dbException
     * @return integer/null
     */
    public function getLeaveCount() {
        if ($query = $this->db->prepare ( 'SELECT COUNT(`nr`) AS `amount` FROM `membership` WHERE `to` IS NOT NULL' )) {
            $query->execute();
            $result = $query->get_result ();
            
            if (! $result) {
                throw new dbException ( $this->db->error, 500 );
            }
            
            if ($result->num_rows == 0) {
                $amount = null;
            } else {
                $amount = $result->fetch_assoc()['amount'];
            }
            $result->close();
            
            return $amount;
        } else {
            throw new dbException ( '500' );
        }
    }
    
    public function getUnknownTSIdentities() {
        if ($query = $this->db->prepare ( 'SELECT v.`client_id`,`name` FROM `unknown_ts_unignored` v
            LEFT JOIN `'.DB_TS3_NAMES.'` names ON v.`client_id` = names.`client_id`' )) {
            $query->execute();
            $result = $query->get_result ();
            
            if (! $result) {
                throw new dbException ( $this->db->error, 500 );
            }
            
            if ($result->num_rows == 0) {
                $resultset = array();
            } else {
                $resultset = array ();
                while ( $row = $result->fetch_assoc () ) {
                    $resultset[] = array(
                        'name' => $row['name'] . ' ('.$row['client_id'].')',
                        'id' => $row['client_id']
                    );
                }
            }
            $result->close();
            
            return $resultset;
        } else {
            throw new dbException ( '500' );
        }
    }
    
    /**
     * Get amount of joins
     * @throws dbException
     * @return integer/null
     */
    public function getMemberCauseEntries() {
        return $this->getCountByKey('nr','membership_cause');
    }
    
    /**
     * Get amount of second accounts
     * @throws dbException
     * @return integer/null
     */
    public function getSecondAccCount() {
        return $this->getCountByID('second_acc');
    }
    
    /**
     * Get amount of ts IDs in DB_TS3_NAMES
     * @throws dbException
     * @return integer/null
     */
    public function getTSIDCount() {
        return $this->getCountByKey('client_id',DB_TS3_NAMES);
    }
    
    /**
     * Get amount of ts data in DB_TS3_DATA
     * @throws dbException
     * @return integer/null
     */
    public function getTSOldDataCount() {
        return $this->getCountByKey('date',DB_TS3_DATA);
    }
    
    /**
     * Get amount of ts statistics data in DB_TS3_STATS
     * @throws dbException
     * @return integer/null
     */
    public function getTSStatsDataCount() {
        return $this->getCountByKey('timestamp',DB_TS3_STATS);
    }
    
    /**
     * Get amount of ts identities ignored
     * @throws dbException
     * @return integer/null
     */
    public function getTSIgnoreCount() {
        return $this->getCountByKey('client_id','ignore_ts_ids');
    }
    
    /**
     * Get amount of known ts channels
     * @throws dbException
     * @return integer/null
     */
    public function getTSChannelCount() {
        return $this->getCountByKey('channel_id','ts_channels');
    }
    
    /**
     * Get amount of known ts identities
     * @throws dbException
     * @return integer/null
     */
    public function getTSIdentityCount() {
        return $this->getCountByKey('client_id','ts_names');
    }
    
    /**
     * Get amount of known ts identities
     * @throws dbException
     * @return integer/null
     */
    public function getTSRelationsCount() {
        return $this->getCountByKey('client_id','ts_relation');
    }
    
    /**
     * Get amount of ts data in DB_TS3_DATA
     * @throws dbException
     * @return integer/null
     */
    public function getTSDataCount() {
        return $this->getCountByKey('date','ts_activity');
    }
    
    /**
     * Get amount entries in global_note table
     * @throws dbException
     * @return integer/null
     */
    public function getGlobalNotesCount() {
        return $this->getCountByKey('id','global_note');
    }
    
    /**
     * Get amount of entries in missing_entries table
     * @throws dbException
     * @return integer/null
     */
    public function getMissingEntriesCount() {
        return $this->getCountByKey('date','missing_entries');
    }
    
    /**
     * Get amount of log entries in `log`
     * @throws dbException
     * @return integer/null
     */
    public function getLogEntryCount() {
        return $this->getCountByKey('date','log');
    }
    
    /**
     * Get amount of unlinked ts IDs
     * @throws dbException
     * @return intereger/null
     */
    public function getUnlinkedTSIdCount() {
        if ($query = $this->db->prepare ( 'SELECT COUNT(`names`.`client_id`) AS `amount` FROM `'.DB_TS3_NAMES.'` names
        LEFT JOIN `ts_relation` rel ON names.client_id = rel.client_id 
        WHERE rel.client_id IS NULL')) {
            $query->execute();
            $result = $query->get_result ();
            
            if (! $result) {
                throw new dbException ( $this->db->error, 500 );
            }
            
            if ($result->num_rows == 0) {
                $amount = null;
            } else {
                $amount = $result->fetch_assoc()['amount'];
            }
            $result->close();
            
            return $amount;
        } else {
            throw new dbException ( $this->db->error );
        }
    }

    /**
     * Get dates for selection where there are noted missing dates
     * @param $date1 first date
     * @param $date2 second date
     * @return array of missing dates
     */
    public function getMissingEntries($date1,$date2) {
        if ($query = $this->db->prepare ( 'SELECT DATE(`date`) as date from `missing_entries` WHERE date BETWEEN ? AND ?')) { // Y-m-d G:i:s Y-m-d h:i:s
            $query->bind_param ( 'ss', $date1, $date2 );
            $query->execute();
            $result = $query->get_result ();
            
            if (! $result) {
                throw new dbException ( $this->db->error, 500 );
            }
            
            if ($result->num_rows == 0) {
                $resultset = null;
            } else {
                $resultset = array ();
                while ( $row = $result->fetch_assoc () ) {
                    $resultset[] = $row['date'];
                }
            }
            $result->close();
            
            return $resultset;
        } else {
            throw new dbException ( $this->db->error );
        }
        
    }
    
    /**
     * Get ts3 summary for id, old non-channel data
     * @param $date1 first date
     * @param $date2 second date
     * @param $id account ID
     * @return sum,avg,days
     */
    public function getMemberTSSummaryOld($date1,$date2,$id) {
        if ($query = $this->db->prepare (
        'select ROUND(SUM(  `time` )) as `timeSum`,
            ROUND(AVG(`time`)) as `avg`,
            COUNT(DISTINCT stats.`date`) as `days` 
            FROM `ts_relation` mt 
            JOIN `'.DB_TS3_DATA.'` stats ON mt.client_id = stats.client_id 
            WHERE mt.id = ? 
            AND stats.date BETWEEN ? AND ?;')) {
            $query->bind_param ( 'iss',$id, $date1, $date2 );
            $query->execute();
            $result = $query->get_result ();
            
            if (! $result) {
                throw new dbException ( $this->db->error, 500 );
            }
            
            if ($result->num_rows == 0) {
                $resultset = null;
            } else {
                if($row = $result->fetch_assoc ()){
                    $resultset = array(
                        //'sum' => date('H:i:s',$row['timeSum']),
                        //'avg' => date('H:i:s',$row['avg']),
                        'days' => $row['days'],
                        //'sum_raw' => $row['timeSum'],
                        'avg_raw' => $row['avg'],
                    );
                }
            }
            $result->close();
            
            return $resultset;
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Get ts3 summary for id
     * @param $date1 first date
     * @param $date2 second date
     * @param $id account ID
     * @return sum,avg,days
     */
    public function getMemberTSSummary($date1,$date2,$id) {
        $days = $this->getMemberTSActiveDays($date1,$date2,$id);
        if ($days == null) {
            return null;
        }
        if ($query = $this->db->prepare (
            'SELECT g.group_id,gn.name,SEC_TO_TIME(SUM(timeAvg)) as TGAvg from (
            SELECT cid,cname,ROUND(SUM(tsum) / '.$days.' ) as timeAvg FROM (
                        SELECT a.date,a.channel_id as cid,c.name as cname,SUM(time) as tsum
                            FROM ts_relation r
                            JOIN ts_activity a ON r.client_id = a.client_id
                            JOIN ts_channels c ON a.channel_id = c.channel_id
                            WHERE r.id = ? AND a.date BETWEEN ? AND ?
                            GROUP BY a.channel_id,a.date
                        ) d
                        GROUP BY cid
            ) d
            LEFT JOIN ts_channel_groups g ON d.cid = g.channel_id
            LEFT JOIN ts_channel_group_names gn ON gn.group_id = g.group_id
            GROUP BY g.group_id;')) {
            $query->bind_param ( 'iss',$id, $date1, $date2 );
            $query->execute();
            $result = $query->get_result ();
            
            if (! $result) {
                throw new dbException ( $this->db->error, 500 );
            }
            
            if ($result->num_rows == 0) {
                $resultset = null;
            } else {
                $resultset = array();
                $resultset['days'] = $days;
                $data = array();
                while ( $row = $result->fetch_assoc () ) {
                    $gid = $row['group_id'];
                    if ($gid == null) {
                        $gid = -1;
                    }
                    $data[$gid] = array(
                        //'timeAvg' => $row['timeAvg']*1000,
                        'timeAvg' => PLOTLY_START_DATE.$row['TGAvg'],
                        'group' => $row['name'] != null ? $row['name'] : "Other",
                    );
                }
                $resultset['data'] = $data;
            }
            $result->close();
            return $resultset;
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Get ts3 summary for id
     * @param $date1 first date
     * @param $date2 second date
     * @param $id account ID
     * @return sum,avg,days
     */
    public function getMemberTSSummaryDetailed($date1,$date2,$id) {
        $days = $this->getMemberTSActiveDays($date1,$date2,$id);
        if ($days == null) {
            return null;
        }
        if ($query = $this->db->prepare (
        'SELECT cid,cname,SEC_TO_TIME(ROUND(SUM(tsum) / '.$days.' )) as timeAvg FROM (
            SELECT a.date,a.channel_id as cid,c.name as cname,SUM(time) as tsum
                FROM ts_relation r
                JOIN ts_activity a ON r.client_id = a.client_id
                JOIN ts_channels c ON a.channel_id = c.channel_id
                WHERE r.id = ? AND a.date BETWEEN ? AND ?
                GROUP BY a.channel_id,a.date
            ) d
            GROUP BY cid')) {
            $query->bind_param ( 'iss',$id, $date1, $date2 );
            $query->execute();
            $result = $query->get_result ();
            
            if (! $result) {
                throw new dbException ( $this->db->error, 500 );
            }
            
            if ($result->num_rows == 0) {
                $resultset = null;
            } else {
                $resultset = array();
                $resultset['days'] = $days;
                $data = array();
                while ( $row = $result->fetch_assoc () ) {
                    $data[$row['cid']] = array(
                        //'timeAvg' => $row['timeAvg']*1000,
                        'timeAvg' => PLOTLY_START_DATE.$row['timeAvg'],
                        'channel' => $row['cname'],
                    );
                }
                $resultset['data'] = $data;
            }
            $result->close();
            
            return $resultset;
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    private function getMemberTSActiveDays($date1,$date2,$id) {
        if ($query = $this->db->prepare (
        'SELECT COUNT(DISTINCT a.`date`) as days
            FROM ts_relation r
            JOIN ts_activity a ON r.client_id = a.client_id
            WHERE r.id = ? AND a.date BETWEEN ? AND ?')) {
            $query->bind_param ( 'iss',$id, $date1, $date2 );
            $query->execute();
            $result = $query->get_result ();
            
            if (! $result) {
                throw new dbException ( $this->db->error, 500 );
            }
            
            $days = null;
            if ($result->num_rows != 0) {
                if ( $row = $result->fetch_assoc () ) {
                    $days = $row['days'];
                }
            }
            $result->close();
            return $days;
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Search for TS ID by name, limited to 20
     * @param key Key to search for
     * @return select2 formated resultset {limit 20}
     * @throw dbException
     */
    public function searchTs3ID($key) {
        $keyName = '%'.$key.'%';
        if ($query = $this->db->prepare ( 'SELECT `name`,`client_id` 
        FROM `'.DB_TS3_NAMES.'` 
        WHERE `name` LIKE ? OR `client_id` = ?
        LIMIT 20')) {
            $query->bind_param ( 'si', $keyName, $key );
            $query->execute();
            $result = $query->get_result ();
            
            if (! $result) {
                throw new dbException ( $this->db->error, 500 );
            }
            
            if ($result->num_rows == 0) {
                $resultset = array();
            } else {
                $resultset = array ();
                while ( $row = $result->fetch_assoc () ) {
                    $resultset[] = array(
                        'text' => $row['name'] . ' ('.$row['client_id'].')',
                        'id' => $row['client_id']
                    );
                }
            }
            $result->close();
            
            return $resultset;
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Get TS3 stats for Host-ID
     * @param String $date1, $date2  String of Y-m-d G:i:s Y-m-d h:i:s
     * @throws dbException
     */
    public function getTS3Stats($date1, $date2) {
        if ($query = $this->db->prepare ( 'SELECT `timestamp`,`clients`,`queryclients` FROM `'.DB_TS3_STATS.'` WHERE `timestamp` >= ? AND `timestamp` < ? + INTERVAL 1 DAY' )) {
            $query->bind_param ( 'ss',$date1, $date2 );
            $query->execute ();
            $result = $query->get_result ();
            
            if (! $result) {
                throw new dbException ( $this->db->error, 500 );
            }
            
            if ($result->num_rows == 0) {
                $resultset = null;
            } else {
                $x = array();
                $total = array();
                $console = array();
                while ( $row = $result->fetch_assoc () ) {
                    $x [] = $row['timestamp'];
                    $total [] = $row['clients'];
                    $console [] = $row['queryclients'];
                }
                $resultset = array (
                    'x' => $x,
                    'total' => $total,
                    'console' => $console
                );
            }
            
            $result->close ();
            
            return $resultset;
        } else {
            throw new dbException ( '500' );
        }
    }
    
    /**
     * Creates a new ts3 channel group
     * @param name Name for new group
     * @return group id
     * @throw dbException
     */
    public function addTs3ChannelGroup($name) {
        if($query = $this->db->prepare (
            'INSERT INTO `ts_channel_group_names` (`name`) VALUES (?)')) {
            $query->bind_param('s',$name);
            if(!$query->execute()){
                throw new dbException($this->db->error);
            }else{
                $id = $this->db->insert_id;
                if ($id == 0) {
                    throw new dbException("Expected insertion ID, got none.");
                }
                $this->insertLog('Add Ts-Channel-Group gid '.$id.' '.$name);
                return $id;
            }
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Delete channel group
     * @param gid Group id
     * @throw dbException
     */
    public function deleteTs3ChannelGroup($gid) {
        if($query = $this->db->prepare (
            'DELETE FROM `ts_channel_group_names` WHERE group_id = ?')) {
            $query->bind_param('i',$gid);
            if(!$query->execute()){
                throw new dbException($this->db->error);
            } else {
                $this->insertLog('Delete Ts-Channel-Group gid '.$gid);
            }
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Remove channel from channel group
     * @param gid Group id
     * @param cid Channel id
     * @throw dbException
     */
    public function removeTs3CGChannel($gid,$cid) {
        if($query = $this->db->prepare (
            'DELETE FROM `ts_channel_groups` WHERE group_id = ? and channel_id = ?')) {
            $query->bind_param('ii',$gid,$cid);
            if(!$query->execute()){
                throw new dbException($this->db->error);
            } else {
                $this->insertLog('Delete Ts-Channel from group gid '.$gid.' cid '.$cid);
            }
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Rename channel group
     * @param gid Group id
     * @param name New Group Name
     * @throw dbException
     */
    public function ts3RenameChannelGroup($gid,$name) {
        if($query = $this->db->prepare (
            'UPDATE `ts_channel_group_names` SET `name` = ? WHERE group_id = ?')) {
            $query->bind_param('si',$name,$gid);
            if(!$query->execute()){
                throw new dbException($this->db->error);
            } else {
                $this->insertLog('Change TsChannelGroup gid '.$gid.' name '.$name);
            }
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Add channel to channel group
     * @param gid Group id
     * @param cid Channel id
     * @throw dbException
     */
    public function addTs3CGChannel($gid,$cid) {
        if($query = $this->db->prepare (
            'INSERT INTO `ts_channel_groups` (`group_id`,`channel_id`) VALUES (?,?)')) {
            $query->bind_param('ii',$gid,$cid);
            if(!$query->execute()){
                throw new dbException($this->db->error);
            } else {
                $this->insertLog('Add Ts-Channel to group gid '.$gid.' cid '.$cid);
            }
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Search for TS channel by name, limited to 20
     * @param key Key to search for
     * @return select2 formated resultset {limit 20}
     * @throw dbException
     */
    public function searchTs3Channel($key) {
        $keyName = '%'.$key.'%';
        if ($query = $this->db->prepare ( 'SELECT c.`name`,c.`channel_id` 
        FROM ts_channels c
        LEFT JOIN ts_channel_groups cg ON cg.channel_id = c.channel_id
        WHERE cg.channel_id IS NULL AND c.`name` LIKE ? OR c.`channel_id` = ?
        LIMIT 20')) {
            $query->bind_param ( 'si', $keyName, $key );
            $query->execute();
            $result = $query->get_result ();
            
            if (! $result) {
                throw new dbException ( $this->db->error, 500 );
            }
            
            if ($result->num_rows == 0) {
                $resultset = array();
            } else {
                $resultset = array ();
                while ( $row = $result->fetch_assoc () ) {
                    $resultset[] = array(
                        'text' => $row['name'] . ' ('.$row['channel_id'].')',
                        'id' => $row['channel_id']
                    );
                }
            }
            $result->close();
            
            return $resultset;
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Return all non-grouped ts3 channels
     * @return array of all channels
     * @throw dbException
     */
    public function ts3UngroupedChannels() {
        if ($query = $this->db->prepare ( 'SELECT tc.`name`,tc.`channel_id` 
        FROM ts_channels tc
        LEFT JOIN ts_channel_groups cg ON cg.channel_id = tc.channel_id
        WHERE cg.channel_id IS NULL')) {
            $query->execute();
            $result = $query->get_result ();
            
            if (! $result) {
                throw new dbException ( $this->db->error, 500 );
            }
            
            if ($result->num_rows == 0) {
                $resultset = array();
            } else {
                $resultset = array ();
                while ( $row = $result->fetch_assoc () ) {
                    $cid = $row['channel_id'];
                    $resultset[$cid] = array(
                        'cname' => $row['name'],
                        'cid' => $row['channel_id']
                    );
                }
            }
            $result->close();
            
            return $resultset;
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Get all channelg roups. Returns array of [group id: {g_name,channels: [{cname,cid}]}]
     * @throws dbException
     */
    public function getTsChannelGroups() {
        if ($query = $this->db->prepare ( 'select gn.group_id,gn.name as gname,
            g.channel_id,c.name as cname FROM ts_channel_group_names gn
        LEFT JOIN ts_channel_groups g ON gn.group_id = g.group_id
        LEFT JOIN ts_channels c ON g.channel_id = c.channel_id' )) {
            $query->execute ();
            $result = $query->get_result ();
            
            if (! $result) {
                throw new dbException ( $this->db->error, 500 );
            }
            
            $resultset = array();
            if ($result->num_rows != 0) {
                $resultset = array();
                while ( $row = $result->fetch_assoc () ) {
                    $group_id = $row['group_id'];
                    if (!isset($resultset[$group_id])) {
                        $resultset[$group_id] = array(
                            'gname' => $row['gname'],
                            'channels' => array(),
                            'id' => $group_id,
                        );
                    }
                    $channelID = $row['channel_id'];
                    if ($channelID != null) {
                        $resultset[$group_id]['channels'][$channelID] = array(
                            'cname' => $row['cname'],
                            'cid' => $channelID
                        );
                    }
                }
            }
            
            $result->close ();
            
            return $resultset;
        } else {
            throw new dbException ( '500' );
        }
    }
    
    /**
     * Get global note entries
     * @param id Member ID
     * @return [{from,to,cause}]
     * @throws dbException
     */
    public function getGlobalNotes() {
        if ($query = $this->db->prepare ( 'SELECT `id`,`from`,`to`,`message` FROM `global_note` ORDER BY `from`')) {
            $query->bind_param('i',$id);
            if(!$query->execute()){
                throw new dbException($this->db->error);
            }
            $result = $query->get_result ();
            
            if (! $result) {
                throw new dbException ( $this->db->error, 500 );
            }
            
            $resultset = array ();
            while ( $row = $result->fetch_assoc () ) {
                $resultset[] = array(
                    'id' => $row['id'],
                    'from' => $row['from'],
                    'to' => $row['to'],
                    'message' => $row['message']
                );
            }
            $result->close();
            
            return $resultset;
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Insert global note entry
     * @param from from date
     * @param to to date
     * @param message of global_note entry
     * @throws dbException
     */
    public function insertGlobalNote($from,$to,$message) {
        if($query = $this->db->prepare (
            'INSERT INTO `global_note` (`from`,`to`,`message`,`added`) VALUES(?,?,?, CURRENT_TIMESTAMP)')) {
            $query->bind_param('sss',$from,$to,$message);
            if(!$query->execute()){
                throw new dbException($this->db->error);
            } else {
                $this->insertLog('Add GlobalNote '.$from.'-'.$to.' '.$message);
            }
        } else {
            throw new dbException ( $this->db->error );
        }
    }
    
    /**
     * Delete global note entry
     * @param id entry ID
     * @throws dbException
     */
    public function deleteGlobalNote($id) {
        if($query = $this->db->prepare (
            'DELETE FROM `global_note` WHERE `id` = ?')) {
            $query->bind_param('i',$id);
            if(!$query->execute()){
                throw new dbException($this->db->error);
            } else {
                $this->insertLog('Delete GlobalNote '.$id);
            }
        } else {
            throw new dbException ( $this->db->error );
        }
    }

    public function __destruct() {
        $this->db->close ();
    }
}
