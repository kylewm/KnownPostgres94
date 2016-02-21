<?php

namespace IdnoPlugins\Postgres94;

use Idno\Core\Idno;
use Idno\Core\DataConcierge;

class Postgres94 extends \Idno\Data\AbstractSQL {

    function init()
    {
        parent::init();
        //assume connection via UNIX domain socket for now
        $connstr = "pgsql:dbname={$this->dbname}";
        $this->client = new \PDO($connstr);
        $this->client->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->checkAndUpgradeSchema();
    }

    private static function encode($obj)
    {
        return json_encode($obj, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private static function decode($obj)
    {
        return json_decode($obj, true);
    }


    private function checkAndUpgradeSchema()
    {
    }

    /**
     * Saves a record to the specified database collection
     *
     * @param string $collection
     * @param array $array
     * @return id | false
     */
    function saveRecord($collection, $array)
    {
        $collection = $this->sanitiseCollection($collection);
        if (empty($array['_id'])) {
            $array['_id'] = md5(rand() . microtime(true));
        }
        if (empty($array['uuid'])) {
            $array['uuid'] = \Idno\Core\Idno::site()->config()->getURL() . 'view/' . $array['_id'];
        }
        $jdoc = self::encode($array);

        $this->client->beginTransaction();
        try {
            $exists = false;
            $stmt = $this->client->prepare("select 1 from $collection where _id=:id");
            if ($stmt->execute(['id' => $array['_id']])) {
                $exists = $stmt->fetchColumn();
            }

            if ($exists) {
                $stmt = $this->client->prepare("update $collection set uuid=:uuid, jdoc=:jdoc where _id=:id");
            } else {
                $stmt = $this->client->prepare("insert into $collection (_id, uuid, jdoc) values (:id, :uuid, :jdoc)");
            }

            $result = $stmt->execute([
                'uuid' => $array['uuid'],
                'id' => $array['_id'],
                'jdoc' => $jdoc,
            ]);

            $this->client->commit();
        } catch (\Exception $e) {
            Idno::site()->logging()->error('Exception saving record', [
                'collection' => $collection,
                'array' => $array,
                'error' => $e,
            ]);
            $this->client->rollback();
        }

        return $array['_id'];
    }

    /**
     * Retrieves a record from the database by its UUID
     *
     * @param string $id
     * @param string $collection The collection to retrieve from (default: entities)
     * @return array
     */
    function getRecordByUUID($uuid, $collection = 'entities') {
        $collection = $this->sanitiseCollection($collection);
        $stmt = $this->client->prepare("select jdoc from $collection where uuid=:uuid");

        if ($stmt->execute(['uuid' => $uuid])) {
            if ($jdoc = $stmt->fetchColumn()) {
                return self::decode($jdoc);
            }
        }
        return false;
    }

    /**
     * Retrieves a record from the database by ID
     *
     * @param string $id
     * @param string $entities The collection name to retrieve from (default: 'entities')
     * @return array
     */
    function getRecord($id, $collection = 'entities')
    {
        $collection = $this->sanitiseCollection($collection);
        $stmt = $this->client->prepare("select jdoc from $collection where _id=:id");

        if ($stmt->execute(['id' => $id])) {
            if ($jdoc = $stmt->fetchColumn()) {
                return self::decode($jdoc);
            }
        }
        return false;
    }

    /**
     * Retrieves ANY record from a collection
     *
     * @param string $collection
     * @return array
     */
    function getAnyRecord($collection = 'entities') {
        $collection = $this->sanitiseCollection($collection);
        $stmt = $this->client->prepare("select jdoc from $collection limit 1");
        if ($stmt->execute()) {
            if ($jdoc = $stmt->fetchColumn()) {
                return json_decode($jdoc, true);
            }
        }
        return false;
    }

    /**
     * Retrieves a set of records from the database with given parameters, in
     * reverse chronological order
     *
     * @param array $parameters Query parameters in MongoDB format
     * @param int $limit Maximum number of records to return
     * @param int $offset Number of records to skip
     * @param string $collection The collection to interrogate (default: 'entities')
     * @return iterator|false Iterator or false, depending on success
     */
    function getRecords($fields, $parameters, $limit, $offset, $collection = 'entities')
    {
        $collection = $this->sanitiseCollection($collection);

        $variables = [];
        $query = "select jdoc from $collection"
               . " where " . $this->build_where_from_array($parameters, $variables)
               . " order by jdoc->>'created' desc limit $limit offset $offset";

        try {
            $stmt = $this->client->prepare($query);
            if ($stmt->execute($variables)) {
                $records = [];
                foreach ($stmt as $row) {
                    $records[] = self::decode($row['jdoc']);
                }
                return $records;
            }
        } catch (\Exception $e) {
            error_log($e->getMessage());
        }

        return false;
    }

    private function build_where_from_array($parameters, &$variables, &$counter=0, $keyPrefix='jdoc', $joiner='and')
    {
        $subwhere = [];
        foreach ((array) $parameters as $key => $value) {
            if ($key === '$or') {
                $subwhere[] = '(' . $this->build_where_from_array($value, $variables, $counter, $keyPrefix, 'or') . ')';
            } else {
                if (!is_array($value)) {
                    $subwhere[] = "{$keyPrefix}->>'$key' = :v{$counter}";
                    $variables["v{$counter}"] = $value;
                    $counter++;
                }
                else {
                    if (!empty($value['$in'])) {
                        $placeholders = [];
                        foreach($value['$in'] as $val) {
                            $placeholders[] = ":v{$counter}";
                            $variables["v{$counter}"] = $val;
                            $counter++;
                        }
                        $subwhere[] = "{$keyPrefix}->>'$key' in (" . implode(", ", $placeholders) . ")";
                    }
                    if (!empty($value['$not'])) {
                        // key not in array
                        if (!empty($value['$not']['$in'])) {
                            $placeholders = [];
                            foreach($value['$not']['$in'] as $val) {
                                $placeholders[] = ":v{$counter}";
                                $variables["v{$counter}"] = $val;
                                $counter++;
                            }
                            $subwhere[] = "{$keyPrefix}->>'$key' not in (" . implode(", ", $placeholders) . ")";
                        }
                        // key != value
                        else {
                            $subwhere[] = "{$keyPrefix}->>'$key' != :v{$counter}";
                            $variables["v{$counter}"] = $value['$not'];
                            $counter++;
                        }
                    }
                }
            }
        }
        return implode(" $joiner ", $subwhere);
    }

    /**
     * Export a collection to JSON.
     * @param string $collection
     * @return bool|string
     */
    function exportRecords($collection = 'entities')
    {
        // TODO
    }

    /**
     * Count the number of records that match the given parameters
     * @param array $parameters
     * @param string $collection The collection to interrogate (default: 'entities')
     * @return int
     */
    function countRecords($parameters, $collection = 'entities')
    {
        $collection = $this->sanitiseCollection($collection);

        $variables = [];
        $query = "select count(*) from $collection"
               . " where " . $this->build_where_from_array($parameters, $variables);

        $stmt = $this->client->prepare($query);
        if ($stmt->execute($variables)) {
            $count = $stmt->fetchColumn();
            return $count;
        }

        return 0;
    }

    /**
     * Remove an entity from the database
     * @param string $id
     * @return true|false
     */
    function deleteRecord($id, $collection = 'entities')
    {
        $collection = $this->sanitiseCollection($collection);
        $query = "delete from $collection where _id=:id";
        $stmt = $this->client->prepare($query);
        return $stmt->execute(['id' => $id]);
    }

}