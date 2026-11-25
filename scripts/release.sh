#!/usr/bin/env bash
# Packages releases/subscription_v<version>.zip,
# and unzips it to releases/subscription/, the folder the wordpress.org deploy
# (.github/workflows/deploy-wordpress-org.yml) uploads.
#
# The file set is everything `.distignore` does not exclude; `wp dist-archive`
# (wp-cli/dist-archive-command) builds the zip. Run through `./wps release
# free`, or anywhere with composer, yarn, jq and wp-cli.
set -euo pipefail

VERSION=$(jq -r '.version' package.json)
PLUGIN_NAME='subscription'
ZIP="releases/${PLUGIN_NAME}_v${VERSION}.zip"
CHANGELOG_FILE="./changelog.txt"

clean() {
  rm -rf ./releases ./vendor
  mkdir -p ./releases
}

ensureDistArchive() {
  if ! wp help dist-archive >/dev/null 2>&1; then
    echo "[+] Installing wp-cli/dist-archive-command"
    wp package install wp-cli/dist-archive-command:^3
  fi
}

build() {
  echo "[+] Installing production composer packages"
  composer install --no-dev --no-ansi --no-cache --no-interaction --ignore-platform-req=php --quiet

  echo "[+] Building assets"
  yarn install --ignore-engines
  yarn build
  yarn update-lang-files
}

buildChangelogs() {
  echo "[+] Generating readme.txt from readme-template.txt and changelog.txt"

  TMP_CHANGELOG_FILE="./releases/tmp_changelog.txt"

  awk '
      function format_date(date_str) {
          split(date_str, parts, "-")
          year = parts[1]
          month = parts[2] + 0  # Convert to number to remove leading zero
          day = parts[3] + 0
          
          # Month names array
          months["1"] = "Jan"; months["2"] = "Feb"; months["3"] = "Mar"
          months["4"] = "Apr"; months["5"] = "May"; months["6"] = "Jun"
          months["7"] = "Jul"; months["8"] = "Aug"; months["9"] = "Sep"
          months["10"] = "Oct"; months["11"] = "Nov"; months["12"] = "Dec"
          
          return months[month] " " day ", " year
      }
      BEGIN { output = ""; skip_header = 1 }
      /^[*]{3}/ { next }
      /^202[0-9]/ {
          formatted_date = format_date($1)
          version = $4
          output = output "\n= " version " - " formatted_date " =\n"
          next
      }
      /^[*]/ {
          sub(/[*]/, "-  ", $0)  # Add three spaces after "-"
          output = output $0 "\n"
      }
      END { print output }
  ' "$CHANGELOG_FILE" > "$TMP_CHANGELOG_FILE"

  sed "/\[autofill_changelogs___DO_NOT_TOUCH_THIS_LINE\]/{
    r $TMP_CHANGELOG_FILE
    d
  }" readme-template.txt > readme.txt

  rm "$TMP_CHANGELOG_FILE"
}

archive() {
  echo "[+] Creating $ZIP"
  wp dist-archive . "$ZIP" --plugin-dirname="$PLUGIN_NAME" --force
  unzip -q "$ZIP" -d ./releases
}

# Runs however the script ends: clean() removed the development vendor/, and
# the bench cannot load the plugin without it.
finish() {
  echo "[+] Re-installing development dependencies"
  composer install --no-interaction --quiet
}
trap finish EXIT

echo "[+] ${PLUGIN_NAME} is packaging..."

ensureDistArchive
clean
build
buildChangelogs
archive

echo "[+] Done: $ZIP"
