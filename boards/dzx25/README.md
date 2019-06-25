# Board converter for Discuz! X2.5:
This folder dedicates to converting and merging the well-known Chinese forum software Discuz! **X2.5** database to an existing installation of MyBB.

# Supported forum software versions:
MyBB >= **1.8.20**\
Discuz! **X2.5** Release **20121101** upto **20171001**\
UCenter **1.6.0** Release **20110501** upto **20170101**

# About converting users and their profiles:
Basic user data in Discuz! X2.5 reside in two places, the Discuz! forum tables itself and the UCenter tables, since an instance of UCenter is essentially installed or an existing one is used when installing Discuz! X2.5.

If you have two or more Discuz! installed linking to the same UCenter instance, and you wish to convert & merge all these Discuz! into a MyBB installation, you should probably convert the users stored in UCenter first, and then merge any existing user in those Discuz! forums. Yes, there could be no such users in a Discuz! but surely they're users of yours.

The **correct** steps of converting users should be:
1. Regardless where you are, reconfigure database connection info for UCenter. It's OK to change database configuration now. Remember to check prefix of UCenter tables: in default Discuz! X2.5 installation, the table prefix of UCenter is `dz_ucenter_`.
1. Run `import_ucusers` module to migrate user data stored in UCenter.
1. Revert database connection info back to Discuz!.
1. Continue with other user modules to update user infomation.

# Supported modules:
Almost all modules that are supported by the original [MyBB Merge System](https://github.com/mybb/merge-system) can be used at this time, including:
1. settings, Board Settings (supports only a limited number of fields)
1. usergroups, Usergroups
1. users, Users
1. forums, Forums
1. forumperms, Forum Permissions
1. threads, Threads
1. polls, Polls
1. pollvotes, Poll Votes
1. posts, Posts
1. privatemessages, Private Messages
1. moderators, Moderators
1. avatars, User Avatars
1. attachments, Attachments

# Exteded supported modules:
Some useful and kinda easy-to-import data, which are not supported by the original MyBB Merge System, can be converted as the board converter provides some customed module dependencies.
1. import_threadprefixes, Thread Prefixes
1. import_announcements, Announcements
1. import_profilefields, Extended User Profile Fields (at least those supported by MyBB naively)
1. import_userfields, Extended User Profile Infos
1. import_buddies, Buddies
1. import_ucusers, Users in UCenter
1. import_userpermission, User permissions

# Not supported modules:
1. categories
Discuz! doesn't use categories to hold any 'category forum/board'
1. events
Events in MyBB may be correspoding to the activities in Discuz!, but I don't have that need to convert such data, so I leave it.