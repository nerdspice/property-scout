<?php
/*
Plugin Name: Property Scout
Description: Real estate property search plugin using various external sources, such as Zillow.
Author: nerdspice
Version: 0.1
*/

class PropertyScout {
  const OPTIONS_KEY = 'prop_scout_options';
  
  private static $_instance;
  private $geoip_record;
  private $cache = array();

  protected $options = null;
  protected $zillow_client = null;
  protected $geoip_reader = null;

  public $plugin_dir;
  public $plugin_url;
  public $db_dir = 'db/';
  public $geoip_db = 'GeoLite2-City.mmdb';

  public function __construct() {
    $this->plugin_dir = plugin_dir_path(__FILE__);
    $this->plugin_url = plugin_dir_url(__FILE__);
  }

  public function run() {
    $this->registerShortcodes();
    $this->initHooks();
    $this->loadComposerLibs();
    $this->initZillowLib();
    $this->initGeoIP();
  }

  public function registerShortcodes() {
    //add_shortcode('template', array($this, 'templateSC'));
  }

  public function initHooks() {
    add_action('admin_init', array($this, 'wpAdminInit'));
    add_action('init', array($this, 'wpInit'));
    add_filter('prop_scout_search', array($this, 'filterPropScoutSearch'), 10, 4);
  }

  public function loadComposerLibs() {
    require_once $this->plugin_dir.'vendor/autoload.php';
  }

  public function initZillowLib() {
    $zwsid = $this->getOption('zillow_zwsid');
    $this->zillow_client = new \Zillow\ZillowClient($zwsid);
  }

  public function initGeoIP() {

  }

  // lazy load the reader as needed (performance improvement)
  public function getGeoIPReader() {
    if(is_null($this->geoip_reader)) {
      $db_path = $this->getGeoIPDBPath();
      $this->geoip_reader = new \GeoIp2\Database\Reader($db_path);
    }
    return $this->geoip_reader;
  }

  public function wpInit() {
    //$this->registerPostTypes();
    add_action('admin_menu', array($this, 'wpAdminMenu'));
    do_action('prop_scout_init', $this);
  }

  public function wpAdminInit() {
    
  }

  public function wpAdminMenu() {
    if(is_admin()) {
      add_submenu_page('options-general.php', __('Settings', 'prop-scout'), __('Property Scout', 'prop-scout'), 'manage_options', 'prop_scout_settings', array($this, 'wpAdminSettings'));
      add_filter('plugin_action_links', array($this, 'wpPluginLinks'), 10, 2);
    }
  }

  public function wpPluginLinks($links, $file) {
    if ($file != plugin_basename(__FILE__)) return $links;
    array_unshift($links, '<a href="'.esc_url(admin_url('options-general.php')).'?page=prop_scout_settings">'.esc_html__('Settings', 'prop-scout').'</a>');
    return $links;
  }

  public function wpAdminSettings() {
    require_once($this->plugin_dir.'/settings-page.php');
  }

  public function saveOptions() {
    if(is_null($this->options)) $this->options = array();
    update_option(static::OPTIONS_KEY, $this->options, false);
  }

  public function setOption($opt, $val) {
    if(is_null($this->options)) $this->options = array();
    $this->options[$opt] = $val;
    $this->saveOptions();
  }

  protected function checkOptions() {
    if(is_null($this->options)) {
      $this->options = get_option(static::OPTIONS_KEY, array());
    }
  }

  public function getOptions() {
    $this->checkOptions();
    return $this->options;
  }

  public function getOption($opt) {
    $this->checkOptions();
    return @$this->options[$opt];
  }

  public function getDemographics($state, $city) {
    $cache_key = '_zdemographics_'.$state.$city;
    
    if(!isset($this->cache[$cache_key])) {
      $client = $this->zillow_client;
      $resp = $client->GetDemographics(array('state'=>$state, 'city'=>$city));
      $resp = $client->isSuccessful() ? $resp : array();
      $this->cache[$cache_key] = $resp;
      return $resp;
    } else {
      return $this->cache[$cache_key];
    }
  }

  public function getDemographicsByZip($zip) {
    $cache_key = '_zdemographics_'.$zip;
    
    if(!isset($this->cache[$cache_key])) {
      $client = $this->zillow_client;
      $resp = $client->GetDemographics(array('zip'=>$zip));
      $resp = $client->isSuccessful() ? $resp : array();
      $this->cache[$cache_key] = $resp;
      return $resp;
    } else {
      return $this->cache[$cache_key];
    }
  }

  public function getPropertyByFullAddress($addr, $getDetails = false, $getRent = false) {
    $parts = explode(' ', $addr);
    $zip = array_pop($parts);
    $cache_key = '_zfullprop_'.$addr.$zip;
    
    if(!isset($this->cache[$cache_key])) {
      $client = $this->zillow_client;
      $resp = $client->GetDeepSearchResults(array('address'=>$addr, 'citystatezip'=>$zip, 'rentzestimate'=>$getRent));
      $resp = $client->isSuccessful() ? $this->formatZillowResponse($resp, $getDetails) : array();
      $this->cache[$cache_key] = $resp;
      return $resp;
    } else {
      return $this->cache[$cache_key];
    }
  }

  public function formatZillowResponse($resp, $getDetails = false) {
    $resp = @$resp['results']['result'] ?: array();
    if(isset($resp['zpid'])) $resp = array($resp);
    if($getDetails) $resp = $this->getZillowPropDetails($resp);
    return $resp;
  }

  public function getZillowPropDetails($resp) {
    if(!is_array($resp)) return $resp;
    $client = $this->zillow_client;

    foreach($resp as &$r) {
      $zpid = trim(@$r['zpid']);
      if(!$zpid) continue;

      $details = $client->GetUpdatedPropertyDetails(array('zpid'=>$zpid));
      //$details = $this->formatZillowResponse($details);
      //$r['_details'] = @$details[0] ?: array();
      $r['_updated_details'] = $details;
    }

    return $resp;
  }

  public function getZillowComps($zpid, $count = 10) {
    $cache_key = '_zcomp_'.$zpid.$count;
    if(!isset($this->cache[$cache_key])) {
      $client = $this->zillow_client;
      $resp = $client->GetDeepComps(array('zpid'=>$zpid, 'count'=>$count, 'rentzestimate'=>true));
      $resp = $client->isSuccessful() ? $resp : array();
      $this->cache[$cache_key] = $resp;
      return $resp;
    } else {
      return $this->cache[$cache_key];
    }
  }

  public function filterPropScoutSearch($results, $search, $state = '', $getPhotos = false) {
    //$city  = $this->getIP2('city');
    if(!$state) $state = $this->getIP2('state');
    $client = $this->zillow_client;
    $resp = $client->GetSearchResults(array('address'=>$search, 'citystatezip'=>$state));
    //$resp = $this->zillow_client->GetRegionChildren(['state'=>$state, 'childtype'=>'neighborhood']);
    //$resp = $this->zillow_client->GetRegionChildren(['regionid'=>'343268']);
    return $client->isSuccessful() ? $this->formatZillowResponse($resp, $getPhotos) : array();
  }

  public function getGeoIPDBPath() {
    return $this->plugin_dir.$this->db_dir.$this->geoip_db;
  }

  public function getGeoIPDBType() {
    $type = $this->getGeoIPReader()->metadata()->databaseType;
    switch($type) {
      case 'GeoLite2-City': $type = 'city'; break;
      case 'GeoLite2-Country': $type = 'country'; break;
    }
    return $type;
  }

  public function getRequestIP() {
    $ip = (@$_SERVER['HTTP_X_FORWARDED_FOR']) ?: $_SERVER['REMOTE_ADDR'];
    return $ip;
  }

  function getGeoIPRecord($ip = '') {
    if(is_null($this->geoip_record)) {
      if(!$ip) $ip = $this->getRequestIP();
      $type = $this->getGeoIPDBType();
      $this->geoip_record = $this->getGeoIPReader()->$type($ip);
    }
    return $this->geoip_record;
  }

  public function getIP2($type, $ip = '', $statefull = false) {
    $record = $this->getGeoIPRecord($ip);
    $data = false;

    $city = $record->city->name;
    $state = $record->mostSpecificSubdivision->isoCode;
    $state_name = $record->mostSpecificSubdivision->name;
    $zip = $record->postal->code;

    switch($type) {
      case 'city': $data = $city; break;
      case 'state': $data = $state; break;
      case 'statename': $data = $state_name; break;
      case 'zipcode': $data = $zip; break;
      case 'all': $data = [$city, ($statefull ? $state_name : $state), $zip]; break;
    }

    return $data;
  }



  public static function getInstance() {
    if(!self::$_instance) {
      self::$_instance = new static();
    }

    return self::$_instance;
  }

  public static function init() {
    static::getInstance()->run();
  }
}

PropertyScout::init();
