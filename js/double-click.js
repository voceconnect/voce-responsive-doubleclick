/* jshint unused:false, -W060 */
var dc_ord = 0, dc_screen_width = 0;

function get_dc_ord() {
	if(dc_ord === 0) {
		dc_ord = window.ord || Math.floor(Math.random()*1E16);
	}
	return dc_ord;
}

function get_dc_screen_width() {
	if(dc_screen_width === 0) {
		if( typeof( window.innerWidth ) === 'number' ) {
			//Non-IE
			dc_screen_width = window.innerWidth;
		} else if( document.documentElement && ( document.documentElement.clientWidth || document.documentElement.clientHeight ) ) {
			//IE 6+ in 'standards compliant mode'
			dc_screen_width = document.documentElement.clientWidth;
		} else if( document.body && ( document.body.clientWidth || document.body.clientHeight ) ) {
			//IE 4 compatible
			dc_screen_width = document.body.clientWidth;
		}
	}
	return dc_screen_width;
}

function print_dc_ad(partial_url, sizes) {
	var screen_width = get_dc_screen_width(),
	ss = 's';

	if(screen_width >= 980) {
		ss = 'l';
	} else if (screen_width >= 768) {
		ss = 'm';
	}

	var ord = get_dc_ord();
	$.each( sizes, function(idx, size){
		if(size.min_width <= screen_width) {
			if('undefined' !== typeof size.ad_size && '' !== size.ad_size) {
				if('undefined' === typeof debug_doubleclick) {
					document.write('<script type="text/javascript" src="http://ad.doubleclick.net/adj/' + partial_url + 'pos=' + size.pos + ';ss=' + ss + ';sz=' + size.ad_size + ';ord=' + ord + '?"><\/script>');
				} else {
					var size_parts = size.ad_size.split('x');
					document.write('<div style="background-color: #034A99; margin:0 auto; color: #fff; width:' + size_parts[0] + 'px; height:' + size_parts[1] + 'px;">Ad size: ' + size.ad_size +
						'<br />Ad URL: http://ad.doubleclick.net/adj/' + partial_url + 'pos=' + size.pos + ';ss=' + ss + ';sz=' + size.ad_size + '</div>');
				}
			}
			return;
		}
	} );
}