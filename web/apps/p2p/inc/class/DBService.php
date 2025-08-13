<?php

class DBService
{

    /**
     * @throws Throwable
     */
    static function runInTransaction($callback) {
        global $db;
        $db->beginTransaction();
        try {
            $res = call_user_func($callback);
            $db->commit();
            return $res;
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }


}