#!/bin/sh
# Dev only: copy the component straight into a local Joomla site (default: the ECR dev copy), skipping the installer.
# Use the installer (zip) for anything that changes the manifest or the SQL.
set -e
cd "$(dirname "$0")/../com_ra_footpaths"
site=${1:-/Users/richardhigham/.t3/projects/ecr-website-build/site}
rsync -a --delete site/src/ "$site/components/com_ra_footpaths/src/"
rsync -a --delete site/tmpl/ "$site/components/com_ra_footpaths/tmpl/"
rsync -a site/language/ "$site/components/com_ra_footpaths/language/"
cp site/language/en-GB/com_ra_footpaths.ini "$site/language/en-GB/com_ra_footpaths.ini"
for d in src tmpl services sql language; do rsync -a --delete "administrator/$d/" "$site/administrator/components/com_ra_footpaths/$d/"; done
cp administrator/access.xml administrator/config.xml ra_footpaths.xml "$site/administrator/components/com_ra_footpaths/"
cp administrator/language/en-GB/*.ini "$site/administrator/language/en-GB/"
rsync -a --delete media/ "$site/media/com_ra_footpaths/"
echo "Synced com_ra_footpaths into $site"
