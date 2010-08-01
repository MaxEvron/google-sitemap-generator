<?php

/*
 $Id$

 Google XML Sitemaps Generator for WordPress
 ==============================================================================
 
 This generator will create a sitemaps.org compliant sitemap of your WordPress blog.
 Currently homepage, posts, static pages, categories, archives and author pages are supported.
 
 The priority of a post depends on its comments. You can choose the way the priority
 is calculated in the options screen.
 
 Feel free to visit my website under www.arnebrachhold.de!

 For aditional details like installation instructions, please check the readme.txt and documentation.txt files.
 
 Have fun!
   Arne


 Info for WordPress:
 ==============================================================================
 Plugin Name: Google XML Sitemaps
 Plugin URI: http://www.arnebrachhold.de/redir/sitemap-home/
 Description: This plugin will generate a special XML sitemap which will help search engines like Google, Yahoo, Bing and Ask.com to better index your blog.
 Version: 4.0beta1
 Author: Arne Brachhold
 Author URI: http://www.arnebrachhold.de/
 Text Domain: sitemap
 Domain Path: /lang/
 
*/

/**
 * Loader class for the Google Sitemap Generator
 *
 * This class takes care of the sitemap plugin and tries to load the different parts as late as possible.
 * On normal requests, only this small class is loaded. When the sitemap needs to be rebuild, the generator itself is loaded.
 * The last stage is the user interface which is loaded when the administration page is requested.
 */
class GoogleSitemapGeneratorLoader {
	/**
	 * Enabled the sitemap plugin with registering all required hooks
	 *
	 * If the sm_command and sm_key GET params are given, the function will init the generator to rebuild the sitemap.
	 */
	function Enable() {
		
		//Register the sitemap creator to wordpress...
		add_action('admin_menu', array('GoogleSitemapGeneratorLoader', 'RegisterAdminPage'));
		
		//Nice icon for Admin Menu (requires Ozh Admin Drop Down Plugin)
		add_filter('ozh_adminmenu_icon', array('GoogleSitemapGeneratorLoader', 'RegisterAdminIcon'));
				
		//Additional links on the plugin page
		add_filter('plugin_row_meta', array('GoogleSitemapGeneratorLoader', 'RegisterPluginLinks'),10,2);

		//Existing posts was deleted
		add_action('delete_post', array('GoogleSitemapGeneratorLoader', 'CallSendPing'),9999,1);
			
		//Existing post was published
		add_action('publish_post', array('GoogleSitemapGeneratorLoader', 'CallSendPing'),9999,1);
			
		//Existing page was published
		add_action('publish_page', array('GoogleSitemapGeneratorLoader', 'CallSendPing'),9999,1);
		
		//Robots.txt request
		add_action('do_robots', array('GoogleSitemapGeneratorLoader', 'CallDoRobots'),100,0);
		
		//Help topics for context sensitive help
		add_filter('contextual_help_list', array('GoogleSitemapGeneratorLoader', 'CallHtmlShowHelpList'),9999,2);
		
		add_filter('query_vars', array('GoogleSitemapGeneratorLoader', 'RegisterQueryVars'),1,1);
		
		add_filter('rewrite_rules_array', array('GoogleSitemapGeneratorLoader', 'AddRewriteRules'),1,1);
		
		add_filter('template_redirect', array('GoogleSitemapGeneratorLoader', 'DoTemplateRedirect'),1,0);
		
		//Check if this is a BUILD-NOW request (key will be checked later)
		if(!empty($_GET["sm_command"]) && !empty($_GET["sm_key"])) {
			GoogleSitemapGeneratorLoader::CallCheckForManualBuild();
		}
		
		//Check if the result of a ping request should be shown
		if(!empty($_GET["sm_ping_service"])) {
			GoogleSitemapGeneratorLoader::CallShowPingResult();
		}
	}
	
	function RegisterQueryVars($vars) {
	    array_push($vars, 'xml_sitemap');
	    return $vars;
	}
	
	function AddRewriteRules($rules){
		$newrules = array();
		$newrules['sitemap-?([^\.]+)?\.xml$'] = 'index.php?xml_sitemap=params=$matches[1]';
		$newrules['sitemap-?([^\.]+)?\.xml\.gz$'] = 'index.php?xml_sitemap=params=$matches[1];zip=true';
		
		return $newrules + $rules;
	}
	
	function DoTemplateRedirect(){
		global $wp_query;
		if(!empty($wp_query->query_vars["xml_sitemap"])) {
			GoogleSitemapGeneratorLoader::CallShowSitemap($wp_query->query_vars["xml_sitemap"]);
		}
	}
	
	
	/**
	 * Outputs the warning bar if multisite mode is activated
	 */
	function AddMultisiteWarning() {
		echo "<div id='sm-multisite-warning' class='error fade'><p><strong>".__('Google XML Sitemaps is not multisite compatible.','sitemap')."</strong><br /> ".sprintf(__('Unfortunately the Google XML Sitemaps plugin was not tested with the multisite feature of WordPress 3.0 yet. The plugin will not be active until you disable the multisite mode. Otherwise go to <a href="%1$s">active plugins</a> and deactivate the Google XML Sitemaps plugin to make this message disappear.','sitemap'), "plugins.php?plugin_status=active")."</p></div>";
	}

	/**
	 * Registers the plugin in the admin menu system
	 */
	function RegisterAdminPage() {
		
		if (function_exists('add_options_page')) {
			add_options_page(__('XML-Sitemap Generator','sitemap'), __('XML-Sitemap','sitemap'), 'level_10', GoogleSitemapGeneratorLoader::GetBaseName(), array('GoogleSitemapGeneratorLoader','CallHtmlShowOptionsPage'));
		}
	}
	
	function RegisterAdminIcon($hook) {
		if ( $hook == GoogleSitemapGeneratorLoader::GetBaseName() && function_exists('plugins_url')) {
			return plugins_url('img/icon-arne.gif',GoogleSitemapGeneratorLoader::GetBaseName());
		}
		return $hook;
	}
	
	function RegisterPluginLinks($links, $file) {
		$base = GoogleSitemapGeneratorLoader::GetBaseName();
		if ($file == $base) {
			$links[] = '<a href="options-general.php?page=' . GoogleSitemapGeneratorLoader::GetBaseName() .'">' . __('Settings','sitemap') . '</a>';
			$links[] = '<a href="http://www.arnebrachhold.de/redir/sitemap-plist-faq/">' . __('FAQ','sitemap') . '</a>';
			$links[] = '<a href="http://www.arnebrachhold.de/redir/sitemap-plist-support/">' . __('Support','sitemap') . '</a>';
			$links[] = '<a href="http://www.arnebrachhold.de/redir/sitemap-plist-donate/">' . __('Donate','sitemap') . '</a>';
		}
		return $links;
	}
	
	/**
	 * Invokes the HtmlShowOptionsPage method of the generator
	 */
	function CallHtmlShowOptionsPage() {
		if(GoogleSitemapGeneratorLoader::LoadPlugin()) {
			$gs = &GoogleSitemapGenerator::GetInstance();
			$gs->HtmlShowOptionsPage();
		}
	}
	
	/**
	 * Invokes the ShowPingResult method of the generator
	 */
	function CallShowPingResult() {
		if(GoogleSitemapGeneratorLoader::LoadPlugin()) {
			$gs = &GoogleSitemapGenerator::GetInstance();
			$gs->ShowPingResult();
		}
	}
	
	/**
	 * Invokes the ShowPingResult method of the generator
	 */
	function CallSendPing() {
		if(GoogleSitemapGeneratorLoader::LoadPlugin()) {
			$gs = &GoogleSitemapGenerator::GetInstance();
			$gs->SendPing();
		}
	}
	
	/**
	 * Invokes the ShowSitemap method of the generator
	 */
	function CallShowSitemap($options) {
		if(GoogleSitemapGeneratorLoader::LoadPlugin()) {
			$gs = &GoogleSitemapGenerator::GetInstance();
			$gs->ShowSitemap($options);
		}
	}
	

	function CallHtmlShowHelpList($filterVal,$screen) {
		
		$id = get_plugin_page_hookname(GoogleSitemapGeneratorLoader::GetBaseName(),'options-general.php');		
		
		if($screen == $id) {
			$links = array(
				__('Plugin Homepage','sitemap')=>'http://www.arnebrachhold.de/redir/sitemap-help-home/',
				__('My Sitemaps FAQ','sitemap')=>'http://www.arnebrachhold.de/redir/sitemap-help-faq/'
			);
			
			$filterVal[$id] = '';
			
			$i=0;
			foreach($links AS $text=>$url) {
				$filterVal[$id].='<a href="' . $url . '">' . $text . '</a>' . ($i < (count($links)-1)?'<br />':'') ;
				$i++;
			}
		}
		return $filterVal;
	}
	
	function CallDoRobots() {
		if(GoogleSitemapGeneratorLoader::LoadPlugin()) {
			$gs = &GoogleSitemapGenerator::GetInstance();
			$gs->DoRobots();
		}
	}
	
	/**
	 * Loads the actual generator class and tries to raise the memory and time limits if not already done by WP
	 *
	 * @return boolean true if run successfully
	 */
	function LoadPlugin() {
		
		$mem = abs(intval(@ini_get('memory_limit')));
		if($mem && $mem < 64) {
			@ini_set('memory_limit', '64M');
		}
		
		$time = abs(intval(@ini_get("max_execution_time")));
		if($time != 0 && $time < 120) {
			@set_time_limit(120);
		}
		
		if(!class_exists("GoogleSitemapGenerator")) {
			
			$path = trailingslashit(dirname(__FILE__));
			
			if(!file_exists( $path . 'sitemap-core.php')) return false;
			require_once($path. 'sitemap-core.php');
		}

		GoogleSitemapGenerator::Enable();
		return true;
	}
	
	/**
	 * Returns the plugin basename of the plugin (using __FILE__)
	 *
	 * @return string The plugin basename, "sitemap" for example
	 */
	function GetBaseName() {
		return plugin_basename(__FILE__);
	}
	
	/**
	 * Returns the name of this loader script, using __FILE__
	 *
	 * @return string The __FILE__ value of this loader script
	 */
	function GetPluginFile() {
		return __FILE__;
	}
	
	/**
	 * Returns the plugin version
	 *
	 * Uses the WP API to get the meta data from the top of this file (comment)
	 *
	 * @return string The version like 3.1.1
	 */
	function GetVersion() {
		if(!isset($GLOBALS["sm_version"])) {
			if(!function_exists('get_plugin_data')) {
				if(file_exists(ABSPATH . 'wp-admin/includes/plugin.php')) require_once(ABSPATH . 'wp-admin/includes/plugin.php'); //2.3+
				else if(file_exists(ABSPATH . 'wp-admin/admin-functions.php')) require_once(ABSPATH . 'wp-admin/admin-functions.php'); //2.1
				else return "0.ERROR";
			}
			$data = get_plugin_data(__FILE__, false, false);
			$GLOBALS["sm_version"] = $data['Version'];
		}
		return $GLOBALS["sm_version"];
	}
}

//Enable the plugin for the init hook, but only if WP is loaded. Calling this php file directly will do nothing.
if(defined('ABSPATH') && defined('WPINC')) {
	add_action("init",array("GoogleSitemapGeneratorLoader","Enable"),1000,0);
}
?>