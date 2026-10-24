=== Galerio Media ===
Contributors: adasha
Tags: media library, folders, filebird, uploads, gallery
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0-alpha.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Media Library folders, shared and per person, with a FileBird import, and uploads kept private to the people who made them.

== Description ==

= Folders =

Files can be filed in folders, nested as deeply as needed. Shared folders are seen by everyone who can upload and managed by editors. When Settings → Media allows it, each person can also have their own folders, which only they see and manage; two people can use the same folder names. A file can be in one shared folder and in one of each person's own. Folders organise the Media Library only: files stay where they are on disk.

The Media Library's grid and the media picker get a folder column: "All files", "Not in a folder", the shared folders and "My folders". Click a folder to see its files; drag files onto a folder to file them (all of a bulk selection at once); files uploaded while a folder is open go into it. Folders can be added, renamed and deleted there, by the people allowed to. The list view has a folder filter and a Folders column, and each file's details a folder dropdown.

= Importing from FileBird =

`wp galerio media import-filebird` copies FileBird's folders, and the files in them, into Galerio's folders. FileBird's shared folders become shared folders, and each person's own become theirs. Add `--dry-run` to see what would be imported. FileBird is left as it was, and the import can be run again.

= Uploads private to their owners =

People who can't edit others' posts, such as students with the contributor role, see only their own uploads in the Media Library, the media picker and the block editor's media tab. Editors and administrators see everything. Files on the site itself stay public, as WordPress has them. A switch on Settings → Media turns this off.

= Attachment pages =

Settings → Media has a switch for WordPress's own option that gives each uploaded file a page of its own. Off, links to a file go to the file itself.

Galerio is in alpha. Expect breaking changes.

== Changelog ==

= 0.1.0-alpha.1 =
* Folders for the Media Library, shared and per person: a folder tree in the grid and the media picker, with drag-and-drop filing and uploads into the open folder, plus a filter and column in the list view and a dropdown in each file's details.
* `wp galerio media import-filebird`.
* Uploads private to the people who made them.
* Switches on Settings → Media for personal folders, private uploads and attachment pages.
