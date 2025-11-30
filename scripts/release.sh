#!/usr/bin/env bash
VERSION=$(cat ./package.json | jq -r '.version')
PLUGIN_NAME='subscription'
ZIP_NAME='subscription'
PLUGIN_CONSTANT='WPSUBS'

clean() {
  rm -rf ./releases
  rm -rf ./vendor
}

build() {
  echo "[+] Starting combingFiles"

  composer install --no-dev --no-ansi --no-cache --no-interaction --ignore-platform-req=php --quiet
  yarn build
  yarn update-lang-files

  mkdir -p releases/$PLUGIN_NAME

  cp -r ./assets ./releases/$PLUGIN_NAME
  cp -r ./build ./releases/$PLUGIN_NAME
  cp -r ./includes ./releases/$PLUGIN_NAME
  cp -r ./languages ./releases/$PLUGIN_NAME
  cp -r ./src ./releases/$PLUGIN_NAME
  cp -r ./templates ./releases/$PLUGIN_NAME
  cp -r ./vendor ./releases/$PLUGIN_NAME
  cp -r ./readme-template.txt ./releases/$PLUGIN_NAME
  cp -r ./changelog.txt ./releases/$PLUGIN_NAME
  cp -r ./index.php ./releases/$PLUGIN_NAME
  cp -r ./composer.json ./releases/$PLUGIN_NAME
  cp -r ./$PLUGIN_NAME.php ./releases/$PLUGIN_NAME

  echo "[+] Finished combingFiles"
}

setVersion(){
  echo "[+] Setting up version"

  FILES=(
    "./releases/$PLUGIN_NAME/$PLUGIN_NAME.php"
    "./releases/$PLUGIN_NAME/readme-template.txt"
  )

  for FILE in "${FILES[@]}"; do
    if [[ -f "$FILE" ]]; then
      # Cross-platform approach using temporary file
      sed "s/#${PLUGIN_CONSTANT}_VERSION/$VERSION/g" "$FILE" > "$FILE.tmp" && mv "$FILE.tmp" "$FILE"
    else
      echo "[+] File $FILE not found!"
    fi
  done

  echo "[+] Version setting complete"
}

zipFolder(){
  echo "[+] Creating zip"
  current_dir=$(pwd)
  cd "${current_dir}/releases" && zip -qr "${current_dir}/releases/${ZIP_NAME}_v${VERSION}.zip" .
  echo "[+] Finished creating zip"
}

reInstallPackages(){
  echo "[+] Re-installing dependencies"

  cd ..
  composer install --no-interaction --quiet

  echo "[+] Finished re-installing dependencies"
}

buildChangelogs(){
  echo "[+] Formatting & Copying changelogs"

  CHANGELOG_FILE="./releases/$PLUGIN_NAME/changelog.txt"
  README_FILE="./releases/$PLUGIN_NAME/readme.txt"
  README_TEMPLATE_FILE="./releases/$PLUGIN_NAME/readme-template.txt"
  ROOT_README_FILE="./readme.txt"
  TMP_CHANGELOG_FILE="./releases/$PLUGIN_NAME/tmp_changelog.txt"

  # Use a more portable date formatting approach
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

  # Cross-platform sed approach using temporary file
  sed "/\[autofill_changelogs___DO_NOT_TOUCH_THIS_LINE\]/{
    r $TMP_CHANGELOG_FILE
    d
  }" "$README_TEMPLATE_FILE" > "$README_TEMPLATE_FILE.tmp" && mv "$README_TEMPLATE_FILE.tmp" "$README_TEMPLATE_FILE"

  cp "$README_TEMPLATE_FILE" "$ROOT_README_FILE"
  mv "$README_TEMPLATE_FILE" "$README_FILE"
  rm "$TMP_CHANGELOG_FILE"

  echo "[+] Finished formatting & Copying changelogs"
}

buildThePackage() {
  clean
  build
  setVersion
  buildChangelogs
  zipFolder
  reInstallPackages
}

# Fixed: Use parameter expansion that works on both macOS and Ubuntu
CAPITALIZED_PLUGIN_NAME="$(echo ${PLUGIN_NAME:0:1} | tr '[:lower:]' '[:upper:]')${PLUGIN_NAME:1}"
echo "[+] $CAPITALIZED_PLUGIN_NAME is packaging..."

buildThePackage

echo "[+] Done"