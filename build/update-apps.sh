#!/bin/bash
# Update Nextcloud apps from latest git master
# For local development environment
# Use from Nextcloud server folder with `./build/update-apps.sh`
#
# It automatically:
# - goes through all apps which are not shipped via server
# - shows the app name in bold and uses whitespace for separation
# - changes to the master branch
# - removes local changes to package-lock.json to prevent conflicts
# - removes local built files js/appname.js & .map to prevent conflicts
# - pulls from master quietly
# - shows the 3 most recent commits for context
# - removes branches merged into master
#
# TODO: Currently the "xargs git branch -d" errors with "fatal: branch name required" if there are no branches to delete
# TODO: Do the build steps if they are consistent for the apps (like `npm run dev`)

find apps* -maxdepth 2 -name .git -exec sh -c 'cd {}/../ && printf "\n\033[1m${PWD##*/}\033[0m\n" && git checkout master && git checkout -- package-lock.json && git checkout -- js/$(printf "${PWD##*/}").js && git checkout -- js/$(printf "${PWD##*/}").js.map && git pull --quiet -p && git --no-pager log -3 --pretty=format:"%h %Cblue%ar%x09%an %Creset%s" && printf "\n" && git branch --merged master | grep -v "master$" | xargs git branch -d && cd ..' \;
