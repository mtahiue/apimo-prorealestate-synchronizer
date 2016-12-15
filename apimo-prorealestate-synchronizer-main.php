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
                    // Verifies if the property is not to old to be added
                    if (strtotime($property->updated_at) >= strtotime('-5 days')) {
                        // Parse the property object
                        $data = $this->parseJSONOutput($property);

                        if (null !== $data) {
                            // Creates or updates a listing
                            $this->manageListingPost($data);
                        }
                    }
                }
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
            'postTitle' => array(),
            'postContent' => array(),
            'images_url' => array(),
            'customMetaAltTitle' => $property->address,
            'customMetaPrice' => $property->price->value,
            'customMetaPricePrefix' => ($property->price->hide ? 'Price On Ask' : ''),
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
            'ct_property_type' => '',
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

        foreach ($property->areas as $area) {
            if ($area->type == 1 ||
                $area->type == 53 ||
                $area->type == 70
            ) {
                $data['customTaxBeds'] += 1;
            } else if ($area->type == 8 ||
                $area->type == 41 ||
                $area->type == 13 ||
                $area->type == 42
            ) {
                $data['customTaxBaths'] += 1;
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
        $postTitle = (isset($data['postTitle'][$this->siteLanguage])
         ? $data['postTitle'][$this->siteLanguage]
         : $data['postTitle'][0]
        );
        $postContent = (isset($data['postContent'][$this->siteLanguage])
            ? $data['postContent'][$this->siteLanguage]
            : $data['postContent'][0]
        );
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
        $post = get_page_by_title($postTitle, OBJECT, 'listings');

        if (null === $post) {
            // Insert post and retrieve postId
            $postId = wp_insert_post($postInformation);
        }
        else {
            $postInformation['ID'] = $post->ID;
            $postId = $post->ID;

            // Update post
            wp_update_post($postInformation);
        }

        // Updates the image and the featured image with the first given image
        $imagesIds = array();

        foreach ($imagesUrl as $imageId => $imageUrl) {
            // Retrieve the media from its name
            $media = $this->isMediaPosted($imageUrl);

            // Add the media for the main post
            if (!$media) {
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
        wp_set_post_terms($postId, $ctPropertyType, 'property_type', false);
        wp_set_post_terms($postId, $customTaxBeds, 'beds', false);
        wp_set_post_terms($postId, $customTaxBaths, 'baths', false);
        wp_set_post_terms($postId, $ctCtStatus, 'ct_status', false);
        wp_set_post_terms($postId, $customTaxCity, 'city', false);
        wp_set_post_terms($postId, $customTaxState, 'state', false);
        wp_set_post_terms($postId, $customTaxZip, 'zipcode', false);
        wp_set_post_terms($postId, $customTaxCountry, 'country', false);
        wp_set_post_terms($postId, $customTaxCommunity, 'community', false);
        wp_set_post_terms($postId, $customTaxFeat, 'additional_features', false);
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
            wp_schedule_event(time(), 'hourly', 'apimo_prorealestate_synchronizer_hourly_event');
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
