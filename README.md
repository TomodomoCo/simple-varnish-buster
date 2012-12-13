Simple Varnish Buster
=====================

## WordPress plugin for simple, lightweight Varnish cache busting

Simple Varnish Buster is a WordPress plugin that ensures that when you update some content on your WordPress site, it 'busts' through the layer of Varnish caching so that visitors will immediately see the new content as soon as you've clicked 'Publish'.

## Foolish Assumptions

 * Your Varnish server is running on 127.0.0.1. If it is not, you will need to change this in the plugin's Settings page.
 * You would like to invalidate caches for the homepage, feeds and the page that has just been changed in WordPress.
 * You already have a speedy but robust set of Varnish rules for your site.
 * Varnish has an ACL configured which will allow your web server (perhaps running over the loopback interface with 127.0.0.1) to `PURGE` the cache for a given URL.

The plugin is available under the [GNU General Public License version 2](https://www.gnu.org/licenses/gpl-2.0.html), or (at your option), any later version.
