Script for a bot written in php from scratch to crawl a web based text game @ torn.com, using curl library and local browser session data for authorization.  
The purpose was to gain advantage in the game by processing site data and sending event notifications to IRC channel in real time, tracking and coordination.  
The script is running in a loop with non blocking http requests system, similar to javascript Promises (bot.php line 203). That allowed me to queue any number of non blocking http requests for data, that were created automatically at intervals by the system, and on demand by user commands, then process available results as soon as ready.  
Then there's the system for user interaction using commands, that was developed from scratch using php socket library, to be compatible with IRC protocol.