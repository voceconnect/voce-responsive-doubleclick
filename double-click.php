<?php
voce_load_plugin( 'voce-settings-api' );

class Voce_Responsive_Double_Click {

	private static $breakpoints;
	

	public static function Initialize() {
		$settings_api = Voce_Settings_API::GetInstance();

		//handle migration of group
		if ( !$settings_api->get_setting( 'doubleclick_id', 'doubleclick' ) && $settings_api->get_setting( 'doubleclick_id', 'general-settings' ) ) {
			$settings_api->set_setting( 'doubleclick_id', 'doubleclick', $settings_api->get_setting( 'doubleclick_id', 'general-settings' ) );
		}

		$settings_api->add_page( 'Site Settings', 'Site Settings', 'site-settings', 'manage_options', 'General settings for site', 'options-general.php' )
			->add_group( 'DoubleClick', 'doubleclick', null, 'General Site Settings' )
				->add_setting( 'DoubleClick ID', 'doubleclick_id' );

		add_action('template_redirect', function() {
			wp_enqueue_script('voce-doubleclick', plugins_url('js/double-click.js', __FILE__), array(), $ver = '1.0');
		});
		
		if(defined('DC_DEBUG') && DC_DEBUG) {
			add_action('wp_head', function() {
				?>
				<script type="text/javascript">
					var debug_doubleclick = 1;
				</script>
				<?php 
			});
			
		}

		add_filter('get_double_click_ad_args', function($args, $sizes) {
			$args['pos'] = false;
			return $args;
		}, 10, 2);
		
		self::$breakpoints = array(0, 768, 980);
		
	}

	/**
	 *
	 * @param array $sizes array of key=>value sets of sizes for the ads, the key is the WINDOW 
	 *		min-width and the value is a comma separated set of 'wxh' values accapted by the position
	 * @param array $args array
	 * Optional $args arguments:
	 * - pos_postfix - the name of the slot/position of the ad space; ex: 'top', 'bottom', '720x210.1'
	 * - zone - the section of the site; ie: 'homepage', 'posts', 'authors'
	 * - tile - a unique value for each ad call on a page, keeps the browser from pulling in the 
	 *		same ad multiple times if all other values are the same
	 * - keywords - array of keywords used to target the ad
	 * - extra_pairs - array of extra key-value pairs to add to the ad url
	 * @return string 
	 */
	public static function GetTheDoubleclickAd( $sizes, $args = array() ) {
		static $tile_cntr = 1;
		
		$args = wp_parse_args($args, array(
			'pos_postfix' => 'brd',
			'pos' => false,
			'zone' => '', 
			'tile' => false, 
			'keywords' => array(),
			'extra_pairs' => array()
		));

		$dc_id = Voce_Settings_API::GetInstance()->get_setting( 'doubleclick_id', 'doubleclick' );
		
		if ( !$dc_id ) {
			return '';
		}
		
		if ( !is_array( $args['keywords'] ) ) {
			$args['keywords'] = array( $args['keywords'] );
		}
		
		$args = apply_filters('get_double_click_ad_args', $args, $sizes);

		extract($args);
		
		if ( !$tile ) {
			$tile = $tile_cntr++;
		}
		
		if(empty($sizes)) {
			return;
		}
		
		//convert sizes array to structured data
		$sizes = array_map(function($size) { return array('ad_size' => $size); }, $sizes);
		
		ksort($sizes); //put sizes in reverse order of width

		
		//this is where things get messy, need to increment tile and pos based on breakpoints
		static $breakpoint_data = array();

		for($i = 0; $i < count(self::$breakpoints); $i++) {
			$breakpoint = self::$breakpoints[$i];

			//we want to fill in all break points so find the next smallest one that is set and use it
			$j = $i;
			while(!isset($sizes[self::$breakpoints[$j]]) && $j > 0) {
				$j--;
			}
			if(isset($sizes[self::$breakpoints[$j]])) {
				$sizes[$breakpoint] = $sizes[self::$breakpoints[$j]];
			}
				
			
			$size = $sizes[$breakpoint]['ad_size'];
			
			if(!isset($breakpoint_data[$breakpoint]))
				$breakpoint_data[$breakpoint] = array();
			
			if(!isset($breakpoint_data[$breakpoint][$size])) 
				$breakpoint_data[$breakpoint][$size] = array();
			
			if(!$pos) {
				if(!isset($breakpoint_data[$breakpoint][$size][$pos_postfix])) 
					$breakpoint_data[$breakpoint][$size][$pos_postfix] = 0;
				
				$breakpoint_data[$breakpoint][$size][$pos_postfix]++;
				
				$sizes[$breakpoint]['pos'] = $breakpoint_data[$breakpoint][$size][$pos_postfix] . $pos_postfix;
					
			} else {
				$sizes[$breakpoint]['pos'] = $pos;
			}
			
		}
		
		krsort($sizes); //now reverse the order
		
		//convert sizes to an array that keeps its sort in js.
		$size_sets = array();
		foreach($sizes as $min_width => $data) {
			$data['min_width'] = $min_width;
			$size_sets[] = $data;
		}
		
		$default_size = reset($sizes); //use the largest size for noscript
		
		$kws = '';
		if(count($keywords) > 0) {
			$kws = 'kw=' . implode(',', array_map(array(__CLASS__, 'PrepareKeyword'), $keywords)) . ';';
		}
		
		$the_extra_pairs = '';
		if(count($extra_pairs) > 0 ) {
			foreach($extra_pairs as $key => $value) {
				$the_extra_pairs .= urlencode($key) . '=' . urlencode($value) . ';';
			}
		}

		$wrapper_start = '<div id="ad_'. $tile . '" class="dc_ad">';
		$wrapper_end = '</div>';

		
		
		$partial_uri = sprintf( '%1$s/%2$s;%3$stile=%4$s;%5$s', $dc_id, $zone, $kws, $tile, $the_extra_pairs );

		$out = $wrapper_start . '<script type="text/javascript">';
		$out .= "//<![CDATA[ \n";
		$out .= sprintf("print_dc_ad('%s', %s)", $partial_uri, json_encode($size_sets));
		$out .= "\n//]]>";
		$out .= '</script> ';
		$out .= sprintf( '<noscript><a href="http://ad.doubleclick.net/jump/%s;pos=%s;ss=l;sz=%s">', $partial_uri, $default_size['pos'], $default_size['ad_size'] );
		$out .= sprintf( '<img src="http://ad.doubleclick.net/ad/%1$s/%2$s;ss=l;%3$s', $dc_id, $zone, $kws );
		$out .= sprintf( ';tile=%s;pos=%s;sz=%s;ord=?" border="0" alt="" /></a></noscript>', $tile, $default_size['pos'], $default_size['ad_size'] );
		$out .= sprintf( '%1$s<div id="ad_src_%2$s" style="display:none" class="doubleclick_ad_src">%3$s</div>', $wrapper_end, $tile, $partial_uri );

		return $out;
	}

	private static function PrepareKeyword( $keyword = '' ) {
		if ( !empty( $keyword ) ) {
			$keyword = urlencode( strtolower( str_replace( array( ' ', '&amp;' ), array( '', 'and' ), $keyword ) ) );
		}
		return $keyword;
	}

}

add_action( 'init', array( new Voce_Responsive_Double_Click(), 'initialize' ) );

