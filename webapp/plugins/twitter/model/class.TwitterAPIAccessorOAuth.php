<?php
/**
 *
 * ThinkUp/webapp/plugins/twitter/model/class.TwitterAPIAccessorOAuth.php
 *
 * Copyright (c) 2009-2010 Gina Trapani
 *
 * LICENSE:
 *
 * This file is part of ThinkUp (http://thinkupapp.com).
 *
 * ThinkUp is free software: you can redistribute it and/or modify it under the terms of the GNU General Public
 * License as published by the Free Software Foundation, either version 2 of the License, or (at your option) any
 * later version.
 *
 * ThinkUp is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more
 * details.
 *
 * You should have received a copy of the GNU General Public License along with ThinkUp.  If not, see
 * <http://www.gnu.org/licenses/>.
 *
 *
 * Twitter API Accessor
 * Accesses the Twitter.com API via OAuth authentication.
 *
 * @license http://www.gnu.org/licenses/gpl.html
 * @copyright 2009-2010 Gina Trapani
 * @author Gina Trapani <ginatrapani[at]gmail[dot]com>
 */

class TwitterAPIAccessorOAuth {
    /**
     * @var boolean
     */
    var $available = true;
    /**
     * @var str
     */
    var $next_api_reset = null;
    /**
     * @var str
     */
    var $cURL_source;
    /**
     * @var TwitterOAuthThinkUp
     */
    var $to;
    /**
     * @var str
     */
    var $oauth_access_token;
    /**
     * @var str
     */
    var $oauth_access_token_secret;
    /**
     * @var int
     */
    var $next_cursor;
    /**
     * @var int defaults to 3
     */
   // this is now the fallback default- should be set in plugin config
    var $total_errors_to_tolerate = 3;
    /**
     * Tally of the API errors returned during a given run
     * When this number equals or exceeds the $total_errors_to_tolerate, the crawling stops
     * @var ints
     */
    var $total_errors_so_far = 0;
    /**
     * The maximum number of API calls that should be made during a given crawl. This setting is here to ratchet
     * down activity for whitelisted Twitter accounts which get 20k calls per hour.
     * @var int Defaults to 350
     */
    var $max_api_calls_per_crawl = 350;
    /**
     * Constructor
     * @param str $oauth_access_token
     * @param str $oauth_access_token_secret
     * @param str $oauth_consumer_key
     * @param str $oauth_consumer_secret
     * @param int $num_twitter_errors
     * @param int $max_api_calls_per_crawl
     * @return TwitterAPIAccessorOAuth
     */
    public function __construct($oauth_access_token, $oauth_access_token_secret, $oauth_consumer_key,
    $oauth_consumer_secret, $num_twitter_errors, $max_api_calls_per_crawl) {
        $this->$oauth_access_token = $oauth_access_token;
        $this->$oauth_access_token_secret = $oauth_access_token_secret;

        $this->to = new TwitterOAuthThinkUp($oauth_consumer_key, $oauth_consumer_secret, $this->$oauth_access_token,
        $this->$oauth_access_token_secret);
        $this->cURL_source = $this->prepAPI();

        $logger = Logger::getInstance();
        $te = (int) $num_twitter_errors;
        if (is_integer($te) && $te > 0) {
            $this->total_errors_to_tolerate = $te;
        }

        $this->max_api_calls_per_crawl = $max_api_calls_per_crawl;
        $logger->logInfo('Errors to tolerate: ' . $this->total_errors_to_tolerate, __METHOD__.','.__LINE__);
    }

    /**
     * Verify OAuth Twitter credentials.
     * @return mixed -1 if not authorized; array of user data if authorized
     */
    public function verifyCredentials() {
        $auth = $this->cURL_source['credentials'];
        list($cURL_status, $twitter_data) = $this->apiRequestFromWebapp($auth);
        if ($cURL_status == 200) {
            $user = $this->parseXML($twitter_data);
            return $user[0];
        } else {
            return - 1;
        }
    }
    /**
     * Make an API request from the webapp (as opposed to the crawler)
     * @param str $url
     * @return array (cURL status, cURL retrieved content)
     */
    public function apiRequestFromWebapp($url) {
        $content = $this->to->OAuthRequest($url, 'GET', array());
        $status = $this->to->lastStatusCode();
        return array($status, $content);
    }

    /**
     * Define how to access the Twitter API.
     * @return array URLs by API call.
     */
    public function prepAPI() {
        # Define how to access Twitter API
        $api_domain = 'https://api.twitter.com/1';
        $api_format = 'xml';
        $search_domain = 'http://search.twitter.com';
        $search_format = 'json';

        # Define method paths ... [id] is a placeholder
        $api_method = array(
            "end_session"=>"/account/end_session", "rate_limit"=>"/account/rate_limit_status", 
            "delivery_device"=>"/account/update_delivery_device", "location"=>"/account/update_location", 
            "profile"=>"/account/update_profile", "profile_background"=>"/account/update_profile_background_image", 
            "profile_colors"=>"/account/update_profile_colors", "profile_image"=>"/account/update_profile_image", 
            "credentials"=>"/account/verify_credentials", "block"=>"/blocks/create/[id]", 
            "remove_block"=>"/blocks/destroy/[id]", "messages_received"=>"/direct_messages", 
            "delete_message"=>"/direct_messages/destroy/[id]", "post_message"=>"/direct_messages/new", 
            "messages_sent"=>"/direct_messages/sent", "favorites"=>"/favorites/[id]", 
            "create_favorite"=>"/favorites/create/[id]", "remove_favorite"=>"/favorites/destroy/[id]", 
            "followers_ids"=>"/followers/ids", "following_ids"=>"/friends/ids", "follow"=>"/friendships/create/[id]", 
            "unfollow"=>"/friendships/destroy/[id]", "confirm_follow"=>"/friendships/exists", 
            "show_friendship"=>"/friendships/show", "test"=>"/help/test", 
            "turn_on_notification"=>"/notifications/follow/[id]", "turn_off_notification"=>"/notifications/leave/[id]",
            "delete_tweet"=>"/statuses/destroy/[id]", "followers"=>"/statuses/followers", 
            "following"=>"/statuses/friends", "friends_timeline"=>"/statuses/friends_timeline", 
            "public_timeline"=>"/statuses/public_timeline", "mentions"=>"/statuses/mentions", 
            "show_tweet"=>"/statuses/show/[id]", "post_tweet"=>"/statuses/update", 
            "user_timeline"=>"/statuses/user_timeline/[id]", "show_user"=>"/users/show/[id]", 
            "retweeted_by_me"=>"/statuses/retweeted_by_me", "retweets_of_me"=>"/statuses/retweets_of_me", 
            "retweeted_by"=>"/statuses/[id]/retweeted_by");
        # Construct cURL sources
        foreach ($api_method as $key=>$value) {
            $urls[$key] = $api_domain.$value.".".$api_format;
        }
        $urls['search'] = $search_domain."/search.".$search_format;
        $urls['search_web'] = $search_domain."/search";
        $urls['trends'] = $search_domain."/trends.json";

        return $urls;
    }

    /**
     * Parse JSON list of tweets.
     * @param str $data JSON list of tweets.
     * @return array Posts
     */
    public function parseJSON($data) {
        $pj = json_decode($data);
        //print_r($pj);
        $parsed_payload = array();
        foreach ($pj->results as $p) {
            $parsed_payload[] = array('post_id'=>$p->id,
            'author_user_id'=>$p->from_user_id, 'user_id'=>$p->from_user_id,
            'pub_date'=>gmdate("Y-m-d H:i:s", strToTime($p->created_at)), 'post_text'=>$p->text, 
            'author_username'=>$p->from_user, 'user_name'=>$p->from_user,
            'in_reply_to_user_id'=>$p->to_user_id, 
            'author_avatar'=>$p->profile_image_url, 'avatar'=>$p->profile_image_url,
            'in_reply_to_post_id'=>'', 'author_fullname'=>'', 'full_name'=>'', 'source'=>'twitter', 
            'location'=>'', 'url'=>'', 
            'description'=>'', 'is_protected'=>0, 'follower_count'=>0, 'post_count'=>0, 'joined'=>'');
        }
        return $parsed_payload;
    }
    /**
     * Parse error XML
     * @param str $data
     * @return array Error
     */
    public function parseError($data) {
        $parsed_payload = array();
        try {
            $xml = $this->createParserFromString(utf8_encode($data));
            if ($xml != false) {
                $root = $xml->getName();
                switch ($root) {
                    case 'hash':
                        $parsed_payload = array('request'=>$xml->request, 'error'=>$xml->error);
                        break;
                    default:
                        break;
                }
            }
        } catch(Exception $e) {
            $logger = Logger::getInstance();
            $logger->logUserError('parseError Exception caught: ' . $e->getMessage(), __METHOD__.','.__LINE__);
        }

        return $parsed_payload;
    }

    /**
     * Parse XML data returned from Twitter.
     * @param str $data
     * @return array Mixed data types, users, IDs, tweets, etc
     */
    public function parseXML($data) {
        $parsed_payload = array();
        try {
            $xml = $this->createParserFromString(utf8_encode($data));
            if ($xml != false) {
                $root = $xml->getName();
                switch ($root) {
                    case 'user':
                        $parsed_payload[] = array('user_id'=>$xml->id, 'user_name'=>$xml->screen_name,
                            'full_name'=>$xml->name, 'avatar'=>$xml->profile_image_url, 'location'=>$xml->location, 
                            'description'=>$xml->description, 'url'=>$xml->url, 'is_protected'=>$xml->protected , 
                            'follower_count'=>$xml->followers_count, 'friend_count'=>$xml->friends_count, 
                            'post_count'=>$xml->statuses_count, 'favorites_count'=>$xml->favourites_count, 
                            'joined'=>gmdate("Y-m-d H:i:s", strToTime($xml->created_at)), 'network'=>'twitter');
                        break;
                    case 'ids':
                        foreach ($xml->children() as $item) {
                            $parsed_payload[] = array('id'=>$item);
                        }
                        break;
                    case 'id_list':
                        $this->next_cursor = $xml->next_cursor;
                        foreach ($xml->ids->children() as $item) {
                            $parsed_payload[] = array('id'=>$item);
                        }
                        break;
                    case 'status':
                        $georss = null;
                        $namespaces = $xml->getNameSpaces(true);
                        if (isset($namespaces['georss'])) {
                            $georss = $xml->geo->children($namespaces['georss']);
                        }
                        $parsed_payload[] = array('post_id'=>$xml->id,
                            'author_user_id'=>$xml->user->id, 'user_id'=>$xml->user->id,
                            'author_username'=>$xml->user->screen_name, 'user_name'=>$xml->user->screen_name,
                            'author_fullname'=>$xml->user->name, 'full_name'=>$xml->user->name,
                            'author_avatar'=>$xml->user->profile_image_url, 'avatar'=>$xml->user->profile_image_url, 
                            'location'=>$xml->user->location, 
                            'description'=>$xml->user->description, 'url'=>$xml->user->url, 
                            'is_protected'=>$xml->user->protected , 'followers'=>$xml->user->followers_count, 
                            'following'=>$xml->user->friends_count, 'tweets'=>$xml->user->statuses_count, 
                            'joined'=>gmdate("Y-m-d H:i:s", strToTime($xml->user->created_at)), 
                            'post_text'=>$xml->text, 'pub_date'=>gmdate("Y-m-d H:i:s", strToTime($xml->created_at)), 
                            'in_reply_to_post_id'=>$xml->in_reply_to_status_id, 
                            'in_reply_to_user_id'=>$xml->in_reply_to_user_id, 'source'=>$xml->source, 
                            'favorited' => $xml->favorited,
                            'geo'=>(isset($georss)?$georss->point:''), 'place'=>$xml->place->full_name, 
                            'network'=>'twitter');
                        break;
                    case 'users_list':
                        $this->next_cursor = $xml->next_cursor;
                        foreach ($xml->users->children() as $item) {
                            $parsed_payload[] = array('post_id'=>$item->status->id, 'user_id'=>$item->id,
                                'user_name'=>$item->screen_name, 'full_name'=>$item->name, 
                                'avatar'=>$item->profile_image_url, 'location'=>$item->location, 
                                'description'=>$item->description, 'url'=>$item->url, 'is_protected'=>$item->protected,
                                'friend_count'=>$item->friends_count, 'follower_count'=>$item->followers_count, 
                                'joined'=>gmdate("Y-m-d H:i:s", strToTime($item->created_at)), 
                                'post_text'=>$item->status->text, 
                                'last_post'=>gmdate("Y-m-d H:i:s", strToTime($item->status->created_at)), 
                                'pub_date'=>gmdate("Y-m-d H:i:s", strToTime($item->status->created_at)), 
                                'favorites_count'=>$item->favourites_count, 'post_count'=>$item->statuses_count,
                                'network'=>'twitter');
                        }
                        break;
                    case 'users':
                        foreach ($xml->children() as $item) {
                            $parsed_payload[] = array('post_id'=>$item->status->id, 'user_id'=>$item->id,
                                'user_name'=>$item->screen_name, 'full_name'=>$item->name, 
                                'avatar'=>$item->profile_image_url, 'location'=>$item->location, 
                                'description'=>$item->description, 'url'=>$item->url, 'is_protected'=>$item->protected,
                                'friend_count'=>$item->friends_count, 'follower_count'=>$item->followers_count, 
                                'joined'=>gmdate("Y-m-d H:i:s", strToTime($item->created_at)), 
                                'post_text'=>$item->status->text, 
                                'last_post'=>gmdate("Y-m-d H:i:s", strToTime($item->status->created_at)), 
                                'pub_date'=>gmdate("Y-m-d H:i:s", strToTime($item->status->created_at)), 
                                'favorites_count'=>$item->favourites_count, 'post_count'=>$item->statuses_count, 
                                'source'=>$item->status->source, 
                                'in_reply_to_post_id'=>$item->status->in_reply_to_status_id, 'network'=>'twitter');
                        }
                        break;
                    case 'statuses':
                        foreach ($xml->children() as $item) {
                            $georss = null;
                            $namespaces = $item->getNameSpaces(true);
                            if(isset($namespaces['georss'])) {
                                $georss = $item->geo->children($namespaces['georss']);
                            }
                            $parsed_payload[] = array('post_id'=>$item->id,
                                'author_user_id'=>$item->user->id, 'user_id'=>$item->user->id,
                                'author_username'=>$item->user->screen_name, 'user_name'=>$item->user->screen_name,
                                'author_fullname'=>$item->user->name, 'full_name'=>$item->user->name,
                                'author_avatar'=>$item->user->profile_image_url,
                                'avatar'=>$item->user->profile_image_url, 
                                'location'=>$item->user->location, 
                                'description'=>$item->user->description, 'url'=>$item->user->url, 
                                'is_protected'=>$item->user->protected , 'follower_count'=>$item->user->followers_count,
                                'friend_count'=>$item->user->friends_count, 'post_count'=>$item->user->statuses_count,
                                'joined'=>gmdate("Y-m-d H:i:s", strToTime($item->user->created_at)), 
                                'post_text'=>$item->text, 
                                'pub_date'=>gmdate("Y-m-d H:i:s", strToTime($item->created_at)), 
                                'favorites_count'=>$item->user->favourites_count, 
                                'in_reply_to_post_id'=>$item->in_reply_to_status_id, 
                                'in_reply_to_user_id'=>$item->in_reply_to_user_id, 'source'=>$item->source, 
                                'favorited' => $xml->favorited,
                                'geo'=>(isset($georss)?$georss->point:''), 'place'=>$item->place->full_name, 
                                'network'=>'twitter');
                        }
                        break;
                    case 'hash':
                        $parsed_payload = array('remaining-hits'=>$xml-> {'remaining-hits'} ,
                            'hourly-limit'=>$xml-> {'hourly-limit'} , 'reset-time'=>$xml-> {'reset-time-in-seconds'} );
                        break;
                    case 'relationship':
                        $parsed_payload = array('source_follows_target'=>$xml->source->following,
                            'target_follows_source'=>$xml->target->following);
                        break;
                    default:
                        break;
                }
            }
        } catch(Exception $e) {
            $logger = Logger::getInstance();
            $logger->logUserError('parseXML Exception caught: ' . $e->getMessage(), __METHOD__.','.__LINE__);
        }
        return $parsed_payload;
    }
    /**
     * Get next cursor.
     * @return int
     */
    public function getNextCursor() {
        return $this->next_cursor;
    }
    /**
     * Create DOM from URL.
     * @param str $url
     * @return DOMDocument
     */
    public function createDOMfromURL($url) {
        $doc = new DOMDocument();
        $doc->load($url);
        return $doc;
    }
    /**
     * Create XML parser from string.
     * @param str $data
     * @return object
     */
    public function createParserFromString($data) {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($data);
        if (!$xml) {
            foreach (libxml_get_errors() as $error) {
                $this->logXMLError($error, $data);
            }
            libxml_clear_errors();
        }
        return $xml;
    }
    /**
     * Log XML error.
     * @param object $error
     * @param str $data
     */
    private function logXMLError($error, $data) {
        $xml = explode("\n", $data);
        $logger = Logger::getInstance();
        $logger->logUserError('LIBXML '.$xml[$error->line - 1], __METHOD__.','.__LINE__);
        $logger->logUserError('LIBXML '.str_repeat('-', $error->column) . "^", __METHOD__.','.__LINE__);

        switch ($error->level) {
            case LIBXML_ERR_WARNING:
                $logger->logInfo("LIBXML Warning $error->code: ", __METHOD__.','.__LINE__);
                break;
            case LIBXML_ERR_ERROR:
                $logger->logInfo("LIBXML Error $error->code: ", __METHOD__.','.__LINE__);
                break;
            case LIBXML_ERR_FATAL:
                $logger->logInfo("LIBXML Fatal Error $error->code: ", __METHOD__.','.__LINE__);
                break;
        }
        $logger->logUserError('LIBXML '.trim($error->message). " Line: $error->line, Column $error->column",
        __METHOD__.','.__LINE__);
    }
}
