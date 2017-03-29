<?php

/*
 * Thanks @toscho
 * See: http://wordpress.stackexchange.com/questions/49549/how-to-get-posts-by-content
 */

class ApimoProrealestateSynchronizer_PostsByContent
{
  protected static $content = '';

  protected static $like = TRUE;

  /**
   * Mapper for get_posts() with extra arguments 'content' and 'like'
   *
   * 'content' must be a string with optional '%' for free values.
   * 'like' must be TRUE or FALSE.
   *
   * @param array $args See get_posts.
   * @return array
   */
  public static function get($args)
  {
    if (isset ($args['content'])) {
      // This is TRUE by default for get_posts().
      // We need FALSE to let the WHERE filter do its work.
      $args['suppress_filters'] = FALSE;
      self::$content = $args['content'];
      add_filter('posts_where', array(__CLASS__, 'where_filter'));
    }

    isset ($args['like']) and self::$like = (bool)$like;

    return get_posts($args);
  }

  /**
   * Changes the WHERE clause.
   *
   * @param string $where
   * @return string
   */
  public static function where_filter($where)
  {
    // Make sure we run this just once.
    remove_filter('posts_where', array(__CLASS__, 'where_filter'));

    global $wpdb;
    $like = self::$like ? 'LIKE' : 'NOT LIKE';
    // Escape the searched text.
    $extra = $wpdb->prepare('%s', self::$content);

    // Reset vars for the next use.
    self::$content = '';
    self::$like = TRUE;

    return "$where AND post_content $like $extra";
  }
}