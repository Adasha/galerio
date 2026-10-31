#!/usr/bin/env python3
"""Import a content manifest into a Galerio site through the REST API.

The manifest (see demo-masters.json) lists taxonomy terms, profiles, works and
collections, each with the source of its facts and image. The import is
idempotent: items are matched by slug, so running it again updates what it made
and creates what is missing. Nothing is written unless --apply is given.

Usage:
  tools/import/galerio-import.py MANIFEST [--env FILE | --url URL --user USER]
                                          [--apply] [--only STAGE,...]

Credentials come from an env file (default .env.galerio-net at the repository
root) holding GALERIO_NET_URL, GALERIO_NET_USER and GALERIO_NET_APP_PASSWORD,
or from --url/--user with the application password in GALERIO_IMPORT_PASSWORD.
Stages: terms, folders, media, profiles, works, collections, links.
"""

import argparse
import base64
import io
import json
import os
import re
import sys
import time
import urllib.error
import urllib.parse
import urllib.request

UA = 'galerio-import (https://galerio.net)'
STAGES = ['terms', 'folders', 'media', 'profiles', 'works', 'collections', 'links']
MAX_PIXELS = 3000
MAX_COLLECTION_IMAGES = 8
POST_TYPES = {'profile': 'galerio_profile', 'work': 'galerio_work', 'collection': 'galerio_collection'}
LOCAL_HOSTS = ('localhost', '127.0.0.1')


class Api:
	"""A small REST client for one site."""

	def __init__(self, url, user, password, apply):
		self.url = url.rstrip('/')
		self.apply = apply
		token = base64.b64encode(f'{user}:{password}'.encode()).decode()
		self.headers = {'Authorization': f'Basic {token}', 'User-Agent': UA}

	def call(self, method, path, body=None, raw=None, headers=None, retries=3):
		"""Send a request and return (status, parsed JSON)."""
		url = f'{self.url}/wp-json{path}'
		data = raw if raw is not None else (json.dumps(body).encode() if body is not None else None)
		hdrs = dict(self.headers)
		if body is not None:
			hdrs['Content-Type'] = 'application/json'
		hdrs.update(headers or {})
		for attempt in range(retries):
			try:
				req = urllib.request.Request(url, data=data, method=method, headers=hdrs)
				with urllib.request.urlopen(req, timeout=120) as res:
					return res.status, json.load(res)
			except urllib.error.HTTPError as err:
				text = err.read().decode('utf-8', 'replace')
				if err.code in (429, 502, 503, 504) and attempt < retries - 1:
					time.sleep(3 * (attempt + 1))
					continue
				try:
					return err.code, json.loads(text)
				except ValueError:
					return err.code, {'message': text[:300]}
			except (urllib.error.URLError, TimeoutError) as err:
				if attempt == retries - 1:
					return 0, {'message': str(err)}
				time.sleep(3)
		return 0, {}


def load_env(path):
	env = {}
	with open(path) as handle:
		for line in handle:
			line = line.strip()
			if line and not line.startswith('#') and '=' in line:
				key, value = line.split('=', 1)
				env[key.strip()] = value.strip().strip('"\'')
	return env


def log(message):
	print(message, flush=True)


def slugify(text):
	text = re.sub(r'[^a-z0-9]+', '-', text.lower())
	return text.strip('-')


class Importer:
	def __init__(self, api, manifest, stages):
		self.api = api
		self.m = manifest
		self.stages = stages
		self.taxonomies = {}   # taxonomy slug => rest_base
		self.terms = {}        # (taxonomy, name) => id
		self.media = {}        # work key => attachment id
		self.folders = {}      # folder path (tuple of names) => term ID
		self.ids = {'profile': {}, 'work': {}, 'collection': {}}
		self.problems = []

	def fail(self, message):
		self.problems.append(message)
		log(f'  !! {message}')

	# -- discovery ---------------------------------------------------------

	def discover(self):
		status, data = self.api.call('GET', '/wp/v2/taxonomies?context=edit')
		if status != 200:
			sys.exit(f'Cannot read taxonomies (HTTP {status}): {data.get("message")}')
		self.taxonomies = {slug: tax['rest_base'] for slug, tax in data.items()}

	def preflight_field_writes(self):
		"""Refuse to go on if the site can't save fields over REST yet."""
		status, post = self.api.call('POST', '/wp/v2/galerio_work', {
			'title': 'galerio-import preflight', 'status': 'draft', 'galerio_fields': {'year': 1234},
		})
		if status not in (200, 201):
			sys.exit(f'Preflight failed creating a draft (HTTP {status}): {post.get("message")}')
		saved = (post.get('galerio_fields') or {}).get('year')
		self.api.call('DELETE', f'/wp/v2/galerio_work/{post["id"]}?force=true')
		if saved != 1234:
			sys.exit('This site ignores field writes over REST: release the Galerio Core version with '
				'writable galerio_fields first. Nothing was imported.')

	# -- helpers -----------------------------------------------------------

	def find_post(self, kind, slug):
		path = f'/wp/v2/{POST_TYPES[kind]}?slug={urllib.parse.quote(slug)}&status=any&_fields=id'
		status, data = self.api.call('GET', path)
		return data[0]['id'] if status == 200 and data else None

	def save_post(self, kind, key, payload):
		"""Create or update a post by slug; return its ID (0 in a dry run)."""
		slug = payload.setdefault('slug', slugify(key))
		existing = self.find_post(kind, slug)
		verb = 'update' if existing else 'create'
		log(f'  {verb} {kind}: {payload["title"]}')
		if not self.api.apply:
			return existing or 0
		path = f'/wp/v2/{POST_TYPES[kind]}' + (f'/{existing}' if existing else '')
		status, data = self.api.call('POST', path, payload)
		if status not in (200, 201):
			self.fail(f'{kind} {key}: HTTP {status} {data.get("message")}')
			return existing or 0
		return data['id']

	def term_ids(self, taxonomy, names):
		ids = []
		for name in names:
			term_id = self.terms.get((taxonomy, name))
			if term_id:
				ids.append(term_id)
			elif self.api.apply:
				self.fail(f'term {taxonomy}/{name} is missing')
		return ids

	# -- stages ------------------------------------------------------------

	def stage_terms(self):
		log('== Terms')
		for taxonomy, entries in self.m.get('terms', {}).items():
			if taxonomy not in self.taxonomies:
				self.fail(f'taxonomy {taxonomy} does not exist on this site; skipped')
				continue
			base = self.taxonomies[taxonomy]
			status, data = self.api.call('GET', f'/wp/v2/{base}?per_page=100&hide_empty=false&_fields=id,name,parent')
			have = {term['name'].lower(): term['id'] for term in data} if status == 200 else {}
			for entry in entries:
				entry = {'name': entry} if isinstance(entry, str) else entry
				name, parent = entry['name'], entry.get('parent')
				found = have.get(name.lower())
				if found:
					self.terms[(taxonomy, name)] = found
					continue
				log(f'  create term: {taxonomy}/{name}')
				if not self.api.apply:
					continue
				body = {'name': name, 'description': entry.get('description', '')}
				if parent:
					body['parent'] = self.terms.get((taxonomy, parent)) or have.get(parent.lower(), 0)
				status, data = self.api.call('POST', f'/wp/v2/{base}', body)
				if status in (200, 201):
					self.terms[(taxonomy, name)] = data['id']
					have[name.lower()] = data['id']
				else:
					self.fail(f'term {taxonomy}/{name}: HTTP {status} {data.get("message")}')
			for name, term_id in have.items():
				for entry in entries:
					entry_name = entry if isinstance(entry, str) else entry['name']
					if entry_name.lower() == name:
						self.terms.setdefault((taxonomy, entry_name), term_id)

	def fetch_image(self, urls):
		"""Download the first image that works and return (bytes, filename, mime)."""
		from PIL import Image
		for url in urls:
			try:
				req = urllib.request.Request(url, headers={'User-Agent': UA})
				with urllib.request.urlopen(req, timeout=120) as res:
					raw = res.read()
				image = Image.open(io.BytesIO(raw))
				image.load()
				if max(image.size) > MAX_PIXELS:
					image.thumbnail((MAX_PIXELS, MAX_PIXELS))
				if image.mode not in ('RGB', 'L'):
					image = image.convert('RGB')
				out = io.BytesIO()
				image.save(out, 'JPEG', quality=88, optimize=True)
				return out.getvalue()
			except Exception as err:  # noqa: BLE001 - try the next URL.
				log(f'    image {url}: {err}')
		return None

	def stage_folders(self):
		"""Make the Media Library folders the manifest's images are filed in."""
		log('== Media folders')
		status, data = self.api.call('GET', '/galerio-media/v1/folders')
		if status != 200:
			self.fail(f'cannot read media folders (HTTP {status}): {data.get("message")}')
			return
		by_id = {folder['id']: folder for folder in data['folders']}

		def path_of(folder):
			names = [folder['name']]
			while folder['parent'] in by_id:
				folder = by_id[folder['parent']]
				names.insert(0, folder['name'])
			return tuple(names)

		for folder in data['folders']:
			self.folders.setdefault(path_of(folder), folder['id'])

		for work in self.m['works']:
			path = tuple(work.get('folder', ()))
			for depth in range(1, len(path) + 1):
				sub = path[:depth]
				if sub in self.folders:
					continue
				log(f'  create folder: {"/".join(sub)}')
				if not self.api.apply:
					self.folders[sub] = 0
					continue
				status, data = self.api.call('POST', '/galerio-media/v1/folders', {'name': sub[-1], 'parent': self.folders.get(sub[:-1], 0)})
				if status in (200, 201):
					self.folders[sub] = data['id']
				else:
					self.fail(f'folder {"/".join(sub)}: HTTP {status} {data.get("message")}')

	def file_image(self, work, attachment):
		"""Put an uploaded image in its work's folder."""
		folder = self.folders.get(tuple(work.get('folder', ())))
		if not folder:
			return
		status, data = self.api.call('POST', '/galerio-media/v1/file', {'folder': folder, 'attachments': [attachment]})
		if status not in (200, 201):
			self.fail(f'filing the image for {work["key"]}: HTTP {status} {data.get("message")}')

	def find_media(self, work):
		"""The ID of a work's image if it has been uploaded already, else 0."""
		stem = slugify(work['key']) + '-image'
		status, data = self.api.call('GET', f'/wp/v2/media?search={urllib.parse.quote(stem)}&_fields=id,source_url')
		pattern = re.compile(rf'^{re.escape(stem)}(-scaled|-\d+)?\.jpg$')
		found = [item['id'] for item in data if pattern.match(item['source_url'].rsplit('/', 1)[-1])] if status == 200 else []
		return found[0] if found else 0

	def load_media(self):
		"""Find the images already uploaded, when the media stage isn't run."""
		for work in self.m['works']:
			if work.get('image') and work['key'] not in self.media:
				found = self.find_media(work)
				if found:
					self.media[work['key']] = found

	def stage_media(self):
		log('== Media')
		for work in self.m['works']:
			image = work.get('image')
			if not image:
				continue
			# The suffix keeps the attachment's slug from clashing with the work's.
			filename = slugify(work['key']) + '-image.jpg'
			already = self.find_media(work)
			if already:
				self.media[work['key']] = already
				log(f'  have image: {work["title"]}')
				if self.api.apply:
					self.file_image(work, already)
				continue
			log(f'  upload image: {work["title"]}')
			if not self.api.apply:
				continue
			jpeg = self.fetch_image([image['url']] + image.get('fallback', []))
			if not jpeg:
				self.fail(f'image for {work["key"]} could not be fetched')
				continue
			status, data = self.api.call('POST', '/wp/v2/media', raw=jpeg, headers={
				'Content-Type': 'image/jpeg',
				'Content-Disposition': f'attachment; filename="{filename}"',
			})
			if status not in (200, 201):
				self.fail(f'image for {work["key"]}: HTTP {status} {data.get("message")}')
				continue
			self.media[work['key']] = data['id']
			self.file_image(work, data['id'])
			credit = image.get('credit', '')
			self.api.call('POST', f'/wp/v2/media/{data["id"]}', {
				'title': work['title'],
				'alt_text': image.get('alt', work['title']),
				'caption': credit,
				'description': f'Source: {image.get("page", "")} ({image.get("licence", "")})',
			})

	def stage_profiles(self):
		log('== Profiles')
		for profile in self.m['profiles']:
			payload = {'title': profile['title'], 'status': 'publish', 'galerio_fields': profile.get('fields', {})}
			# No portrait is available, so a profile shows one of the creator's works.
			own = [self.media[w['key']] for w in self.m['works'] if w['creator'] == profile['key'] and self.media.get(w['key'])]
			if own:
				payload['featured_media'] = own[0]
			payload.update(self._terms(profile))
			self.ids['profile'][profile['key']] = self.save_post('profile', profile['key'], payload)

	def _terms(self, item):
		payload = {}
		for taxonomy, names in item.get('terms', {}).items():
			if taxonomy in self.taxonomies:
				payload[self.taxonomies[taxonomy]] = self.term_ids(taxonomy, names)
		return payload

	def stage_works(self):
		log('== Works')
		for work in self.m['works']:
			fields = dict(work.get('fields', {}))
			creator = self.ids['profile'].get(work['creator'])
			if creator is None:
				creator = self.lookup_profile(work['creator'])
			fields['creator'] = creator or 0
			fields['definitive_title'] = work.get('definitive_title', fields.get('definitive_title', ''))
			source = work.get('source')
			if source:
				fields['website'] = {'url': source['url'], 'label': source.get('label', 'Source record'), 'offline': False}
			media_id = self.media.get(work['key'])
			if media_id:
				fields['definitive_image'] = media_id
			payload = {'title': work['title'], 'status': 'publish', 'galerio_fields': fields}
			if media_id:
				payload['featured_media'] = media_id
			payload.update(self._terms(work))
			self.ids['work'][work['key']] = self.save_post('work', work['key'], payload)

	def lookup_profile(self, key):
		"""Find a profile that already exists on the site by its slug."""
		return self.find_post('profile', slugify(key))

	def stage_collections(self):
		log('== Collections')
		for collection in self.m['collections']:
			fields = dict(collection.get('fields', {}))
			fields['works'] = [self.ids['work'][k] for k in collection.get('works', []) if self.ids['work'].get(k)]
			fields['profiles'] = [i for i in (self.ids['profile'].get(k) or self.lookup_profile(k) for k in collection.get('profiles', [])) if i]
			images = [self.media[k] for k in collection.get('works', []) if self.media.get(k)][:MAX_COLLECTION_IMAGES]
			if images:
				fields['images'] = images
			payload = {'title': collection['title'], 'status': 'publish', 'galerio_fields': fields}
			if images:
				payload['featured_media'] = images[0]
			payload.update(self._terms(collection))
			self.ids['collection'][collection['key']] = self.save_post('collection', collection['key'], payload)

	def stage_links(self):
		log('== Related works')
		for work in self.m['works']:
			related = [self.ids['work'].get(k) for k in work.get('related', [])]
			related = [i for i in related if i]
			post_id = self.ids['work'].get(work['key'])
			if not related or not post_id or not self.api.apply:
				continue
			status, data = self.api.call('POST', f'/wp/v2/galerio_work/{post_id}', {'galerio_fields': {'related_works': related}})
			if status not in (200, 201):
				self.fail(f'related works for {work["key"]}: HTTP {status} {data.get("message")}')

	def preload_ids(self):
		"""Fill ID maps from the site when stages are run separately."""
		for kind, items in (('profile', self.m['profiles']), ('work', self.m['works']), ('collection', self.m['collections'])):
			for item in items:
				self.ids[kind].setdefault(item['key'], self.find_post(kind, slugify(item['key'])) or 0)
		for taxonomy, entries in self.m.get('terms', {}).items():
			if taxonomy not in self.taxonomies:
				continue
			status, data = self.api.call('GET', f'/wp/v2/{self.taxonomies[taxonomy]}?per_page=100&hide_empty=false&_fields=id,name')
			if status == 200:
				for term in data:
					for entry in entries:
						name = entry if isinstance(entry, str) else entry['name']
						if name.lower() == term['name'].lower():
							self.terms[(taxonomy, name)] = term['id']

	def run(self):
		self.discover()
		if self.api.apply:
			self.preflight_field_writes()
		self.preload_ids()
		if 'media' in self.stages:
			self.stages = list(set(self.stages) | {'folders'})
		elif {'profiles', 'works', 'collections'} & set(self.stages):
			self.load_media()
		for stage in STAGES:
			if stage in self.stages:
				getattr(self, f'stage_{stage}')()
		log('')
		log(('Applied' if self.api.apply else 'Dry run (nothing written)') + f'; {len(self.problems)} problem(s).')
		for problem in self.problems:
			log(f'  - {problem}')
		return 1 if self.problems else 0


def main():
	parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
	parser.add_argument('manifest')
	parser.add_argument('--env', default=None, help='env file with GALERIO_NET_* values')
	parser.add_argument('--url')
	parser.add_argument('--user')
	parser.add_argument('--apply', action='store_true')
	parser.add_argument('--only', default=','.join(STAGES))
	args = parser.parse_args()

	root = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
	if args.url:
		url, user, password = args.url, args.user, os.environ.get('GALERIO_IMPORT_PASSWORD', '')
	else:
		env = load_env(args.env or os.path.join(root, '.env.galerio-net'))
		url, user, password = env['GALERIO_NET_URL'], env['GALERIO_NET_USER'], env['GALERIO_NET_APP_PASSWORD']
	if not (url and user and password):
		sys.exit('Missing URL, user or password.')

	with open(args.manifest) as handle:
		manifest = json.load(handle)
	stages = [s for s in args.only.split(',') if s in STAGES]
	host = urllib.parse.urlparse(url).hostname
	log(f'Target: {url} ({"APPLY" if args.apply else "dry run"}) as {user}; host {host}')
	sys.exit(Importer(Api(url, user, password, args.apply), manifest, stages).run())


if __name__ == '__main__':
	main()
