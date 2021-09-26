#!/bin/bash
# Update Nextcloud server and apps from latest git master
# For local development environment
# Use from Nextcloud server folder with `./build/update.sh`

# The command automatically:
# - updates the server
# - goes through all apps which are not shipped via server
# - shows the app name in bold and uses whitespace for separation
# - changes to the master branch
# - pulls from master quietly
# - shows the 3 most recent commits for context
# - removes branches merged into master

# TODO: Currently the "xargs git branch -d" errors with "fatal: branch name required" if there are no branches to delete
# TODO: Remove local changes to package-lock.json, js/appname.js & .map to prevent conflicts, but only if the file exists locally as otherwise the script stops
# TODO: Automatically build apps if possible, e.g. using `npm run dev`

# Update server
printf "\n\033[1m${PWD##*/}\033[0m\n"
git checkout master
git pull --quiet -p
git --no-pager log -3 --pretty=format:"%h %Cblue%ar%x09%an %Creset%s"
printf "\n"
git branch --merged master | grep -v "master$" | xargs git branch -d
git submodule update --init

# Update apps
find apps* -maxdepth 2 -name .git -exec sh -c 'cd {}/../ && printf "\n\033[1m${PWD##*/}\033[0m\n" && git checkout master && git pull --quiet -p && git --no-pager log -3 --pretty=format:"%h %Cblue%ar%x09%an %Creset%s" && printf "\n" && git branch --merged master | grep -v "master$" | xargs git branch -d && cd ..' \;
