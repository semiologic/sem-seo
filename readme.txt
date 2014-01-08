=== Semiologic SEO ===
Contributors: Denis-de-Bernardy, Mike_Koepke
Donate link: http://www.semiologic.com/partners/
Tags: semiologic
Requires at least: 3.0
Tested up to: 3.8
Stable tag: trunk

An SEO plugin for WordPress.


== Description ==

The Semiologic SEO plugin for WordPress will add a couple of SEO gadgets and functionality to any WP site. It was designed with the Semiologic theme in mind, but it can be used with most other themes as well.

The approach in this plugin is consistent with that of other SEO plugins from this site, namely Silo Widgets, Nav Menu widgets, and XML Sitemaps. In other words, it is based on the idea that a small web of static pages that are regularly mentioned in posts will work better than a posts only site.

If you're expecting a plugin with screens full of options, you *will* be disappointed by this plugin. It offers few options because I feel that, as a rule of thumb, it's better to enforce the proper options than to give doubts to end-users.

That being said, I dare suggest that you'll fare better with this plugin and the other SEO plugins on this site than with the All-In-One SEO plugin and its siblings.

This SEO plugin in particular covers the following:

- Web page title (for the Semiologic theme anyway)
- Meta keyword/description tags
- Canonical URL tags
- Various duplicate content worries
- Google+ Authorship
- A few other points, when combined with the Semiologic theme, such as putting the page's content forward

The plugin also features an SEO crash course under Settings / SEO once active, and you'll find additional SEO resources in the semiologic.com's members' area as well as its resources section, so I won't be expanding on the specifics or the details here.

One FAQ note however: meta keywords, which are useless, are dealt with automatically (by concatenating titles, categories and tags), but the field is left around in the event anyone wants to override it. Meta description should be filled in manually if you want one, since there is no point or benefit in generating it automatically.

= Help Me! =

The [Semiologic forum](http://forum.semiologic.com) is the best place to report issues.


== Installation ==

1. Upload the plugin folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress


== Change Log ==

= 2.4.1 =

- WP 3.8 compat

= 2.4 =

- WP 3.6 compat
- PHP 5.4 compat

= 2.3.1 =

- Sitemap entries for archive pages (category, tags, author, date) had too many additional pages if a non-excerpt
listing was set

= 2.3 =

- Added prev and next adjacent canonical links for multi-page and series of pages, such as blog pages
- Fixed duplicate title for multi-page blog post
- Fixed setting of canonical link for blog page

= 2.2 =

- Fix meta description tag not being set for home page
- Clarified SEO->Settings field usage as being for the home page.

= 2.1.1 =

- Remove author link code with support now in Semiologic-Reloaded theme

= 2.1 =

- Google+ authorship inc. new Profile contact fields
- Fixed duplicate titles on archive type page 2+
- Additional rel=canonical entries for home and archive type pages
- Add link to author page for pages/posts
- Fix: Sem-cache now flushed on SEO options change
- Fix: Silence PHP warning for ob_start call
 
= 2.0.4 =

- WP 3.0.1 compat

= 2.0.3 =

- Fix description handler on non-existent terms

= 2.0.2 =

- Further canonical redirect improvements

= 2.0.1 =

- Apply filters to permalinks
- Rel=canonical improvements
- Canonical redirect improvements

= 2.0 =

- Complete Rewrite
- Localization
- Code enhancements and optimizations
- Add an SEO crash-course