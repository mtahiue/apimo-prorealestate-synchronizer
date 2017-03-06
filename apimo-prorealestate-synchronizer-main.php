<?php

// Includes the core classes
require_once(ABSPATH . 'wp-admin/includes/media.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/image.php');
require_once(ABSPATH . 'wp-admin/includes/plugin.php');
if (!class_exists('WP_Http')) {
  require_once(ABSPATH . WPINC . '/class-http.php');
}

class ApimoProrealestateSynchronizer
{
  /**
   * Instance of this class
   *
   * @var ApimoProrealestateSynchronizer
   */
  private static $instance;

  /**
   * @var string
   */
  private $siteLanguage;

  /**
   * Constructor
   *
   * Initializes the plugin so that the synchronization begins automatically every hour,
   * when a visitor comes to the website
   */
  public function __construct()
  {
    // Retrieve site language
    $this->siteLanguage = $this->getSiteLanguage();

    // Trigger the synchronizer event every hour only if the API settings have been configured
    if (is_array(get_option('apimo_prorealestate_synchronizer_settings_options'))) {
      if (isset(get_option('apimo_prorealestate_synchronizer_settings_options')['apimo_api_provider']) &&
        isset(get_option('apimo_prorealestate_synchronizer_settings_options')['apimo_api_token']) &&
        isset(get_option('apimo_prorealestate_synchronizer_settings_options')['apimo_api_agency'])
      ) {
        add_action(
          'apimo_prorealestate_synchronizer_hourly_event',
          array($this, 'synchronize')
        );

        // For debug only, you can uncomment this line to trigger the event every time the blog is loaded
        //add_action('init', array($this, 'synchronize'));
      }
    }
  }

  /**
   * Retrieve site language
   */
  private function getSiteLanguage()
  {
    return substr(get_bloginfo('language'), 0, 2);
  }

  /**
   * Creates an instance of this class
   *
   * @access public
   * @return ApimoProrealestateSynchronizer An instance of this class
   */
  public static function getInstance()
  {
    if (null === self::$instance) {
      self::$instance = new self;
    }

    return self::$instance;
  }

  /**
   * Synchronizes Apimo and Pro Real Estate plugnins estates
   *
   * @access public
   */
  public function synchronize()
  {
    // Gets the properties
    $return = $this->callApimoAPI(
      'https://api.apimo.pro/agencies/'
      . get_option('apimo_prorealestate_synchronizer_settings_options')['apimo_api_agency']
      . '/properties',
      'GET'
    );

    // Parses the JSON into an array of properties object
    $jsonBody = json_decode($return['body']);

    if (is_object($jsonBody) && isset($jsonBody->properties)) {
      $properties = $jsonBody->properties;

      if (is_array($properties)) {
        foreach ($properties as $property) {
          // Parse the property object
          $data = $this->parseJSONOutput($property);

          if (null !== $data) {
            // Creates or updates a listing
            $this->manageListingPost($data);
          }
        }

        $this->deleteOldListingPost($properties);
      }
    }
  }

  /**
   * Parses a JSON body and extracts selected values
   *
   * @access private
   * @param stdClass $property
   * @return array $data
   */
  private function parseJSONOutput($property)
  {
    $data = array(
      'user' => $property->user,
      'updated_at' => $property->updated_at,
      'postTitle' => array(),
      'postContent' => array(),
      'images_url' => array(),
      'customMetaAltTitle' => $property->address,
      'customMetaPrice' => (!$property->price->value ? __('Price on ask') : $property->price->value),
      'customMetaPricePrefix' => '',
      'customMetaPricePostfix' => '',
      'customMetaSqFt' => preg_replace('#,#', '.', $property->area->value),
      'customMetaVideoURL' => '',
      'customMetaMLS' => $property->id,
      'customMetaLatLng' => (
      $property->latitude && $property->longitude
        ? $property->latitude . ', ' . $property->longitude
        : ''
      ),
      'customMetaExpireListing' => '',
      'ct_property_type' => $property->type,
      'rooms' => 0,
      'beds' => 0,
      'customTaxBeds' => 0,
      'customTaxBaths' => 0,
      'ct_ct_status' => '',
      'customTaxCity' => $property->city->name,
      'customTaxState' => '',
      'customTaxZip' => $property->city->zipcode,
      'customTaxCountry' => $property->country,
      'customTaxCommunity' => '',
      'customTaxFeat' => '',
    );

    foreach ($property->comments as $comment) {
      $data['postTitle'][$comment->language] = $comment->title;
      $data['postContent'][$comment->language] = $comment->comment;
    }

    $data['rooms'] = $property->rooms;
    $data['beds'] = $property->bedrooms;

    foreach ($property->areas as $area) {
      if ($area->type == 1 ||
        $area->type == 53 ||
        $area->type == 70
      ) {
        $data['customTaxBeds'] += $area->number;
      } else if ($area->type == 8 ||
        $area->type == 41 ||
        $area->type == 13 ||
        $area->type == 42
      ) {
        $data['customTaxBaths'] += $area->number;
      }
    }

    foreach ($property->pictures as $picture) {
      $data['images_url'][] = $picture->url;
    }

    return $data;
  }

  /**
   * Creates or updates a listing post
   *
   * @param array $data
   */
  private function manageListingPost($data)
  {
    // Converts the data for later use
    $postTitle = $data['postTitle'][$this->siteLanguage];
    if ($postTitle == '') {
      foreach ($data['postTitle'] as $lang => $title) {
        $postTitle = $title;
      }
    }

    $postContent = $data['postContent'][$this->siteLanguage];
    if ($postContent == '') {
      foreach ($data['postContent'] as $lang => $title) {
        $postContent = $title;
      }
    }

    $postUpdatedAt = $data['updated_at'];
    $imagesUrl = $data['images_url'];
    $customMetaAltTitle = $data['customMetaAltTitle'];
    $ctPrice = str_replace(array('.', ','), '', $data['customMetaPrice']);
    $customMetaPricePrefix = $data['customMetaPricePrefix'];
    $customMetaPricePostfix = $data['customMetaPricePostfix'];
    $customMetaSqFt = $data['customMetaSqFt'];
    $customMetaVideoURL = $data['customMetaVideoURL'];
    $customMetaMLS = $data['customMetaMLS'];
    $customMetaLatLng = $data['customMetaLatLng'];
    $customMetaExpireListing = $data['customMetaExpireListing'];
    $ctPropertyType = $data['ct_property_type'];
    $rooms = $data['rooms'];
    $beds = $data['beds'];
    $customTaxBeds = $data['customTaxBeds'];
    $customTaxBaths = $data['customTaxBaths'];
    $ctCtStatus = $data['ct_ct_status'];
    $customTaxCity = $data['customTaxCity'];
    $customTaxState = $data['customTaxState'];
    $customTaxZip = $data['customTaxZip'];
    $customTaxCountry = $data['customTaxCountry'];
    $customTaxCommunity = $data['customTaxCommunity'];
    $customTaxFeat = $data['customTaxFeat'];

    // Creates a listing post
    $postInformation = array(
      'post_title' => wp_strip_all_tags(trim($postTitle)),
      'post_content' => $postContent,
      'post_type' => 'listings',
      'post_status' => 'publish',
    );

    // Verifies if the listing does not already exist
    if ($postTitle != '') {
      $post = get_page_by_title($postTitle, OBJECT, 'listings');

      if (NULL === $post) {
        // Insert post and retrieve postId
        $postId = wp_insert_post($postInformation);
      }
      else {
        // Verifies if the property is not to old to be added
        if (strtotime($postUpdatedAt) >= strtotime('-5 days')) {
          return;
        }

        $postInformation['ID'] = $post->ID;
        $postId = $post->ID;

        // Update post
        wp_update_post($postInformation);
      }

      // Delete attachments
      $attachments = get_attached_media('image', $postId);
      foreach ($attachments as $attachment) {
        $imageStillPresent = false;
        foreach ($imagesUrl as $imageId => $imageUrl) {
          if ($attachment->post_title == $this->getFileNameFromURL($imageUrl)) {
            $imageStillPresent = true;
            unset($imagesUrl[$imageId]);
          }
        }
        if (!$imageStillPresent) {
          wp_delete_attachment($attachment->ID, TRUE);
        }
      }

      // Updates the image and the featured image with the first given image
      $imagesIds = array();

      foreach ($imagesUrl as $imageId => $imageUrl) {
        media_sideload_image($imageUrl, $postId);

        // Retrieve the last inserted media
        $args = array(
          'post_type' => 'attachment',
          'numberposts' => 1,
          'orderby' => 'date',
          'order' => 'DESC',
        );
        $medias = get_posts($args);

        // Just one media, but still an array returned by get_posts
        foreach ($medias as $attachment) {
          // Make sure the media's name is equal to the file name
          wp_update_post(array(
            'ID' => $attachment->ID,
            'post_name' => $this->getFileNameFromURL($imageUrl),
            'post_title' => $this->getFileNameFromURL($imageUrl),
          ));
          $media = $attachment;
        }

        if (!empty($media) && !is_wp_error($media)) {
          // Set the first image as the thumbnail
          if (empty($imagesIds)) {
            set_post_thumbnail($postId, $media->ID);
          }

          $imagesIds[] = $media->ID;
        }
      }

      $positions = implode(',', $imagesIds);
      update_post_meta($postId, '_ct_images_position', $positions);

      // Updates custom meta
      update_post_meta($postId, '_ct_listing_alt_title', esc_attr(strip_tags($customMetaAltTitle)));
      update_post_meta($postId, '_ct_price', esc_attr(strip_tags($ctPrice)));
      update_post_meta($postId, '_ct_price_prefix', esc_attr(strip_tags($customMetaPricePrefix)));
      update_post_meta($postId, '_ct_price_postfix', esc_attr(strip_tags($customMetaPricePostfix)));
      update_post_meta($postId, '_ct_sqft', esc_attr(strip_tags($customMetaSqFt)));
      update_post_meta($postId, '_ct_video', esc_attr(strip_tags($customMetaVideoURL)));
      update_post_meta($postId, '_ct_mls', esc_attr(strip_tags($customMetaMLS)));
      update_post_meta($postId, '_ct_latlng', esc_attr(strip_tags($customMetaLatLng)));
      update_post_meta($postId, '_ct_listing_expire', esc_attr(strip_tags($customMetaExpireListing)));

      // Updates custom taxonomies
      wp_set_post_terms($postId, $ctPropertyType, 'property_type', FALSE);
      wp_set_post_terms($postId, $beds, 'beds', FALSE);
      wp_set_post_terms($postId, $customTaxBaths, 'baths', FALSE);
      wp_set_post_terms($postId, $ctCtStatus, 'ct_status', FALSE);
      wp_set_post_terms($postId, $customTaxState, 'state', FALSE);
      wp_set_post_terms($postId, $customTaxCity, 'city', FALSE);
      wp_set_post_terms($postId, $customTaxZip, 'zipcode', FALSE);
      wp_set_post_terms($postId, $customTaxCountry, 'country', FALSE);
      wp_set_post_terms($postId, $customTaxCommunity, 'community', FALSE);
      wp_set_post_terms($postId, $rooms, 'additional_features', FALSE);
    }
  }

  /**
   * Delete old listings
   *
   * @param $properties
   */
  private function deleteOldListingPost($properties)
  {
    $parsedProperties = array();

    // Parse once for all the properties
    foreach ($properties as $property) {
      $parsedProperties[] = $this->parseJSONOutput($property);
    }

    // Retrieve the current posts
    $posts = get_posts(array(
      'post_type' => 'listings',
      'numberposts' => -1,
    ));

    foreach ($posts as $post) {
      $postMustBeRemoved = true;

      // Verifies if the post exists
      foreach ($parsedProperties as $property) {
        $postTitle = $property['postTitle'][$this->siteLanguage];
        if ($postTitle == '') {
          foreach ($property['postTitle'] as $lang => $title) {
            $postTitle = $title;
          }
        }

        if ($postTitle == $post->post_title) {
          $postMustBeRemoved = false;
          break;
        }
      }

      // If not, we can execute the action
      if ($postMustBeRemoved) {
        // Delete the post
        wp_delete_post($post->ID);
      }
    }
  }

  /**
   * Verifies if a media is already posted or not for a given image URL.
   *
   * @access private
   * @param string $imageUrl
   * @return object
   */
  private function isMediaPosted($imageUrl)
  {
    $imageName = $this->getFileNameFromURL($imageUrl);

    $args = array(
      'post_type' => 'attachment',
      'posts_per_page' => -1,
      'post_status' => 'any',
      's' => $imageName,
    );

    $medias = get_posts($args);

    if (isset($medias) && is_array($medias)) {
      foreach ($medias as $media) {
        return $media;
      }
    }

    return null;
  }

  /**
   * Return the filename for a given URL.
   *
   * @access private
   * @param string $imageUrl
   * @return string $filename
   */
  private function getFileNameFromURL($imageUrl)
  {
    $imageUrlData = pathinfo($imageUrl);
    return $imageUrlData['filename'];
  }

  /**
   * Calls the Apimo API
   *
   * @access private
   * @param string $url The API URL to call
   * @param string $method The HTTP method to use
   * @param array $body The JSON formatted body to send to the API
   * @return array $response
   */
  private function callApimoAPI($url, $method, $body = null)
  {
    $headers = array(
      'Authorization' => 'Basic ' . base64_encode(
          get_option('apimo_prorealestate_synchronizer_settings_options')['apimo_api_provider'] . ':' .
          get_option('apimo_prorealestate_synchronizer_settings_options')['apimo_api_token']
        ),
      'content-type' => 'application/json',
    );

    if (null === $body || !is_array($body)) {
      $body = array();
    }

    if (!isset($body['limit'])) {
      $body['limit'] = 100;
    }
    if (!isset($body['offset'])) {
      $body['offset'] = 0;
    }

    $request = new WP_Http;
    $response = $request->request($url, array(
      'method' => $method,
      'headers' => $headers,
      'body' => $body,
    ));

    if (is_array($response) && !is_wp_error($response)) {
      $headers = $response['headers']; // array of http header lines
      $body = $response['body']; // use the content
    } else {
      $body = $response->get_error_message();
    }

    return array(
      'headers' => $headers,
      'body' => $body,
    );
  }

  /**
   * Activation hook
   */
  public function install()
  {
    if (!wp_next_scheduled('apimo_prorealestate_synchronizer_hourly_event')) {
      wp_schedule_event(time(), 'daily', 'apimo_prorealestate_synchronizer_hourly_event');
    }
  }

  /**
   * Deactivation hook
   */
  public function uninstall()
  {
    wp_clear_scheduled_hook('apimo_prorealestate_synchronizer_hourly_event');
  }
}
