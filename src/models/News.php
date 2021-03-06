<?php

namespace BNETDocs\Models;

use \BNETDocs\Libraries\Model;

class News extends Model {

  public $acl_allowed;
  public $news_posts;
  public $pagination;
  public $user;
  public $user_session;

  public function __construct() {
    parent::__construct();
    $this->acl_allowed  = null;
    $this->news_posts   = null;
    $this->pagination   = null;
    $this->user         = null;
    $this->user_session = null;
  }

}
