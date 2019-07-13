# Board converter for Discuz! UCenter:
This folder contains converter for merging the well-known Chinese forum software Discuz!'s dedicated users database, UCenter, into MyBB.

# Supported forum software versions:
MyBB >= **1.8.20**\
UCenter **1.6.0** Release **20110501** upto **20170101**

# About converting users and their profiles:
Basic user data in Discuz! X2.5 reside in two places, the Discuz! forum tables itself and the UCenter tables, since an instance of UCenter is essentially installed or an existing one is used when installing Discuz! X2.5.

If you have two or more Discuz! installed linking to the same UCenter instance, and you wish to convert & merge all these Discuz! into a MyBB installation, you should probably convert the users stored in UCenter first **by using this converter**, and then merge any existing user in those Discuz! forums **by using other dedicated Discuz! converters**. Yes, there could be no such users in a Discuz! but surely they're users of yours.

**Important note about `email`:** in UCenter 1.6.0 (20170101) that comes with the latest version of Discuz! X2.5 (20170101), the `email` field is stored in a `CHAR/32` field in table `members`, which is far less than the length of the same purpose field using a `CHAR/40` one in table `common_member` in Discuz!. The converter tries its best to tell if an email is cut off by UCenter when duplicated usernames are found.

# Supported modules:
This converter's main module is the `users` module. It also privodes other modules:
1. `users`, Users
1. `privatemessages`, Private messages
1. `avatars`, User avatars
1. `buddies`, User buddies. The friend links in UCenter seems old ones imported from very old Discuz! versions.

# Not supported modules:
This converter doesn't support any post-related modules, since the UCenter only stores user data.