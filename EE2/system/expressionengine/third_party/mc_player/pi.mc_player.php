<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');

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
		- <audio> tag support (awaiting support from LongTail)
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
	'pi_version'		=> '0.2',
	'pi_author'			=> 'Michael C.',
	'pi_author_url'		=> 'http://www.pro-image.co.il/',
	'pi_description'	=> 'An imlementation of the JW HTML5 Media Player',
	'pi_usage'			=> Mc_player::usage()
);

class Mc_player
{

	public function __construct()
	{
		$this->EE =& get_instance();
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
			$this->EE->TMPL->log_item($tab . '- ' . $string);
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
	private function calculateSize($native_width, $native_height, $fit_in_width, $controlbar, $pl_position = '', $pl_size = '')
	{
		$size = array();
		
		$skin_height = 0; // height of skin control interface
		if ($controlbar != 'over') { $skin_height = 24; }

		if ($fit_in_width)
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
		// global $TMPL, $SESS; // EE1

		// Each parent function, after using a session cache
		// variable, must unset it to prevent it persisting
		// beyond the current context.
		if (isset($this->EE->session->cache['mc']['player']['playlist'])) // inline JSON playlist
		{
			$playlist = $this->EE->session->cache['mc']['player']['playlist'];
			unset($this->EE->session->cache['mc']['player']['playlist']);
		}
		elseif ($this->EE->TMPL->fetch_param('playlist')) // external XML playlist (placed in the playlist parameter)
		{
			$file = $this->EE->TMPL->fetch_param('playlist');
			$is_playlist = true;
		}
		elseif ($this->EE->TMPL->fetch_param('file')) // single file
		{
			$file = $this->EE->TMPL->fetch_param('file');
		}
		$levels = (isset($this->EE->session->cache['mc']['player']['levels'])) ? $this->EE->session->cache['mc']['player']['levels'] : '';
			unset($this->EE->session->cache['mc']['player']['levels']);
		$plugins = (isset($this->EE->session->cache['mc']['player']['plugins'])) ? $this->EE->session->cache['mc']['player']['plugins'] : '';
			unset($this->EE->session->cache['mc']['player']['plugins']);
		$modes = (isset($this->EE->session->cache['mc']['player']['modes'])) ? $this->EE->session->cache['mc']['player']['modes'] : '';
			unset($this->EE->session->cache['mc']['player']['modes']);
		
	// Validate parameter values
		
		// Container tag stuff
		$container_tag = $this->EE->TMPL->fetch_param('container_tag', 'video'); // valid values are 'video' (the default), 'audio', 'div', 'span', 'a'
		if ($this->EE->TMPL->fetch_param('container_id')) // Use specified ID of container
		{
			$container_id = $this->EE->TMPL->fetch_param('container_id');
		}
		elseif (isset($file) && $file != '') // generate unique ID based on filename
		{
			$container_id = 'player_' . str_replace('.','-',basename(html_entity_decode($file)));
		}
		elseif (isset($this->EE->session->cache['mc']['player']['id']) && $this->EE->session->cache['mc']['player']['id'] != '')
		{
			$container_id = 'player_' . str_replace('.','-',$this->EE->session->cache['mc']['player']['id']); // Use ID prepared downstream
		}
		else // resort to a default ID
		{
			$container_id = "player_container";
		}

		// class only carries through to the native containers;
		// if they are replaced via js with flash or other
		// players, the class is not respected
		$container_class = $this->EE->TMPL->fetch_param('container_class', 'media_player');

		// Size
		if ( ctype_digit($this->EE->TMPL->fetch_param('width')) ) {
			$native_width = $this->EE->TMPL->fetch_param('width');
		} else {
			$native_width = 300; // Default width of <video> element?
			$this->_log_item("WARNING in MC Player plugin: Specified 'width' is not an integer; defaulting to 300");
		}
		
		// $native_height is the height of the display area
		// only. If the controlbar is displayed on bottom,
		// for example, the element's height would be
		// $native_height + $skin_height.
		if ( ctype_digit($this->EE->TMPL->fetch_param('height')) )
		{
			$native_height = $this->EE->TMPL->fetch_param('height');
		}
		elseif ($container_tag == 'audio')
		{
			$native_height = 0; // Default height for audio player
			if ($this->EE->TMPL->fetch_param('height'))
			{
				$this->_log_item("NOTICE in MC Player plugin: 'container_tag' was set to 'audio', but 'height' was invalid; defaulting to 0");
			}
		}
		else
		{
			$native_height = 150; // Default height of <video> element?
			$this->_log_item("WARNING in MC Player plugin: Specified 'height' is not an integer; defaulting to 150");
		}
		
		if ( ctype_digit($this->EE->TMPL->fetch_param('fit_in_width')) )
		{
			$fit_in_width = $this->EE->TMPL->fetch_param('fit_in_width');
		}
		elseif ($this->EE->TMPL->fetch_param('fit_in_width') !== false)
		{
			$this->_log_item("WARNING in MC Player plugin: Specified 'fit_in_width' is not an integer; ignoring");
			$fit_in_width = '';
		}
		else
		{
			$fit_in_width = '';
		}
		
		// Other
		$playerpath = $this->EE->TMPL->fetch_param('playerpath');
		$bgcolor = $this->EE->TMPL->fetch_param('bgcolor');
		$image = $this->EE->TMPL->fetch_param('image');
		$link = $this->EE->TMPL->fetch_param('link');
		if ($this->EE->TMPL->fetch_param('autostart'))
		{
			$autostart = $this->EE->TMPL->fetch_param('autostart');
		}
		elseif ($this->EE->TMPL->fetch_param('autoplay')) // because I kept forgetting which 'auto-' to use :p
		{
			$autostart = $this->EE->TMPL->fetch_param('autoplay');
		}
		else
		{
			$autostart = '';
		}
		$bufferlength = $this->EE->TMPL->fetch_param('bufferlength');
		$displayclick = $this->EE->TMPL->fetch_param('displayclick');
		$fullscreen = $this->EE->TMPL->fetch_param('fullscreen');
		$mute = $this->EE->TMPL->fetch_param('mute');
		$volume = $this->EE->TMPL->fetch_param('volume', '80');
		$showmute = $this->EE->TMPL->fetch_param('showmute');
		
		// Controlbar
		if ($this->EE->TMPL->fetch_param('controlbar'))
		{
			$controlbar = $this->EE->TMPL->fetch_param('controlbar');
			if ( ($container_tag == 'audio') && ($controlbar == 'over') )
			{
				$this->_log_item("NOTICE in MC Player plugin: Audio container specified but controlbar set to 'over'; may not display as intended.");
			}
		}
		elseif ($container_tag == 'audio')
		{
			$controlbar = 'bottom';
		}
		else
		{
			$controlbar = 'over';
		}
		
		// Streaming stuff
		$streamer = $this->EE->TMPL->fetch_param('streamer');
		$http_startparam = $this->EE->TMPL->fetch_param('http.startparam');
		switch ($this->EE->TMPL->fetch_param('provider'))
			{
				case 'http':
				case 'rtmp':
				case 'youtube':
					$provider = $this->EE->TMPL->fetch_param('provider');
				case false:
					break;
				default:
					$this->_log_item("WARNING in MC Player plugin: Specified 'provider' for player is not valid (http|rtmp|youtube); ignoring parameter");
					unset($provider, $streamer);
					break;
			}
		
		// Playlist stuff
		$shuffle = $this->EE->TMPL->fetch_param('shuffle');
		$repeat = $this->EE->TMPL->fetch_param('repeat');
		
		switch ($repeat) // input value verification
		{
			case "none":
			case "list":
			case "always":
			case "single":
				break;
			case false:
				$repeat = '';
				break;
			default:
				$this->_log_item("WARNING in MC Player plugin: Invalid 'repeat' for player tag; ignoring");
				$repeat = '';
				break;
		}
		
		if (isset($this->EE->session->cache['mc']['player']['playlist_position']) !== false)
		{
			$specified_pl_position = $this->EE->session->cache['mc']['player']['playlist_position'];
				unset($this->EE->session->cache['mc']['player']['playlist_position']);
		}
		elseif ($this->EE->TMPL->fetch_param('playlist_position') !== false)
		{
			$specified_pl_position = $this->EE->TMPL->fetch_param('playlist_position');
		}
		// take into account if a playlist size was provided without a position (intent is to show playlist)
		elseif ($this->EE->TMPL->fetch_param('playlist_size') !== false)
		{
			$specified_pl_position = "bottom"; // default to 'bottom'
		}
		
		if (isset($specified_pl_position))
		{
			switch ($specified_pl_position) // input value verification
			{
				case "left":
				case "right":
				case "top":
				case "bottom":
					$pl_position = $specified_pl_position;
					break;
				default:
					$this->_log_item("WARNING in MC Player plugin: Invalid 'playlist_position' for player tag; defaulting to 'bottom'");
					$pl_position = "bottom";
					break;
			}
		}
		// end playlist stuff
		
		
		// Playlist Size
		if (isset($this->EE->session->cache['mc']['player']['playlist_size']))
		{
			$pl_size = $this->EE->session->cache['mc']['player']['playlist_size'];
				unset($this->EE->session->cache['mc']['player']['playlist_size']);
		}
		elseif ($this->EE->TMPL->fetch_param('playlist_size') !== false)
		{
			$pl_size = $this->EE->TMPL->fetch_param('playlist_size');
		}
		// using $pl_position here merely to help determine if a playlist was specified.
		elseif (isset($is_playlist) || isset($pl_position))
		{
			$pl_size = 180;
		}
		
		if (isset($pl_size) && !ctype_digit($pl_size)) { // 'size' should be an integer
			$this->_log_item("WARNING in MC Player plugin: Specified 'playlist_size' for player or 'size' for playlist is not an integer; resetting to default (180)");
			$pl_size = 180;
		}


		$size = $this->calculateSize($native_width, $native_height, $fit_in_width, $controlbar, $pl_position, $pl_size);


		// ----------------------------------------
		//   Container code
		// ----------------------------------------
		
		$container_params = ' id="'.$container_id.'" class="'.$container_class.'"';
		
		// Create the container
		switch ($container_tag)
		{
			case "div":
			case "span":
				$container = '<' . $container_tag . $container_params . '>Javascript must be enabled to play this media.</'.$container_tag.'>';
				break;
			case "a":
				$container = '<a href="'.$file.'"' . $container_params . '>Javascript must be enabled to play this media.</a>';
				break;

			case "audio":
// Currently the JW Player script does not support the <audio> tag.
// It does, however, work with the <video> tag, just with an audio
// file specified. When native <audio> support is implemented,
// replace the following section's <video> tags with <audio> tags.


				$container = PHP_EOL . '<video' . $container_params;
				if ($file) // $file might be either a media file or an XML playlist
				{
					$container .= ' src="'.$file.'"';
				}
				/*else
				{
					$this->_log_item("ERROR in MC Player plugin: '<video>' element specified but neither 'file' nor 'playlist' parameters provided; unable to continue.");
					return $this->EE->TMPL->no_results();
				}*/
				$container .= ' width="'.$size['width'].'"';
				$container .= ' height="'.$size['height'].'"';
				if ($image) $container .= ' poster="'.$image.'"';
				$container .= ' controls="controls"'; // always show controlbar with audio
				if ($bufferlength) $container .= ' preload="auto"'; // autobuffer unless buffer set to 0
				if ($autostart) $container .= ' autoplay="autoplay"';
				if ($bgcolor) $container .= ' style="background-color: '.$bgcolor.';"';
				$container .= '>' . PHP_EOL . '</video>';
				break;


			 // We put "default" here because if an invalid
			 // "container_tag" is specified, we want to write a
			 // warning to the log, and then continue on to use the
			 // regular "video" behavior
			default:
					$this->_log_item("WARNING in MC Player plugin: Specified 'container_tag' is not valid; defaulting to <video>");

			case "video":
				$container = PHP_EOL . '<video' . $container_params;
				if (isset($file) && $file != '') // $file might be either a media file or an XML playlist
				{
					$container .= ' src="'.$file.'"';
				}
				/*else
				{
					$this->_log_item("ERROR in MC Player plugin: '<video>' element specified but neither 'file' nor 'playlist' parameters provided; unable to continue.");
					return $this->EE->TMPL->no_results();
				}*/
				$container .= ' width="'.$size['width'].'"';
				$container .= ' height="'.$size['height'].'"';
				if ($image) $container .= ' poster="'.$image.'"';
				if ($controlbar != 'none') $container .= ' controls="controls"'; // show controlbar unless set to 'none'
				if ($bufferlength) $container .= ' preload="auto"'; // autobuffer unless buffer set to 0
				if ($autostart) $container .= ' autoplay="autoplay"';
				if ($bgcolor) $container .= ' style="background-color: '.$bgcolor.';"';
				$container .= '>' . PHP_EOL . '</video>';
				break;
		}
		
		
		// ----------------------------------------
		//   Player script code
		// ----------------------------------------
		
		$script_start = PHP_EOL . "<script type='text/javascript'>";
		$script_start .= PHP_EOL . "jwplayer('" . $container_id . "').setup({";
		$script = ($playerpath) ? PHP_EOL . "flashplayer: '".$playerpath."'" . ',' : '';

			$script_properties = array();
			$script_properties['wmode'] = ($this->EE->TMPL->fetch_param('wmode')) ? PHP_EOL . "wmode: '".$this->EE->TMPL->fetch_param('wmode')."'" . ',' : PHP_EOL . "wmode: 'opaque'" . ',';
			$script_properties['skin'] = ($this->EE->TMPL->fetch_param('skin')) ? PHP_EOL . "skin: '".$this->EE->TMPL->fetch_param('skin')."'" . ',' : '';
			$script_properties['bgcolor'] = ($bgcolor) ? PHP_EOL . "bgcolor: '".$bgcolor."'" . ',' : '';
			$script_properties['width'] = ($size['width']) ? PHP_EOL . "width: '".$size['width']."'" . ',' : '';
			$script_properties['height'] = ($size['height']) ? PHP_EOL . "height: '".$size['height']."'" . ',' : '';
			$script_properties['image'] = ($image) ? PHP_EOL . "image: '".$image."'" . ',' : '';
			$script_properties['link'] = ($link) ? PHP_EOL . "link: '".$link."'" . ',' : '';
			$script_properties['autostart'] = ($autostart) ? PHP_EOL . "autostart: '".$autostart."'" . ',' : '';
			$script_properties['displayclick'] = ($displayclick) ? PHP_EOL . "displayclick: '".$displayclick."'" . ',' : '';
			$script_properties['fullscreen'] = ($fullscreen) ? PHP_EOL . "fullscreen: '".$fullscreen."'" . ',' : '';
			$script_properties['mute'] = ($mute) ? PHP_EOL . "mute: '".$mute."'" . ',' : '';
			$script_properties['volume'] = ($volume) ? PHP_EOL . "volume: '".$volume."'" . ',' : '';
			$script_properties['shuffle'] = ($shuffle) ? PHP_EOL . "shuffle: '".$shuffle."'" . ',' : '';
			$script_properties['repeat'] = ($repeat) ? PHP_EOL . "repeat: '".$repeat."'" . ',' : '';
			$script_properties['controlbar'] = ($controlbar) ? PHP_EOL . "'controlbar.position': '".$controlbar."'" . ',' : '';
			$script_properties['showmute'] = ($showmute) ? PHP_EOL . "'display.showmute': '".$showmute."'" . ',' : '';
			$script_properties['pl_position'] = ($pl_position) ? PHP_EOL . "'playlist.position': '".$pl_position."'" . ',' : '';
			$script_properties['pl_size'] = ($pl_size) ? PHP_EOL . "'playlist.size': '".$pl_size."'" . ',' : '';
			$script_properties['http_startparam'] = ($http_startparam) ? PHP_EOL . "'http.startparam': '".$http_startparam."'" . ',' : '';
		
	
			// Determine which type of player to create based on which
			// variables have been prepared by the other functions
			if (isset($playlist))
			{
				// playlist exists
				$script .= PHP_EOL . $playlist . ',';
			}
			elseif ($levels != '')
			{
				// levels exist
				$script .= PHP_EOL . $levels . ',';
			}
			elseif ($file != '')
			{
				// regular file stuff
				$file = ($file) ? "file: '".$file."'" : '';
				$script .= PHP_EOL . $file . ',';
			}
			else
			{
				$this->_log_item("ERROR in MC Player plugin: No file, playlist, or levels specified; aborting");
				return $this->EE->TMPL->no_results();
			}
			
			if ($plugins != '')
			{
				// plugins exists
				$script .= PHP_EOL . $plugins . ',';
			}
			
			if ($modes != '')
			{
				// plugins exists
				$script .= PHP_EOL . $modes . ',';
			}
			
			foreach ($script_properties as $value) // Catch-all
			{
				$script .= $value;
			}
			
			if (isset($provider))
			{
				switch ($provider)
				{
					case 'http':
					case 'rtmp':
						$script .= PHP_EOL . 'provider: "' . $provider . '",';
						$script .= ($streamer) ? PHP_EOL . 'streamer: "' . $streamer . '",' : ''; // only valid for RTMP or HTTP
						break;
					case 'youtube':
						$script .= PHP_EOL . 'provider: "' . $provider . '",';
						break;
					default:
						$this->_log_item("WARNING in MC Player plugin: Specified 'provider' for player is not valid (http|rtmp|youtube); ignoring parameter");
						unset($provider, $streamer);
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
		// global $TMPL, $SESS; // EE1
		
		$this->EE->session->cache['mc']['player']['playlist_position'] = $this->EE->TMPL->fetch_param('position','');
		$this->EE->session->cache['mc']['player']['playlist_size'] = $this->EE->TMPL->fetch_param('size','');

		if (isset($this->EE->session->cache['mc']['player']['items']) !== FALSE)
		{
			$this->EE->session->cache['mc']['player']['playlist'] = "playlist: [" . $this->indent(trim($this->EE->session->cache['mc']['player']['items'], ",")) . PHP_EOL . "]";
			unset($this->EE->session->cache['mc']['player']['items']);
		}
		else
		{
			$this->_log_item("ERROR in MC Player plugin: 'playlist' container specified, but no 'item' items were found");
			return $this->EE->TMPL->no_results();
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
		// global $TMPL, $SESS; // EE1

		// Assignment
		$file = $this->EE->TMPL->fetch_param('file');
		$image = $this->EE->TMPL->fetch_param('image');
		$duration = $this->EE->TMPL->fetch_param('duration');
		$start = $this->EE->TMPL->fetch_param('start');
		$title = $this->EE->TMPL->fetch_param('title');
		$description = $this->EE->TMPL->fetch_param('description');
		$streamer = $this->EE->TMPL->fetch_param('streamer');
		$provider = $this->EE->TMPL->fetch_param('provider');
		if (isset($this->EE->session->cache['mc']['player']['levels']))
		{
			$levels = $this->EE->session->cache['mc']['player']['levels'];
		}
		else
		{
			$levels = '';
		}
		unset($this->EE->session->cache['mc']['player']['levels']);
		

	// Validation & Formatting
		
		// 'file' or 'levels' required
		if ($levels != '')
		{
			$item = PHP_EOL . trim($levels, ",\t" . PHP_EOL) . ",";
			if ($file) $this->_log_item("NOTICE in MC Player plugin: 'file' specified for playlist item when 'levels' already specified; ignoring 'file'");
		}
		elseif ($file != '')
		{
			if (!isset($this->EE->session->cache['mc']['player']['id']))
			{
				$this->EE->session->cache['mc']['player']['id'] = '';
			}
			$this->EE->session->cache['mc']['player']['id'] .= "_" . basename(html_entity_decode($file)); // used for auto-naming container ID
			$item = PHP_EOL . 'file: "'.$file.'",';
		}
		else
		{
			$this->_log_item("WARNING in MC Player plugin: No 'file' specified for playlist item; skipping");
			return $this->EE->TMPL->no_results();
		}
		
		// image
		$item .= ($image) ? PHP_EOL . 'image: "' . $image . '",' : '';
		
		// 'duration' and 'start' should be integers
		if (ctype_digit($duration)) {
			$item .= PHP_EOL . 'duration: '.$duration.',';
		} elseif ($duration != '') {
			$this->_log_item("WARNING in MC Player plugin: Specified 'duration' for item is not an integer; ignoring parameter");
			unset($duration);
		}
		
		if (ctype_digit($start)) {
			$item .= PHP_EOL . 'start: '.$start.',';
		} elseif ($start != '') {
			$this->_log_item("WARNING in MC Player plugin: Specified 'start' for item is not an integer; ignoring parameter");
			unset($start);
		}

		$item .= ($title) ? PHP_EOL . 'title: "' . $title . '",' : '';
		$item .= ($description) ? PHP_EOL . 'description: "' . $description . '",' : '';

		switch ($provider)
		{
			case 'http':
			case 'rtmp':
				$item .= PHP_EOL . 'provider: "' . $provider . '",';
				$item .= ($streamer) ? PHP_EOL . "\t\t" . 'streamer: "' . $streamer . '",' : ''; // only valid for RTMP or HTTP
				break;
			case 'youtube':
				$item .= PHP_EOL . 'provider: "' . $provider . '",';
				break;
			case '':
				break;
			default:
				$this->_log_item("WARNING in MC Player plugin: Specified 'provider' for item is not valid (http|rtmp|youtube); ignoring parameter");
				unset($provider);
				break;
		}
		
		
		
		$item = PHP_EOL . "{" . $this->indent(trim($item, ", ")) . PHP_EOL . "},";
		
		// store result in session for use upstream
		if (!isset($this->EE->session->cache['mc']['player']['items']))
		{
			$this->EE->session->cache['mc']['player']['items'] = '';
		}
		$this->EE->session->cache['mc']['player']['items'] .= $item;
		
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
		// global $TMPL, $SESS; // EE1

		if (isset($this->EE->session->cache['mc']['player']['level_list']) !== FALSE)
		{
			$this->EE->session->cache['mc']['player']['levels'] = PHP_EOL . "levels: [" . trim($this->EE->session->cache['mc']['player']['level_list'], ",") . PHP_EOL . "]";
			unset($this->EE->session->cache['mc']['player']['level_list']);
		}
		else
		{
			$this->_log_item("ERROR in MC Player plugin: 'levels' container specified, but no 'level' items were found");
			return $this->EE->TMPL->no_results();
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
		$bitrate = $this->EE->TMPL->fetch_param('bitrate');
		$width = $this->EE->TMPL->fetch_param('width');
		$file = $this->EE->TMPL->fetch_param('file');

		// Validation & Formatting
		if (ctype_digit($bitrate))
		{
			$bitrate = 'bitrate: '.$bitrate.', ';
		}
		elseif ($bitrate)
		{
			$this->_log_item("WARNING in MC Player plugin: Specified 'bitrate' for level is not an integer; ignoring parameter");
			unset($bitrate);
		}
		
		if (ctype_digit($width))
		{
			$width = 'width: '.$width.', ';
		}
		elseif ($width)
		{
			$this->_log_item("WARNING in MC Player plugin: Specified 'width' for level is not an integer; ignoring parameter");
			unset($width);
		}
		
		if ($file)
		{
			$this->EE->session->cache['mc']['player']['id'] .= "_" . basename(html_entity_decode($file)); // used for auto-naming container ID
			$file = 'file: "'.$file.'"';
		}
		else
		{
			$this->_log_item("ERROR in MC Player plugin: No 'file' specified for level; unable to process level");
			return $this->EE->TMPL->no_results();
		}

		$level = $this->indent(PHP_EOL . "{ " . $bitrate . $width . $file . " },");

		// store result in session for use upstream
		$this->EE->session->cache['mc']['player']['level_list'] .= $level;

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
		// global $TMPL, $SESS; // EE1

		if (isset($this->EE->session->cache['mc']['player']['plugin_list']) !== FALSE)
		{
			$this->EE->session->cache['mc']['player']['plugins'] = PHP_EOL . "plugins: {" . trim($this->EE->session->cache['mc']['player']['plugin_list'], ",") . PHP_EOL . "}";
			unset($this->EE->session->cache['mc']['player']['plugin_list']);
		}
		else
		{
			$this->_log_item("ERROR in MC Player plugin: 'plugins' container specified, but no 'plugin' items were found");
			return $this->EE->TMPL->no_results();
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
		// global $TMPL, $SESS; // EE1

		if ($plugin_name = $this->EE->TMPL->fetch_param('plugin_name'))
		{
			// Process each param
			$params = '';
			foreach ($this->EE->TMPL->tagparams as $pname => $pvalue)
			{
				if ($pname != "plugin_name") $params .= $pname . ': "' . $pvalue . '", ';
			}
			
			// Format params into full plugin line
			$plugin = $this->indent(PHP_EOL . $plugin_name . ": { " . trim($params, ', ') . " },");

			// store result in session for use upstream
			$this->EE->session->cache['mc']['player']['plugin_list'] .= $plugin;
		}
		else
		{
			$this->_log_item("ERROR in MC Player plugin: No 'plugin_name' specified for plugin; unable to continue");
			return $this->EE->TMPL->no_results();
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
		// global $TMPL, $SESS; // EE1

		if (isset($this->EE->session->cache['mc']['player']['mode_list']) !== FALSE)
		{
			$this->EE->session->cache['mc']['player']['modes'] = PHP_EOL . "modes: [" . trim($this->EE->session->cache['mc']['player']['mode_list'], ",") . PHP_EOL . "]";
			unset($this->EE->session->cache['mc']['player']['mode_list']);
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

		$mode = '';
		$mode_params = '';
		
		switch ($type = $this->EE->TMPL->fetch_param('type'))
		{
			case 'flash':
				if ($src = $this->EE->TMPL->fetch_param('src'))
				{
					$mode_params .= PHP_EOL . 'type: "' . $type . '", ';
					$mode_params .= PHP_EOL . 'src: "' . $src . '", ';
				}
				else
				{
					$this->_log_item("ERROR in MC Player plugin: No 'src' specified for 'flash' mode; skipping current mode");
				}
				break;
			
			case 'html5':
			case 'download':
					$mode_params .= PHP_EOL . 'type: "' . $type . '", ';
				break;
			
			default:
				$this->_log_item("ERROR in MC Player plugin: No 'type' specified for mode; skipping current mode");
				break;
		}
		
		// Include alternate config if specified
		if (isset($this->EE->session->cache['mc']['player']['mode']['config']) !== FALSE)
		{
			$mode_params = PHP_EOL . "config: {" . trim($this->EE->session->cache['mc']['player']['mode']['config'], ",") . PHP_EOL . "}";
			unset($this->EE->session->cache['mc']['player']['mode']['config']);
		}
		
		// Format params into full plugin line
		$mode = $this->indent(PHP_EOL . "{ " . $this->indent(trim($mode_params, ', ')) . PHP_EOL . "},");
		// store result in session for use upstream
		$this->EE->session->cache['mc']['player']['mode_list'] .= $mode;

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
		// global $TMPL, $SESS; // EE1

			// Process each param
			$params = '';
			foreach ($this->EE->TMPL->tagparams as $name => $value)
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
			$this->EE->session->cache['mc']['player']['mode']['config'] = $config;

	} // END function config()


	/**
	 * Usage
	 *
	 * This function describes how the plugin is used.
	 *
	 * @access	public
	 * @return	string
	 */
	public static function usage()
	{
		ob_start();
?>

== Requirements =====================================================

- JW Media Player 5.7 or higher

== Initial Setup ====================================================

Upload the JW Media Player files to a directory on your server. Then
add the following line in the <head>...</head> section of your site,
making sure to modify the path to the script:
<script type='text/javascript' src='/path/to/jwplayer.js'></script>

== Examples =========================================================

-- Single video file: -----------------------------------------------
{exp:mc_player:play file="single.mp4" width="320" height="240" playerpath="/path/to/player.swf"}

-- Single audio file ------------------------------------------------
{exp:mc_player:play file="song.mp3" width="320" height="0" playerpath="/path/to/player.swf" container_tag="audio" controlbar="bottom"}


-- XML Playlist -----------------------------------------------------
{exp:mc_player:play width="320" height="240" playerpath="/path/to/player.swf"}
	{exp:mc_player:playlist type="xml" file="videos.xml" position="right" size="360"}
{/exp:mc_player:play}


-- Javascript Playlist ----------------------------------------------
{exp:mc_player:play width="320" height="240" playerpath="/path/to/player.swf"}
	{exp:mc_player:playlist type="javascript" position="right" size="360"}

		{exp:mc_player:item file="entry_1.mp4" image="entry_1.jpg" duration="54"}
		{exp:mc_player:item file="entry_2.mp4"}
		{exp:mc_player:item file="entry_3.mp4"}

	{/exp:mc_player:playlist}
{/exp:mc_player:play}


-- File with levels -------------------------------------------------
{exp:mc_player:play height="240" playerpath="/path/to/player.swf" file="levels" image="vid.jpg" duration="54" start="" title="" description=""}
	{exp:mc_player:levels}
	
			{exp:mc_player:level bitrate="300" file="vid_320.mp4" width="320"}
			{exp:mc_player:level bitrate="600" file="vid_480.mp4" width="480"}
			{exp:mc_player:level bitrate="900" file="vid_720.mp4" width="720"}

	{/exp:mc_player:levels}
{/exp:mc_player:play}

-- JS playlist with files & levels ----------------------------------
Note: If any tag is used as a pair instead of a single tag, all tags of that type in the template have to be pairs.

{exp:mc_player:play width="320" height="240" playerpath="/path/to/player.swf"}
	{exp:mc_player:playlist type="javascript" position="right" size="360"}

		{exp:mc_player:item file="entry_1.mp4" image="entry_1.jpg" duration="54"}{/exp:mc_player:item}
		{exp:mc_player:item file="entry_2.mp4" image="entry_2.jpg" duration="42"}{/exp:mc_player:item}
		{exp:mc_player:item}
			{exp:mc_player:levels}
				{exp:mc_player:level bitrate="300" file="entry_3_320.mp4" width="320"}
				{exp:mc_player:level bitrate="600" file="entry_3_480.mp4" width="480"}
				{exp:mc_player:level bitrate="900" file="entry_3_720.mp4" width="720"}
			{/exp:mc_player:levels}
		{/exp:mc_player:item}
		{exp:mc_player:item file="entry_4.mp4" image="entry_4.jpg" duration="564"}{/exp:mc_player:item}

	{/exp:mc_player:playlist}
{/exp:mc_player:play}




<?php
		$buffer = ob_get_contents();
		ob_end_clean();
		return $buffer;
	} // END function usage()
}


/* End of file pi.mc_player.php */
/* Location: ./system/expressionengine/third_party/mc_player/pi.mc_player.php */