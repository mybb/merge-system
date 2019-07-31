# Board converter for Discuz! UCenter:
This folder contains a converter for merging the well-known Chinese forum software Discuz!'s dedicated users database, UCenter, into MyBB.

# Converter requirement:
PHP version >= 5.4\
MySQL Server version >= 5.1 (Recommended version: 5.6+)\

# Supported forum software versions:
MyBB >= **1.8.20**\
Discuz! UCenter **1.6.0** Release **20110501** upto **20170101**

# About converting users and their profiles:
User data in Discuz! X2.5 (and newer) reside in two places, the Discuz! forum tables itself and the UCenter tables. Since Discuz! X2.5, an instance of Discuz! UCenter is required or installed when installing a Discuz! X forum.

If you have two or more Discuz! installed linking to the same UCenter instance, and you wish to convert & merge all these Discuz! into a MyBB installation, you should probably convert the users stored in UCenter first **by using this converter**, and then merge any existing user in those Discuz! forums **by using other dedicated Discuz! converters**. Yes, there could be no such users in a Discuz! but surely they're users of yours.

**Important note about `users` module:** as the core user data of a Discuz! reside in Discuz! UCenter database, you should first import users using this converter and then run other Discuz! converters. If you import users through Discuz! converter, login information of users like password and salt may be incorrect.

**Important note about `email` field:** in UCenter 1.6.0 that comes with Discuz! X2.5 (and newer), the `email` field is stored in a `CHAR/32` field in table `members`, which is shorter than the length of the same purpose field using a `CHAR/40` one in table `common_member` in Discuz! X2.5 (and newer). The converter tries its best to find the full email, but may fail or mess up. In the converter's board file `./merge/boards/dzucenter.php`, a constant define can turn this feature off.

# Converter settings:
There are a few switches that control the behavior of this converter in various way. Please refer to the converter's board file `./merge/boards/dzucenter.php` for constant defines of those switches.

# Supported modules:
This converter's main module is the `users` module. It also privodes other modules:
1. `privatemessages`, Private messages
1. `avatars`, User avatars

# Not supported modules:
1. `buddies`, User buddies, if your UCenter database contains very old user friendship data. But this module is not implemented by now.
1. This converter doesn't support any post-related module, since the UCenter only stores user data.