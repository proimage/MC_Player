Single file
===========
	{exp:mc_player:play file="single.mp4" width="320" height="240"}


XML Playlist
============
	{exp:mc_player:play width="320" height="240"}
		{exp:mc_player:playlist type="xml" file="videos.xml" position="right" size="360"}
	{/exp:mc_player}


Javascript Playlist
===================
	{exp:mc_player:play width="320" height="240"}
		{exp:mc_player:playlist type="javascript" position="right" size="360"}

			{exp:mc_player:item file="entry_1.mp4" image="entry_1.jpg" duration="54"}
			{exp:mc_player:item file="entry_2.mp4"}
			{exp:mc_player:item file="entry_3.mp4"}

		{/exp:mc_player:playlist}
	{/exp:mc_player}


File with levels
================
	{exp:mc_player:play height="240" file="levels" image="vid.jpg" duration="54" start="" title="" description=""}
		{exp:mc_player:levels}
		
				{exp:mc_player:level bitrate="300" file="vid_320.mp4" width="320"}
				{exp:mc_player:level bitrate="600" file="vid_480.mp4" width="480"}
				{exp:mc_player:level bitrate="900" file="vid_720.mp4" width="720"}

		{/exp:mc_player:levels}
	{/exp:mc_player}

JS playlist with files & levels
===============================

If any tag is used as a pair instead of a single tag, all tags of that type have to be pairs.

	{exp:mc_player:play width="320" height="240"}
		{exp:mc_player:playlist type="javascript" position="right" size="360"}

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
	{/exp:mc_player}


Heirarchy
---------
	player
		playlist
			item
		levels
			level