# Board converter for Discuz! UCenter:
This folder contains converter for merging the well-known Chinese forum software Discuz!'s dedicated users database, UCenter, into MyBB.

# Converter requirement:
PHP version >= 5.4 (I have tested it with PHP 5.5)\
MySQL Server version >= 5.1 (I have tested it with MySQL Server 5.1.41)\

# Supported forum software versions:
MyBB >= **1.8.20**\
Discuz! **X2.5** Release **20121101** upto **20171001**\
UCenter **1.6.0** Release **20110501** upto **20170101**

# About converting users and their profiles:
Basic user data in Discuz! X2.5 reside in two places, the Discuz! forum tables itself and the UCenter tables, since an instance of UCenter is essentially installed or an existing one is used when installing Discuz! X2.5.

If you have two or more Discuz! installed linking to the same UCenter instance, and you wish to convert & merge all these Discuz! into a MyBB installation, you should probably convert the users stored in UCenter first **by using this converter**, and then merge any existing user in those Discuz! forums **by using other dedicated Discuz! converters**. Yes, there could be no such users in a Discuz! but surely they're users of yours.

**Important note about `users` module:** as the core user data of a Discuz! reside in Discuz! UCenter database, you should first import users using this converter and then run other Discuz! converter. If you import users through Discuz! converter, login information of users may be incorrect.

**Important note about `email` field:** in UCenter 1.6.0 that comes with Discuz! X2.5, the `email` field is stored in a `CHAR/32` field in table `members`, which is shorter than the length of the same purpose field using a `CHAR/40` one in table `common_member` in Discuz! X2.5. The converter tries its best to find the full email, but may fail. In the converter's board file `./merge/boards/dzx25.php`, a constant define can turn this feature off.

# Supported modules:
This converter's main module is the `users` module. It also privodes other modules:
1. `privatemessages`, Private messages
1. `avatars`, User avatars

# Not supported modules:
1. `buddies`, User buddies, if your UCenter database contains very old user friendship data. But it's not supported now.
1. This converter doesn't support any post-related modules, since the UCenter only stores user data.