=== Galerio Media ===
Contributors: adasha
Tags: media library, folders, filebird, uploads, gallery
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.2.0-alpha.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Media Library folders, shared and per person, with a FileBird import, and uploads kept private to the people who made them.

== Description ==

= Folders =

Files can be filed in folders, nested as deeply as needed. Shared folders are seen by everyone who can upload and managed by the roles chosen on Settings → Media (editors, until changed) and administrators. When Settings → Media allows it, each person can also have their own folders, which only they see and manage; two people can use the same folder names. A file can be in one shared folder and in one of each person's own. Folders organise the Media Library only: files stay where they are on disk.

The Media Library (grid and list views) and the media picker get a folder column: "All files", "Not in a folder", the shared folders and "My folders". Click a folder to see its files; drag files onto a folder to file them (in the list view, a row, or all the ticked rows); files uploaded while a folder is open go into it, including from the list view's "Add Media File". "New" adds a folder at the top, and "New folder inside" one inside the chosen folder; folders can also be renamed and deleted there, and dragged onto another folder (or onto the "Folders" or "My folders" heading, to go to the top), by the people allowed to: shared folders move among the shared ones, each person's own among theirs. The list view also has a Folders column, and each file's details a folder dropdown.

= Folder directories =

With "Folder directories" on in Settings → Media, a file uploaded into a shared folder is saved in a directory named after the folder's path: a file uploaded into collections › visions goes into `uploads/collections/visions/` instead of a year and month directory. Personal folders keep the year and month directories, so file addresses never carry people's names. Only new uploads are placed this way; renaming or moving a folder later doesn't move files already saved.

= Importing from FileBird =

`wp galerio media import-filebird` copies FileBird's folders, and the files in them, into Galerio's folders. FileBird's shared folders become shared folders, and each person's own become theirs. Add `--dry-run` to see what would be imported. FileBird is left as it was, and the import can be run again.

= Uploads private to their owners =

The roles chosen on Settings → Media see only their own uploads in the Media Library, the media picker and the block editor's media tab. Until the list is saved, these are the roles that can upload but can't edit others' posts, such as students with the contributor role. Someone with another role as well, and administrators always, see everything. Files on the site itself stay public, as WordPress has them. Clear the list to turn this off.

= Attachment pages =

Settings → Media has a switch for WordPress's own option that gives each uploaded file a page of its own. Off, links to a file go to the file itself.

Galerio is in alpha. Expect breaking changes.

== Changelog ==

= 0.2.0-alpha.1 =
* The folder tree in the Media Library's list view too: click a folder to filter the list, drag rows onto folders, and upload into the open folder with "Add Media File".
* "New folder inside" adds a subfolder to the chosen folder; "New" always adds one at the top.
* Folders move by dragging, onto another folder or onto a heading to go to the top.
* Settings → Media chooses which roles manage shared folders and which see only their own uploads. Until saved, these give the same behaviour as before.
* Personal folders come first and have amber icons.
* Tidier tree: it stays in view in the grid, and list rows are shorter.

= 0.1.0-alpha.1 =
* Folders for the Media Library, shared and per person: a folder tree in the grid and the media picker, with drag-and-drop filing and uploads into the open folder, plus a filter and column in the list view and a dropdown in each file's details.
* `wp galerio media import-filebird`.
* Uploads private to the people who made them.
* New uploads into shared folders saved in the folder's directory, when switched on.
* Switches on Settings → Media for personal folders, folder directories, private uploads and attachment pages.
