<?php

namespace BNETDocs\Libraries;

use \CarlBennett\MVC\Libraries\Database;
use \CarlBennett\MVC\Libraries\DatabaseDriver;
use \BNETDocs\Libraries\Exceptions\CommentNotFoundException;
use \BNETDocs\Libraries\Exceptions\QueryException;
use \BNETDocs\Libraries\User;
use \CarlBennett\MVC\Libraries\Common;
use \CarlBennett\MVC\Libraries\Markdown;
use \DateTime;
use \DateTimeZone;
use \InvalidArgumentException;
use \JsonSerializable;
use \PDO;
use \PDOException;
use \StdClass;

class Comment implements JsonSerializable {

  const PARENT_TYPE_DOCUMENT  = 0;
  const PARENT_TYPE_COMMENT   = 1;
  const PARENT_TYPE_NEWS_POST = 2;
  const PARENT_TYPE_PACKET    = 3;
  const PARENT_TYPE_SERVER    = 4;
  const PARENT_TYPE_USER      = 5;

  protected $content;
  protected $created_datetime;
  protected $edited_count;
  protected $edited_datetime;
  protected $id;
  protected $parent_id;
  protected $parent_type;
  protected $user_id;

  public function __construct($data) {
    if (is_numeric($data)) {
      $this->content          = null;
      $this->created_datetime = null;
      $this->edited_count     = null;
      $this->edited_datetime  = null;
      $this->id               = (int) $data;
      $this->parent_id        = null;
      $this->parent_type      = null;
      $this->user_id          = null;
      $this->refresh();
    } else if ($data instanceof StdClass) {
      self::normalize($data);
      $this->content          = $data->content;
      $this->created_datetime = $data->created_datetime;
      $this->edited_count     = $data->edited_count;
      $this->edited_datetime  = $data->edited_datetime;
      $this->id               = $data->id;
      $this->parent_id        = $data->parent_id;
      $this->parent_type      = $data->parent_type;
      $this->user_id          = $data->user_id;
    } else {
      throw new InvalidArgumentException("Cannot use data argument");
    }
  }

  public static function create($parent_type, $parent_id, $user_id, $content) {
    if (!isset(Common::$database)) {
      Common::$database = DatabaseDriver::getDatabaseObject();
    }
    $successful = false;
    try {
      $stmt = Common::$database->prepare("
        INSERT INTO `comments` (
          `id`, `parent_type`, `parent_id`, `user_id`, `created_datetime`,
          `edited_count`, `edited_datetime`, `content`
        ) VALUES (
          NULL, :parent_type, :parent_id, :user_id, NOW(), 0, NULL, :content
        );
      ");
      $stmt->bindParam(":parent_type", $parent_type, PDO::PARAM_INT);
      $stmt->bindParam(":parent_id", $parent_id, PDO::PARAM_INT);
      $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
      $stmt->bindParam(":content", $content, PDO::PARAM_STR);
      $successful = $stmt->execute();
      $stmt->closeCursor();
    } catch (PDOException $e) {
      throw new QueryException("Cannot create comment", $e);
    } finally {
      $ck = "bnetdocs-comment-" . $parent_type . "-" . $parent_id;
      Common::$cache->delete($ck);
      return $successful;
    }
  }

  public static function delete($id, $parent_type, $parent_id) {
    if (!isset(Common::$database)) {
      Common::$database = DatabaseDriver::getDatabaseObject();
    }
    $successful = false;
    try {
      $stmt = Common::$database->prepare("
        DELETE FROM `comments` WHERE `id` = :id LIMIT 1;
      ");
      $stmt->bindParam(":id", $id, PDO::PARAM_INT);
      $successful = $stmt->execute();
      $stmt->closeCursor();
      if ($successful) {
        Common::$cache->delete("bnetdocs-comment-" . (int) $id);
        Common::$cache->delete(
          "bnetdocs-comment-" . (int) $parent_type . "-" . (int) $parent_id
        );
      }
    } catch (PDOException $e) {
      throw new QueryException("Cannot delete comment", $e);
    } finally {
      return $successful;
    }
  }

  public static function getAll($parent_type, $parent_id) {
    $ck = "bnetdocs-comment-" . $parent_type . "-" . $parent_id;
    $cv = Common::$cache->get($ck);
    if ($cv !== false && !empty($cv)) {
      $ids     = explode(",", $cv);
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
          `content`,
          `created_datetime`,
          `edited_count`,
          `edited_datetime`,
          `id`,
          `parent_id`,
          `parent_type`,
          `user_id`
        FROM `comments`
        WHERE
          `parent_type` = :parent_type AND
          `parent_id` = :parent_id
        ORDER BY
          `created_datetime` ASC,
          `id` ASC
        ;
      ");
      $stmt->bindParam(":parent_type", $parent_type, PDO::PARAM_INT);
      $stmt->bindParam(":parent_id", $parent_id, PDO::PARAM_INT);
      if (!$stmt->execute()) {
        throw new QueryException("Cannot refresh comment");
      }
      $ids     = [];
      $objects = [];
      while ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
        $ids[]     = (int) $row->id;
        $objects[] = new self($row);
        Common::$cache->set(
          "bnetdocs-comment-" . $row->id, serialize($row), 300
        );
      }
      $stmt->closeCursor();
      Common::$cache->set($ck, implode(",", $ids), 300);
      return $objects;
    } catch (PDOException $e) {
      throw new QueryException("Cannot refresh comment", $e);
    }
    return null;
  }

  public function getContent($prepare) {
    if (!$prepare) {
      return $this->content;
    }
    $md = new Markdown();
    return $md->text(filter_var($this->content, FILTER_SANITIZE_FULL_SPECIAL_CHARS));
  }

  public function getCreatedDateTime() {
    if (is_null($this->created_datetime)) {
      return $this->created_datetime;
    } else {
      $tz = new DateTimeZone("UTC");
      $dt = new DateTime($this->created_datetime);
      $dt->setTimezone($tz);
      return $dt;
    }
  }

  public function getEditedCount() {
    return $this->edited_count;
  }

  public function getEditedDateTime() {
    if (is_null($this->edited_datetime)) {
      return $this->edited_datetime;
    } else {
      $tz = new DateTimeZone("UTC");
      $dt = new DateTime($this->edited_datetime);
      $dt->setTimezone($tz);
      return $dt;
    }
  }

  public function getId() {
    return $this->id;
  }

  public function getParentId() {
    return $this->parent_id;
  }

  public function getParentType() {
    return $this->parent_type;
  }

  public function getUser() {
    if (is_null($this->user_id)) return null;
    return new User($this->user_id);
  }

  public function getUserId() {
    return $this->user_id;
  }

  public function jsonSerialize() {
    $created_datetime = $this->getCreatedDateTime();
    if (!is_null($created_datetime)) $created_datetime = [
      "iso"  => $created_datetime->format("r"),
      "unix" => $created_datetime->getTimestamp(),
    ];

    $edited_datetime = $this->getEditedDateTime();
    if (!is_null($edited_datetime)) $edited_datetime = [
      "iso"  => $edited_datetime->format("r"),
      "unix" => $edited_datetime->getTimestamp(),
    ];

    return [
      "content"          => $this->getContent(true),
      "created_datetime" => $created_datetime,
      "edited_count"     => $this->getEditedCount(),
      "edited_datetime"  => $edited_datetime,
      "id"               => $this->getId(),
      "parent_id"        => $this->getParentId(),
      "parent_type"      => $this->getParentType(),
      "user"             => $this->getUser(),
    ];
  }

  protected static function normalize(StdClass &$data) {
    $data->content          = (string) $data->content;
    $data->created_datetime = (string) $data->created_datetime;
    $data->edited_count     = (int)    $data->edited_count;
    $data->edited_datetime  = (string) $data->edited_datetime;
    $data->id               = (int)    $data->id;
    $data->parent_id        = (int)    $data->parent_id;
    $data->parent_type      = (int)    $data->parent_type;
    $data->user_id          = (int)    $data->user_id;

    return true;
  }

  public function refresh() {
    $ck = "bnetdocs-comment-" . $this->id;
    $cv = Common::$cache->get($ck);
    if ($cv !== false) {
      $cv = unserialize($cv);
      $this->content          = $cv->content;
      $this->created_datetime = $cv->created_datetime;
      $this->edited_count     = $cv->edited_count;
      $this->edited_datetime  = $cv->edited_datetime;
      $this->id               = $cv->id;
      $this->parent_id        = $cv->parent_id;
      $this->parent_type      = $cv->parent_type;
      $this->user_id          = $cv->user_id;
      return true;
    }
    if (!isset(Common::$database)) {
      Common::$database = DatabaseDriver::getDatabaseObject();
    }
    try {
      $stmt = Common::$database->prepare("
        SELECT
          `content`,
          `created_datetime`,
          `edited_count`,
          `edited_datetime`,
          `id`,
          `parent_id`,
          `parent_type`,
          `user_id`
        FROM `comments`
        WHERE `id` = :id
        LIMIT 1;
      ");
      $stmt->bindParam(":id", $this->id, PDO::PARAM_INT);
      if (!$stmt->execute()) {
        throw new QueryException("Cannot refresh comment");
      } else if ($stmt->rowCount() == 0) {
        throw new CommentNotFoundException($this->id);
      }
      $row = $stmt->fetch(PDO::FETCH_OBJ);
      $stmt->closeCursor();
      self::normalize($row);
      $this->content          = $row->content;
      $this->created_datetime = $row->created_datetime;
      $this->edited_count     = $row->edited_count;
      $this->edited_datetime  = $row->edited_datetime;
      $this->id               = $row->id;
      $this->parent_id        = $row->parent_id;
      $this->parent_type      = $row->parent_type;
      $this->user_id          = $row->user_id;
      Common::$cache->set($ck, serialize($row), 300);
      return true;
    } catch (PDOException $e) {
      throw new QueryException("Cannot refresh comment", $e);
    }
    return false;
  }

}
