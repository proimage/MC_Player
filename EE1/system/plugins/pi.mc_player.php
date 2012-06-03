<?php


/*
	MC Player Plugin

	@package		ExpressionEngine
	@subpackage		Addons
	@category		Plugin
	@author			Michael C.
	@link			http://www.pro-image.co.il/
*/


/*
	To-Do List:
		- <audio> tag support (awaiting support from LongTail: http://www.longtailvideo.com/support/forums/jw-player/player-development-and-customization/14088/html-5-player-audio)
		- Skins (w/ skin height recalculation)
*/

/*
	Index
		class Mc_player
			private function indent
			private function calculateSize
			function play
				- Prepare session cache vars from child tags
				- Fetch tag parameters, validate where needed, & assign to simple variables
				- Call calculateSize function
				- Create container tag
				- Create player script code
			function playlist
			function item
			function levels
			function level
			function plugins
			function plugin
			function modes
			function mode
			function config
*/

$plugin_info = array(
	'pi_name'			=> 'MC Player',
	'pi_version'		=> '0.2.6',
	'pi_author'			=> 'Michael C.',
	'pi_author_url'		=> 'http://www.pro-image.co.il/',
	'pi_description'	=> 'An imlementation of the JW HTML5 Media Player',
	'pi_usage'			=> Mc_player::usage()
);

class Mc_player
{

	function Mc_player()
	{

		/*
		 * As seen here: https://github.com/newism/nsm.body_class.ee_addon/pull/1/files
		 * @danott fork
		 * Technique from @erikreagan in Dan Benjamin's Shrimp plugin
		 * EE version check to properly reference our EE objects
		 */
		if (version_compare(APP_VER, '2', '<'))
		{
			// EE 1.x is in play
			global $TMPL, $SESS;
			$this->TMPL =& $TMPL;
			$this->SESS =& $SESS;
		} else {
			// EE 2.x is in play
			$this->EE  =& get_instance();
			$this->TMPL =& $this->EE->TMPL;
			$this->SESS =& $this->EE->session;
		}
	}

	/**
	 * Indent
	 *
	 * Indents lines in a given string by the specified number of levels
	 *
	 * @param		text			String to log
	 * @param		indent_levels	Num of levels to indent
	 * @access		private
	 * @return		string
	 */
	private function indent($text,$indent_levels = 1)
	{
		return ( str_replace(PHP_EOL, PHP_EOL . str_repeat("\t", $indent_levels), $text) );
	}

	/**
	 * _log_item
	 *
	 * Write items to template debugging log
	 *
	 * @param      string    String to log
	 * @param      int       Tab indention
	 * @access     private
	 * @return     void
	 */
	private function _log_item($string = FALSE, $indent = 1)
	{

		if ($string)
		{
			$tab = str_repeat('&nbsp;', 4 * $indent);
			$this->TMPL->log_item($tab . '- ' . $string);
		}
	}
	// End function _log_item()


	/**
	 * CalculateSize
	 *
	 * This function calculates the final size of the video player
	 *
	 * @param		native_width	Video width in pixels
	 * @param		native_height	Video height in pixels
	 * @param		fit_in_width	Scale the video to fit in this # of pixels
	 * @param		controlbar		Positioning of the controlbar
	 * @param		pl_position		Positioning of the playlist
	 * @param		pl_size			Size to allocate to the playlist
	 * @access		private
	 * @return		array
	 */
	private function calculateSize($native_width, $native_height, $fit_in_width, $controlbar, $pl_position = '', $pl_size = '', $skin_height = 0)
	{
		$size = array();

		// $skin_height = 0; // height of skin control interface
		if ($controlbar == 'over') { $skin_height = 0; }

		if ($fit_in_width && ($native_height > 0))
		{
			$display_width = $fit_in_width;
			$ratio = $native_width / $native_height;
			$display_height = round($display_width / $ratio);
			$size['width'] = $display_width;
			$size['height'] = $display_height + $skin_height;

		}
		else // native size
		{
			$size['width'] = $native_width;
			$size['height'] = $native_height + $skin_height;
		}

		switch ($pl_position)
		{
			case "left":
			case "right":
				$size['width'] = $size['width'] + $pl_size;
				break;
			case "top":
			case "bottom":
				$size['height'] = $size['height'] + $pl_size;
				break;
		}
		return $size;
	}


	/**
	 * Play
	 *
	 * This function puts everything together into a functional player
	 *
	 * @access	public
	 * @return	string
	 */
	function play()
	{

		// Each parent function, after using a session cache
		// variable, must unset it to prevent it persisting
		// beyond the current context.

		// playlist embedded tag
		if (isset($this->SESS->cache['mc']['player']['playlist'])) // inline JSON playlist
		{
			$playlist['code'] = $this->SESS->cache['mc']['player']['playlist'];
			unset($this->SESS->cache['mc']['player']['playlist']);
		}
		elseif (isset($this->SESS->cache['mc']['player']['playlist_file']))  // external XML playlist (placed in the playlist tag)
		{
			$playlist['params']['file'] = $this->SESS->cache['mc']['player']['playlist_file'];
			unset($this->SESS->cache['mc']['player']['playlist_file']);
		}
		elseif ($this->TMPL->fetch_param('file')) // single file
		{
			$player['params']['file'] = $this->TMPL->fetch_param('file');
		}

		// levels embedded tag
		$levels = (isset($this->SESS->cache['mc']['player']['levels'])) ? $this->SESS->cache['mc']['player']['levels'] : '';
			unset($this->SESS->cache['mc']['player']['levels']);

		// plugins embedded tag
		$plugins = (isset($this->SESS->cache['mc']['player']['plugins'])) ? $this->SESS->cache['mc']['player']['plugins'] : '';
			unset($this->SESS->cache['mc']['player']['plugins']);

		// modes embedded tag
		$modes = (isset($this->SESS->cache['mc']['player']['modes'])) ? $this->SESS->cache['mc']['player']['modes'] : '';
			unset($this->SESS->cache['mc']['player']['modes']);
		/*
			Prepare a warning if modes are specified with HTML5 before
			flash AND a playlist is specified.

			HTML5 mode requires native browser playback support for any
			given media format. The player script detects if a browser
			supports playback of the specified media file in the
			specified mode. Firefox & Opera don't currently (Q1 2012)
			support MP4 playback, but the script is unable to detect
			media file formats when they are in a playlist, and thus
			will not fall back to Flash mode in that specific instance.
		*/
		if ( (strpos($modes, 'html5') < strpos($modes, 'flash')) && (isset($playlist['code']) || isset($playlist['params']['file'])) )
		{
			$this->_log_item("WARNING in MC Player plugin: 'html5' mode and a playlist were both specified. Browsers will be unable to play media files they do not natively support. Either prioritize 'flash' over 'html5' modes, or use individual files instead of a playlist to fix the issue.");
		}


	// Validate parameter values

		// Container tag stuff
		$player['params']['container_tag'] = ($this->TMPL->fetch_param('container_tag')) ? $this->TMPL->fetch_param('container_tag') : 'div'; // valid values are 'div' (the default), 'video', 'audio', 'span', 'a'
		if ($this->TMPL->fetch_param('container_id')) // Use specified ID of container
		{
			$player['params']['container_id'] = $this->TMPL->fetch_param('container_id');
		}
		elseif (isset($player['params']['file']) && $player['params']['file'] != '') // generate unique ID based on filename
		{
			$player['params']['container_id'] = 'player_' . str_replace('.','-',basename(html_entity_decode($player['params']['file'])));
		}
		elseif (isset($this->SESS->cache['mc']['player']['id']) && $this->SESS->cache['mc']['player']['id'] != '')
		{
			$player['params']['container_id'] = 'player_' . str_replace('.','-',$this->SESS->cache['mc']['player']['id']); // Use ID prepared downstream
		}
		else // resort to a default ID
		{
			$player['params']['container_id'] = "player_container";
		}

		// class only carries through to the native containers;
		// if they are replaced via js with flash or other
		// players, the class is not respected
		$player['params']['container_class'] = ($this->TMPL->fetch_param('container_class')) ? $this->TMPL->fetch_param('container_class') : 'media_player';

		// Sizes
		if ($this->TMPL->fetch_param('width')) // 'width' specified
		{
			if ( ctype_digit($this->TMPL->fetch_param('width')) ) // and if the width is a digit
			{
				$player['params']['native_width'] = $this->TMPL->fetch_param('width');
			}
			else
			{
				$this->_log_item("WARNING in MC Player plugin: Specified 'width' is not a number; defaulting to 480");
				$player['params']['native_width'] = 480; // Default width of <video> element
			}
		}
		else // 'width' not specified
		{
				$this->_log_item("WARNING in MC Player plugin: 'width' not specified; defaulting to 480");
				$player['params']['native_width'] = 480;
		}

		// 'native_height' is the height of the display area only.
		// If the controlbar is displayed on bottom, for example,
		// the element's height would be:
		// 'native_height' + 'skin_height'
		if ($this->TMPL->fetch_param('height') !== FALSE) // if 'height' parameter provided
		{
			if ( ctype_digit($this->TMPL->fetch_param('height')) ) // and if it's a number
			{
				$player['params']['native_height'] = $this->TMPL->fetch_param('height'); // use it
			}
			elseif ($player['params']['container_tag'] == 'audio') // if it's not a number, but 'audio' tag was specified
			{
				$player['params']['native_height'] = 0; // Default height for audio player
				if ($this->TMPL->fetch_param('height'))
				{
					$this->_log_item("NOTICE in MC Player plugin: 'container_tag' was set to 'audio', but 'height' was invalid; defaulting to 0");
				}
			}
			else
			{
				$player['params']['native_height'] = ($player['params']['native_width'] / 16) * 9; // Default ratio of <video> element
				$this->_log_item("WARNING in MC Player plugin: Specified 'height' is not an integer; defaulting to a 16:9 ratio (" . $player['params']['native_width'] . '×' . ($player['params']['native_width'] / 16) * 9 . ")");
			}
		}
		elseif ($player['params']['container_tag'] == 'audio') // 'height' not specified, but container is 'audio'
		{
				$player['params']['native_height'] = 0; // Default height for audio player
		}
		else // 'height' not specified
		{
				$player['params']['native_height'] = ($player['params']['native_width'] / 16) * 9;
				$this->_log_item("WARNING in MC Player plugin: 'height' not specified; defaulting to a 16:9 ratio (" . $player['params']['native_width'] . '×' . ($player['params']['native_width'] / 16) * 9 . ")");
		}

		if ( ctype_digit($this->TMPL->fetch_param('fit_in_width')) ) // valid fit_in_width specified
		{
			$player['params']['fit_in_width'] = $this->TMPL->fetch_param('fit_in_width');
		}
		elseif ($this->TMPL->fetch_param('fit_in_width') !== false)
		{
			$this->_log_item("WARNING in MC Player plugin: Specified 'fit_in_width' is not an integer; ignoring");
			$player['params']['fit_in_width'] = '';
		}
		else
		{
			$player['params']['fit_in_width'] = '';
		}

		// Other
		$player['params']['playerpath'] = ($this->TMPL->fetch_param('playerpath')) ? $this->TMPL->fetch_param('playerpath') : '';
		$player['params']['bgcolor'] = ($this->TMPL->fetch_param('bgcolor')) ? $this->TMPL->fetch_param('bgcolor') : '';
		$player['params']['image'] = ($this->TMPL->fetch_param('image')) ? $this->TMPL->fetch_param('image') : '';
		$player['params']['link'] = ($this->TMPL->fetch_param('link')) ? $this->TMPL->fetch_param('link') : '';
		if ($this->TMPL->fetch_param('autostart'))
		{
			$player['params']['autostart'] = $this->TMPL->fetch_param('autostart');
		}
		elseif ($this->TMPL->fetch_param('autoplay')) // because I kept forgetting which 'auto-' to use :p
		{
			$player['params']['autostart'] = $this->TMPL->fetch_param('autoplay');
		}
		else
		{
			$player['params']['autostart'] = '';
		}
		$player['params']['bufferlength'] = ($this->TMPL->fetch_param('bufferlength')) ? $this->TMPL->fetch_param('bufferlength') : '';
		$player['params']['displayclick'] = ($this->TMPL->fetch_param('displayclick')) ? $this->TMPL->fetch_param('displayclick') : '';
		$player['params']['fullscreen'] = ($this->TMPL->fetch_param('fullscreen')) ? $this->TMPL->fetch_param('fullscreen') : '';
		$player['params']['mute'] = ($this->TMPL->fetch_param('mute')) ? $this->TMPL->fetch_param('mute') : '';
		$player['params']['volume'] = ($this->TMPL->fetch_param('volume')) ? $this->TMPL->fetch_param('volume') : '80';
		$player['params']['showmute'] = ($this->TMPL->fetch_param('showmute')) ? $this->TMPL->fetch_param('showmute') : '';
		$player['params']['skin_height'] = ($this->TMPL->fetch_param('skin_height')) ? $this->TMPL->fetch_param('skin_height') : '';

		// Playlist stuff
		$player['params']['shuffle'] = ($this->TMPL->fetch_param('shuffle')) ? $this->TMPL->fetch_param('shuffle') : '';
		$player['params']['repeat'] = ($this->TMPL->fetch_param('repeat')) ? $this->TMPL->fetch_param('repeat') : '';

		switch ($player['params']['repeat']) // input value verification
		{
			case "none":
			case "list":
			case "always":
			case "single":
				break;
			case false:
				$player['params']['repeat'] = '';
				break;
			default:
				$this->_log_item("WARNING in MC Player plugin: Invalid 'repeat' for player tag; ignoring");
				$player['params']['repeat'] = '';
				break;
		}

		// Playlist position
		if (isset($this->SESS->cache['mc']['player']['playlist_position']) !== false)
		{
			$playlist['params']['position'] = $this->SESS->cache['mc']['player']['playlist_position'];
				unset($this->SESS->cache['mc']['player']['playlist_position']);
		}
		else
		{
			$playlist['params']['position'] = '';
		}
		// end playlist position


		// Playlist Size
		if (isset($this->SESS->cache['mc']['player']['playlist_size']) !== false)
		{
			$playlist['params']['size'] = $this->SESS->cache['mc']['player']['playlist_size'];
				unset($this->SESS->cache['mc']['player']['playlist_size']);
		}
		else
		{
			$playlist['params']['size'] = '';
		}

		// Controlbar
		if ($this->TMPL->fetch_param('controlbar') === FALSE AND ($playlist['params']['position'] OR $playlist['params']['size']) AND ( $player['params']['native_height'] == 0 OR ($player['params']['container_tag'] == 'audio' AND $player['params']['native_height'] === FALSE))) // if controlbar is unspecified AND a playlist is showing AND the height would be zero, default the controlbar to top.
		{
			$player['params']['controlbar'] = 'top';
			$this->_log_item("NOTICE in MC Player plugin: Controlbar position defaults to 'top' when player height is zero and a playlist is shown; override with the 'controlbar' parameter.");
		}
		elseif ( ($player['params']['native_height'] <= 64) && ($this->TMPL->fetch_param('controlbar') == 'over') ) // warn (but allow) if player height not enough to show controlbar in 'over' mode properly
		{
			$player['params']['controlbar'] = 'over';
			$this->_log_item("WARNING in MC Player plugin: Player height is insufficient for properly displaying the controlbar in 'over' mode; controlbar may not display as intended.");
		}
		elseif ( ($player['params']['native_height'] <= 64) && ($this->TMPL->fetch_param('controlbar') === FALSE) ) // default controlbar position for height < 64 is 'bottom'
		{
			$player['params']['controlbar'] = 'bottom';
			$this->_log_item("NOTICE in MC Player plugin: Player height is insufficient for properly displaying the controlbar in the default 'over' mode; defaulting to 'bottom'; override with the 'controlbar' parameter.");
		}
		elseif ($this->TMPL->fetch_param('controlbar'))
		{
			$player['params']['controlbar'] = $this->TMPL->fetch_param('controlbar');
		}
		else
		{
			$player['params']['controlbar'] = 'over';
		}

		// Streaming stuff
		$player['params']['streamer'] = ($this->TMPL->fetch_param('streamer')) ? $this->TMPL->fetch_param('streamer') : '';
		$player['params']['http_startparam'] = ($this->TMPL->fetch_param('http.startparam')) ? $this->TMPL->fetch_param('http.startparam') : '';
		switch ($this->TMPL->fetch_param('provider'))
			{
				case 'http':
				case 'rtmp':
				case 'youtube':
					$player['params']['provider'] = $this->TMPL->fetch_param('provider');
				case false:
					break;
				default:
					$this->_log_item("WARNING in MC Player plugin: Specified 'provider' for player is not valid (http|rtmp|youtube); ignoring parameter");
					unset($player['params']['provider'], $player['params']['streamer']);
					break;
			}

		$player['size'] = $this->calculateSize($player['params']['native_width'], $player['params']['native_height'], $player['params']['fit_in_width'], $player['params']['controlbar'], $playlist['params']['position'], $playlist['params']['size']);

		// ----------------------------------------
		//   Container code
		// ----------------------------------------

		$container_params = ' id="' . $player['params']['container_id'] . '" class="' . $player['params']['container_class'] . '"';

		// Create the container
		switch ($player['params']['container_tag'])
		{

			case "video":
				$container = PHP_EOL . '<video' . $container_params;
				if (isset($player['params']['file']) && $player['params']['file'] != '')
				{
					$container .= ' src="' . $player['params']['file'] . '"';
				}
				elseif (isset($playlist['code']) || (isset($playlist['params']['file']) && $playlist['params']['file'] != '')) // a playlist was specified
				{
					$this->_log_item("ERROR in MC Player plugin: Playlists are not supported by the '<video>' element (use 'div' instead of 'video'); unable to continue.");
					return $this->TMPL->no_results();
				}
				else
				{
					$this->_log_item("ERROR in MC Player plugin: 'video' element specified but 'file' parameter not provided; unable to continue.");
					return $this->TMPL->no_results();
				}
				$container .= ' width="' . $player['size']['width'] . '"';
				$container .= ' height="' . $player['size']['height'] . '"';
				if ($player['params']['image']) $container .= ' poster="' . $player['params']['image'] . '"';
				if ($player['params']['controlbar'] != 'none') $container .= ' controls="controls"'; // show controlbar unless set to 'none'
				if ($player['params']['bufferlength']) $container .= ' preload="auto"'; // autobuffer unless buffer set to 0
				if ($player['params']['autostart']) $container .= ' autoplay="autoplay"';
				if ($player['params']['bgcolor']) $container .= ' style="background-color: ' . $player['params']['bgcolor'] . ';"';
				$container .= '>' . PHP_EOL . '</video>';
				break;

			case "audio":
// Currently the JW Player script does not support the <audio> tag.
// It does, however, work with the <video> tag, just with an audio
// file specified. When native <audio> support is implemented,
// replace the following section's <video> tags with <audio> tags.

				$container = PHP_EOL . '<video' . $container_params;
				if (isset($player['params']['file']) && $player['params']['file'] != '')
				{
					$container .= ' src="'.$player['params']['file'].'"';
				}
				elseif (isset($playlist['code']) || (isset($playlist['params']['file']) && $playlist['params']['file'] != '')) // a playlist was specified
				{
					$this->_log_item("ERROR in MC Player plugin: Playlists are not supported by the '<audio>' element (use 'div' instead of 'audio'); unable to continue.");
					return $this->TMPL->no_results();
				}
				else
				{
					$this->_log_item("ERROR in MC Player plugin: '<audio>' element specified but 'file' parameter not provided; unable to continue.");
					return $this->TMPL->no_results();
				}
				$container .= ' width="' . $player['size']['width'] . '"';
				$container .= ' height="' . $player['size']['height'] . '"';
				if ($player['params']['image']) $container .= ' poster="' . $player['params']['image'] . '"';
				$container .= ' controls="controls"'; // always show controlbar with audio
				if ($player['params']['bufferlength']) $container .= ' preload="auto"'; // autobuffer unless buffer set to 0
				if ($player['params']['autostart']) $container .= ' autoplay="autoplay"';
				if ($player['params']['bgcolor']) $container .= ' style="background-color: ' . $player['params']['bgcolor'] . ';"';
				$container .= '>' . PHP_EOL . '</video>';
				break;


			case "a":
				$container = '<a' . $container_params;
				if (isset($player['params']['file']) && $player['params']['file'] != '')
				{
					$container .= ' href="'.$player['params']['file'].'">Click to download (Javascript must be enabled to play this media)';
				}
				else
				{
					$this->_log_item("NOTICE in MC Player plugin: '<a>' element specified but no 'file' parameter provided; results may not be as expected for browsers without javascript.");
					$container .= ' href="#">Javascript must be enabled to play this media';
				}
				$container .= '</a>';
				break;

			 // We put "default" here because if an invalid
			 // "container_tag" is specified, we want to write a
			 // warning to the log, and then continue on to use the
			 // regular "div" behavior
			default:
					$this->_log_item("WARNING in MC Player plugin: Specified 'container_tag' is not valid; defaulting to <div>");
					$player['params']['container_tag'] = 'div';

			case "span":
			case "div":
				$container = '<' . $player['params']['container_tag'] . $container_params . '>Javascript must be enabled to play this media.</'.$player['params']['container_tag'].'>';
				break;
		}


		// ----------------------------------------
		//   Player script code
		// ----------------------------------------

		$script_start = PHP_EOL . "<script type='text/javascript'>";
		$script_start .= PHP_EOL . "jwplayer('" . $player['params']['container_id'] . "').setup({";
		$script = ($player['params']['playerpath']) ? PHP_EOL . "flashplayer: '" . $player['params']['playerpath'] . "'" . ',' : '';

			$script_properties = array();
			$script_properties['wmode'] = ($this->TMPL->fetch_param('wmode')) ? PHP_EOL . "wmode: '" . $this->TMPL->fetch_param('wmode') . "'" . ',' : PHP_EOL . "wmode: 'opaque'" . ',';
			$script_properties['skin'] = ($this->TMPL->fetch_param('skin')) ? PHP_EOL . "skin: '" . $this->TMPL->fetch_param('skin') . "'" . ',' : '';
			$script_properties['bgcolor'] = ($player['params']['bgcolor']) ? PHP_EOL . "bgcolor: '" . $player['params']['bgcolor'] . "'" . ',' : '';
			$script_properties['width'] = ($player['size']['width']) ? PHP_EOL . "width: '" . $player['size']['width'] . "'" . ',' : '';
			$script_properties['height'] = ($player['size']['height']) ? PHP_EOL . "height: '" . $player['size']['height'] . "'" . ',' : '';
			$script_properties['image'] = ($player['params']['image']) ? PHP_EOL . "image: '" . $player['params']['image'] . "'" . ',' : '';
			$script_properties['link'] = ($player['params']['link']) ? PHP_EOL . "link: '" . $player['params']['link'] . "'" . ',' : '';
			$script_properties['autostart'] = ($player['params']['autostart']) ? PHP_EOL . "autostart: '" . $player['params']['autostart'] . "'" . ',' : '';
			$script_properties['displayclick'] = ($player['params']['displayclick']) ? PHP_EOL . "displayclick: '" . $player['params']['displayclick'] . "'" . ',' : '';
			$script_properties['fullscreen'] = ($player['params']['fullscreen']) ? PHP_EOL . "fullscreen: '" . $player['params']['fullscreen'] . "'" . ',' : '';
			$script_properties['mute'] = ($player['params']['mute']) ? PHP_EOL . "mute: '" . $player['params']['mute'] . "'" . ',' : '';
			$script_properties['volume'] = ($player['params']['volume']) ? PHP_EOL . "volume: '" . $player['params']['volume'] . "'" . ',' : '';
			$script_properties['shuffle'] = ($player['params']['shuffle']) ? PHP_EOL . "shuffle: '" . $player['params']['shuffle'] . "'" . ',' : '';
			$script_properties['repeat'] = ($player['params']['repeat']) ? PHP_EOL . "repeat: '" . $player['params']['repeat'] . "'" . ',' : '';
			$script_properties['controlbar'] = ($player['params']['controlbar']) ? PHP_EOL . "'controlbar.position': '" . $player['params']['controlbar'] . "'" . ',' : '';
			$script_properties['showmute'] = ($player['params']['showmute']) ? PHP_EOL . "'display.showmute': '" . $player['params']['showmute'] . "'" . ',' : '';
			$script_properties['pl_position'] = ($playlist['params']['position']) ? PHP_EOL . "'playlist.position': '" . $playlist['params']['position'] . "'" . ',' : '';
			$script_properties['pl_size'] = ($playlist['params']['size']) ? PHP_EOL . "'playlist.size': '" . $playlist['params']['size'] . "'" . ',' : '';
			$script_properties['http_startparam'] = ($player['params']['http_startparam']) ? PHP_EOL . "'http.startparam': '" . $player['params']['http_startparam'] . "'" . ',' : '';


			// Determine which type of player to create based on which
			// variables have been prepared by the other functions
			if (isset($playlist['code']) && $playlist['code'] != '')
			{
				// playlist exists
				$script .= PHP_EOL . $playlist['code'] . ',';
			}
			elseif (isset($playlist['params']['file']) && $playlist['params']['file'] != '')
			{
				// Playlist mRSS file exists
				$script .= PHP_EOL . "'playlistfile': '" . $playlist['params']['file'] . "'" . ',';
			}
			elseif ($levels)
			{
				// levels exist
				$script .= PHP_EOL . $levels . ',';
			}
			elseif (isset($player['params']['file']) && $player['params']['file'] != '')
			{
				// regular file stuff
				$script .= PHP_EOL . "file: '" . $player['params']['file'] . "'" . ',';
			}
			else
			{
				$this->_log_item("ERROR in MC Player plugin: No file, playlist, or levels specified; aborting");
				return $this->TMPL->no_results();
			}

			if ($plugins)
			{
				// plugins exists
				$script .= PHP_EOL . $plugins . ',';
			}

			if ($modes)
			{
				// plugins exists
				$script .= PHP_EOL . $modes . ',';
			}

			foreach ($script_properties as $value) // Catch-all
			{
				$script .= $value;
			}

			if (isset($player['params']['provider']))
			{
				switch ($player['params']['provider'])
				{
					case 'http':
					case 'rtmp':
						$script .= PHP_EOL . 'provider: "' . $player['params']['provider'] . '",';
						$script .= ($player['params']['streamer']) ? PHP_EOL . 'streamer: "' . $player['params']['streamer'] . '",' : ''; // only valid for RTMP or HTTP
						break;
					case 'youtube':
						$script .= PHP_EOL . 'provider: "' . $player['params']['provider'] . '",';
						break;
					default:
						$this->_log_item("WARNING in MC Player plugin: Specified 'provider' for player is not valid (http|rtmp|youtube); ignoring parameter");
						unset($player['params']['provider'], $player['params']['streamer']);
						break;
				}
			}

		$script_end = PHP_EOL . "});";
		$script_end .= PHP_EOL . "</script>";

		return $container . $script_start . $this->indent(trim($script, ',')) . $script_end;


	} // END function play()


	/**
	 * Playlist
	 *
	 * This function combines all playlist items into a list
	 *
	 * @access	public
	 * @return	session cache
	 */
	function playlist()
	{


		$this->SESS->cache['mc']['player']['playlist_file'] = ($this->TMPL->fetch_param('file')) ? $this->TMPL->fetch_param('file') : '';

		// Size
		if ($this->TMPL->fetch_param('size')) // if something was specified for 'size'...
		{
			if (ctype_digit($this->TMPL->fetch_param('size'))) // ...and if 'size' is an integer
			{
				$this->SESS->cache['mc']['player']['playlist_size'] = $this->TMPL->fetch_param('size');
			}
			else // otherwise, if 'size' ISN'T an integer, use default
			{
				$this->_log_item("WARNING in MC Player plugin: Specified 'size' for playlist is not an integer; resetting to default (180)");
				$this->SESS->cache['mc']['player']['playlist_size'] = 160;
			}
		}

		// Position
		if ($this->TMPL->fetch_param('position'))
		{
			switch ($this->TMPL->fetch_param('position')) // input value verification
			{
				case "left":
				case "right":
				case "top":
				case "bottom":
					$this->SESS->cache['mc']['player']['playlist_position'] = $this->TMPL->fetch_param('position');
					break;
				default:
					$this->_log_item("WARNING in MC Player plugin: Invalid 'position' for playlist tag; defaulting to 'bottom'");
					$this->SESS->cache['mc']['player']['playlist_position'] = "bottom";
					break;
			}
		}

		// Items
		if (isset($this->SESS->cache['mc']['player']['items']) !== FALSE)
		{
			$this->SESS->cache['mc']['player']['playlist'] = "playlist: [" . $this->indent(trim($this->SESS->cache['mc']['player']['items'], ",")) . PHP_EOL . "]";
			unset($this->SESS->cache['mc']['player']['items']);
		}
		elseif (isset($this->SESS->cache['mc']['player']['playlist_file']) !== FALSE)
		{
			// do nothing; no error either
		}
		else
		{
			$this->_log_item("ERROR in MC Player plugin: 'playlist' container specified, but neither 'file' parameter nor embedded 'item' items were found");
			return $this->TMPL->no_results();
		}
	} // END function playlist()


	/**
	 * Item
	 *
	 * This function prepares each playlist item
	 *
	 * @access	public
	 * @return	session cache
	 */
	function item()
	{


		// Assignment
		$item['params']['file'] = ($this->TMPL->fetch_param('file')) ? $this->TMPL->fetch_param('file') : '';
		$item['params']['image'] = ($this->TMPL->fetch_param('image')) ? $this->TMPL->fetch_param('image') : '';
		$item['params']['duration'] = ($this->TMPL->fetch_param('duration')) ? $this->TMPL->fetch_param('duration') : NULL;
		$item['params']['start'] = ($this->TMPL->fetch_param('start')) ? $this->TMPL->fetch_param('start') : NULL;
		$item['params']['title'] = ($this->TMPL->fetch_param('title')) ? $this->TMPL->fetch_param('title') : '';
		$item['params']['description'] = ($this->TMPL->fetch_param('description')) ? $this->TMPL->fetch_param('description') : '';
		$item['params']['streamer'] = ($this->TMPL->fetch_param('streamer')) ? $this->TMPL->fetch_param('streamer') : '';
		$item['params']['provider'] = ($this->TMPL->fetch_param('provider')) ? $this->TMPL->fetch_param('provider') : '';
		if (isset($this->SESS->cache['mc']['player']['levels']))
		{
			$item['levels'] = $this->SESS->cache['mc']['player']['levels'];
		}
		else
		{
			$item['levels'] = '';
		}
		unset($this->SESS->cache['mc']['player']['levels']);


	// Validation & Formatting

		// 'file' or 'levels' required
		if ($item['levels'])
		{
			$item['code'] = PHP_EOL . trim($item['levels'], ",\t" . PHP_EOL) . ",";
			if ($item['params']['file']) $this->_log_item("NOTICE in MC Player plugin: 'file' specified for playlist item when 'levels' already specified; ignoring 'file'");
		}
		elseif ($item['params']['file'])
		{
			if (!isset($this->SESS->cache['mc']['player']['id']))
			{
				$this->SESS->cache['mc']['player']['id'] = '';
			}
			$this->SESS->cache['mc']['player']['id'] .= "_" . basename(html_entity_decode($item['params']['file'])); // used for auto-naming container ID
			$item['code'] = PHP_EOL . 'file: "' . $item['params']['file'] . '",';
		}
		else
		{
			$this->_log_item("WARNING in MC Player plugin: No 'file' specified for playlist item; skipping");
			return $this->TMPL->no_results();
		}

		// image
		$item['code'] .= ($item['params']['image']) ? PHP_EOL . 'image: "' . $item['params']['image'] . '",' : '';

		// 'duration' and 'start' should be integers
		if (ctype_digit($item['params']['duration'])) {
			$item['code'] .= PHP_EOL . 'duration: ' . $item['params']['duration'] . ',';
		} elseif ($item['params']['duration'] != '') {
			$this->_log_item("WARNING in MC Player plugin: Specified 'duration' for item is not an integer; ignoring parameter");
			unset($item['params']['duration']);
		}

		if (ctype_digit($item['params']['start'])) {
			$item['code'] .= PHP_EOL . 'start: '.$item['params']['start'].',';
		} elseif ($item['params']['start'] != '') {
			$this->_log_item("WARNING in MC Player plugin: Specified 'start' for item is not an integer; ignoring parameter");
			unset($item['params']['start']);
		}

		$item['code'] .= ($item['params']['title']) ? PHP_EOL . 'title: "' . $item['params']['title'] . '",' : '';
		$item['code'] .= ($item['params']['description']) ? PHP_EOL . 'description: "' . $item['params']['description'] . '",' : '';

		switch ($item['params']['provider'])
		{
			case 'http':
			case 'rtmp':
				$item['code'] .= PHP_EOL . 'provider: "' . $item['params']['provider'] . '",';
				$item['code'] .= ($item['params']['streamer']) ? PHP_EOL . "\t\t" . 'streamer: "' . $item['params']['streamer'] . '",' : ''; // only valid for RTMP or HTTP
				break;
			case 'youtube':
				$item['code'] .= PHP_EOL . 'provider: "' . $item['params']['provider'] . '",';
				break;
			case '':
				break;
			default:
				$this->_log_item("WARNING in MC Player plugin: Specified 'provider' for item is not valid (http|rtmp|youtube); ignoring parameter");
				unset($item['params']['provider']);
				break;
		}



		$item['code'] = PHP_EOL . "{" . $this->indent(trim($item['code'], ", ")) . PHP_EOL . "},";

		// store result in session for use upstream
		if (!isset($this->SESS->cache['mc']['player']['items']))
		{
			$this->SESS->cache['mc']['player']['items'] = '';
		}
		$this->SESS->cache['mc']['player']['items'] .= $item['code'];

	} // END function item()


	/**
	 * Levels
	 *
	 * This function combines each level into a list
	 *
	 * @access	public
	 * @return	session cache
	 */
	function levels()
	{


		if (isset($this->SESS->cache['mc']['player']['level_list']) !== FALSE)
		{
			$this->SESS->cache['mc']['player']['levels'] = PHP_EOL . "levels: [" . trim($this->SESS->cache['mc']['player']['level_list'], ",") . PHP_EOL . "]";
			unset($this->SESS->cache['mc']['player']['level_list']);
		}
		else
		{
			$this->_log_item("ERROR in MC Player plugin: 'levels' container specified, but no 'level' items were found");
			return $this->TMPL->no_results();
		}
	} // END function levels()


	/**
	 * Level
	 *
	 * This function prepares each level
	 *
	 * @access	public
	 * @return	session cache
	 */
	function level()
	{


		// Assignment
		$level['params']['bitrate'] = ($this->TMPL->fetch_param('bitrate')) ? $this->TMPL->fetch_param('bitrate') : '';
		$level['params']['width'] = ($this->TMPL->fetch_param('width')) ? $this->TMPL->fetch_param('width') : '';
		$level['params']['file'] = ($this->TMPL->fetch_param('file')) ? $this->TMPL->fetch_param('file') : '';

		// Validation & Formatting
		if (ctype_digit($level['params']['bitrate']))
		{
			$level['code']['bitrate'] = 'bitrate: ' . $level['params']['bitrate'] . ', ';
		}
		elseif ($level['params']['bitrate'])
		{
			$this->_log_item("WARNING in MC Player plugin: Specified 'bitrate' for level is not an integer; ignoring parameter");
			unset($level['params']['bitrate']);
		}

		if (ctype_digit($level['params']['width']))
		{
			$level['code']['width'] = 'width: ' . $level['params']['width'] . ', ';
		}
		elseif ($level['params']['width'])
		{
			$this->_log_item("WARNING in MC Player plugin: Specified 'width' for level is not an integer; ignoring parameter");
			unset($level['params']['width']);
		}

		if ($level['params']['file'])
		{
			$this->SESS->cache['mc']['player']['id'] .= "_" . basename(html_entity_decode($level['params']['file'])); // used for auto-naming container ID
			$level['code']['file'] = 'file: "' . $level['params']['file'] . '"';
		}
		else
		{
			$this->_log_item("ERROR in MC Player plugin: No 'file' specified for level; unable to process level");
			return $this->TMPL->no_results();
		}

		$level['code']['output'] = $this->indent(PHP_EOL . "{ " . $level['code']['bitrate'] . $level['code']['width'] . $level['code']['file'] . " },");

		// store result in session for use upstream
		$this->SESS->cache['mc']['player']['level_list'] .= $level['code']['output'];

	} // END function level()


	/**
	 * Plugins
	 *
	 * This function combines each plugin into a list
	 *
	 * @access	public
	 * @return	session cache
	 */
	function plugins()
	{


		if (isset($this->SESS->cache['mc']['player']['plugin_list']) !== FALSE)
		{
			$this->SESS->cache['mc']['player']['plugins'] = PHP_EOL . "plugins: {" . trim($this->SESS->cache['mc']['player']['plugin_list'], ",") . PHP_EOL . "}";
			unset($this->SESS->cache['mc']['player']['plugin_list']);
		}
		else
		{
			$this->_log_item("ERROR in MC Player plugin: 'plugins' container specified, but no 'plugin' items were found");
			return $this->TMPL->no_results();
		}
	} // END function plugins()


	/**
	 * Plugin
	 *
	 * This function prepares each plugin
	 *
	 * @access	public
	 * @return	session cache
	 */
	function plugin()
	{


		if ($plugin_name = $this->TMPL->fetch_param('plugin_name'))
		{
			// Prepare vars
			$this->SESS->cache['mc']['player']['plugin_list'] = '';

			// Process each param
			$params = '';
			foreach ($this->TMPL->tagparams as $pname => $pvalue)
			{
				if ($pname != "plugin_name") $params .= $pname . ': "' . $pvalue . '", ';
			}

			// Format params into full plugin line
			$plugin = $this->indent(PHP_EOL . $plugin_name . ": { " . trim($params, ', ') . " },");

			// store result in session for use upstream
			$this->SESS->cache['mc']['player']['plugin_list'] .= $plugin;
		}
		else
		{
			$this->_log_item("ERROR in MC Player plugin: No 'plugin_name' specified for plugin; unable to continue");
			return $this->TMPL->no_results();
		}

	} // END function plugin()


	/**
	 * Modes
	 *
	 * This function combines each mode into a list
	 *
	 * @access	public
	 * @return	session cache
	 */
	function modes()
	{


		if (isset($this->SESS->cache['mc']['player']['mode_list']) !== FALSE)
		{
			$this->SESS->cache['mc']['player']['modes'] = PHP_EOL . "modes: [" . trim($this->SESS->cache['mc']['player']['mode_list'], ",") . PHP_EOL . "]";
			unset($this->SESS->cache['mc']['player']['mode_list']);
		}
		else
		{
			$this->_log_item("ERROR in MC Player plugin: 'modes' container specified, but no 'mode' items were found; ignoring.");
		}
	} // END function modes()


	/**
	 * Mode
	 *
	 * This function prepares each player mode
	 *
	 * @access	public
	 * @return	session cache
	 */
	function mode()
	{

		// Assignment
		$mode['params']['type'] = ($this->TMPL->fetch_param('type')) ? $this->TMPL->fetch_param('type') : '';
		$mode['params']['src'] = ($this->TMPL->fetch_param('src')) ? $this->TMPL->fetch_param('src') : '';


		// $mode['code']['params'] = '';

		switch ($mode['params']['type'])
		{
			case 'flash':
				if ($mode['params']['src'])
				{
					$mode['code']['params'] = PHP_EOL . 'type: "' . $mode['params']['type'] . '", ';
					$mode['code']['params'] .= PHP_EOL . 'src: "' . $mode['params']['src'] . '", ';
				}
				else
				{
					$this->_log_item("ERROR in MC Player plugin: No 'src' specified for 'flash' mode; skipping current mode");
				}
				break;

			case 'html5':
			case 'download':
					$mode['code']['params'] = PHP_EOL . 'type: "' . $mode['params']['type'] . '", ';
				break;

			default:
				$this->_log_item("ERROR in MC Player plugin: No 'type' specified for mode; skipping current mode");
				break;
		}

		// Include alternate config if specified
		if (isset($this->SESS->cache['mc']['player']['mode']['config']) !== FALSE)
		{
			$mode['code']['params'] = PHP_EOL . "config: {" . trim($this->SESS->cache['mc']['player']['mode']['config'], ",") . PHP_EOL . "}";
			unset($this->SESS->cache['mc']['player']['mode']['config']);
		}

		// Format params into full plugin line
		$mode['code']['output'] = $this->indent(PHP_EOL . "{ " . $this->indent(trim($mode['code']['params'], ', ')) . PHP_EOL . "},");
		// store result in session for use upstream
		if (isset($this->SESS->cache['mc']['player']['mode_list']))
		{
			$this->SESS->cache['mc']['player']['mode_list'] .= $mode['code']['output'];
		}
		else
		{
			$this->SESS->cache['mc']['player']['mode_list'] = $mode['code']['output'];
		}

	} // END function mode()


	/**
	 * Config
	 *
	 * This function deals with configuration variables
	 *
	 * @access	public
	 * @return	session cache
	 */
	function config()
	{

			// Process each param
			$params = '';
			foreach ($this->TMPL->tagparams as $name => $value)
			{
				if ($name == 'provider')
				{
					switch ($value)
					{
						case 'http':
						case 'rtmp':
						case 'youtube':
							$params .= PHP_EOL . "\t\t" . 'provider: "' . $value . '",';
							break;
						case '':
							break;
						default:
							$this->_log_item("WARNING in MC Player plugin: Specified 'provider' for mode is not valid (http|rtmp|youtube); ignoring parameter");
							break;
					}
				}
				else
				{
					$params .= PHP_EOL . "\t\t" . $name . ': "' . $value . '",';
				}
			}

			// store result in session for use upstream
			$this->SESS->cache['mc']['player']['mode']['config'] = $config;

	} // END function config()


	/**
	 * Usage
	 *
	 * This function describes how the plugin is used.
	 *
	 * @access	public
	 * @return	string
	 */
	function usage()
	{
		ob_start();
?>

Requirements
============

- JW Media Player 5.7 or higher


Initial Setup
=============

Upload the JW Media Player files to a directory on your server. Then
add the following line in the <head>...</head> section of your site,
making sure to modify the path to the script:
<script type='text/javascript' src='/path/to/jwplayer.js'></script>


Examples
========

Single file
-----------

### Video
	{exp:mc_player:play file="video.mp4" width="480" height="270" playerpath="/path/to/player.swf"}

### Audio
	{exp:mc_player:play file="audio.mp3" width="480" playerpath="/path/to/player.swf"
	 container_tag="audio"}

Note: The JW Player does not yet support the HTML 5 `<audio>` tag; in this case, the `<video>` tag will be used.


Playlists
---------

### XML/RSS
	{exp:mc_player:play width="480" height="270" playerpath="/path/to/player.swf"}
		{exp:mc_player:playlist file="playlist.xml" position="right" size="360"}
	{/exp:mc_player:play}

### Javascript
	{exp:mc_player:play width="480" height="270" playerpath="/path/to/player.swf"}
		{exp:mc_player:playlist position="bottom" size="240"}
			{exp:mc_player:item file="entry_1.mp4" image="entry_1.jpg" duration="54" title="First item"}
			{exp:mc_player:item file="entry_2.mp4"}
			{exp:mc_player:item file="entry_3.mp4"}
		{/exp:mc_player:playlist}
	{/exp:mc_player:play}


File with levels
----------------

	{exp:mc_player:play width="480" height="270" playerpath="/path/to/player.swf"
	 provider="http" http.startparam="starttime"}
		{exp:mc_player:levels}
				{exp:mc_player:level bitrate="300" file="vid_320.mp4" width="320"}
				{exp:mc_player:level bitrate="600" file="vid_480.mp4" width="480"}
				{exp:mc_player:level bitrate="900" file="vid_720.mp4" width="720"}
		{/exp:mc_player:levels}
	{/exp:mc_player:play}


File with modes
---------------

	{exp:mc_player:play width="480" height="270" file="vid_480.mp4"}
		{exp:mc_player:modes}
				{exp:mc_player:mode type="html5"}
				{exp:mc_player:mode type="flash" src="/path/to/player.swf"}
				{exp:mc_player:mode type="download"}
		{/exp:mc_player:modes}
	{/exp:mc_player:play}


File with skin & plugin
-----------------------

	{exp:mc_player:play width="480" height="270" file="vid_480.mp4" skin="/path/to/skin.zip" skin_height="42" controlbar="bottom"}
		{exp:mc_player:plugins}
				{exp:mc_player:plugin plugin_name="hd" file="vid_720.mp4" fullscreen="true"}
				{exp:mc_player:plugin plugin_name="gapro" accountid="UKsi93X940-24"}
		{/exp:mc_player:plugins}
	{/exp:mc_player:play}

NOTES:
{exp:mc_player:play} - The "skin_height" parameter can be added when the controlbar is "top" or "bottom" and the skin's controlbar height differs from the default skin's height of 24 pixels.
{exp:mc_player:plugin} - Any parameters specified will be passed along as-is; only the "plugin_name" parameter is required.


JS playlist with files & levels
-------------------------------

	{exp:mc_player:play width="320" height="240"}
		{exp:mc_player:playlist position="right" size="360"}
			{exp:mc_player:item file="entry_1.mp4" image="entry_1.jpg" duration="54"}{/exp:mc_player:item}
			{exp:mc_player:item file="entry_2.mp4" image="entry_2.jpg" duration="42"}{/exp:mc_player:item}
			{exp:mc_player:item}
				{exp:mc_player:levels}
					{exp:mc_player:level bitrate="300" file="entry_1_320.mp4" width="320"}
					{exp:mc_player:level bitrate="600" file="entry_1_480.mp4" width="480"}
					{exp:mc_player:level bitrate="900" file="entry_1_720.mp4" width="720"}
				{/exp:mc_player:levels}
			{/exp:mc_player:item}
			{exp:mc_player:item file="entry_3.mp4" image="entry_3.jpg" duration="564"}{/exp:mc_player:item}
		{/exp:mc_player:playlist}
	{/exp:mc_player:play}

Please note: if any tag is used as a tag pair instead of a single tag, all tags of that specific type must also be pairs (in this case, the `exp:mc_player:item` tag pairs)

A simple view of the heirarchy for the above code could be:

	- player
		-- playlist
			--- item 1
			--- item 2
			--- item 3
				---- levels
					----- level
					----- level
					----- level
			--- item 4


Parameters (WIP)
================

{exp:mc_player:play}
--------------------

	- skin_height = $pixels_integer // default = 0
	Used when specifying a custom player skin that has a controlbar whose height is different than the 24 pixels of the default skin. Only useful when the controlbar param is set to "top" or "bottom".

{exp:mc_player:plugin}
--------------------

	- plugin_name = $string
	The name of the plugin

<?php
		$buffer = ob_get_contents();
		ob_end_clean();
		return $buffer;
	} // END function usage()
}


/* End of file pi.mc_player.php */
/* Location: /system/plugins/pi.mc_player.php */