# Board converter for Discuz! X2.5:
This folder dedicates to converting and merging the well-known Chinese forum software **Discuz! X2.5** database into an existing installation of MyBB.

This dedicated converter has not been much tested, problems could break the merging process. Submit a issue if you encounter any unsucessful job with this converter.

# Converter requirement:
PHP version >= 5.4 (I have tested it with PHP 5.5)\
MySQL Server version >= 5.1 (I have tested it with MySQL Server 5.1.41)\

# Supported forum software versions:
MyBB >= **1.8.20**\
Discuz! **X2.5** Release **20121101** upto **20171001**\
UCenter **1.6.0** Release **20110501** upto **20170101**

# About converting users and their profiles:
User data in Discuz! X2.5 reside in two places, the Discuz! forum tables itself and the UCenter tables, since an instance of UCenter is essentially installed or an existing one is used when installing Discuz! X2.5.

If you have two or more Discuz! installed linking to the same UCenter instance, and you wish to convert & merge all these Discuz! into a MyBB installation, you should probably convert the users stored in UCenter first, and then merge any existing user from those Discuz! forums into MyBB.

**Important note about `users` module:** as the core user data of a Discuz! reside in Discuz! UCenter database, you should first import users using **Discuz! UCenter** converter and then run this converter. If you import users through this converter, login information of users may be incorrect.

**Important note about `email` field:** in UCenter 1.6.0 that comes with Discuz! X2.5, the `email` field is stored in a `CHAR/32` field in table `members`, which is shorter than the length of the same purpose field using a `CHAR/40` one in table `common_member` in Discuz! X2.5. The converter tries its best to find the full email, but may fail. In the converter's board file `./merge/boards/dzx25.php`, a constant define can turn this feature off.

# Supported modules:
Almost all modules that are supported by the original [MyBB Merge System](https://github.com/mybb/merge-system) can be used at this time, including:
1. `settings`, Board Settings (supports only a limited number of settings)
1. `usergroups`, Usergroups
1. `users`, Users
1. `forums`, Forums
1. `forumperms`, Forum Permissions
1. `threads`, Threads
1. `polls`, Polls
1. `pollvotes`, Poll Votes
1. `posts`, Posts
1. `moderators`, Moderators
1. `attachments`, Attachments

# Exteded supported modules:
Some useful and kinda easy-to-import data, which are not supported by the original MyBB Merge System, can be converted as the board converter provides some customed module dependencies.
1. `threadprefixes`, Thread Prefixes
1. `announcements`, Announcements
1. `profilefields`, Extended User Profile Fields (at least those supported by MyBB naively)
1. `userfields`, Extended User Profile Infos

# Not supported modules:
1. `privatemessages`, Private messages, they're handled in the UCenter converter.
1. `avatars`, User avatars, they're handled in the UCenter converter.

# Modules may be supported:
1. `buddies`, User friendships.
