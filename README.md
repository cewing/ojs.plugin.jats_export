# OJS JATS Export Plugin #

This plugin is a modification of the **native** export plugin shipped with the
Open Journal System.

The plugin is not suitable for direct export of articles, and should only be
used in conjunction with the
[RCR Export Control script](https://github.com/cewing/rcr_export_control).

The intention of the plugin is to provide properly formatted JATS XML metadata
for a given article, along with the unprocessed body HTML of the article as
well as file and image references. The export control plugin then reads the
exported XML and replaces the unformatted HTML with equivalent JATS formatted
xml.

