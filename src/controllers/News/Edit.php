<?php

namespace BNETDocs\Controllers\News;

use \BNETDocs\Libraries\CSRF;
use \CarlBennett\MVC\Libraries\Common;
use \BNETDocs\Libraries\Controller;
use \CarlBennett\MVC\Libraries\DatabaseDriver;
use \BNETDocs\Libraries\Exceptions\NewsPostNotFoundException;
use \BNETDocs\Libraries\Exceptions\UnspecifiedViewException;
use \BNETDocs\Libraries\Logger;
use \BNETDocs\Libraries\NewsCategory;
use \BNETDocs\Libraries\NewsPost;
use \BNETDocs\Libraries\Router;
use \BNETDocs\Libraries\User;
use \BNETDocs\Libraries\UserSession;
use \BNETDocs\Models\News\Edit as NewsEditModel;
use \BNETDocs\Views\News\EditHtml as NewsEditHtmlView;
use \DateTime;
use \DateTimeZone;
use \InvalidArgumentException;

class Edit extends Controller {

  public function run(Router &$router) {
    switch ($router->getRequestPathExtension()) {
      case "htm": case "html": case "":
        $view = new NewsEditHtmlView();
      break;
      default:
        throw new UnspecifiedViewException();
    }

    $data                   = $router->getRequestQueryArray();
    $model                  = new NewsEditModel();
    $model->category        = null;
    $model->content         = null;
    $model->csrf_id         = mt_rand();
    $model->csrf_token      = CSRF::generate($model->csrf_id, 900); // 15 mins
    $model->error           = null;
    $model->markdown        = null;
    $model->news_categories = null;
    $model->news_post_id    = (isset($data["id"]) ? $data["id"] : null);
    $model->news_post       = null;
    $model->published       = null;
    $model->title           = null;
    $model->user_session    = UserSession::load($router);
    $model->user            = (isset($model->user_session) ?
                               new User($model->user_session->user_id) : null);

    $model->acl_allowed = ($model->user &&
      $model->user->getOptionsBitmask() & User::OPTION_ACL_NEWS_MODIFY
    );

    try { $model->news_post = new NewsPost($model->news_post_id); }
    catch (NewsPostNotFoundException $e) { $model->news_post = null; }
    catch (InvalidArgumentException $e) { $model->news_post = null; }

    if ($model->news_post === null) {
      $model->error = "NOT_FOUND";
    } else {
      $flags = $model->news_post->getOptionsBitmask();

      $model->news_categories = NewsCategory::getAll();
      usort($model->news_categories, function($a, $b){
        $oA = $a->getSortId();
        $oB = $b->getSortId();
        if ($oA == $oB) return 0;
        return ($oA < $oB) ? -1 : 1;
      });

      $model->category  = $model->news_post->getCategoryId();
      $model->content   = $model->news_post->getContent(false);
      $model->markdown  = ($flags & NewsPost::OPTION_MARKDOWN);
      $model->published = ($flags & NewsPost::OPTION_PUBLISHED);
      $model->title     = $model->news_post->getTitle();

      if ($router->getRequestMethod() == "POST") {
        $this->handlePost($router, $model);
      }
    }

    ob_start();
    $view->render($model);
    $router->setResponseCode(($model->acl_allowed ? 200 : 403));
    $router->setResponseTTL(0);
    $router->setResponseHeader("Content-Type", $view->getMimeType());
    $router->setResponseContent(ob_get_contents());
    ob_end_clean();
  }

  protected function handlePost(Router &$router, NewsEditModel &$model) {
    if (!$model->acl_allowed) {
      $model->error = "ACL_NOT_SET";
      return;
    }
    if (!isset(Common::$database)) {
      Common::$database = DatabaseDriver::getDatabaseObject();
    }

    $data       = $router->getRequestBodyArray();
    $csrf_id    = (isset($data["csrf_id"   ]) ? $data["csrf_id"   ] : null);
    $csrf_token = (isset($data["csrf_token"]) ? $data["csrf_token"] : null);
    $csrf_valid = CSRF::validate($csrf_id, $csrf_token);
    $category   = (isset($data["category"  ]) ? $data["category"  ] : null);
    $title      = (isset($data["title"     ]) ? $data["title"     ] : null);
    $markdown   = (isset($data["markdown"  ]) ? $data["markdown"  ] : null);
    $content    = (isset($data["content"   ]) ? $data["content"   ] : null);
    $publish    = (isset($data["publish"   ]) ? $data["publish"   ] : null);
    $save       = (isset($data["save"      ]) ? $data["save"      ] : null);

    $model->category = $category;
    $model->title    = $title;
    $model->markdown = $markdown;
    $model->content  = $content;

    if (!$csrf_valid) {
      $model->error = "INVALID_CSRF";
      return;
    }
    CSRF::invalidate($csrf_id);

    if (empty($title)) {
      $model->error = "EMPTY_TITLE";
    } else if (empty($content)) {
      $model->error = "EMPTY_CONTENT";
    }

    $user_id = $model->user_session->user_id;

    try {

      $model->news_post->setCategoryId($model->category);
      $model->news_post->setTitle($model->title);
      $model->news_post->setMarkdown($model->markdown);
      $model->news_post->setContent($model->content);
      $model->news_post->setPublished($publish);

      $model->news_post->setEditedCount(
        $model->news_post->getEditedCount() + 1
      );
      $model->news_post->setEditedDateTime(
        new DateTime("now", new DateTimeZone("UTC"))
      );

      $success = $model->news_post->save();

    } catch (QueryException $e) {

      // SQL error occurred. We can show a friendly message to the user while
      // also notifying this problem to staff.
      Logger::logException($e);

      $success = false;

    }

    if (!$success) {
      $model->error = "INTERNAL_ERROR";
    } else {
      $model->error = false;
    }

    Logger::logEvent(
      "news_edited",
      $user_id,
      getenv("REMOTE_ADDR"),
      json_encode([
        "error"           => $model->error,
        "news_post_id"    => $model->news_post_id,
        "category_id"     => $model->news_post->getCategoryId(),
        "options_bitmask" => $model->news_post->getOptionsBitmask(),
        "title"           => $model->news_post->getTitle(),
        "content"         => $model->news_post->getContent(false),
      ])
    );
  }

}
