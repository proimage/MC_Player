Changelog
=========
- 0.2.4 (2012-03-13):
	- Made controlbar/playlist positioning smarter by default
	- Bug fixes
- 0.2.3 (2012-02-05):
	- Fixed a bunch of "undeclared variable" PHP notifications again
	- Changed how external playlists work (now uses an extra {exp:mc_player:playlist file="playlist.xml"} tag inside the existing {exp:mc_player:play} tags)
	- Changed default video size from 300x150 to 480x270
	- Renamed most internal variables for consistency
- 0.2.2 (2011-11-23):
	- Changed plugin file encoding from "UTF-8" to "UTF-8 without BOM" (suspected as causing "Headers already sent" PHP errors)
	- Forgot to increment the internal version number in the previous update. :p
- 0.2.1 (2011-11-22):
	- Minor change to prevent some PHP Notice messages when pl_size & pl_position parameters are not provided
	- Began this changelog


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