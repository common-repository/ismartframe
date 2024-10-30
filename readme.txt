=== iSmartFrame ===
Contributors: ismartframe
Tags: cache, caching, performance, ismartframe
Requires at least: 5.6
Tested up to: 6.6
Requires PHP: 7.2
Stable tag: 1.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Official WordPress plugin to manage iSmartFrame cache.

== Description ==

The **iSmartFrame** plugin enhances your WordPress website’s performance by automatically purging specific page caches whenever pages are created, updated, or deleted. This ensures your content is always fresh and your site loads faster.

**Boosted Site Performance**
Reduces loading times by purging only the updated page cache, keeping the rest of your site fast.

**Real-Time Updates**
Ensures logged-in users and comment authors always see the latest content, enhancing user experience.

**SEO Optimization**
Faster load times improve search engine rankings and visibility.

**Customizable Cache Control**
Allows you to purge specific pages, tags, or URLs, offering flexible cache management.

== External Service Usage ==

This plugin relies on a third-party service to perform certain actions, such as clearing the cache when necessary.

- **Service URL:** https://app.ismartframe.com/api/v1/cache/purge
- **Purpose:** The service is used to trigger cache purges on iSmartFrame's servers when specific actions occur on your WordPress site.
- **Data Sent:** The following data is sent to the service:
  - Site URL
  - Cache-related data (e.g., cache status and instructions for clearing)

For more information, please refer to:

- **Privacy Policy:** https://www.ismartframe.com/en/privacy-policy/

== Installation ==

1. Install the plugin through the WordPress plugins screen directly and activate it.
2. Configure the plugin by going to [Admin menu > iSmartFrame].
3. Fill the API key field and click 'Check API Key and Save' to start using the plugin.

== Features ==

- **Automatic Cache Purge**
  Instantly clears cache when new content is published, or updates are made, ensuring only the updated page is affected.

- **Targeted Cache Management**
  Purges only the specific page cache or tag without clearing the entire website cache, keeping the rest of your site fast.

- **Full Compatibility**
  Seamlessly integrates with all Wordpress versions 6.6+, optimizing performance for any website.

- **Complete Website Cache Clearing**
  Allows a global cleanup of the site. iSmartFrame retrieves the information directly from the origin server.

== Screenshots ==

1. The iSmartFrame plugin settings page.
2. The iSmartFrame plugin settings page.
3. Example of cache purging in action for a post, including a side panel with confirmation message.

== Changelog ==

= 1.2 =
* Bug fix.

= 1.0 =
* Initial release.
