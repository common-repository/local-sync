=== WP Duplicate - WordPress Migration Plugin ===
Contributors: localsync, dark-prince
Donate link: https://revmakx.com
Tags: clone, migrate, wp duplicate, wpduplicate, copy site, local sync, local site, dev site, duplicate site, duplicator, cloning, migration, simple cloning, easiest cloning, free cloning
Requires at least: 3.0.1
Tested up to: 6.3.1
Stable tag: 1.1.6
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Easily migrate or clone your WordPress Site from one host to another.

== Description ==

WP Duplicate (Formerly LocalSync) provides you with the easiest and the fastest way to clone or migrate a site from one server to another with the click of a button.<br>
The migration process is highly reliable and is proven to migrate bigger sites without any errors.<br>

== Why WP Duplicate? == 

<h2><strong>Simplest Cloning Tool</strong></h2>Just install the WP Duplicate plugin on the destination site and the live site and start syncing, no need to enter FTP details.<br><br>
<h2><strong>Incremental Cloning</strong></h2>Only the changed files are cloned from the source site to the destination site. So the cloning process is faster.<br><br>
<h2><strong>Load Images From Live Site</strong></h2>With WP Duplicate you can directly load the images of Live site, so that you do not need to copy media files which will save a lot of time during the cloning process.<br><br>
<h2><strong>Clone Live Site To Local Computer</strong></h2>With WP Duplicate you can clone any production site to your Local Computer (MAMP, LAMP, XAMP, etc..).

== Installation ==

1. Spin off a WP site on the destination server.
2. Install and activate the "WP Duplicate Plugin" on the WP site created as mentioned above.
3. Go to "WP admin dashboard -> WP Duplicate" and select "This is duplicate site" button.
4. Install and activate "WP Duplicate Plugin" on the live WP site.
5. Go to "WP admin dashboard -> WP Duplicate" and select "This is prod site" button.
6. Login with your WP Duplicate account (created on https://localsync.io) on the live WP site.
7. Copy the prod key and go to the "WP admin dashboard -> WP Duplicate" on the local WP site.
8. Paste the prod key, and Add Site.
9. Now you are ready to copy the live site to the duplicate site.

== Screenshots ==

1. Duplicate Site settings page.
2. Live Site settings page.

== Frequently Asked Questions ==

= Can I clone the site to the local Computer? =

Yes, you can clone the live site to the WP site on your local desktop server.

= Where is the documentation for your plugin? =

Visit <a href="https://docs.localsync.io">https://docs.localsync.io</a> for the documentation.

== Changelog ==
= 1.1.6 =
*Release Date - 21 Sep 2023*

* Fix : The migration process failed in a few cases.

= 1.1.5 =
*Release Date - 01 Sep 2023*

* Fix : PHP v8.2.0 fixes.

* Improvement : Tested upto WP 6.3.1.
* Improvement : WPMerge DB tables are excluded by default.

= 1.1.4 =
*Release Date - 23 Jun 2023*

* Fix : Relative URLs did not work in a few cases.

= 1.1.3 =
*Release Date - 20 Jun 2023*

* Fix : The migration process failed in a few cases.
* Fix : Relative URLs did not work on the duplicate site.

* Improvement : Tested upto WordPress 6.2.2.

= 1.1.2 =
*Release Date - 11 Oct 2022*

* Fix : Local site elementor images collapsed in a few cases when loaded from the live site.

= 1.1.1 =
*Release Date - 22 Jul 2022*

* Fix : Pushing and pulling failed in a few sites having MariaDB Database.

= 1.1.0 =
*Release Date - 29 Jun 2022*

* Improvement : The migration process is revamped to increase reliability and prevent errors in bigger sites.
* Improvement : Renamed the plugin to WP Duplicate.

= 1.0.5 =
*Release Date - 22 Apr 2021*

* Fix : Not able to install WP Duplicate plugin on WPTimeCapsule's staging site.
* Fix : Not able to include, exclude Files/Folders on WPTimeCapsule plugin settings page when WP Duplicate plugin is active.

* Improvement : LOCAL_SYNC_DOWNLOAD_CHUNK_SIZE constant is introduced.
* Improvement : LOCAL_SYNC_UPLOAD_CHUNK_SIZE constant is introduced.

= 1.0.4 =
*Release Date - 12 Apr 2021*

* Fix : False positive virus warning by Windows Defender.
* Fix : Not able to get file data for downloading the file in a few cases.

* Improvement : Tested upto WordPress 5.7.

= 1.0.3 =
*Release Date - 28 Feb 2020*

* Improvement : Calculating modified files logic improved.

= 1.0.2 =
*Release Date - 21 Feb 2020*

* Fix : Pages built with elementor collapsed after cloning, in some cases.
* Fix : Replacing site URL failed, in some cases.
* Fix : Syncing on Windows OS failed.

* Improvement : Excluding few unnecessary DB tables by default.

= 1.0.1 =
*Release Date - 17 Feb 2020*

* Improvement : Major improvements.

= 1.0 =
* First Version

== Upgrade Notice ==

= 1.0 =
First Version
