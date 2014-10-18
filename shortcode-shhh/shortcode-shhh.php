<?php
/*
Plugin Name: Shortcode Shhh
Plugin URI: http://www.sprisemedia.com/freebies/shortcode-shhh/
Description: Remove unused shortcodes from your posts and pages while retaining the ability to reinstate them later. <a href="plugins.php?page=shortcode-shhh.php">Settings</a>
Version: .1
Author: Sprise Media
Author URI: http://www.sprisemedia.com
*/

/*
Shortcode Shhh, a plugin for WordPress
Copyright (C) 2014 Sprise Media LLC (http://www.sprisemedia.com/)

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

if(!defined('ABSPATH')) exit; // Exit if accessed directly

if(!class_exists('SPR_Shhh')) {

class SPR_Shhh {  
	var $config = array();
	
	public function __construct() {  
		$this->config['title'] = 'Shortcode Shhh';
		$this->config['pref'] = 'shortcode_shhh_';
		$this->config['opts'] = array('codes', 'closes', 'trailing', 'args');
				
				
		// Installation call
		register_activation_hook( __FILE__, array( $this, 'install' ) );

		// Create settings page
		add_action( 'admin_menu', array($this, 'admin_pane') );
		
		// Do it
		add_action( 'the_post', array($this,'shhh') );
	}
	
	
	/* Install Plugin */
	
	public function install(){		
		add_option($this->config['pref'].'version', '.1' );
		add_option($this->config['pref'].'closes', 'y' );
	}	

	
	/* Do the things */
	public function shhh(){
		global $post, $GLOBALS, $pages;
		
		// Cleanin' up the shortcodes
		$GLOBALS['post']->post_content = $post->post_content = $this->shhh_filter($post->post_content);
		$pages[0] = $this->shhh_filter($pages[0]);
	}
	
	public function shhh_filter($text = ''){
		if(is_admin() || strpos($text, '[') === false) return $text;
		
		$closes = 	$this->grab('closes');
		$trailing = $this->grab('trailing');
		$args = 	$this->grab('args');
		$str = 		$this->grab('codes');
		
		$codes = (strpos($str,"\n") !== false ? explode("\r\n", $str) : array($str) );		
		
		foreach($codes as $row) {
			$replace = array($row);
			
			// Shorttag closure?
			if($closes == 'y') $replace[] = '[/'.substr($row, 1);
			
			// Trailing line breaks? 
			if($trailing == 'y') array_unshift($replace, $row."\r\n" );
			
			// Shortcodes with arguments
			if($args == 'y') {
				preg_match('/\\'.substr($row,0,-1).'(.*)\]/', $text, $matches);
				if(!empty($matches)) {
					array_unshift($replace,$matches[0]);
					array_unshift($replace,$matches[0]."\r\n");
				}
			}
			
			// Make the corrections to the text
			foreach($replace as $t) $text = str_replace($t,'',$text); 
		}
		
		return $text;
	}



	/* Admin Page */

	public function admin_pane() {		
		add_plugins_page($this->config['title'], $this->config['title'], 'edit_theme_options', 'shortcode-shhh.php', array($this, 'admin_pane_render') );
	}

	public function admin_pane_render(){	
		if(isset($_POST['submit'])) $this->save_options();
		
		$html = '<h1 style="padding: 40px 0 20px 0;">Shortcode Shhhh</h1>';
		$html .= '<p>Enter in some shortcodes below and they (and their output) will be removed from your pages and posts. Remove from the list to reinstate the shortcode.</p>';
		
		$html .= '<form method="post" action="'.site_url().'/wp-admin/admin.php?page=shortcode-shhh.php">';
		
		$codes = 	$this->grab('codes');
		$closes = 	$this->grab('closes');
		$trailing = $this->grab('trailing');
		$args = 	$this->grab('args');
		
		$emstyle = 'style="font-size: .9em;"';
		
		$html .= '		
		<table class="widefat">
			<tr>
				<td valign="top">Shortcodes to suppress:<br /> 
					<em '.$emstyle.'>One per line, eg: [some-shortcode]</em></td>
				<td><textarea name="shortcode_shhh_codes" style="min-width: 500px; min-height: 250px;">'.$codes.'</textarea></td>
			</tr>	
			<tr>
				<td>Shhh Shorttags With Closures Too<br /> 
					<em '.$emstyle.'>eg: [/some-shortcode]</em></td>
				<td><input type="checkbox" name="shortcode_shhh_closes" value="y" '.($closes === 'y' ? 'checked' : '').'/></td>
			</tr>		
			<tr>
				<td>Shhh Trailing Line Breaks<br /> 
					<em '.$emstyle.'>Remove returns after shortcode</em></td>
				<td><input type="checkbox" name="shortcode_shhh_trailing" value="y" '.($trailing === 'y' ? 'checked' : '').'/></td>
			</tr>		
			<tr>
				<td>Shhh Shortcodes w/ Parameters<br /> 
					<em '.$emstyle.'>eg: [some-shortcode arg="val"]</em></td>
				<td><input type="checkbox" name="shortcode_shhh_args" value="y" '.($args === 'y' ? 'checked' : '').'/></td>
			</tr>		
		</table>
		<p><input class="button-primary button-large" type="submit" name="submit" value="Submit" /></p>
		</form>';
		
		echo $html;
	}	
		
	private function save_options(){
		foreach($this->config['opts'] as $row) {
			$row = $this->config['pref'].$row;
			
			$newval = wp_kses($_POST[$row], array());
			
			if($row == $this->config['pref'].'codes') {
				$codes = (strpos($newval,"\r\n") !== false ? explode("\r\n", $newval) : array($newval) );	
				
				// Add brackets if missing
				for($i=0; $i<count($codes) ;$i++){					
					if(substr($codes[$i],0,1) != '[') $codes[$i] = '['.$codes[$i];
					if(substr($codes[$i],-1) != ']') $codes[$i] = $codes[$i].']';
				}
				
				$newval = implode("\r\n",$codes);
			}
			
			update_option( $row, $newval );
		}
	}
	
	private function grab($opt = ''){
		return get_option($this->config['pref'].$opt);
	}
}
  
if(!isset($spr_shh) || !is_object($spr_shhh)) $spr_shhh = new SPR_Shhh();  

} // class exists check
