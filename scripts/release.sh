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

  yarn build
  composer install --no-dev --no-ansi --no-cache --no-interaction --ignore-platform-req=php --quiet

  mkdir -p releases/$PLUGIN_NAME

  cp -r ./assets ./releases/$PLUGIN_NAME
  cp -r ./build ./releases/$PLUGIN_NAME
  cp -r ./includes ./releases/$PLUGIN_NAME
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
      sed -i "s/#${PLUGIN_CONSTANT}_VERSION/$VERSION/g" "$FILE"
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

  awk '
      BEGIN { output = ""; skip_header = 1 }
      /^[*]{3}/ { next }
      /^202[0-9]/ {
          split($1, date_parts, "-")
          month = date_parts[2] + 0  # Strip leading zero if present
          formatted_date = strftime("%b %d, %Y", mktime(date_parts[1] " " month " " date_parts[3] " 00 00 00"))
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

  sed -i "/\[autofill_changelogs___DO_NOT_TOUCH_THIS_LINE\]/{
      r $TMP_CHANGELOG_FILE
      d
  }" "$README_TEMPLATE_FILE"

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

echo "[+] ${PLUGIN_NAME^} is packaging..."

buildThePackage

echo "[+] Done"