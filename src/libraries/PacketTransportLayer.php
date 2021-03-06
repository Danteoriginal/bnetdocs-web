<?php

namespace BNETDocs\Libraries;

use \CarlBennett\MVC\Libraries\Common;
use \CarlBennett\MVC\Libraries\Database;
use \CarlBennett\MVC\Libraries\DatabaseDriver;
use \BNETDocs\Libraries\Exceptions\PacketTransportLayerNotFoundException;
use \BNETDocs\Libraries\Exceptions\QueryException;
use \InvalidArgumentException;
use \PDO;
use \PDOException;
use \StdClass;

class PacketTransportLayer {

  protected $id;
  protected $label;
  protected $tag;

  public function __construct($data) {
    if (is_numeric($data)) {
      $this->id    = (int) $data;
      $this->label = null;
      $this->tag   = null;
      $this->refresh();
    } else if ($data instanceof StdClass) {
      self::normalize($data);
      $this->id    = $data->id;
      $this->label = $data->label;
      $this->tag   = $data->tag;
    } else {
      throw new InvalidArgumentException("Cannot use data argument");
    }
  }

  public static function getAllPacketTransportLayers() {
    $cache_key = "bnetdocs-packettransportlayers";
    $cache_val = Common::$cache->get($cache_key);
    if ($cache_val !== false && !empty($cache_val)) {
      $ids     = explode(",", $cache_val);
      $objects = [];
      foreach ($ids as $id) {
        $objects[] = new self($id);
      }
      return $objects;
    }
    if (!isset(Common::$database)) {
      Common::$database = DatabaseDriver::getDatabaseObject();
    }
    try {
      $stmt = Common::$database->prepare("
        SELECT
          `id`,
          `label`,
          `tag`
        FROM `packet_transport_layers`
        ORDER BY `id` ASC;
      ");
      if (!$stmt->execute()) {
        throw new QueryException("Cannot refresh packet transport layers");
      }
      $ids     = [];
      $objects = [];
      while ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
        $ids[]     = (int) $row->id;
        $objects[] = new self($row);
        Common::$cache->set(
          "bnetdocs-packettransportlayer-" . $row->id, serialize($row), 300
        );
      }
      $stmt->closeCursor();
      Common::$cache->set($cache_key, implode(",", $ids), 300);
      return $objects;
    } catch (PDOException $e) {
      throw new QueryException("Cannot refresh packet transport layers", $e);
    }
    return null;
  }

  public function getId() {
    return $this->id;
  }

  public function getLabel() {
    return $this->label;
  }

  public function getTag() {
    return $this->tag;
  }

  protected static function normalize(StdClass &$data) {
    $data->id    = (int)    $data->id;
    $data->label = (string) $data->label;
    $data->tag   = (string) $data->tag;

    return true;
  }

  public function refresh() {
    $cache_key = "bnetdocs-packettransportlayer-" . $this->id;
    $cache_val = Common::$cache->get($cache_key);
    if ($cache_val !== false) {
      $cache_val = unserialize($cache_val);
      $this->label = $cache_val->label;
      $this->tag   = $cache_val->tag;
      return true;
    }
    if (!isset(Common::$database)) {
      Common::$database = DatabaseDriver::getDatabaseObject();
    }
    try {
      $stmt = Common::$database->prepare("
        SELECT
          `id`,
          `label`,
          `tag`
        FROM `packet_transport_layers`
        WHERE `id` = :id
        LIMIT 1;
      ");
      $stmt->bindParam(":id", $this->id, PDO::PARAM_INT);
      if (!$stmt->execute()) {
        throw new QueryException("Cannot refresh packet transport layer");
      } else if ($stmt->rowCount() == 0) {
        throw new PacketTransportLayerNotFoundException($this->id);
      }
      $row = $stmt->fetch(PDO::FETCH_OBJ);
      $stmt->closeCursor();
      self::normalize($row);
      $this->label = $row->label;
      $this->tag   = $row->tag;
      Common::$cache->set($cache_key, serialize($row), 300);
      return true;
    } catch (PDOException $e) {
      throw new QueryException("Cannot refresh packet transport layer", $e);
    }
    return false;
  }

}
